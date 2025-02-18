<?php

declare(strict_types=1);

namespace Drupal\event\Service\Weather;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Http\ClientFactory;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Service for fetching weather data from external API.
 */
class WeatherService implements WeatherServiceInterface {

  /**
   * The HTTP client factory.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  protected $httpClientFactory;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The API key for the weather service.
   *
   * @var string
   */
  protected $apiKey;

  /**
   * The API base URL.
   *
   * @var string
   */
  protected $apiUrl;

  /**
   * Constructs a new WeatherService.
   *
   * @param \Drupal\Core\Http\ClientFactory $http_client_factory
   *   The HTTP client factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(
    ClientFactory $http_client_factory,
    ConfigFactoryInterface $config_factory
  ) {
    $this->httpClientFactory = $http_client_factory;
    $this->configFactory = $config_factory;

    $config = $this->configFactory->get('event.settings');
    $this->apiKey = $config->get('weather_api_key');
    $this->apiUrl = $config->get('weather_api_url');
  }

  /**
   * Gets weather data for specific coordinates.
   *
   * @param float $latitude
   *   The latitude.
   * @param float $longitude
   *   The longitude.
   *
   * @return array
   *   Weather data.
   */
  public function getWeatherByCoordinates(float $latitude, float $longitude): array {
    try {
      $client = $this->httpClientFactory->fromOptions();
      $response = $client->request('GET', $this->apiUrl, [
        'query' => [
          'key' => $this->apiKey,
          'q' => "$latitude,$longitude",
          'aqi' => 'no',
        ],
      ]);

      $data = Json::decode((string) $response->getBody());

      if (!isset($data['current'])) {
        return [];
      }

      return [
        'temperature' => $data['current']['temp_c'],
        'condition' => $data['current']['condition']['text'],
        'icon' => $data['current']['condition']['icon'],
        'wind_speed' => $data['current']['wind_kph'],
        'humidity' => $data['current']['humidity'],
      ];
    }
    catch (GuzzleException $e) {
      return [];
    }
  }

  /**
   * Gets weather data from a WKT POINT string.
   *
   * @param string $wkt
   *   The WKT POINT string (e.g., "POINT (30.5 50.2)").
   *
   * @return array
   *   Weather data.
   */
  public function getWeatherFromWkt(string $wkt): array {
    if (empty($wkt)) {
      return [];
    }

    if (preg_match('/POINT \(([\d.-]+) ([\d.-]+)\)/', $wkt, $matches)) {
      $longitude = (float) $matches[1];
      $latitude = (float) $matches[2];

      return $this->getWeatherByCoordinates($latitude, $longitude);
    }

    return [];
  }
}
