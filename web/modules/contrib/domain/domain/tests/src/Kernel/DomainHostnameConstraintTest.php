<?php

namespace Drupal\Tests\domain\Kernel;

use Drupal\domain\Entity\Domain;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests hostname validation constraints on the domain entity.
 *
 * @group domain
 */
#[Group('domain')]
#[RunTestsInSeparateProcesses]
class DomainHostnameConstraintTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['domain'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('domain');
  }

  /**
   * Creates a domain entity with the given hostname.
   *
   * @param string $hostname
   *   The hostname to use.
   *
   * @return \Drupal\domain\Entity\Domain
   *   The domain entity (unsaved).
   */
  protected function createDomainEntity(string $hostname): Domain {
    /** @var \Drupal\domain\Entity\Domain $domain */
    $domain = Domain::create([
      'id' => str_replace(['.', ':'], '_', $hostname),
      'hostname' => $hostname,
      'name' => 'Test ' . $hostname,
      'scheme' => 'http',
      'status' => 1,
      'weight' => 0,
      'is_default' => FALSE,
    ]);
    return $domain;
  }

  /**
   * Validates a domain entity via config typed data.
   *
   * @param \Drupal\domain\Entity\Domain $domain
   *   The domain entity to validate.
   *
   * @return \Symfony\Component\Validator\ConstraintViolationListInterface
   *   The violations.
   */
  protected function validateDomain(Domain $domain) {
    /** @var \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager */
    $typed_config_manager = $this->container->get('config.typed');
    return $typed_config_manager
      ->createFromNameAndData(
        $domain->getConfigDependencyName(),
        $domain->toArray()
      )
      ->validate();
  }

  /**
   * Extracts violation messages for a given property path.
   *
   * @param \Symfony\Component\Validator\ConstraintViolationListInterface $violations
   *   The violation list.
   * @param string $property_path
   *   The property path to filter by (e.g. 'hostname').
   *
   * @return string[]
   *   The violation messages for that property path.
   */
  protected function getMessagesForPropertyPath($violations, string $property_path): array {
    $messages = [];
    foreach ($violations as $violation) {
      if ($violation->getPropertyPath() === $property_path) {
        $messages[] = (string) $violation->getMessage();
      }
    }
    return $messages;
  }

  /**
   * Asserts violation messages from a specific constraint.
   *
   * @param \Symfony\Component\Validator\ConstraintViolationListInterface $violations
   *   The violation list.
   * @param string $constraint_name
   *   The short class name of the constraint.
   *
   * @return string[]
   *   The violation messages from that constraint.
   */
  protected function getMessagesForConstraint($violations, string $constraint_name): array {
    $messages = [];
    foreach ($violations as $violation) {
      $constraint = $violation->getConstraint();
      $class = get_class($constraint);
      $short = substr($class, strrpos($class, '\\') + 1);
      if ($short === $constraint_name) {
        $messages[] = (string) $violation->getMessage();
      }
    }
    return $messages;
  }

  /**
   * Tests that valid hostnames produce no violations.
   */
  public function testValidHostnames(): void {
    $valid = [
      'localhost',
      'localhost:8080',
      'example.com',
      'one.example.com',
      'sub.one.example.com',
      'example.com:8080',
      'www.example.com',
    ];
    foreach ($valid as $hostname) {
      $domain = $this->createDomainEntity($hostname);
      $messages = $this->getMessagesForConstraint(
        $this->validateDomain($domain),
        'DomainHostnameConstraint'
      );
      $this->assertEmpty($messages, "Expected no DomainHostname violations for '$hostname', got: " . implode(', ', $messages));
    }
  }

  /**
   * Tests that a hostname without a dot is rejected.
   */
  public function testNoDot(): void {
    $domain = $this->createDomainEntity('examplecom');
    $messages = $this->getMessagesForPropertyPath(
      $this->validateDomain($domain),
      'hostname'
    );
    $this->assertContains(
      'At least one dot (.) is required, except when using <em>localhost</em>.',
      $messages
    );
  }

  /**
   * Tests that too many colons are rejected.
   */
  public function testTooManyColons(): void {
    $domain = $this->createDomainEntity('example.com::8080');
    $messages = $this->getMessagesForPropertyPath(
      $this->validateDomain($domain),
      'hostname'
    );
    $this->assertContains(
      'Only one colon (:) is allowed.',
      $messages
    );
  }

  /**
   * Tests that a non-numeric port is rejected.
   */
  public function testPortNotNumeric(): void {
    $domain = $this->createDomainEntity('example.com:abc');
    $messages = $this->getMessagesForPropertyPath(
      $this->validateDomain($domain),
      'hostname'
    );
    $this->assertContains(
      'The port protocol must be an integer.',
      $messages
    );
  }

  /**
   * Tests that a hostname starting with a dot is rejected.
   */
  public function testStartsWithDot(): void {
    $domain = $this->createDomainEntity('.example.com');
    $messages = $this->getMessagesForPropertyPath(
      $this->validateDomain($domain),
      'hostname'
    );
    $this->assertContains(
      'The domain must not begin with a dot (.)',
      $messages
    );
  }

  /**
   * Tests that a hostname ending with a dot is rejected.
   */
  public function testEndsWithDot(): void {
    $domain = $this->createDomainEntity('example.com.');
    $messages = $this->getMessagesForPropertyPath(
      $this->validateDomain($domain),
      'hostname'
    );
    $this->assertContains(
      'The domain must not end with a dot (.)',
      $messages
    );
  }

  /**
   * Tests that invalid characters are rejected.
   */
  public function testInvalidCharacters(): void {
    $domain = $this->createDomainEntity("\xC3\xA9xample.com");
    $messages = $this->getMessagesForPropertyPath(
      $this->validateDomain($domain),
      'hostname'
    );
    $this->assertContains(
      'Only alphanumeric characters, dashes, and a colon are allowed.',
      $messages
    );
  }

  /**
   * Tests that uppercase characters are rejected.
   */
  public function testNotLowercase(): void {
    $domain = $this->createDomainEntity('EXAMPLE.com');
    $messages = $this->getMessagesForPropertyPath(
      $this->validateDomain($domain),
      'hostname'
    );
    $this->assertContains(
      'Only lower-case characters are allowed.',
      $messages
    );
  }

  /**
   * Tests that www prefix is rejected when config is set.
   */
  public function testWwwPrefix(): void {
    $this->config('domain.settings')->set('www_prefix', TRUE)->save();
    $domain = $this->createDomainEntity('www.example.com');
    $messages = $this->getMessagesForPropertyPath(
      $this->validateDomain($domain),
      'hostname'
    );
    $this->assertContains(
      'WWW prefix handling: Domains must be registered without the www. prefix.',
      $messages
    );
  }

  /**
   * Tests that non-ASCII is allowed when config permits it.
   */
  public function testAllowNonAscii(): void {
    $this->config('domain.settings')
      ->set('allow_non_ascii', TRUE)
      ->save();
    $domain = $this->createDomainEntity("\xC3\xA9xample.com");
    $messages = $this->getMessagesForConstraint(
      $this->validateDomain($domain),
      'DomainHostnameConstraint'
    );
    $this->assertEmpty($messages, 'Expected no violations for non-ASCII hostname when allowed.');
  }

  /**
   * Tests that duplicate hostnames are rejected.
   */
  public function testDuplicateHostname(): void {
    // Save the first domain.
    $first = $this->createDomainEntity('duplicate.example.com');
    $first->save();

    // Create a second domain with the same hostname.
    $second = Domain::create([
      'id' => 'duplicate_example_com_2',
      'hostname' => 'duplicate.example.com',
      'name' => 'Duplicate test',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 1,
      'is_default' => FALSE,
    ]);
    $messages = $this->getMessagesForPropertyPath(
      $this->validateDomain($second),
      'hostname'
    );
    $this->assertContains(
      'The hostname (duplicate.example.com) is already registered.',
      $messages
    );
  }

  /**
   * Tests that re-saving an existing domain does not trigger uniqueness.
   */
  public function testResaveExistingDomain(): void {
    $domain = $this->createDomainEntity('resave.example.com');
    $domain->save();

    // Reload and validate — same hostname, same domain_id.
    $reloaded = Domain::load($domain->id());
    $messages = $this->getMessagesForConstraint(
      $this->validateDomain($reloaded),
      'DomainUniqueHostnameConstraint'
    );
    $this->assertEmpty($messages, 'Re-saving an existing domain must not trigger a uniqueness violation.');
  }

  /**
   * Tests that valid schemes produce no violations.
   */
  public function testValidScheme(): void {
    foreach (['http', 'https', 'variable'] as $scheme) {
      $domain = $this->createDomainEntity('example.com');
      $domain->set('scheme', $scheme);
      $messages = $this->getMessagesForPropertyPath(
        $this->validateDomain($domain),
        'scheme'
      );
      $this->assertEmpty($messages, "Expected no violations for scheme '$scheme', got: " . implode(', ', $messages));
    }
  }

  /**
   * Tests that an invalid scheme produces a violation.
   */
  public function testInvalidScheme(): void {
    $domain = $this->createDomainEntity('example.com');
    $domain->set('scheme', 'ftp');
    $messages = $this->getMessagesForPropertyPath(
      $this->validateDomain($domain),
      'scheme'
    );
    $this->assertNotEmpty($messages, 'Expected a violation for invalid scheme "ftp".');
  }

  /**
   * Tests that a positive domain_id produces no violations.
   */
  public function testValidDomainId(): void {
    $domain = $this->createDomainEntity('example.com');
    $domain->set('domain_id', 12345);
    $messages = $this->getMessagesForPropertyPath(
      $this->validateDomain($domain),
      'domain_id'
    );
    $this->assertEmpty($messages, 'Expected no violations for positive domain_id.');
  }

  /**
   * Tests that a negative domain_id produces a Range violation.
   */
  public function testNegativeDomainId(): void {
    $domain = $this->createDomainEntity('example.com');
    $domain->set('domain_id', -1);
    $messages = $this->getMessagesForPropertyPath(
      $this->validateDomain($domain),
      'domain_id'
    );
    $this->assertNotEmpty($messages, 'Expected a Range violation for negative domain_id.');
  }

  /**
   * Tests hook_domain_validate_alter integration.
   */
  public function testHookDomainValidateAlter(): void {
    $this->enableModules(['domain_test']);
    $this->container->get('module_handler')->reload();

    $domain = $this->createDomainEntity('fail.example.com');
    $messages = $this->getMessagesForPropertyPath(
      $this->validateDomain($domain),
      'hostname'
    );
    $this->assertContains(
      'Fail.example.com cannot be registered',
      $messages
    );
  }

}
