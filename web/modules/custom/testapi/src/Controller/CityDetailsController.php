<?php

namespace Drupal\testapi\Controller;

use Drupal\Core\Controller\ControllerBase;
use GuzzleHttp\Exception\GuzzleException;

class CityDetailsController extends ControllerBase {
  public function viewCity($city): array {

    $cityService = \Drupal::service('testapi.city_details');
    $city_details = $cityService->getCityDetails($city);

    // 1. Define table headers
    $header = [
      'id' => $this->t('ID'),
      'name' => $this->t('Item Name'),
      'capital' => $this->t('Capital'),
      'region' => $this->t('Region'),
      'subregion' => $this->t('Subregion'),
    ];
    $id = 0;
    $rows = [];


    foreach ($city_details['data']['objects'] as $key => $city) {
      // Debugging line to check the output of each city name
      $rows[$key] = [
        'id' => $id++,
        'name' => $city['names']['common'],
        'capital' => $city['capitals'][0]['name'],  
        'region' => $city['region'],
        'subregion' => $city['subregion'],
      ];
    }

    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No data available to display.'),
      '#cache' => [
        'max-age' => 0, // Set to 0 for real-time testing, or apply custom cache contexts
      ],
    ];
   
  }
}