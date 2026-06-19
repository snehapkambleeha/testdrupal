<?php

namespace Drupal\domain\Entity;

use Drupal\Core\Config\ConfigValueException;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\DomainStorageInterface;

/**
 * Defines the domain entity.
 *
 * @ConfigEntityType(
 *   id = "domain",
 *   label = @Translation("Domain record"),
 *   module = "domain",
 *   handlers = {
 *     "storage" = "Drupal\domain\DomainStorage",
 *     "access" = "Drupal\domain\DomainAccessControlHandler",
 *     "list_builder" = "Drupal\domain\DomainListBuilder",
 *     "form" = {
 *       "default" = "Drupal\domain\Form\DomainForm",
 *       "edit" = "Drupal\domain\Form\DomainForm",
 *       "delete" = "Drupal\domain\Form\DomainDeleteForm"
 *     }
 *   },
 *   static_cache = TRUE,
 *   config_prefix = "record",
 *   admin_permission = "administer domains",
 *   entity_keys = {
 *     "id" = "id",
 *     "domain_id" = "domain_id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "status" = "status",
 *     "weight" = "weight"
 *   },
 *   links = {
 *     "delete-form" = "/admin/config/domain/delete/{domain}",
 *     "edit-form" = "/admin/config/domain/edit/{domain}",
 *     "collection" = "/admin/config/domain",
 *   },
 *   uri_callback = "Drupal\domain\Entity\Domain::uri",
 *   config_export = {
 *     "id",
 *     "domain_id",
 *     "hostname",
 *     "name",
 *     "scheme",
 *     "status",
 *     "weight",
 *     "is_default",
 *     "path_prefix",
 *   }
 * )
 */
class Domain extends ConfigEntityBase implements DomainInterface {

  use StringTranslationTrait;

  /**
   * The ID of the domain entity.
   *
   * @var string
   */
  protected $id;

  /**
   * The domain record ID.
   *
   * @var int
   */
  protected $domain_id;

  /**
   * The domain list name (e.g. Drupal).
   *
   * @var string
   */
  protected $name;

  /**
   * The domain hostname (e.g. example.com).
   *
   * @var string
   */
  protected $hostname;

  /**
   * The domain record sort order.
   *
   * @var int
   */
  protected $weight;

  /**
   * Indicates the default domain.
   *
   * @var bool
   */
  protected $is_default = FALSE;

  /**
   * The domain record protocol (e.g. http://).
   *
   * @var string
   */
  protected $scheme;

  /**
   * The domain record base path, a calculated value.
   *
   * @var string
   */
  protected $path;

  /**
   * The domain record current url, a calculated value.
   *
   * @var string
   */
  protected $url;

  /**
   * The domain record http response test (e.g. 200), a calculated value.
   *
   * @var int
   */
  protected $response = NULL;

  /**
   * The redirect method to use, if needed.
   *
   * @var int|null
   */
  protected $redirect = NULL;

  /**
   * The type of match returned by the negotiator.
   *
   * @var int
   */
  protected $matchType;

  /**
   * The canonical hostname for the domain.
   *
   * @var string
   */
  protected $canonical;

