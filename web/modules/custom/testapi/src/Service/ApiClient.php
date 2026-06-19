<?php

namespace Drupal\testapi\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Class ApiClient to manage external REST API calls.
 */
class ApiClient {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Constructs an ApiClient object.
   */
  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;
  }

  /**
   * Fetches data from an external API endpoint.
   *
   * @param string $endpoint
   *   The specific endpoint path.
   *
   * @return array|null
   *   The decoded response data, or NULL on failure.
   */
  public function fetchData(string $endpoint): ?array {
    $base_url = 'https://api.restcountries.com/countries/v5/names.common/Canada';
    
    try {
      $response = $this->httpClient->request('GET', $base_url . $endpoint, [
        'headers' => [
          'Accept' => 'application/json',
          'Authorization' => 'Bearer rc_live_demo',
        ],
        'timeout' => 5.0, // Timeout after 5 seconds
      ]);

      if ($response->getStatusCode() === 200) {
        $body = $response->getBody()->getContents();
        return json_decode($body, TRUE);
      }
    }
    catch (GuzzleException $e) {
      // Log errors using Drupal's core logging system
      \Drupal::logger('api_consumer')->error('API request failed: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return NULL;
  }
}
