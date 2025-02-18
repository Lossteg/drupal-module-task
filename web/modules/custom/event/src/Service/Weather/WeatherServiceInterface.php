<?php

declare(strict_types=1);

namespace Drupal\event\Service\Weather;

/**
 * Interface for the weather service.
 */
interface WeatherServiceInterface {

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
  public function getWeatherByCoordinates(float $latitude, float $longitude): array;

  /**
   * Gets weather data from a WKT POINT string.
   *
   * @param string $wkt
   *   The WKT POINT string (e.g., "POINT (30.5 50.2)").
   *
   * @return array
   *   Weather data.
   */
  public function getWeatherFromWkt(string $wkt): array;

}
