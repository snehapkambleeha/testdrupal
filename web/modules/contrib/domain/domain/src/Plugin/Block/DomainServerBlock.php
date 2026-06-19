<?php

namespace Drupal\domain\Plugin\Block;

use Drupal\Component\Utility\Html;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a server information block for a domain request.
 */
#[Block(
  id: 'domain_server_block',
  admin_label: new TranslatableMarkup('Domain server information'),
)]
class DomainServerBlock extends DomainBlockBase {

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->renderer = $container->get('renderer');
    $instance->configFactory = $container->get('config.factory');

    return $instance;
  }

  /**
   * Overrides \Drupal\block\BlockBase::access().
   */
  public function access(AccountInterface $account, $return_as_object = FALSE) {
    $access = AccessResult::allowedIfHasPermissions($account,
              ['administer domains', 'view domain information'], 'OR');
    return $return_as_object ? $access : $access->isAllowed();
  }

  /**
   * Build the output.
   */
  public function build() {
    /** @var \Drupal\domain\DomainInterface $domain */
    $domain = $this->domainNegotiationContext->getDomain();

    if (is_null($domain)) {
      return [
        '#markup' => $this->t('No domain record could be loaded.'),
      ];
    }
    $header = [$this->t('Server'), $this->t('Value')];
    $rows = [];
    $rows[] = [
      $this->t('HTTP_HOST request'),
      Html::escape($_SERVER['HTTP_HOST']),
    ];
    // Check the response test.
    $domain->getResponse();
    $check = $this->domainStorage->loadByHostname($_SERVER['HTTP_HOST']);
    $match = $this->t('Exact match');
    // This value is not translatable.
    $environment = 'default';
    if (is_null($check)) {
      // Specific check for Domain Alias.
      if (isset($domain->alias)) {
        $match = $this->t('ALIAS: Using alias %id', ['%id' => $domain->alias->getPattern()]);
        // Get the environment.
        $environment = $domain->alias->getEnvironment();
      }
      else {
        $match = $this->t('FALSE: Using default domain.');
      }
    }

    $rows[] = [
      $this->t('Domain match'),
      $match,
    ];
    $rows[] = [
      $this->t('Environment'),
      $environment,
    ];
    $rows[] = [
      $this->t('Canonical hostname'),
      $domain->getCanonical(),
    ];
    $rows[] = [
      $this->t('Base path'),
      $domain->getPath(),
    ];
    $rows[] = [
      $this->t('Current URL'),
      $domain->getUrl(),
    ];

    $ignore_www = (bool) $this->configFactory->get('domain.settings')->get('www_prefix');
    $rows[] = [
      $this->t('Strip www prefix'),
      $ignore_www ? $this->t('On') : $this->t('Off'),
    ];
    $list = $domain->toArray();
    ksort($list);
    foreach ($list as $key => $value) {
      if (is_null($value)) {
        $value = $this->t('NULL');
      }
      elseif ($value === TRUE) {
        $value = $this->t('TRUE');
      }
      elseif ($value === FALSE) {
        $value = $this->t('FALSE');
      }
      elseif ($key === 'status' || $key === 'is_default') {
        $value = (bool) $value ? $this->t('FALSE') : $this->t('TRUE');
      }
      $rows[] = [
        Html::escape($key),
        !is_array($value) ? Html::escape($value) : $this->printArray($value),
      ];
    }

    return [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];
  }

  /**
   * Prints array data for the server block.
   *
   * @param array $array
   *   An array of data. Note that we support two levels of nesting.
   *
   * @return string
   *   A suitable output string.
   */
  public function printArray(array $array) {
    $items = [];
    foreach ($array as $key => $val) {
      if (!is_array($val)) {
        $value = Html::escape($val);
      }
      else {
        $list = [];
        foreach ($val as $k => $v) {
          $list[] = $this->t('@key : @value', ['@key' => $k, '@value' => $v]);
        }
        $value = implode('<br />', $list);
      }
      $items[] = $this->t('@key : @value', ['@key' => $key, '@value' => $value]);
    }
    $variables = [
      'domain_server' => [
        '#theme' => 'item_list',
        '#items' => $items,
      ],
    ];

    return $this->renderer->render($variables);
  }

}
