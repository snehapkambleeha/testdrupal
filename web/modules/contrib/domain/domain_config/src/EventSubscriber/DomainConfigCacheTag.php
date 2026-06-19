<?php

namespace Drupal\domain_config\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\domain_config\Config\DomainConfigOverrideCrudEvent;
use Drupal\domain_config\Config\DomainConfigOverrideEvents;
use Drupal\system\EventSubscriber\ConfigCacheTag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Delegates domain config override events to core's ConfigCacheTag.
 *
 * Domain config overrides fire ConfigCollectionEvents::SAVE_IN_COLLECTION
 * instead of ConfigEvents::SAVE, so core's ConfigCacheTag never sees them.
 * This subscriber bridges the gap by forwarding domain override events
 * to core's handler, which invalidates route_match and rendered cache
 * tags when system.site changes.
 *
 * @see \Drupal\system\EventSubscriber\ConfigCacheTag
 */
class DomainConfigCacheTag implements EventSubscriberInterface {

  public function __construct(
    protected ConfigCacheTag $configCacheTag,
  ) {}

  /**
   * Forwards domain config override events to core's ConfigCacheTag.
   *
   * @param \Drupal\domain_config\Config\DomainConfigOverrideCrudEvent $event
   *   The event to process.
   */
  public function onOverrideChange(DomainConfigOverrideCrudEvent $event) {
    $this->configCacheTag->onSave(
      new ConfigCrudEvent($event->getOverride())
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[DomainConfigOverrideEvents::SAVE_OVERRIDE][] = ['onOverrideChange'];
    $events[DomainConfigOverrideEvents::DELETE_OVERRIDE][] = ['onOverrideChange'];
    return $events;
  }

}
