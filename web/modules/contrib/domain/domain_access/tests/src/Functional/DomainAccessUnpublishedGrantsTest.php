<?php

namespace Drupal\Tests\domain_access\Functional;

use Drupal\Core\Database\Database;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\domain\Functional\DomainTestBase;
use Drupal\domain_access\DomainAccessManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verify unpublished nodes are included for update/delete node_access queries.
 *
 * This covers the case where other modules (e.g., ACB) combine grants with AND
 * logic and rely on node_access-tagged list queries for update/delete.
 *
 * @group domain_access
 */
#[Group('domain_access')]
#[RunTestsInSeparateProcesses]
class DomainAccessUnpublishedGrantsTest extends DomainTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['domain', 'domain_access', 'field', 'node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Ensure node_access table is clear.
    // Otherwise, it keeps a record allowing "view" access to all nodes.
    Database::getConnection()->delete('node_access')->execute();

    // Ensure basic content types exist when not using standard profile.
    if ($this->profile !== 'standard') {
      $this->drupalCreateContentType([
        'type' => 'page',
        'name' => 'Basic page',
        'display_submitted' => FALSE,
      ]);
    }
  }

  /**
   * Test that unpublished nodes are returned for view/update/delete queries.
   */
  public function testDomainUnpublishedQueries(): void {
    // Get two domains IDs for testing.
    $this->domainCreateTestDomains(2);
    $domains = $this->getDomains();
    $domain_ids = array_keys($domains);
    $one = $domain_ids[0];
    $two = $domain_ids[1];

    // Create a user who can edit and delete domain content.
    $account = $this->drupalCreateUser([
      'access content',
      'edit domain content',
      'delete domain content',
    ]);

    // Assign user to domain $two.
    $this->addDomainsToEntity('user', $account->id(), $two, DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD);

    // Create an unpublished node assigned to domain $two.
    $node = $this->drupalCreateNode([
      'type' => 'page',
      'status' => 0,
      DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD => [$two],
    ]);

    // Explicitly set active domain to $two.
    $negotiator = \Drupal::service('domain.negotiator');
    $negotiator->setActiveDomain($domains[$two]);

    // Check user grants for the node.
    $this->assertTrue($this->checkUserGrants($account, 'update', $node), 'User can update unpublished node on assigned domain.');
    $this->assertTrue($this->checkUserGrants($account, 'delete', $node), 'User can delete unpublished node on assigned domain.');
    $this->assertFalse($this->checkUserGrants($account, 'view', $node), 'User cannot view unpublished node on assigned domain.');

    $viewResults = $this->runQuery($account, 'view');
    $updateResults = $this->runQuery($account, 'update');
    $deleteResults = $this->runQuery($account, 'delete');
    $this->assertNotContains($node->id(), $viewResults, 'Unpublished node does not appear in view results on assigned domain.');
    $this->assertContains($node->id(), $updateResults, 'Unpublished node appears in update results on assigned domain.');
    $this->assertContains($node->id(), $deleteResults, 'Unpublished node appears in delete results on assigned domain.');

    $this->assertNotContainsEquals($node->id(), $this->entityQuery($account), 'Unpublished node does not appear in entity query results on assigned domain.');

    // Switch active domain to $one.
    $negotiator->setActiveDomain($domains[$one]);

    $viewResults = $this->runQuery($account, 'view');
    $updateResults = $this->runQuery($account, 'update');
    $deleteResults = $this->runQuery($account, 'delete');
    $this->assertNotContains($node->id(), $viewResults, 'Unpublished node does not appear in view results on unassigned domain.');
    $this->assertNotContains($node->id(), $updateResults, 'Unpublished node not in view results on unassigned domain.');
    $this->assertNotContains($node->id(), $deleteResults, 'Unpublished node not in delete results on unassigned domain.');

    // Create a user who can view unpublished domain content.
    $account_2 = $this->drupalCreateUser([
      'access content',
      'view unpublished domain content',
    ]);

    // Assign user viewing unpublished content to domain $two.
    $this->addDomainsToEntity('user', $account_2->id(), $two, DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD);

    // Switch active domain to $two and confirm unpublished node is listed.
    $negotiator->setActiveDomain($domains[$two]);

    // Check user grants for the node.
    $this->assertTrue($this->checkUserGrants($account_2, 'view', $node), 'User can view unpublished node on assigned domain.');

    $viewResults = $this->runQuery($account_2, 'view');
    $this->assertContains($node->id(), $viewResults, 'Unpublished node appears in view results on assigned domain.');

    $this->assertContainsEquals($node->id(), $this->entityQuery($account_2), 'Unpublished node appears in entity query results on unassigned domain.');

    // Switch active domain to $one.
    $negotiator->setActiveDomain($domains[$one]);

    // Check user grants for the node.
    $this->assertFalse($this->checkUserGrants($account_2, 'view', $node), 'User cannot view unpublished node on unassigned domain.');

    $viewResults = $this->runQuery($account_2, 'view');
    $this->assertNotContains($node->id(), $viewResults, 'Unpublished node does not appear in view results on unassigned domain.');

    $this->assertNotContainsEquals($node->id(), $this->entityQuery($account_2), 'Unpublished node does not appear in entity query results on unassigned domain.');
  }

  /**
   * Helper to run a node_access-tagged query for a given op.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to check grants for.
   * @param string $op
   *   The operation to check grants for (e.g., 'view', 'update', 'delete').
   *
   * @return array
   *   The list of node IDs that the user has access to for the specified
   *   operation.
   */
  protected function runQuery($account, string $op) {
    $connection = Database::getConnection();
    $query = $connection->select('node', 'n');
    $query->fields('n', ['nid']);
    $query->addTag('node_access');
    $query->addMetaData('op', $op);
    $query->addMetaData('account', $account);
    return $query->execute()->fetchCol();
  }

  /**
   * Check user grants for a specific operation.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to check grants for.
   * @param string $op
   *   The operation to check grants for (e.g., 'view', 'update', 'delete').
   * @param \Drupal\node\NodeInterface $node
   *   The node entity to check grants against.
   */
  protected function checkUserGrants(AccountInterface $account, string $op, $node) {
    // Debug what grants the user has.
    $grants = \Drupal::moduleHandler()->invokeAll('node_grants', [$account, $op]);

    // Debug what records exist for a specific node.
    $connection = Database::getConnection();
    $records = $connection->select('node_access', 'na')
      ->fields('na')
      ->condition('na.nid', $node->id())
      ->execute()
      ->fetchAll();

    // Check if grants match.
    foreach ($records as $record) {
      if (isset($grants[$record->realm]) && in_array($record->gid, $grants[$record->realm])) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Check that a simple entityQuery correctly applies grants.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to use when querying.
   */
  protected function entityQuery(AccountInterface $account) {
    $account_switcher = \Drupal::service('account_switcher');
    $account_switcher->switchTo($account);
    try {
      $query = \Drupal::entityQuery('node')->accessCheck();
      $nids = $query->execute();
    }
    finally {
      $account_switcher->switchBack();
    }
    return $nids;
  }

}
