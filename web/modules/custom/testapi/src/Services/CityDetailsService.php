<?php

namespace Drupal\testapi\Services;

use GuzzleHttp\ClientInterface;

/**
 * Service to fetch current weather conditions.
 */
class CityDetailsService {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Constructs a new CityDetailsService object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client service.
   */
  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;
  }

  /**
   * Fetches the list of cities from the API.
   *
   */
  public function getCityDetails ($city_name) {
    try {
      // Example third-party API request using the injected service
      // Demo key: no account required.

      $response = $this->httpClient->request('GET', "https://api.restcountries.com/countries/v5/names.common/$city_name?api-key=rc_live_ff59427e22da4a7b8bff22d938ab1b42");
      $data = json_decode($response->getBody()->getContents(), TRUE);
      return $data;

    }
    catch (\Exception $e) {
      return 'Unavailable';
    }
  }

}
