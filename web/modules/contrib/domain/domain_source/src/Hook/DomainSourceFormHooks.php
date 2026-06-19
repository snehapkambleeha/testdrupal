<?php

namespace Drupal\domain_source\Hook;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\DomainRedirectResponse;
use Drupal\domain_access\DomainAccessManagerInterface;
use Drupal\domain_source\DomainSourceElementManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Form hook implementations for domain_source.
 */
class DomainSourceFormHooks {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
    protected DomainNegotiatorInterface $negotiator,
    protected DomainSourceElementManagerInterface $elementManager,
  ) {}

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(&$form, &$form_state, $form_id) {
    $object = $form_state->getFormObject();
    // Set up our TrustedRedirect handler for form saves.
    if (isset($form[DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD])
        && $object instanceof FormInterface && is_callable([$object, 'getEntity'])
        && $entity = $object->getEntity()
       ) {
      $form['#validate'][] = [self::class, 'formValidate'];
      foreach ($form['actions'] as $key => $element) {
        // Redirect submit handlers, but not the preview button.
        if ($key !== 'preview' && isset($element['#type'])
            && $element['#type'] === 'submit') {
          $form['actions'][$key]['#submit'][] = [self::class, 'formSubmit'];
        }
      }
    }
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for node_form.
   */
  #[Hook('form_node_form_alter')]
  public function formNodeFormAlter(&$form, FormStateInterface $form_state, $form_id) {
    $hide = TRUE;
    $form = $this->elementManager->setFormOptions($form, $form_state, DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD, $hide);
    // If using a select field, load the JS to show/hide options.
    if (isset($form[DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD]['widget']['#type']) && $this->moduleHandler->moduleExists('domain_access') && isset($form[DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD])) {
      if ($form[DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD]['widget']['#type'] === 'select') {
        $form['#attached']['library'][] = 'domain_source/drupal.domain_source';
      }
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter() for devel_generate.
   */
  #[Hook('form_devel_generate_form_content_alter')]
  public function formDevelGenerateFormContentAlter(&$form, &$form_state, $form_id) {
    $list = ['_derive' => $this->t('Derive from domain selection')];
    /** @var \Drupal\domain\DomainStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('domain');
    $list += $storage->loadOptionsList();
    $form['domain_source'] = [
      '#title' => $this->t('Domain source'),
      '#type' => 'checkboxes',
      '#options' => $list,
      '#weight' => 4,
      '#multiple' => TRUE,
      '#size' => count($list) > 5 ? 5 : count($list),
      '#default_value' => ['_derive'],
      '#description' => $this->t('Sets the source domain for created nodes.'),
    ];
  }

  /**
   * Validate form submissions.
   */
  public static function formValidate($element, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $access_values = [];
    if (isset($values[DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD]) && \Drupal::moduleHandler()->moduleExists('domain_access') && isset($values[DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD])) {
      $access_values = $values[DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD];
      $source_value = current($values[DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD]);
    }
    $source_valid = FALSE;
    // @phpstan-ignore-next-line
    if (empty($source_value)) {
      $source_valid = TRUE;
    }
    else {
      foreach ($access_values as $value) {
        if (is_array($value) && $value === $source_value) {
          $source_valid = TRUE;
        }
        elseif (is_string($value) && isset($source_value['target_id']) && $value === $source_value['target_id']) {
          $source_valid = TRUE;
        }
      }
    }
    if (!$source_valid) {
      $form_state->setError($element, t('The source domain must be selected as a publishing option.'));
    }
  }

  /**
   * Submit handler for domain source form redirect.
   */
  public static function formSubmit(&$form, FormStateInterface $form_state) {
    $object = $form_state->getFormObject();
    if (!$object instanceof EntityFormInterface) {
      return;
    }

    $entity = $object->getEntity();
    if (!$entity) {
      return;
    }

    $current_domain = \Drupal::service('domain.negotiator')->getActiveDomain();
    $domain_source_id = \Drupal::service('domain_source.helper')->getSourceDomainId($entity);

    if (!$current_domain instanceof DomainInterface || empty($domain_source_id)) {
      return;
    }

    if ($current_domain->id() !== $domain_source_id) {
      $url_object = $entity->toUrl('canonical', ['absolute' => TRUE]);

      if ($url_object instanceof Url) {
        $url = $url_object->toString();
        $redirect_host = parse_url($url, PHP_URL_HOST);

        if (!empty($redirect_host) && DomainRedirectResponse::checkTrustedHost($redirect_host)) {
          $redirect = new TrustedRedirectResponse($url, Response::HTTP_FOUND, ['Cache-Control' => 'no-cache, must-revalidate']);
          $form_state->setResponse($redirect);
        }
      }
    }
  }

}
