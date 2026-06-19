<?php

namespace Drupal\Tests\domain_access\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\domain_access\Plugin\Condition\DomainAccessCondition;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Domain Access condition.
 *
 * @group domain_access
 */
#[Group('domain_access')]
#[RunTestsInSeparateProcesses]
class DomainAccessConditionTest extends KernelTestBase {

  use NodeCreationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The condition plugin.
   *
   * @var \Drupal\domain_access\Plugin\Condition\DomainAccessCondition
   */
  protected $condition;

  /**
   * Domain list.
   *
   * @var \Drupal\domain\DomainInterface[]
   */
  protected $domains = [];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'text',
    'user',
    'node',
    'domain',
    'domain_access',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('domain');
    $this->installEntitySchema('field_storage_config');
    $this->installEntitySchema('field_config');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('system', ['sequences']);
    $this->installConfig($this::$modules);

    // Create a node type.
    $node_type = NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ]);
    $node_type->save();

    // Create domains.
    $this->entityTypeManager = $this->container->get('entity_type.manager');

    $this->domains['example_com'] = $this->entityTypeManager->getStorage('domain')->create([
      'id' => 'example_com',
      'name' => 'Example.com',
      'hostname' => 'example.com',
    ]);
    $this->domains['example_com']->save();

    $this->domains['test_domain'] = $this->entityTypeManager->getStorage('domain')->create([
      'id' => 'test_domain',
      'name' => 'Test Domain',
      'hostname' => 'test.example.com',
    ]);
    $this->domains['test_domain']->save();

    // Create the condition plugin.
    $this->condition = new DomainAccessCondition(
      ['domains' => []],
      'domain_access',
      \Drupal::service('plugin.manager.condition')->getDefinition('domain_access'),
      $this->entityTypeManager,
    );
  }

  /**
   * Tests the domain access condition without any domains configured.
   */
  public function testDomainContentConditionEmptyDomains(): void {
    // With no domains configured, the condition should evaluate to TRUE.
    $this->assertTrue($this->condition->evaluate(), 'Domain condition with no domains returns TRUE');
  }

  /**
   * Tests the domain access condition with a node that has domain access.
   */
  public function testDomainContentConditionWithMatchingNode(): void {
    // Create a node with domain access.
    $node = Node::create([
      'type' => 'article',
      'title' => 'Test Article',
      'field_domain_access' => [
        ['target_id' => 'example_com'],
      ],
    ]);
    $node->save();

    // Configure the condition with one domain.
    $this->condition->setConfiguration([
      'domains' => ['example_com' => 'example_com'],
    ]);

    // Create a context for the node.
    $context = new Context(new EntityContextDefinition('entity:node'), $node);
    $this->condition->setContext('node', $context);

    // The condition should evaluate to TRUE.
    $this->assertTrue($this->condition->evaluate(), 'Domain condition with matching node returns TRUE');
  }

  /**
   * Tests domain access condition with a node that doesn't have domain access.
   */
  public function testDomainContentConditionWithNonMatchingNode(): void {
    // Create a node with domain access.
    $node = Node::create([
      'type' => 'article',
      'title' => 'Test Article',
      'field_domain_access' => [
        ['target_id' => 'example_com'],
      ],
    ]);
    $node->save();

    // Configure the condition with a different domain.
    $this->condition->setConfiguration([
      'domains' => ['test_domain' => 'test_domain'],
    ]);

    // Create a context for the node.
    $context = new Context(new EntityContextDefinition('entity:node'), $node);
    $this->condition->setContext('node', $context);

    // The condition should evaluate to FALSE.
    $this->assertFalse($this->condition->evaluate(), 'Domain condition with non-matching node returns FALSE');
  }

  /**
   * Tests domain access condition with a node that has multiple domain access.
   */
  public function testDomainContentConditionWithMultipleDomains(): void {
    // Create a node with multiple domain access.
    $node = Node::create([
      'type' => 'article',
      'title' => 'Test Article',
      'field_domain_access' => [
        ['target_id' => 'example_com'],
        ['target_id' => 'test_domain'],
      ],
    ]);
    $node->save();

    // Configure the condition with one domain.
    $this->condition->setConfiguration([
      'domains' => ['test_domain' => 'test_domain'],
    ]);

    // Create a context for the node.
    $context = new Context(new EntityContextDefinition('entity:node'), $node);
    $this->condition->setContext('node', $context);

    // The condition should evaluate to TRUE.
    $this->assertTrue($this->condition->evaluate(), 'Domain condition with one matching domain returns TRUE');
  }

  /**
   * Tests domain access condition with a node but without the domain field.
   */
  public function testDomainContentConditionWithoutDomainField(): void {
    // Create a different node type without the domain field.
    $node_type = NodeType::create([
      'type' => 'page',
      'name' => 'Page',
    ]);
    $node_type->save();

    // Create a node of that type.
    $node = Node::create([
      'type' => 'page',
      'title' => 'Test Page',
    ]);
    $node->save();

    // Configure the condition with one domain.
    $this->condition->setConfiguration([
      'domains' => ['example_com' => 'example_com'],
    ]);

    // Create a context for the node.
    $context = new Context(new EntityContextDefinition('entity:node'), $node);
    $this->condition->setContext('node', $context);

    // The condition should evaluate to FALSE.
    $this->assertFalse($this->condition->evaluate(), 'Domain condition with node missing domain field returns FALSE');
  }

  /**
   * Tests the summary method of the condition.
   */
  public function testSummary(): void {
    // Test with no domains.
    $summary = $this->condition->summary();
    $this->assertEquals('Content is assigned to any domain', $summary);

    // Test with one domain.
    $this->condition->setConfiguration([
      'domains' => ['example_com' => 'example_com'],
    ]);
    $summary = $this->condition->summary();
    $this->assertEquals('Content is assigned to Example.com', $summary);

    // Test with multiple domains.
    $this->condition->setConfiguration([
      'domains' => [
        'example_com' => 'example_com',
        'test_domain' => 'test_domain',
      ],
    ]);
    $summary = $this->condition->summary();
    $this->assertEquals('Content is assigned to Example.com, Test Domain', $summary);

    // Test with negation.
    $this->condition->setConfig('negate', TRUE);
    $summary = $this->condition->summary();
    $this->assertEquals('Content is not assigned to Example.com, Test Domain', $summary);
  }

  /**
   * Tests the configuration form.
   */
  public function testBuildConfigurationForm(): void {
    $form = [];
    $form_state = new FormState();

    $form = $this->condition->buildConfigurationForm($form, $form_state);

    $this->assertArrayHasKey('domains', $form);
    $this->assertEquals('checkboxes', $form['domains']['#type']);
    $this->assertEquals(['example_com' => 'Example.com', 'test_domain' => 'Test Domain'], $form['domains']['#options']);
  }

  /**
   * Tests the cache contexts.
   */
  public function testGetCacheContexts(): void {
    $contexts = $this->condition->getCacheContexts();
    $this->assertContains('domain', $contexts);
  }

}
