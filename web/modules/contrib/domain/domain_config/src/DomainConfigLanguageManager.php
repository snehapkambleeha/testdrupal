<?php

namespace Drupal\domain_config;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\domain_config\Config\DomainLanguageConfigFactoryOverrideInterface;
use Drupal\language\ConfigurableLanguageManager;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\language\LanguageNegotiatorInterface;

/**
 * Extends the language manager to set the current language on runtime.
 */
class DomainConfigLanguageManager extends ConfigurableLanguageManager {

  /**
   * The decorated language manager.
   *
   * @var \Drupal\language\ConfigurableLanguageManagerInterface
   */
  protected ConfigurableLanguageManagerInterface $decoratedManager;

  /**
   * The domain language configuration override service.
   *
   * @var \Drupal\domain_config\Config\DomainLanguageConfigFactoryOverrideInterface
   */
  protected $domainConfigFactoryOverride;

  /**
   * Set language manager that is being decorated by this service.
   *
   * @param \Drupal\language\ConfigurableLanguageManagerInterface $manager
   *   The language manager that is being decorated.
   */
  public function setDecoratedLanguageManager(ConfigurableLanguageManagerInterface $manager): void {
    $this->decoratedManager = $manager;
  }

  /**
   * Set the domain language config factory override.
   *
   * @param \Drupal\domain_config\Config\DomainLanguageConfigFactoryOverrideInterface $factory_override
   *   The domain language config factory override.
   */
  public function setDomainConfigFactoryOverride(DomainLanguageConfigFactoryOverrideInterface $factory_override): void {
    $this->domainConfigFactoryOverride = $factory_override;
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguageConfigOverride($langcode, $name) {
    return $this->decoratedManager->getLanguageConfigOverride($langcode, $name);
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguageConfigOverrideStorage($langcode) {
    return $this->decoratedManager->getLanguageConfigOverrideStorage($langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function getNegotiator() {
    return $this->decoratedManager->getNegotiator();
  }

  /**
   * {@inheritdoc}
   */
  public function setNegotiator(LanguageNegotiatorInterface $negotiator): void {
    $this->decoratedManager->setNegotiator($negotiator);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinedLanguageTypes() {
    return $this->decoratedManager->getDefinedLanguageTypes();
  }

  /**
   * {@inheritdoc}
   */
  public function saveLanguageTypesConfiguration(array $values): void {
    $this->decoratedManager->saveLanguageTypesConfiguration($values);
  }

  /**
   * {@inheritdoc}
   */
  public function updateLockedLanguageWeights(): void {
    $this->decoratedManager->updateLockedLanguageWeights();
  }

  /**
   * {@inheritdoc}
   */
  public function getStandardLanguageListWithoutConfigured() {
    return $this->decoratedManager->getStandardLanguageListWithoutConfigured();
  }

  /**
   * {@inheritdoc}
   */
  public function getNegotiatedLanguageMethod($type = LanguageInterface::TYPE_INTERFACE) {
    return $this->decoratedManager->getNegotiatedLanguageMethod($type);
  }

  /**
   * {@inheritdoc}
   */
  public function isMultilingual() {
    return $this->decoratedManager->isMultilingual();
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguageTypes() {
    return $this->decoratedManager->getLanguageTypes();
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinedLanguageTypesInfo() {
    return $this->decoratedManager->getDefinedLanguageTypesInfo();
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentLanguage($type = LanguageInterface::TYPE_INTERFACE) {
    return $this->decoratedManager->getCurrentLanguage($type);
  }

  /**
   * {@inheritdoc}
   */
  public function reset($type = NULL) {
    $this->decoratedManager->reset($type);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultLanguage() {
    return $this->decoratedManager->getDefaultLanguage();
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguages($flags = LanguageInterface::STATE_CONFIGURABLE) {
    return $this->decoratedManager->getLanguages($flags);
  }

  /**
   * {@inheritdoc}
   */
  public function getNativeLanguages() {
    return $this->decoratedManager->getNativeLanguages();
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguage($langcode) {
    return $this->decoratedManager->getLanguage($langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguageName($langcode) {
    return $this->decoratedManager->getLanguageName($langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultLockedLanguages($weight = 0) {
    return $this->decoratedManager->getDefaultLockedLanguages($weight);
  }

  /**
   * {@inheritdoc}
   */
  public function isLanguageLocked($langcode) {
    return $this->decoratedManager->isLanguageLocked($langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function getFallbackCandidates(array $context = []) {
    return $this->decoratedManager->getFallbackCandidates($context);
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguageSwitchLinks($type, Url $url) {
    return $this->decoratedManager->getLanguageSwitchLinks($type, $url);
  }

  /**
   * {@inheritdoc}
   */
  public function setConfigOverrideLanguage(?LanguageInterface $language = NULL) {
    $this->decoratedManager->setConfigOverrideLanguage($language);
    $this->domainConfigFactoryOverride->setLanguage($language);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigOverrideLanguage() {
    return $this->decoratedManager->getConfigOverrideLanguage();
  }

}
