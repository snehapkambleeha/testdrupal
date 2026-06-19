<?php

namespace Drupal\domain_access\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\domain\DomainElementManagerInterface;
use Drupal\domain_access\DomainAccessManager;
use Drupal\domain_access\DomainAccessManagerInterface;

/**
 * Form hook implementations for domain_access.
 */
class DomainAccessFormHooks {

  use StringTranslationTrait;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected DomainElementManagerInterface $elementManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(&$form, &$form_state, $form_id) {
    $object = $form_state->getFormObject();
    if ($object instanceof EntityFormInterface) {
      $form['#process'][] = [self::class, 'defaultFormValues'];
    }
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for node_form.
   */
  #[Hook('form_node_form_alter')]
  public function formNodeFormAlter(&$form, FormState $form_state, $form_id) {
    $config = $this->configFactory->get('domain_access.settings');
    $move_enabled = (bool) $config->get('node_advanced_tab');
    $has_access_field = isset($form[DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD])
      && !isset($form[DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD]['#group']);
    $has_all_field = isset($form[DomainAccessManagerInterface::DOMAIN_ACCESS_ALL_FIELD])
      && !isset($form[DomainAccessManagerInterface::DOMAIN_ACCESS_ALL_FIELD]['#group']);

    if ($move_enabled && ($has_access_field || $has_all_field)) {
      if ($has_access_field) {
        $form[DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD]['#group'] = 'domain';
      }
      if ($has_all_field) {
        $form[DomainAccessManagerInterface::DOMAIN_ACCESS_ALL_FIELD]['#group'] = 'domain';
      }

      $form['domain'] = [
        '#type' => 'details',
        '#open' => (bool) $config->get('node_advanced_tab_open'),
        '#title' => $this->t('Domain settings'),
        '#group' => 'advanced',
        '#attributes' => [
          'class' => ['node-form-options'],
        ],
        '#attached' => [
          'library' => ['node/drupal.node'],
        ],
        '#weight' => 100,
        '#optional' => TRUE,
      ];
    }

    $form = $this->elementManager->setFormOptions($form, $form_state, DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD);
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for user_form.
   */
  #[Hook('form_user_form_alter')]
  public function formUserFormAlter(&$form, &$form_state, $form_id) {
    $form = $this->elementManager->setFormOptions($form, $form_state, DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD);
  }

  /**
   * Implements hook_form_FORM_ID_alter() for devel_generate.
   */
  #[Hook('form_devel_generate_form_content_alter')]
  public function formDevelGenerateFormContentAlter(&$form, &$form_state, $form_id) {
    $form['submit']['#weight'] = 10;
    $list = ['random-selection' => $this->t('Random selection')];
    /** @var \Drupal\domain\DomainStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('domain');
    $list += $storage->loadOptionsList();
    $form['domain_access'] = [
      '#title' => $this->t('Domains'),
      '#type' => 'checkboxes',
      '#options' => $list,
      '#weight' => 2,
      '#multiple' => TRUE,
      '#size' => count($list) > 5 ? 5 : count($list),
      '#default_value' => ['random-selection'],
      '#description' => $this->t('Sets the domains for created nodes. Random selection overrides other choices.'),
    ];
    $form['domain_all'] = [
      '#title' => $this->t('Send to all affiliates'),
      '#type' => 'radios',
      '#options' => [
        'random-selection' => $this->t('Random selection'),
        'yes' => $this->t('Yes'),
        'no' => $this->t('No'),
      ],
      '#default_value' => 'random-selection',
      '#weight' => 3,
      '#description' => $this->t('Sets visibility across all affiliates.'),
    ];
  }

  /**
   * Implements hook_form_FORM_ID_alter() for devel_generate user.
   */
  #[Hook('form_devel_generate_form_user_alter')]
  public function formDevelGenerateFormUserAlter(&$form, &$form_state, $form_id) {
    $this->formDevelGenerateFormContentAlter($form, $form_state, $form_id);
  }

  /**
   * Implements hook_form_FORM_ID_alter() for field_config_edit.
   */
  #[Hook('form_field_config_edit_form_alter')]
  public function formFieldConfigEditFormAlter(&$form, FormStateInterface $form_state, $form_id) {
    $form_object = $form_state->getFormObject();
    if ($form_object instanceof EntityFormInterface) {
      $config = $form_object->getEntity();
      if ($config instanceof FieldConfigInterface && $config->getName() === DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD) {
        $subform =& $form['third_party_settings']['domain_access'];
        $subform['#type'] = 'fieldset';
        $subform['#title'] = $this->t('Domain access settings');
        $subform['add_current_domain'] = [
          '#title' => $this->t('Add current domain to default values'),
          '#type' => 'checkbox',
          '#default_value' => $config->getThirdPartySetting('domain_access', 'add_current_domain'),
        ];
      }
    }
  }

  /**
   * Defines default values for the domain access field.
   *
   * @see domain_access_entity_field_access()
   */
  public static function defaultFormValues($form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\ContentEntityForm $form_object */
    $form_object = $form_state->getFormObject();
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $entity = $form_object->getEntity();

    if (!$entity->isNew() &&
      isset($form['field_domain_access']) &&
      !$form['field_domain_access']['#access'] &&
      isset($form['field_domain_access']['widget']['#default_value'])
    ) {
      $values = DomainAccessManager::getAccessValues($entity);
      $form['field_domain_access']['widget']['#default_value'] = array_keys($values);
    }

    return $form;
  }

}
