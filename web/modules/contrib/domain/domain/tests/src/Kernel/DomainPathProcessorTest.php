<?php

namespace Drupal\Tests\domain\Kernel;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\domain\Traits\DomainTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests the domain outbound path processor.
 *
 * @group domain
 */
#[Group('domain')]
#[RunTestsInSeparateProcesses]
class DomainPathProcessorTest extends KernelTestBase {

  use DomainTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['domain'];

  /**
   * The path processor under test.
   *
   * @var \Drupal\domain\HttpKernel\DomainPathProcessor
   */
  protected $processor;

  /**
   * The domain storage handler.
   *
   * @var \Drupal\domain\DomainStorageInterface
   */
  protected $domainStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->domainCreateTestDomains(2);
    $this->domainStorage = \Drupal::entityTypeManager()
      ->getStorage('domain');
    $this->processor = \Drupal::service('domain.path_processor');
  }

  /**
   * Tests rewrite with a DomainInterface entity.
   */
  public function testRewriteWithEntity(): void {
    /** @var \Drupal\domain\DomainInterface $domain */
    $domain = $this->domainStorage->load('one_example_com');
    $options = ['domain' => $domain];
    $metadata = new BubbleableMetadata();

    $result = $this->processor->processOutbound(
      '/node/1', $options, NULL, $metadata
    );

    $this->assertSame('/node/1', $result);
    $expected = rtrim($domain->getPath(), '/');
    $this->assertSame($expected, $options['base_url']);
    $this->assertTrue($options['absolute']);
    $this->assertContains('config:domain.record.one_example_com', $metadata->getCacheTags());
  }

  /**
   * Tests no-op when domain option is absent.
   */
  public function testNoOpWithoutOption(): void {
    $options = [];
    $metadata = new BubbleableMetadata();

    $result = $this->processor->processOutbound(
      '/node/1', $options, NULL, $metadata
    );

    $this->assertSame('/node/1', $result);
    $this->assertArrayNotHasKey('base_url', $options);
    $this->assertEmpty($metadata->getCacheContexts());
  }

  /**
   * Tests that external URLs are skipped.
   */
  public function testExternalUrlSkipped(): void {
    $domain = $this->domainStorage->load('one_example_com');
    $options = [
      'external' => TRUE,
      'domain' => $domain,
    ];
    $metadata = new BubbleableMetadata();

    $result = $this->processor->processOutbound(
      'https://other.example.com/page', $options, NULL, $metadata
    );

    $this->assertSame(
      'https://other.example.com/page', $result
    );
    $this->assertArrayNotHasKey('base_url', $options);
  }

  /**
   * Tests that a non-DomainInterface value is ignored.
   */
  public function testNonEntityValueIgnored(): void {
    $options = ['domain' => 'one_example_com'];
    $metadata = new BubbleableMetadata();

    $result = $this->processor->processOutbound(
      '/node/1', $options, NULL, $metadata
    );

    $this->assertSame('/node/1', $result);
    $this->assertArrayNotHasKey('base_url', $options);
  }

  /**
   * Tests that subdirectory base path is preserved.
   *
   * Pushes a request with SCRIPT_NAME and SCRIPT_FILENAME set to
   * simulate a subdirectory install so that setPath() picks up the
   * base path from the request.
   */
  public function testRewriteWithSubdirectoryInstall(): void {
    // Simulate a subdirectory install via request server vars.
    $request = Request::create(
      'http://one.example.com/drupal/node/1',
      'GET', [], [], [],
      [
        'SCRIPT_NAME' => '/drupal/index.php',
        'SCRIPT_FILENAME' => '/var/www/html/web/index.php',
      ]
    );
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);

    /** @var \Drupal\domain\DomainInterface $domain */
    $domain = $this->domainStorage->load('one_example_com');
    // Force path recalculation with subdirectory request.
    $domain->setPath();
    $options = ['domain' => $domain];
    $metadata = new BubbleableMetadata();

    $this->processor->processOutbound(
      '/node/1', $options, NULL, $metadata
    );

    $this->assertStringEndsWith('/drupal', $options['base_url']);
  }

  /**
   * Tests that NULL bubbleable metadata is handled.
   */
  public function testNullBubbleableMetadata(): void {
    $domain = $this->domainStorage->load('one_example_com');
    $options = ['domain' => $domain];

    $result = $this->processor->processOutbound(
      '/node/1', $options
    );

    $this->assertSame('/node/1', $result);
    $this->assertArrayHasKey('base_url', $options);
    $this->assertTrue($options['absolute']);
  }

}
