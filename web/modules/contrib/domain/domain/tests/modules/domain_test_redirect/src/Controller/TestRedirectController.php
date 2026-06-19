<?php

namespace Drupal\domain_test_redirect\Controller;

use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for testing redirect functionality in Domain module.
 */
class TestRedirectController {

  /**
   * Non-trusted redirects to a target URL for testing purposes.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The HTTP redirect response.
   */
  public function nonTrustedRedirect(Request $request) {
    $target_url = $request->query->get('url');
    return new RedirectResponse($target_url);
  }

  /**
   * Trusted redirects to a target URL for testing purposes.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The HTTP redirect response.
   */
  public function trustedRedirect(Request $request) {
    $target_url = $request->query->get('url');
    return new TrustedRedirectResponse($target_url);
  }

}
