<?php

namespace Drupal\domain_config_middleware_test;

use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Middleware for the domain_config_test module.
 */
class Middleware implements HttpKernelInterface {

  /**
   * The request type.
   *
   * @var int
   */
  public const MAIN_REQUEST = 1;

  public function __construct(
    protected HttpKernelInterface $httpKernel,
    protected ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = TRUE): Response {
    // This line should break hooks in our code.
    // @see https://www.drupal.org/node/2896434.
    $config = $this->configFactory->get('domain_config_middleware_test.settings');
    return $this->httpKernel->handle($request, $type, $catch);
  }

}
