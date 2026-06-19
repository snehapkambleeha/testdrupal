<?php

namespace Drupal\Tests\domain\Traits;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;

/**
 * Contains helper classes for tests to set up various configuration.
 */
trait DomainLoginTestTrait {

  /**
   * Logs in a user on a specific host.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to log in.
   * @param string $host
   *   The host on which to perform the login.
   */
  protected function drupalLoginOnHost(AccountInterface $account, $host) {
    if ($this->loggedInUser) {
      $this->drupalLogout();
    }

    if ($this->useOneTimeLoginLinks) {
      // Reload to get latest login timestamp.
      $storage = \Drupal::entityTypeManager()->getStorage('user');
      /** @var \Drupal\user\UserInterface $accountUnchanged */
      $accountUnchanged = $storage->loadUnchanged($account->id());
      $login = $this->drupalUserPassResetUrl($accountUnchanged, ['base_url' => $host]) . '/login?destination=user/' . $account->id();
      $this->drupalGet($login);
    }
    else {
      $this->drupalGet(Url::fromRoute('user.login', [], ['absolute' => TRUE, 'base_url' => $host]));
      $this->submitForm([
        'name' => $account->getAccountName(),
        // @phpstan-ignore-next-line
        'pass' => $account->passRaw,
      ], 'Log in');
    }

    // @see ::drupalUserIsLoggedIn()
    // @phpstan-ignore-next-line
    $account->sessionId = $this->getSession()->getCookie(\Drupal::service('session_configuration')->getOptions(\Drupal::request())['name']);
    $this->assertTrue($this->drupalUserIsLoggedIn($account), "User {$account->getAccountName()} successfully logged in.");

    $this->loggedInUser = $account;
    $this->container->get('current_user')->setAccount($account);
  }

  /**
   * Generates a unique URL for a user to log in and reset their password.
   *
   * @param \Drupal\user\UserInterface $account
   *   An object containing the user account.
   * @param array $options
   *   (optional) A keyed array of settings. Supported options are:
   *   - langcode: A language code to be used when generating locale-sensitive
   *    URLs. If langcode is NULL the users preferred language is used.
   *
   * @return string
   *   A unique URL that provides a one-time log in for the user, from which
   *   they can change their password.
   */
  protected function drupalUserPassResetUrl($account, $options = []) {
    $timestamp = \Drupal::time()->getCurrentTime();
    $langcode = $options['langcode'] ?? $account->getPreferredLangcode();
    $url_options = [
      'absolute' => TRUE,
      'language' => \Drupal::languageManager()->getLanguage($langcode),
    ];
    if ($options['base_url']) {
      $url_options['base_url'] = $options['base_url'];
    }
    return Url::fromRoute('user.reset',
      [
        'uid' => $account->id(),
        'timestamp' => $timestamp,
        'hash' => user_pass_rehash($account, $timestamp),
      ],
      $url_options,
    )->toString();
  }

}
