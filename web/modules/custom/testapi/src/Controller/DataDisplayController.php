<?php

namespace Drupal\testapi\Controller;

use Drupal\Core\Controller\ControllerBase;
use GuzzleHttp\Exception\GuzzleException;

class DataDisplayController extends ControllerBase {


  public function viewData(): array {
    $data = [];
        // Fetch data via your custom service
        // $data = $this->apiClient->fetchData('users');
    $base_url = 'https://api.restcountries.com/countries/v5/names.common/Canada';
        try {
    $client = \Drupal::httpClient();
    $response = $client->request('GET', 'https://api.restcountries.com/countries/v5?limit=25', [
            'headers' => [
            'Authorization' => 'Bearer rc_live_demo',
            'Accept' => 'application/json',
            ]
        ]);


  $status_code = $response->getStatusCode(); // e.g., 201
  if ($status_code === 200) {
        $body = $response->getBody()->getContents();
        $data = json_decode($body, TRUE);
        }
    } 
    catch (GuzzleException $e) {
    // Handle exception
    }

    foreach ($data['data']['objects'] as $item => $value) {
        $countryData['name'] = $value['names']['common']; 
        $countryData['region'] = $value['region'];
        $countryData['subregion'] = $value['subregion'];
    }
    if (empty($data)) {
      return [
        '#markup' => $this->t('Failed to load data from the external API.'),
      ];
    }

    // Process and return your render array
    return [
      '#theme' => 'item_list',
      '#items' => array_column($countryData, 'name'), 
      '#title' => $this->t('User List from API'),
    ];
  }
}
