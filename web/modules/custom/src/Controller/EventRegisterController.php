<?php

declare(strict_types=1);

namespace Drupal\event\Controller;

use Drupal\address\Repository\CountryRepository;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;

/**
 * Provides a controller for event.event_page route.
 */
class EventRegisterController extends ControllerBase {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The country repository.
   *
   * @var \Drupal\address\Repository\CountryRepository
   */
  protected CountryRepository $countryRepository;

  /**
   * Constructs a new EventRegisterController object.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\address\Repository\CountryRepository $country_repository
   *   The country repository service.
   */
  public function __construct(
    DateFormatterInterface $date_formatter,
    CountryRepository $country_repository
  ) {
    $this->dateFormatter = $date_formatter;
    $this->countryRepository = $country_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('date.formatter'),
      $container->get('address.country_repository')
    );
  }

  private function formatDate(string $date): string {
    return $this->dateFormatter->format(
      strtotime($date),
      'custom',
      'd F Y H:i'
    );
  }

  /**
   * Formats WKT POINT coordinates into a readable format.
   *
   * @param string $coordinates
   *   The WKT POINT coordinates string.
   *
   * @return string
   *   Formatted coordinates string or empty string if invalid input.
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
   * Builds the response.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node ID.
   *
   * @return array
   *   A render array.
   */
  public function build(NodeInterface $node): array {
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

    $isLoggedIn = $this->currentUser()->isAuthenticated();

    $build = [
      '#theme' => 'event_page',
      '#attached' => [
        'library' => ['event/registration'],
      ],
      '#event' => $eventData,
      '#is_logged_in' => $isLoggedIn,
    ];

    if ($isLoggedIn) {
      $build['#register_button'] = [
        '#type' => 'link',
        '#title' => $this->t('Register for event'),
        '#url' => Url::fromRoute('event.event_page', ['id' => $node->id()]),
        '#attributes' => [
          'class' => ['button', 'register-event-button'],
        ],
      ];
    } else {
      $build['#login_message'] = $this->t('You must be logged in to register for the event.');
    }

    return $build;
  }

}
