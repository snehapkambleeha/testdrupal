<?php

namespace Drupal\domain\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides entity reference selections for the domain entity type.
 */
#[EntityReferenceSelection(
  id: 'domain_admin',
  label: new TranslatableMarkup('Domain administrator selection'),
  group: 'domain_admin',
  weight: 5,
  entity_types: ['domain'],
)]
class DomainAdminSelection extends DomainSelection {

  /**
   * Sets the context for the alter hook.
   *
   * The only difference between this selector and its parent are the
   * permissions used to restrict access. Since the field information is not
   * available through the DefaultSelector class, we have to coerce that
   * information to pass it to our hook.
   *
   * We could do this by reading the id from the annotation, but setting an
   * explicit variable seems more obvious for developers.
   *
   * @var string
   */
  protected $fieldType = 'admin';

}
