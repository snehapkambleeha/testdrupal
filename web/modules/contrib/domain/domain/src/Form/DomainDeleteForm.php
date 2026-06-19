<?php

namespace Drupal\domain\Form;

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Url;

/**
 * Builds the form to delete a domain record.
 */
class DomainDeleteForm extends EntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('domain.admin');
  }

}