  /**
   * The path prefix for this domain (e.g. "fr", "benl").
   *
   * @var string
   */
  protected $path_prefix = '';

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    /** @var \Drupal\domain\DomainStorageInterface $domain_storage */
    $domain_storage = \Drupal::entityTypeManager()->getStorage('domain');
    $default = $domain_storage->loadDefaultId();
    $count = $storage_controller->getQuery()->accessCheck(FALSE)->count()->execute();
    // Note that we have not created a domain_id, which is only used for
    // node access control and will be added on save.
    $values += [
      'scheme' => $domain_storage->getDefaultScheme(),
      'status' => 1,
      'weight' => $count + 1,
      'is_default' => (int) ($default === FALSE),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isActive() {
    $negotiator = \Drupal::service('domain.negotiator');
    $domain = $negotiator->getActiveDomain();
    if (is_null($domain)) {
      return FALSE;
    }
    return ($this->id() === $domain->id());
  }

  /**
   * {@inheritdoc}
   */
  public function addProperty($name, $value) {
    // @phpstan-ignore-next-line
    if (!isset($this->{$name})) {
      // @phpstan-ignore-next-line
      $this->{$name} = $value;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isDefault() {
    return $this->is_default;
  }

  /**
   * {@inheritdoc}
   */
  public function isHttps() {
    return $this->getScheme(FALSE) === 'https';
  }

  /**
   * {@inheritdoc}
   */
  public function saveDefault() {
    if (!$this->isDefault()) {
      // Swap the current default domain.
      /** @var \Drupal\domain\DomainStorageInterface $storage */
      $storage = \Drupal::entityTypeManager()->getStorage('domain');
      $default = $storage->loadDefaultDomain();
      if ($default instanceof DomainInterface) {
        $default->set('is_default', FALSE);
        $default->setHostname($default->getCanonical());
        $default->save();
      }
      // Save the new default.
      $this->set('is_default', TRUE);
      $this->setHostname($this->getCanonical());
      $this->save();
    }
    else {
      \Drupal::messenger()->addMessage($this->t('The selected domain is already the default.'), 'warning');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function enable() {
    $this->setStatus(TRUE);
    $this->setHostname($this->getCanonical());
    $this->save();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function disable() {
    if (!$this->isDefault()) {
      $this->setStatus(FALSE);
      $this->setHostname($this->getCanonical());
      $this->save();
    }
    else {
      \Drupal::messenger()->addMessage($this->t('The default domain cannot be disabled.'), 'warning');
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function saveProperty($name, $value) {
    // @phpstan-ignore-next-line
    if (isset($this->{$name})) {
      // @phpstan-ignore-next-line
      $this->{$name} = $value;
      $this->setHostname($this->getCanonical());
      $this->save();
      \Drupal::messenger()->addMessage($this->t('The @key attribute was set to @value for domain @hostname.', [
        '@key' => $name,
        '@value' => $value,
        '@hostname' => $this->hostname,
      ]));
    }
    else {
      \Drupal::messenger()->addMessage($this->t('The @key attribute does not exist.', ['@key' => $name]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setPath() {
    $request = \Drupal::requestStack()->getCurrentRequest();
    $base = $request ? $request->getBasePath() . '/' : '/';
    $this->path = $this->getScheme() . $this->getHostname() . $base;
  }

  /**
   * {@inheritdoc}
   */
  public function setUrl() {
    $request = \Drupal::requestStack()->getCurrentRequest();
    if ($request === NULL) {
      return;
    }

    // Without path prefix, keep the original simple behavior.
    if (!\Drupal::getContainer()->getParameter('domain.path_prefix')) {
      $this->url = $this->getScheme() . $this->getHostname() . $request->getRequestUri();
      return;
    }

    // Use getPathInfo() which excludes the base path (e.g., /drupal/)
    // so prefix manipulation works correctly in subdirectory installs.
    $path_info = $request->getPathInfo();
    /** @var \Drupal\domain\HttpKernel\DomainPrefixPathProcessor $processor */
    $processor = \Drupal::service('domain.prefix_path_processor');
    $path_info = $processor->processInbound($path_info, $request);

    $prefix = $this->getPathPrefix();
    if ($prefix !== '') {
      $path_info = '/' . $prefix . $path_info;
    }

    $query = $request->getQueryString();
    $this->url = rtrim($this->getBasePath(), '/')
      . $path_info
      . ($query !== NULL ? '?' . $query : '');
  }

  /**
   * {@inheritdoc}
   */
  public function getBasePath() {
    if (!isset($this->path)) {
      $this->setPath();
    }
    return $this->path;
  }

  /**
   * {@inheritdoc}
   */
  public function getPath() {
    $path = $this->getBasePath();
    $prefix = $this->getPathPrefix();
    if ($prefix !== '') {
      $path .= $prefix . '/';
    }
    return $path;
  }

  /**
   * Returns the raw path of the domain object, without the base url.
   */
  public function getRawPath() {
    return $this->getScheme() . $this->getHostname();
  }

  /**
   * Builds a link from a known internal path.
   *
   * @param string $path
   *   A Drupal-formatted internal path, starting with /. Note that it is the
   *   caller's responsibility to handle the base_path().
   *
   * @return string
   *   The built link.
   */
  public function buildUrl($path) {
    return $this->getRawPath() . $path;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl() {
    if (!isset($this->url)) {
      $this->setUrl();
    }
    return $this->url;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    if (!($storage instanceof DomainStorageInterface)) {
      return;
    }
    // Sets the default domain properly.
    /** @var self $default */
    $default = $storage->loadDefaultDomain();
    if (is_null($default)) {
      $this->is_default = TRUE;
    }
    elseif ($this->is_default && $default->getDomainId() !== $this->getDomainId()) {
      // Swap the current default.
      $default->is_default = FALSE;
      $default->save();
    }
    // Ensures we have a proper domain_id but does not erase existing ones.
    if ($this->isNew() && is_null($this->getDomainId())) {
      $this->createDomainId();
    }
    // Validate path prefix format.
    $this->validatePathPrefix();
    // Prevent duplicate hostname.
    $this->validateHostnameUniqueness($storage);
  }

  /**
   * Validates path prefix format.
   *
   * When the "allow non-ASCII" setting is disabled, only ASCII
   * lowercase alphanumeric characters, hyphens, and underscores
   * are permitted. When enabled, Unicode lowercase letters and
   * numbers are also accepted.
   *
   * @throws \Drupal\Core\Config\ConfigValueException
   *   When the prefix contains invalid characters.
   */
  protected function validatePathPrefix(): void {
    $prefix = $this->getPathPrefix();
    if ($prefix === '') {
      return;
    }
    $non_ascii = (bool) \Drupal::config('domain.settings')
      ->get('allow_non_ascii');
    if ($non_ascii) {
      $pattern = '/^[\p{L}\p{N}][\p{L}\p{N}_\-]*$/u';
    }
    else {
      $pattern = '/^[a-z0-9][a-z0-9_\-]*$/';
    }
    if (!preg_match($pattern, $prefix)) {
      throw new ConfigValueException("The path prefix ($prefix) may only contain lowercase letters, numbers, hyphens, and underscores.");
    }
  }

  /**
   * Validates hostname + path_prefix uniqueness.
   *
   * Allows multiple domains to share a hostname when they have
   * different path prefixes.
   *
   * @param \Drupal\domain\DomainStorageInterface $storage
   *   The domain storage handler.
   *
   * @throws \Drupal\Core\Config\ConfigValueException
   *   When a duplicate hostname + prefix is found.
   */
  protected function validateHostnameUniqueness(DomainStorageInterface $storage): void {
    $hostname = $this->getHostname();
    $prefix = $this->getPathPrefix();
    // Do not use domain loader because it may change hostname.
    /** @var \Drupal\domain\DomainInterface[] $existing */
    $existing = $storage->loadByProperties(['hostname' => $hostname]);
    foreach ($existing as $domain) {
      if ($this->getDomainId() === $domain->getDomainId()) {
        continue;
      }
      if ($prefix === $domain->getPathPrefix()) {
        if ($prefix === '') {
          throw new ConfigValueException("The hostname ($hostname) is already registered.");
        }
        throw new ConfigValueException("The hostname ($hostname) with path prefix ($prefix) is already registered.");
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
    // Invalidate cache tags relevant to domains.
    \Drupal::service('cache_tags.invalidator')
      ->invalidateTags(['rendered', 'url.site']);
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);
    foreach ($entities as $entity) {
      $actions = $storage->loadMultiple([
        'domain_default_action.' . $entity->id(),
        'domain_delete_action.' . $entity->id(),
        'domain_disable_action.' . $entity->id(),
        'domain_enable_action.' . $entity->id(),
      ]);
      foreach ($actions as $action) {
        $action->delete();
      }
    }
    // Invalidate cache tags relevant to domains.
    \Drupal::service('cache_tags.invalidator')
      ->invalidateTags(['rendered', 'url.site']);
  }

  /**
   * {@inheritdoc}
   */
  public function createDomainId() {
    // We cannot reliably use sequences (1, 2, 3) because those can be different
    // across environments. Instead, we use the crc32 hash function to create a
    // unique numeric id for each domain. In some systems (Windows?) we have
    // reports of crc32 returning a negative number. Issue #2794047.
    // If we don't use hash(), then crc32() returns different results for 32-
    // and 64-bit systems. On 32-bit systems, the number returned may also be
    // too large for PHP.
    // See #2908236.
    $id = hash('crc32', $this->id());
    $id = abs(hexdec(substr($id, 0, -2)));
    $this->createNumericId($id);
  }

  /**
   * Creates a unique numeric id for use in the {node_access} table.
   *
   * @param int $id
   *   An integer to use as the numeric id.
   */
  public function createNumericId($id) {
    // Ensure that this value is unique.
    $storage = \Drupal::entityTypeManager()->getStorage('domain');
    $result = $storage->loadByProperties(['domain_id' => $id]);
    if (count($result) === 0) {
      $this->domain_id = $id;
    }
    else {
      $id++;
      $this->createNumericId($id);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getScheme($add_suffix = TRUE) {
    $scheme = $this->scheme;
    if ($scheme === 'variable') {
      /** @var \Drupal\domain\DomainStorageInterface $storage */
      $storage = \Drupal::entityTypeManager()->getStorage('domain');
      $scheme = $storage->getDefaultScheme();
    }
    elseif ($scheme !== 'https') {
      $scheme = 'http';
    }
    $scheme .= ($add_suffix) ? '://' : '';

    return $scheme;
  }

  /**
   * {@inheritdoc}
   */
  public function getRawScheme() {
    return $this->scheme;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse() {
    if (is_null($this->response)) {
      $validator = \Drupal::service('domain.validator');
      $validator->checkResponse($this);
    }
    return $this->response;
  }

  /**
   * {@inheritdoc}
   */
  public function setResponse($response) {
    $this->response = $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getLink($current_path = TRUE) {
    $options = ['absolute' => TRUE, 'https' => $this->isHttps()];
    if ($current_path) {
      $url = Url::fromUri($this->getUrl(), $options);
    }
    else {
      $url = Url::fromUri($this->getPath(), $options);
    }

    $label = $this->getCanonical();
    if ($this->getPathPrefix() !== '') {
      $label .= '/' . $this->getPathPrefix();
    }

    return Link::fromTextAndUrl($label, $url)->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function getRedirect() {
    return $this->redirect;
  }

  /**
   * {@inheritdoc}
   */
  public function setRedirect($code = 302) {
    $this->redirect = $code;
  }

  /**
   * {@inheritdoc}
   */
  public function getHostname() {
    return $this->hostname;
  }

  /**
   * {@inheritdoc}
   */
  public function setHostname($hostname) {
    $this->hostname = $hostname;
  }

  /**
   * {@inheritdoc}
   */
  public function getDomainId() {
    return $this->domain_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return $this->weight;
  }

  /**
   * {@inheritdoc}
   */
  public function setMatchType($match_type = DomainNegotiatorInterface::DOMAIN_MATCHED_EXACT) {
    $this->matchType = $match_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getMatchType() {
    return $this->matchType;
  }

  /**
   * {@inheritdoc}
   */
  public function getPort() {
    $ports = explode(':', $this->getHostname());
    if (isset($ports[1])) {
      return ':' . $ports[1];
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function setCanonical($hostname = NULL) {
    if (is_null($hostname)) {
      $this->canonical = $this->getHostname();
    }
    else {
      $this->canonical = $hostname;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCanonical() {
    if (is_null($this->canonical)) {
      $this->setCanonical();
    }

    return $this->canonical;
  }

  /**
   * Entity URI callback.
   *
   * @param \Drupal\domain\DomainInterface $domain
   *   The Domain object.
   *
   * @return \Drupal\Core\Url
   *   The Domain URL.
   */
  public static function uri(DomainInterface $domain): Url {
    return Url::fromUri($domain->getPath(), ['absolute' => TRUE]);
  }

  /**
   * Prevent render errors when Twig wants to read this object.
   *
   * @see \Drupal\Core\Template\TwigExtension::escapeFilter()
   *
   * @return string
   *   The name of the domain being rendered.
   */
  public function toString() {
    return $this->name;
  }

  /**
   * {@inheritdoc}
   */
  public function getPathPrefix(): string {
    return $this->path_prefix ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setPathPrefix(string $prefix): static {
    $this->path_prefix = $prefix;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function matchPathPrefix(string $path): int|false {
    $prefix = $this->getPathPrefix();
    if ($prefix === '') {
      return FALSE;
    }

    // Direct match (fast path for ASCII prefixes).
    if (str_starts_with($path, '/' . $prefix)) {
      $prefix_len = strlen($prefix) + 1;
      if (!isset($path[$prefix_len]) || $path[$prefix_len] === '/') {
        return $prefix_len;
      }
    }

    // Decode the first segment for non-ASCII prefixes
    // sent percent-encoded by browsers. The container
    // parameter short-circuits when non-ASCII is disabled.
    if (\Drupal::getContainer()->getParameter('domain.allow_non_ascii')
      && preg_match('/[\x80-\xff]/', $prefix)
      && str_contains($path, '%')) {
      $slash_pos = strpos($path, '/', 1);
      $raw_segment = $slash_pos !== FALSE
        ? substr($path, 1, $slash_pos - 1)
        : substr($path, 1);
      if (rawurldecode($raw_segment) === $prefix) {
        return $slash_pos !== FALSE ? $slash_pos : strlen($path);
      }
    }

    return FALSE;
  }

}
