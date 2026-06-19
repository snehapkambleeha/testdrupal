<?php

namespace Drupal\domain\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\domain\DomainElementManagerInterface;
use Drupal\domain\DomainInterface;

/**
 * Entity-related hook implementations for domain.
 */
class DomainEntityHooks {

  public function __construct(
    protected DomainElementManagerInterface $elementManager,
  ) {}

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for user_form.
   */
  #[Hook('form_user_form_alter')]
  public function formUserFormAlter(&$form, &$form_state, $form_id) {
    // Add the options hidden from the user silently to the form.
    $form = $this->elementManager->setFormOptions($form, $form_state, DomainInterface::DOMAIN_ADMIN_FIELD);
  }

  /**
   * Implements hook_views_data_alter().
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(array &$data) {
    $table = 'user__' . DomainInterface::DOMAIN_ADMIN_FIELD;
    // Domains are not stored in the database, so remove
    // relationships.
    unset($data[$table][DomainInterface::DOMAIN_ADMIN_FIELD]['relationship']);
  }

}
