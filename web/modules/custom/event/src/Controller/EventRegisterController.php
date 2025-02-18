<?php

declare(strict_types=1);

namespace Drupal\event\Controller;

use Drupal\address\Repository\CountryRepository;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\event\Service\Registration\EventRegisterService;
use Drupal\event\Service\Weather\WeatherService;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provides a controller for event.event_page route.
 */
class EventRegisterController extends ControllerBase {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The country repository.
   *
   * @var \Drupal\address\Repository\CountryRepository
   */
  protected $countryRepository;

  /**
   * The event registration service.
   *
   * @var \Drupal\event\Service\Registration\EventRegisterService
   */
  protected $eventRegistration;

  /**
   * The WeatherAPI service.
   *
   * @var \Drupal\event\Service\Weather\WeatherService
   */
  protected $weatherService;

  /**
   * Constructs a new EventRegisterController object.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\address\Repository\CountryRepository $country_repository
   *   The country repository service.
   * @param \Drupal\event\Service\Registration\EventRegisterService $event_registration
   *   The event registration service.
   * @param \Drupal\event\Service\Weather\WeatherService $weather_service
   *   The WeatherAPI service.
   */
  public function __construct(
    DateFormatterInterface $date_formatter,
    CountryRepository $country_repository,
    EventRegisterService  $event_registration,
    WeatherService $weather_service
  ) {
    $this->dateFormatter = $date_formatter;
    $this->countryRepository = $country_repository;
    $this->eventRegistration = $event_registration;
    $this->weatherService = $weather_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('date.formatter'),
      $container->get('address.country_repository'),
      $container->get('event.registration'),
      $container->get('event.weather_service')
    );
  }

  /**
   * Formats a date string into a human-readable format.
   *
   * @param string $date
   *   The date string to format.
   *
   * @return string
   *   The formatted date in "d F Y H:i" format.
   */
  private function formatDate(string $date): string
  {
    return $this->dateFormatter->format(
      strtotime($date),
      'custom',
      'd F Y H:i'
    );
  }

  /**
   * Formats WKT POINT coordinates into a human-readable format.
   *
   * Takes a Well-Known Text (WKT) POINT string (e.g., "POINT (30.5 50.2)")
   * and converts it into a more readable format with latitude and longitude.
   *
   * @param string $coordinates
   *   The WKT POINT coordinates string.
   *
   * @return string
   *   A formatted string representation of the coordinates,
   *   e.g., "50.2° N, 30.5° E".
   */
  private function formatCoordinates(string $coordinates): string {
    if (empty($coordinates)) {
      return 'Coordinates are not set';
    }

    if (preg_match('/POINT\(([\d.-]+) ([\d.-]+)\)/', $coordinates, $matches)) {
      $longitude = (float)$matches[1];
      $latitude = (float)$matches[2];

      $longitudeDirection = $longitude >= 0 ? 'E' : 'W';
      $latitudeDirection = $latitude >= 0 ? 'N' : 'S';

      return abs($latitude) . '° ' . $latitudeDirection . ', ' . abs($longitude) . '° ' . $longitudeDirection;
    }

    return 'Coordinates are not available';
  }

  /**
   * Gets location data from node field.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return array
   *   Array of location data.
   */
  private function getLocationData(NodeInterface $node): array {
    $location = $node->get('field_location')->first();

    $countryCode = $location->get('country_code')->getValue();
    $countryName = $countryCode ? $this->countryRepository->get($countryCode)->getName() : '';

    return array_filter([
      'city' => $location->get('locality')->getValue(),
      'region' => $location->get('administrative_area')->getValue(),
      'address' => $location->get('address_line1')->getValue(),
      'country' => $countryName,
    ], function ($value) {
      return !empty($value);
    });
  }

  /**
   * Handles user registration for an event and returns a JSON response.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The event node.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the registration status and message.
   */
  public function register(NodeInterface $node): JSONResponse {
    $result = $this->eventRegistration->register($node);

    return new JsonResponse($result);
  }

  /**
   * Returns a render array for the event participants list.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The event node.
   *
   * @return array
   *   A render array.
   */
  public function participants(NodeInterface $node): array {
    $participants = $this->eventRegistration->getRegisteredUsers($node);

    return [
      '#theme' => 'event_participants',
      '#attached' => ['library' => ['event/registration']],
      '#event' => [
        'title' => $node->getTitle(),
        'participants' => $participants,
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Gets weather data for an event.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The event node.
   *
   * @return array
   *   Weather data.
   */
  private function getWeatherData(NodeInterface $node): array {
    $coordinates = $node->get('field_coordinates')->value;

    if (empty($coordinates)) {
      return [];
    }

    return $this->weatherService->getWeatherFromWkt($coordinates);
  }

  /**
   * Builds the response.
   *
   * @param NodeInterface $node
   *   The node ID.
   *
   * @return array
   *   A render array.
   */
  public function build(NodeInterface $node = NULL): array {
    $eventData = [
      'nid' => $node->id(),
      'title' => $node->getTitle(),
      'status' => $node->get('field_status')->value,
      'start_date' => $this->formatDate($node->get('field_start_date')->value),
      'end_date' => $this->formatDate($node->get('field_end_date')->value),
      'description' => $node->get('field_description')->value,
      'max_participants' => $node->get('field_maximum_participants')->value,
      'location' => $this->getLocationData($node),
      'coordinates' => $this->formatCoordinates($node->get('field_coordinates')->value),
    ];

    $weatherData = $this->getWeatherData($node);

    $build = [
      '#theme' => 'event_page',
      '#attached' => ['library' => ['event/registration']],
      '#event' => $eventData,
      '#weather' => $weatherData,
      '#is_logged_in' => $this->currentUser()->isAuthenticated(),
      '#cache' => [
        'contexts' => ['user'],
        'tags' => [
          'node:' . $node->id(),
          'event_registration:' . $node->id(),
        ],
        'max-age' => 600,
      ],
    ];

    if (!$build['#is_logged_in']) {
      $build['#registration_message'] = [
        '#markup' => $this->t('You must be logged in to register for this event.')
      ];
      return $build;
    }

    $userId = (int) $this->currentUser()->id();
    $registrationStatus = $this->eventRegistration->getRegistrationStatus($node, $userId);

    $build['#registration_status'] = $registrationStatus['status'];

    if (!$registrationStatus['status']) {
      $build['#registration_message'] = [
        '#markup' => $registrationStatus['message'],
      ];
    } else {
      $build['#register_button'] = [
        '#type' => 'link',
        '#title' => $this->t('Register for event'),
        '#url' => Url::fromRoute('event.register', ['node' => $node->id()]),
        '#attributes' => [
          'class' => ['button', 'register-event-button'],
        ],
      ];
    }

    if ($this->currentUser()->hasPermission('access event participants')) {
      $build['#participants_link'] = [
        '#type' => 'link',
        '#title' => $this->t('View Participants'),
        '#url' => Url::fromRoute('event.participants', ['node' => $node->id()]),
        '#attributes' => [
          'class' => ['button', 'view-participants-button'],
        ],
      ];
    }

    return $build;
  }

}
