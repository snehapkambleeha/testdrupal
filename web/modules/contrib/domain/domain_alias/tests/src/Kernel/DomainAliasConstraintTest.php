<?php

namespace Drupal\Tests\domain_alias\Kernel;

use Drupal\domain\Entity\Domain;
use Drupal\domain_alias\Entity\DomainAlias;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests validation constraints on the domain alias entity.
 *
 * @group domain_alias
 */
#[Group('domain_alias')]
#[RunTestsInSeparateProcesses]
class DomainAliasConstraintTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['domain', 'domain_alias'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('domain');
    $this->installConfig(['domain_alias']);
  }

  /**
   * Creates a domain alias entity with the given values.
   *
   * @param array $values
   *   Values to merge with defaults.
   *
   * @return \Drupal\domain_alias\Entity\DomainAlias
   *   The domain alias entity (unsaved).
   */
  protected function createAliasEntity(array $values): DomainAlias {
    $values += [
      'id' => 'test_alias',
      'domain_id' => 'example_com',
      'pattern' => 'test.example.com',
      'redirect' => 0,
      'environment' => 'default',
      'weight' => 0,
    ];
    /** @var \Drupal\domain_alias\Entity\DomainAlias $alias */
    $alias = DomainAlias::create($values);
    return $alias;
  }

  /**
   * Creates a domain entity for testing.
   *
   * @param string $hostname
   *   The hostname.
   *
   * @return \Drupal\domain\Entity\Domain
   *   The saved domain entity.
   */
  protected function createDomain(string $hostname): Domain {
    $domain = Domain::create([
      'id' => str_replace(['.', ':'], '_', $hostname),
      'hostname' => $hostname,
      'name' => 'Test ' . $hostname,
      'scheme' => 'http',
      'status' => 1,
      'weight' => 0,
      'is_default' => FALSE,
    ]);
    $domain->save();
    return $domain;
  }

  /**
   * Validates a domain alias entity via config typed data.
   *
   * @param \Drupal\domain_alias\Entity\DomainAlias $alias
   *   The domain alias entity to validate.
   *
   * @return \Symfony\Component\Validator\ConstraintViolationListInterface
   *   The violations.
   */
  protected function validateAlias(DomainAlias $alias) {
    /** @var \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager */
    $typed_config_manager = $this->container->get('config.typed');
    return $typed_config_manager
      ->createFromNameAndData(
        $alias->getConfigDependencyName(),
        $alias->toArray()
      )
      ->validate();
  }

  /**
   * Extracts violation messages for a given property path.
   *
   * @param \Symfony\Component\Validator\ConstraintViolationListInterface $violations
   *   The violation list.
   * @param string $property_path
   *   The property path to filter by.
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
   * Tests that valid patterns produce no violations on pattern.
   */
  public function testValidPatterns(): void {
    $valid = [
      'localhost',
      '*.example.com',
      'example.com:8080',
      'test.example.com',
      'example.com:*',
    ];
    foreach ($valid as $pattern) {
      $alias = $this->createAliasEntity([
        'id' => str_replace(['.', ':', '*'], ['_', '_', 'w'], $pattern),
        'pattern' => $pattern,
      ]);
      $messages = $this->getMessagesForPropertyPath(
        $this->validateAlias($alias),
        'pattern'
      );
      $this->assertEmpty(
        $messages,
        "Expected no pattern violations for '$pattern', got: " . implode(', ', $messages)
      );
    }
  }

  /**
   * Tests that a pattern without a dot is rejected.
   */
  public function testNoDot(): void {
    $alias = $this->createAliasEntity(['pattern' => 'foobar']);
    $messages = $this->getMessagesForPropertyPath(
      $this->validateAlias($alias),
      'pattern'
    );
    $this->assertContains(
      'At least one dot (.) is required, except when using <em>localhost</em>.',
      $messages
    );
  }

  /**
   * Tests that multiple wildcards are rejected.
   */
  public function testMultipleWildcards(): void {
    $alias = $this->createAliasEntity(['pattern' => '*.*.example.com']);
    $messages = $this->getMessagesForPropertyPath(
      $this->validateAlias($alias),
      'pattern'
    );
    $this->assertContains(
      'You may only have one wildcard character in each alias.',
      $messages
    );
  }

  /**
   * Tests that too many colons are rejected.
   */
  public function testTooManyColons(): void {
    $alias = $this->createAliasEntity([
      'pattern' => 'example.com::8080',
    ]);
    $messages = $this->getMessagesForPropertyPath(
      $this->validateAlias($alias),
      'pattern'
    );
    $this->assertContains(
      'You may only have one colon ":" character in each alias.',
      $messages
    );
  }

  /**
   * Tests that an invalid port after colon is rejected.
   */
  public function testInvalidPortAfterColon(): void {
    $alias = $this->createAliasEntity([
      'pattern' => 'example.com:abc',
    ]);
    $messages = $this->getMessagesForPropertyPath(
      $this->validateAlias($alias),
      'pattern'
    );
    $this->assertContains(
      'A colon may only be followed by an integer indicating the proper port or the wildcard character (*).',
      $messages
    );
  }

  /**
   * Tests that a pattern starting with a dot is rejected.
   */
  public function testStartsWithDot(): void {
    $alias = $this->createAliasEntity([
      'pattern' => '.example.com',
    ]);
    $messages = $this->getMessagesForPropertyPath(
      $this->validateAlias($alias),
      'pattern'
    );
    $this->assertContains(
      'The pattern cannot begin with a dot.',
      $messages
    );
  }

  /**
   * Tests that a pattern ending with a dot is rejected.
   */
  public function testEndsWithDot(): void {
    $alias = $this->createAliasEntity([
      'pattern' => 'example.com.',
    ]);
    $messages = $this->getMessagesForPropertyPath(
      $this->validateAlias($alias),
      'pattern'
    );
    $this->assertContains(
      'The pattern cannot end with a dot.',
      $messages
    );
  }

  /**
   * Tests that invalid characters are rejected.
   */
  public function testInvalidCharacters(): void {
    $alias = $this->createAliasEntity([
      'pattern' => 'ex ample.com',
    ]);
    $messages = $this->getMessagesForPropertyPath(
      $this->validateAlias($alias),
      'pattern'
    );
    $this->assertContains(
      'The pattern contains invalid characters.',
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
    $alias = $this->createAliasEntity([
      'pattern' => "\xC3\xA9xample.com",
    ]);
    $messages = $this->getMessagesForPropertyPath(
      $this->validateAlias($alias),
      'pattern'
    );
    // Should have no "invalid characters" violation.
    $this->assertNotContains(
      'The pattern contains invalid characters.',
      $messages
    );
  }

  /**
   * Tests that a pattern matching a domain hostname is rejected.
   */
  public function testPatternMatchesDomain(): void {
    $this->createDomain('example.com');
    $alias = $this->createAliasEntity([
      'pattern' => 'example.com',
    ]);
    $messages = $this->getMessagesForPropertyPath(
      $this->validateAlias($alias),
      'pattern'
    );
    $this->assertContains(
      'The pattern matches an existing domain record.',
      $messages
    );
  }

  /**
   * Tests that a duplicate pattern is rejected.
   */
  public function testDuplicatePattern(): void {
    $first = $this->createAliasEntity([
      'id' => 'first_alias',
      'pattern' => '*.example.com',
    ]);
    $first->save();

    $second = $this->createAliasEntity([
      'id' => 'second_alias',
      'pattern' => '*.example.com',
    ]);
    $messages = $this->getMessagesForPropertyPath(
      $this->validateAlias($second),
      'pattern'
    );
    $this->assertContains(
      'The pattern already exists.',
      $messages
    );
  }

  /**
   * Tests re-saving an existing alias produces no uniqueness violation.
   */
  public function testResaveExistingAlias(): void {
    $alias = $this->createAliasEntity([
      'id' => 'resave_alias',
      'pattern' => 'resave.example.com',
    ]);
    $alias->save();

    // Reload and validate — same pattern, same id.
    $reloaded = DomainAlias::load('resave_alias');
    $messages = $this->getMessagesForPropertyPath(
      $this->validateAlias($reloaded),
      'pattern'
    );
    $this->assertNotContains(
      'The pattern already exists.',
      $messages,
      'Re-saving an existing alias must not trigger a uniqueness violation.'
    );
  }

  /**
   * Tests that valid redirect values produce no violations.
   */
  public function testValidRedirect(): void {
    foreach ([0, 301, 302] as $redirect) {
      $alias = $this->createAliasEntity([
        'id' => 'redirect_' . $redirect,
        'redirect' => $redirect,
      ]);
      $messages = $this->getMessagesForPropertyPath(
        $this->validateAlias($alias),
        'redirect'
      );
      $this->assertEmpty(
        $messages,
        "Expected no violations for redirect $redirect, got: " . implode(', ', $messages)
      );
    }
  }

  /**
   * Tests that an invalid redirect value produces a violation.
   */
  public function testInvalidRedirect(): void {
    $alias = $this->createAliasEntity(['redirect' => 200]);
    $messages = $this->getMessagesForPropertyPath(
      $this->validateAlias($alias),
      'redirect'
    );
    $this->assertNotEmpty(
      $messages,
      'Expected a Choice violation for redirect 200.'
    );
  }

  /**
   * Tests that valid environment values produce no violations.
   */
  public function testValidEnvironment(): void {
    foreach (['default', 'local', 'staging'] as $env) {
      $alias = $this->createAliasEntity([
        'id' => 'env_' . $env,
        'environment' => $env,
      ]);
      $messages = $this->getMessagesForPropertyPath(
        $this->validateAlias($alias),
        'environment'
      );
      $this->assertEmpty(
        $messages,
        "Expected no violations for environment '$env', got: " . implode(', ', $messages)
      );
    }
  }

  /**
   * Tests that an invalid environment produces a violation.
   */
  public function testInvalidEnvironment(): void {
    $alias = $this->createAliasEntity([
      'environment' => 'production',
    ]);
    $messages = $this->getMessagesForPropertyPath(
      $this->validateAlias($alias),
      'environment'
    );
    $this->assertNotEmpty(
      $messages,
      'Expected a violation for environment "production".'
    );
  }

}
