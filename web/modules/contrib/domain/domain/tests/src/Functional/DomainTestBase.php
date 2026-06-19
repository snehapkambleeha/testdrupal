<?php

namespace Drupal\Tests\domain\Functional;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\domain\Traits\DomainTestTrait;
use Drupal\domain\DomainInterface;

/**
 * Base test class for Domain.
 *
 * @package Drupal\Tests\domain\Functional
 */
abstract class DomainTestBase extends BrowserTestBase {

  use DomainTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['domain', 'node'];

  /**
   * We use the standard profile for testing.
   *
   * @var string
   */
  protected $profile = 'standard';

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set the base hostname for domains.
    /** @var \Drupal\domain\DomainStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('domain');
    $this->baseHostname = $storage->createHostname();

    // Ensure that $this->baseTLD is set.
    $this->setBaseDomain();

    $this->database = \Drupal::database();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // Sleep required to ensure that the site directory can be properly emptied.
    // @see https://www.drupal.org/project/gitlab_templates/issues/3500566#comment-16224265
    sleep(6);
    parent::tearDown();
  }

  /**
   * The methods below are brazenly copied from Rules module.
   *
   * They are all helper methods that make writing tests a bit easier.
   */

  /**
   * Finds link with specified locator.
   *
   * @param string $locator
   *   Link id, title, text or image alt.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The link node element.
   */
  public function findLink($locator) {
    return $this->getSession()->getPage()->findLink($locator);
  }

  /**
   * Confirms absence of link with specified locator.
   *
   * @param string $locator
   *   Link id, title, text or image alt.
   *
   * @return bool
   *   TRUE if link is absent, or FALSE.
   */
  public function findNoLink($locator) {
    return $this->getSession()->getPage()->hasLink($locator) === FALSE;
  }

  /**
   * Finds field (input, textarea, select) with specified locator.
   *
   * @param string $locator
   *   Input id, name or label.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The input field element.
   */
  public function findField($locator) {
    return $this->getSession()->getPage()->findField($locator);
  }

  /**
   * Finds no field exists (input, textarea, select) with specified locator.
   *
   * @param string $locator
   *   Input id, name or label.
   */
  public function findNoField($locator) {
    $this->assertSession()->fieldNotExists($locator);
  }

  /**
   * Finds button with specified locator.
   *
   * @param string $locator
   *   Button id, value or alt.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The button node element.
   */
  public function findButton($locator) {
    return $this->getSession()->getPage()->findButton($locator);
  }

  /**
   * Presses button with specified locator.
   *
   * @param string $locator
   *   Button id, value or alt.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function pressButton($locator) {
    $this->getSession()->getPage()->pressButton($locator);
  }

  /**
   * Fills in field (input, textarea, select) with specified locator.
   *
   * @param string $locator
   *   Input id, name or label.
   * @param string $value
   *   Value.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *
   * @see \Behat\Mink\Element\NodeElement::setValue
   */
  public function fillField($locator, $value) {
    $this->getSession()->getPage()->fillField($locator, $value);
  }

  /**
   * Checks checkbox with specified locator.
   *
   * @param string $locator
   *   An input id, name or label.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function checkField($locator) {
    $this->getSession()->getPage()->checkField($locator);
  }

  /**
   * Unchecks checkbox with specified locator.
   *
   * @param string $locator
   *   An input id, name or label.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function uncheckField($locator) {
    $this->getSession()->getPage()->uncheckField($locator);
  }

  /**
   * Selects option from select field with specified locator.
   *
   * @param string $locator
   *   An input id, name or label.
   * @param string $value
   *   The option value.
   * @param bool $multiple
   *   Whether to select multiple options.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *
   * @see NodeElement::selectOption
   */
  public function selectFieldOption($locator, $value, $multiple = FALSE) {
    $this->getSession()->getPage()->selectFieldOption($locator, $value, $multiple);
  }

  /**
   * Returns whether a given user account is logged in.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account object to check.
   *
   * @return bool
   *   TRUE if a given user account is logged in, or FALSE.
   */
  protected function drupalUserIsLoggedIn(AccountInterface $account): bool {
    // @todo This is a temporary hack for the test login fails when setting $cookie_domain.
    if (!isset($account->session_id)) {
      return (bool) $account->id();
    }
    // The session ID is hashed before being stored in the database.
    // @see \Drupal\Core\Session\SessionHandler::read()
    return (bool) $this->database->query("SELECT sid FROM {users_field_data} u INNER JOIN {sessions} s ON u.uid = s.uid WHERE s.sid = :sid", [':sid' => Crypt::hashBase64($account->session_id)])->fetchField();
  }

  /**
   * Login a user on a specific domain.
   *
   * @param \Drupal\domain\DomainInterface $domain
   *   The domain to log the user into.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to login.
   */
  public function domainLogin(DomainInterface $domain, AccountInterface $account) {
    // Due to a quirk in session handling that we cannot directly access, it
    // works if we login, then logout, and then login to a specific domain.
    $this->drupalLogin($account);
    if ($this->loggedInUser !== FALSE) {
      $this->drupalLogout();
    }

    // Login.
    $this->submitForm([
      'name' => $account->getAccountName(),
      // @phpstan-ignore-next-line
      'pass' => $account->passRaw,
    ], 'Log in');

    // @see BrowserTestBase::drupalUserIsLoggedIn()
    // @phpstan-ignore-next-line
    $account->sessionId = $this->getSession()->getCookie($this->getSessionName());
    $this->assertTrue($this->drupalUserIsLoggedIn($account), 'User successfully logged in.');

    $this->loggedInUser = $account;
    $this->container->get('current_user')->setAccount($account);
  }

}
