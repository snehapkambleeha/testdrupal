<?php

namespace Drupal\domain_config_ui\Hook;

use Drupal\config_translation\Form\ConfigTranslationFormBase;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain_config_ui\DomainConfigUIManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Form hook implementations for domain_config_ui.
 */
class DomainConfigUiFormHooks {

  public function __construct(
    protected DomainConfigUIManagerInterface $manager,
    protected AccountProxyInterface $currentUser,
    protected DomainNegotiatorInterface $negotiator,
    protected RequestStack $requestStack,
  ) {}

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(&$form, FormStateInterface $form_state, $form_id) {
    if ($this->manager->getActiveDomainId() && $this->manager->isAllowedRoute()) {
      $form_object = $form_state->getFormObject();
      /** @var \Drupal\Core\Form\ConfigFormBase $form_object */
      if ($form_object instanceof ConfigFormBase) {
        try {
          $method = new \ReflectionMethod($form_object, 'getEditableConfigNames');
          $config_names = $method->invoke($form_object);
          if (empty($config_names)) {
            // No editable config names, try config targets.
            $form['#after_build'][] = [self::class, 'configFormAfterBuild'];
          }
          else {
            $this->enableDomainConfigForm($form, $config_names);
          }
        }
        catch (\ReflectionException) {
          // No getEditableConfigNames method, try config targets.
          $form['#after_build'][] = [self::class, 'configFormAfterBuild'];
        }
      }
      elseif ($form_object instanceof ConfigTranslationFormBase) {
        $reflection = new \ReflectionClass($form_object);
        $property = $reflection->getProperty('mapper');
        /** @var \Drupal\config_translation\ConfigMapperInterface $mapper */
        $mapper = $property->getValue($form_object);
        $config_names = $mapper->getConfigNames();
        if (!empty($config_names)) {
          $form['#validate'][] = [self::class, 'domainTranslateValidate'];
        }
      }
    }
  }

  /**
   * Enable domain management for this form.
   */
  public function enableDomainConfigForm(array &$form, mixed $config_names) {
    if (!empty($config_names) && $this->manager->isAllowedConfiguration($config_names)) {
      $is_registered = $this->manager->isRegisteredConfiguration($config_names);
      if ($this->manager->canAdministerDomainConfig()) {
        $op = $is_registered ? 'remove' : 'enable';
        $config_names = is_array($config_names) ? $config_names : [$config_names];
        $form['domain_config_ui_toggler'] = $this->toggleButton($op, $config_names);
      }
      $form['#validate'][] = $is_registered
        ? [self::class, 'domainPermissionValidate']
        : [self::class, 'defaultPermissionValidate'];
    }
  }

  /**
   * An #after_build callback to extract config names.
   *
   * @param array $form
   *   The element being processed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   The processed element.
   */
  public static function configFormAfterBuild(array $form, FormStateInterface $form_state) {
    $config_targets = $form_state->get('config_targets') ?? [];
    $instance = \Drupal::service(self::class);
    $instance->enableDomainConfigForm($form, array_keys($config_targets));
    return $form;
  }

  /**
   * Generates the markup for the AJAX admin action.
   *
   * @param string $op
   *   An operation: either 'enable' or 'disable'.
   * @param array $config_names
   *   An array of config names.
   */
  protected function toggleButton($op, array $config_names) {
    $admin_form = [];
    if ($this->currentUser->hasPermission('administer domain config ui')) {
      $request = $this->requestStack->getCurrentRequest();
      $domain_id = $this->negotiator->getActiveId();
      if ($op === 'disable') {
        $title = new TranslatableMarkup('Disable domain configuration');
        $params = [
          'op' => $op,
          'domain_id' => $domain_id,
          'config_names' => implode(',', $config_names),
          'destination' => $request->getRequestUri(),
        ];
        $route_name = 'domain_config_ui.inline_action';
      }
      elseif ($op === 'remove') {
        $title = new TranslatableMarkup('Remove domain configuration');
        $params = [
          'domain_id' => $domain_id,
          'config_names' => implode(',', $config_names),
          'remove' => TRUE,
          'destination' => $request->getRequestUri(),
        ];
        $route_name = 'domain_config_ui.delete';
      }
      elseif ($op === 'enable') {
        $title = new TranslatableMarkup('Enable domain configuration');
        $params = [
          'op' => $op,
          'domain_id' => $domain_id,
          'config_names' => implode(',', $config_names),
          'destination' => $request->getRequestUri(),
        ];
        $route_name = 'domain_config_ui.inline_action';
      }
      if (isset($route_name, $params, $title)) {
        $admin_form = [
          '#type' => 'link',
          '#url' => Url::fromRoute($route_name, $params),
          '#title' => $title,
          '#attributes' => [
            'class' => [
              'button',
              'button--primary',
              'button--small',
            ],
          ],
          '#prefix' => '<p>',
          '#suffix' => '</p>',
          '#weight' => -10,
        ];
      }
    }
    return $admin_form;
  }

  /**
   * Validator for domain config permission.
   */
  public static function domainPermissionValidate(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\domain_config_ui\DomainConfigUIManagerInterface $manager */
    $manager = \Drupal::service('domain_config_ui.manager');
    if (!$manager->canUseDomainConfig()) {
      $form_state->setErrorByName('', t('You do not have permission to update this domain configuration override.'));
    }
  }

  /**
   * Validator for default config permission.
   */
  public static function defaultPermissionValidate(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\domain_config_ui\DomainConfigUIManagerInterface $manager */
    $manager = \Drupal::service('domain_config_ui.manager');
    if (!$manager->canSetDefaultDomainConfig()) {
      $form_state->setErrorByName('', t('You do not have permission to update this default domain configuration.'));
    }
  }

  /**
   * Validator for domain config translation.
   */
  public static function domainTranslateValidate(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\domain_config_ui\DomainConfigUIManagerInterface $manager */
    $manager = \Drupal::service('domain_config_ui.manager');
    if (!$manager->canTranslateDomainConfig()) {
      $form_state->setErrorByName('', t('You do not have permission to translate this domain configuration.'));
    }
  }

}
