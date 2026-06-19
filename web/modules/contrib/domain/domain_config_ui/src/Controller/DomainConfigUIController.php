<?php

namespace Drupal\domain_config_ui\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\CachedStorage;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Url;
use Drupal\domain\Controller\DomainControllerBase;
use Drupal\domain_config\Config\DomainConfigCollectionUtils;
use Drupal\domain_config_ui\DomainConfigUIManager;
use Drupal\domain_config_ui\DomainConfigUITrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller routines for AJAX callbacks for domain actions.
 */
class DomainConfigUIController extends DomainControllerBase {

  use DomainConfigUITrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected CachedStorage $configStorage,
    protected MessengerInterface $messenger,
    protected PathMatcherInterface $pathMatcher,
    protected RequestStack $requestStack,
    protected DomainConfigUIManager $domainConfigUiManager,
    protected LanguageManagerInterface $languageManager,
  ) {
    parent::__construct($this->entityTypeManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('config.storage'),
      $container->get('messenger'),
      $container->get('path.matcher'),
      $container->get('request_stack'),
      $container->get('domain_config_ui.manager'),
      $container->get('language_manager'),
    );
  }

  /**
   * Handles AJAX operations to add/remove configuration forms.
   *
   * @param string $op
   *   The operation being performed, either 'enable' to enable the
   *   configuration, 'disable' to disable the domain configuration, or 'remove'
   *   to disable the configuration and remove its stored data.
   * @param string $domain_id
   *   The domain id.
   * @param string $config_names
   *   The configuration name.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response to redirect back to the calling form.
   *   Supported by the UrlGeneratorTrait.
   */
  public function ajaxOperation($op, $domain_id, $config_names) {

    $valid_request = !empty($domain_id) && !empty($config_names)
      && $this->domainConfigUiManager->getActiveDomainId() === $domain_id;

    if ($valid_request) {

      $success = FALSE;
      $message = '';

      $config_names_array = explode(',', $config_names);

      switch ($op) {
        case 'enable':
          $success = $this->domainConfigUiManager->addConfigurationsToDomain($domain_id, $config_names_array);
          if ($success) {
            $message = $this->t('Form added to domain configuration interface.');
          }
          break;

        case 'remove':
          $success = $this->domainConfigUiManager->removeConfigurationsFromDomain($domain_id, $config_names_array);
          if ($success) {
            $message = $this->t('Form removed from domain configuration interface.');
          }
          break;
      }

      // Set a message.
      if ($success) {
        $this->messenger->addMessage($message);
      }
      else {
        $this->messenger->addError($this->t('The operation failed.'));
      }
    }
    else {
      $this->messenger->addError($this->t('Bad request.'));
    }

    // Return to the invoking page.
    $query = $this->requestStack->getCurrentRequest()->query;
    return new RedirectResponse($query->get('destination'), 302);
  }

  /**
   * Lists all stored configuration.
   */
  public function overview() {
    $elements = [];
    $page = [
      'table' => [
        '#type' => 'table',
        '#header' => [
          'name' => t('Configuration key'),
          'domain' => t('Domain'),
          'domain_id' => t('Domain ID'),
          'languages' => t('Languages'),
          'actions' => t('Actions'),
        ],
      ],
    ];
    $languages = $this->languageManager->getLanguages();
    $domains = $this->domainStorage->loadMultipleSorted();
    foreach ($domains as $domain) {
      $domain_collection = $this->configStorage->createCollection(
        DomainConfigCollectionUtils::createDomainConfigCollectionName($domain->id()));
      $domain_configs = $domain_collection->listAll();
      foreach ($domain_configs as $config_name) {
        $conf_languages = [];
        foreach ($languages as $language) {
          $lang_collection = $this->configStorage->createCollection(
            DomainConfigCollectionUtils::createDomainLanguageConfigCollectionName($domain->id(), $language->getId()));
          if ($lang_collection->exists($config_name)) {
            $conf_languages[] = $language->getName();
          }
        }
        $elements[] = [
          'domain' => $domain->label(),
          'domain_id' => $domain->id(),
          'language' => implode(', ', $conf_languages),
          'name' => $config_name,
        ];
      }
    }
    // Sort the items.
    if ($elements !== []) {
      uasort($elements, [$this, 'sortItems']);
      foreach ($elements as $element) {
        $operations = [
          'inspect' => [
            'url' => Url::fromRoute(
              'domain_config_ui.inspect',
              [
                'config_name' => $element['name'],
                'domain_id' => $element['domain_id'],
                'destination' => $this->requestStack->getCurrentRequest()->getRequestUri(),
              ]
            ),
            'title' => $this->t('Inspect'),
          ],
          'delete' => [
            'url' => Url::fromRoute(
              'domain_config_ui.delete',
              [
                'config_names' => $element['name'],
                'domain_id' => $element['domain_id'],
                'destination' => $this->requestStack->getCurrentRequest()->getRequestUri(),
              ]
            ),
            'title' => $this->t('Delete'),
          ],
        ];
        $page['table'][] = [
          'name' => ['#markup' => $element['name']],
          'domain' => ['#markup' => $element['domain']],
          'domain_id' => ['#markup' => $element['domain_id']],
          'language' => ['#markup' => $element['language']],
          'actions' => ['#type' => 'operations', '#links' => $operations],
        ];
      }
    }
    else {
      $page = [
        '#markup' => $this->t('No domain-specific configurations have been found.'),
      ];
    }
    return $page;
  }

  /**
   * Controller for inspecting configuration.
   *
   * @param string $domain_id
   *   The domain id of config object being inspected.
   * @param string $config_name
   *   The domain config object being inspected.
   */
  public function inspectConfig($domain_id = NULL, $config_name = NULL) {
    if (is_null($config_name) || is_null($domain_id)) {
      $url = Url::fromRoute('domain_config_ui.list');
      return new RedirectResponse($url->toString());
    }
    $domain = $this->domainStorage->load($domain_id);
    $domain_collection = $this->configStorage->createCollection('domain.' . $domain->id());
    $config = $domain_collection->read($config_name);
    $page = [
      'help' => [
        '#type' => 'item',
        '#title' => Html::escape($config_name),
        '#markup' => $this->t('This configuration is for the %domain domain.', [
          '%domain' => $domain->label(),
        ]),
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ],
    ];
    $page['text'] = [
      '#markup' => self::printArray($config),
    ];
    return $page;
  }

  /**
   * Sorts items by parent config.
   */
  public function sortItems($a, $b) {
    return strcmp($a['name'], $b['name']);
  }

  /**
   * Prints array data for the form.
   *
   * @param array $array
   *   An array of data. Note that we support two levels of nesting.
   *
   * @return string
   *   A suitable output string.
   */
  public static function printArray(array $array) {
    $items = [];
    foreach ($array as $key => $val) {
      if (!is_array($val)) {
        $value = self::formatValue($val);
        $item = [
          '#theme' => 'item_list',
          '#items' => [$value],
          '#title' => self::formatValue($key),
        ];
        $items[] = \Drupal::service('renderer')->render($item);
      }
      else {
        $list = [];
        foreach ($val as $k => $v) {
          $list[] = t('<strong>@key</strong> : @value', [
            '@key' => $k,
            '@value' => self::formatValue($v),
          ]);
        }
        $variables = [
          '#theme' => 'item_list',
          '#items' => $list,
          '#title' => self::formatValue($key),
        ];
        $items[] = \Drupal::service('renderer')->render($variables);
      }
    }
    $rendered = [
      '#theme' => 'item_list',
      '#items' => $items,
    ];
    return \Drupal::service('renderer')->render($rendered);
  }

  /**
   * Formats a value as a string, for readable output.
   *
   * Taken from config_inspector module.
   *
   * @param mixed $value
   *   The value element.
   *
   * @return string
   *   The value in string form.
   */
  protected static function formatValue($value) {
    if (is_bool($value)) {
      return $value ? 'true' : 'false';
    }
    if (is_scalar($value)) {
      return Html::escape($value);
    }
    // @phpstan-ignore-next-line
    if (empty($value)) {
      return '<' . t('empty') . '>';
    }

    return '<' . gettype($value) . '>';
  }

}
