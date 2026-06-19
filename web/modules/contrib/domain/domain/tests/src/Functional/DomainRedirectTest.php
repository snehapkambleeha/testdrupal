<?php

namespace Drupal\Tests\domain\Functional;

use Symfony\Component\HttpFoundation\Response;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the domain redirection handling.
 *
 * @group domain
 */
#[Group('domain')]
#[RunTestsInSeparateProcesses]
class DomainRedirectTest extends DomainTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['domain', 'domain_test_redirect'];

  /**
   * Test redirections.
   */
  public function testDomainRedirect() {
    $this->domainCreateTestDomains(2);

    $domains = $this->getDomains();
    $domain = $domains['one_example_com'];

    // Redirection targeting a registered domain url.
    $target_url = $domain->getPath() . 'target-path';
    $this->drupalGet('/non-trusted-redirect', [
      'query' => ['url' => $target_url],
    ]);

    // Verify that the redirection has been properly allowed.
    $this->assertEquals($target_url, $this->getUrl());

    // Target an external url that is not a registered domain.
    $target_url = 'http://two.' . $this->baseHostname;
    $this->drupalGet('/non-trusted-redirect', [
      'query' => ['url' => $target_url],
    ]);

    // Verify that the redirection was rejected as expected.
    $this->assertSession()->statusCodeEquals(Response::HTTP_BAD_REQUEST);
    $this->assertSession()->responseContains('Redirects to external URLs are not allowed');

    // Target the same external url with a trusted redirect.
    $this->drupalGet('/trusted-redirect', [
      'query' => ['url' => $target_url],
    ]);

    // Verify that the redirection has been properly allowed.
    $this->assertEquals($target_url, $this->getUrl());
  }

}
