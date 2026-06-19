<?php

namespace Drupal\domain_config\Config;

/**
 * Defines events for domain configuration overrides.
 *
 * @see \Drupal\Core\Config\ConfigCrudEvent
 */
final class DomainConfigOverrideEvents {

  /**
   * The name of the event fired when saving the configuration override.
   *
   * This event allows you to perform custom actions whenever a domain config
   * override is saved. The event listener method receives a
   * \Drupal\domain_config\Config\DomainConfigOverrideCrudEvent instance.
   *
   * @Event
   *
   * @see \Drupal\domain_config\Config\DomainConfigOverrideCrudEvent
   * @see \Drupal\domain_config\Config\DomainConfigOverride::save()
   * @see \Drupal\locale\LocaleConfigSubscriber
   */
  const SAVE_OVERRIDE = 'domain.save_override';

  /**
   * The name of the event fired when deleting the configuration override.
   *
   * This event allows you to perform custom actions whenever a domain config
   * override is deleted. The event listener method receives a
   * \Drupal\domain_config\Config\DomainConfigOverrideCrudEvent instance.
   *
   * @Event
   *
   * @see \Drupal\domain_config\Config\DomainConfigOverrideCrudEvent
   * @see \Drupal\domain_config\Config\DomainConfigOverride::delete()
   * @see \Drupal\locale\LocaleConfigSubscriber
   */
  const DELETE_OVERRIDE = 'domain.delete_override';

}
