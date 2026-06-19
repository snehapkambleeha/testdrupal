<?php

namespace Drupal\Tests\domain\Kernel;

use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\domain\Traits\DomainTestTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests security for the destination query parameter with domains.
 *
 * @group domain
 */
#[Group('domain')]
#[RunTestsInSeparateProcesses]
class DomainDestinationSecurityTest extends KernelTestBase {

  use DomainTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'domain',
    'user',
    'system',
    'field',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('domain');
    $this->installConfig(['domain', 'system']);

    // Create some domains.
    $this->domainCreateTestDomains(2);
  }

  /**
   * Tests domain destination security.
   */
  public function testDomainDestinationSecurity() {
    $domains = \Drupal::entityTypeManager()->getStorage('domain')->loadMultiple();
    /** @var \Drupal\domain\DomainInterface $domain2 */
    $domain2 = reset($domains);
    $this->assertNotNull($domain2, 'A domain was found.');

    // Manually set the hostname and URL if they are empty.
    if (empty($domain2->getHostname())) {
      $domain2->setHostname('one.example.com');
      $domain2->save();
    }

    $subscriber = $this->container->get('domain.subscriber');
    $http_kernel = $this->container->get('http_kernel');

    // Set trusted host patterns for the test.
    $settings = Settings::getAll();
    $settings['trusted_host_patterns'] = ['^.*\.example\.com$'];
    new Settings($settings);

    // 1. Valid case: both domain and path are safe.
    $request = Request::create('/', 'GET', [
      'destination_domain' => $domain2->getUrl(),
      'destination' => 'user/login',
    ]);
    $event = new RequestEvent($http_kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    $subscriber->onKernelRequestDomain($event);

    $this->assertEquals($domain2->getUrl() . 'user/login', $request->query->get('destination'));
    $this->assertFalse($request->query->has('destination_domain'));

    // 2. Security: destination_domain is not a trusted host.
    $request = Request::create('/', 'GET', [
      'destination_domain' => 'http://attacker.com/',
      'destination' => 'user/login',
    ]);
    $event = new RequestEvent($http_kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    $subscriber->onKernelRequestDomain($event);

    // It should NOT set the destination if the host is not trusted.
    $this->assertNull($request->query->get('destination'));
    // It should also remove destination_domain.
    $this->assertFalse($request->query->has('destination_domain'));

    // 3. Security: destination_domain has query parameters (potential
    // injection).
    $request = Request::create('/', 'GET', [
      'destination_domain' => $domain2->getUrl() . '?q=something',
      'destination' => 'user/login',
    ]);
    $event = new RequestEvent($http_kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    $subscriber->onKernelRequestDomain($event);

    $this->assertNull($request->query->get('destination'));
    $this->assertFalse($request->query->has('destination_domain'));

    // 4. Security: destination_domain has fragments (potential injection).
    $request = Request::create('/', 'GET', [
      'destination_domain' => $domain2->getUrl() . '#fragment',
      'destination' => 'user/login',
    ]);
    $event = new RequestEvent($http_kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    $subscriber->onKernelRequestDomain($event);

    $this->assertNull($request->query->get('destination'));
    $this->assertFalse($request->query->has('destination_domain'));

    // 5. Security: destination is an absolute URL (open redirect).
    // Note: In a real request, RequestSanitizer would have removed this
    // destination parameter before it reached DomainSubscriber.
    // We test that DomainSubscriber doesn't do anything weird if it's there
    // (though it doesn't explicitly check for external destination anymore).
    // Actually, DomainSubscriber will just concatenate them.
    $request = Request::create('/', 'GET', [
      'destination_domain' => $domain2->getUrl(),
      'destination' => 'http://attacker.com/malicious',
    ]);
    $event = new RequestEvent($http_kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    $subscriber->onKernelRequestDomain($event);

    $destination = $request->query->get('destination');
    // It will be http://two.example.com/http://attacker.com/malicious
    $this->assertEquals($domain2->getUrl() . 'http://attacker.com/malicious', $destination);
  }

}
