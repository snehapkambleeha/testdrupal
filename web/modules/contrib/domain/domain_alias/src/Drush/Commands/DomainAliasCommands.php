<?php

namespace Drupal\domain_alias\Drush\Commands;

use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\domain_alias\DomainAliasInterface;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for the domain_alias module.
 */
class DomainAliasCommands extends DrushCommands implements CustomEventAwareInterface {

  use AutowireTrait;
  use CustomEventAwareTrait;

  /**
   * The domain entity storage service.
   *
   * @var \Drupal\domain\DomainStorageInterface
   */
  protected $domainStorage = NULL;

  /**
   * The domain entity storage service.
   *
   * @var \Drupal\domain_alias\DomainAliasStorageInterface
   */
  protected $domainAliasStorage = NULL;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TypedConfigManagerInterface $typedConfigManager,
  ) {
    parent::__construct();
  }

  /**
   * List all domain aliases for a domain.
   *
   * @param array $options
   *   The options passed to the command.
   *
   * @option hostname
   *   Filter aliases by the provided hostname.
   * @option environment
   *   Set the environment to assign the alias to.
   * @option redirect
   *   Aliases that have a specific redirect associated to them.
   *
   * @usage drush domain-alias:list
   *   List all domain aliases.
   * @usage drush domain-aliases
   *   List all domain aliases.
   * @usage drush domain-alias:list --hostname=example.com
   *   List all domain aliases for the hostname example.com
   * @usage drush domain-alias:list --environment=default
   *   List all domain aliases for the default environment.
   *
   * @command domain-alias:list
   * @aliases domain-aliases,domain-alias-list
   *
   * @field-labels
   *   id: Machine name
   *   name: Alias
   *   environment: Environment
   *   redirect: Redirect
   *   domain_id: Domain
   * @default-fields id,name,domain_id,environment,redirect
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   Table output.
   *
   * @throws \Drupal\domain_alias\Drush\Commands\DomainAliasCommandException
   */
  public function listDomainAliases(
    array $options = [
      'hostname' => NULL,
      'environment' => NULL,
      'redirect' => NULL,
    ],
  ) {
    // Issue with Drush 0 Value converted to FALSE.
    if ($options['redirect'] === FALSE) {
      $options['redirect'] = 0;
    }

    $loadParameters = [];

    if (!empty($options['hostname'])) {
      // Get the domain by hostname.
      $domain = $this->domainStorage()->loadByHostname($options['hostname']);

      if (empty($domain)) {
        $this->logger()->warning(dt('No domains have been created with that hostname. Use "drush domain:add" to create one.'));
        return new RowsOfFields([]);
      }

      $loadParameters['domain_id'] = $domain->id();
    }

    if (!empty($options['environment'])) {
      if (!in_array($options['environment'], $this->environmentOptions())) {
        throw new DomainAliasCommandException('Provided environment is not a valid option.');
      }

      $loadParameters['environment'] = $options['environment'];
    }

    $aliases = $this->domainAliasStorage()->loadByProperties($loadParameters);

    if (empty($aliases)) {
      $this->logger()->warning(dt('No domain aliases have been created for that host name. Use "drush domain-alias:add" to create one.'));
      return new RowsOfFields([]);
    }

    $keys = [
      'name',
      'redirect',
      'environment',
      'domain_id',
      'id',
    ];

    $rows = [];

    /** @var \Drupal\domain_alias\Entity\DomainAlias[] $aliases */
    foreach ($aliases as $alias) {
      $row = [];

      foreach ($keys as $key) {
        switch ($key) {
          case 'name':
            $v = $alias->label();
            break;

          case 'redirect':
            $options = $this->redirectOptions();
            $v = $options[$alias->get('redirect')] ?? '';
            break;

          case 'environment':
            $v = $alias->get('environment');
            break;

          case 'domain_id':
            $v = $alias->get('domain_id');
            break;

          case 'id':
            $v = $alias->id();
            break;
        }

        $row[$key] = Html::escape($v);
      }

      $rows[] = $row;
    }

    return new RowsOfFields($rows);
  }

  /**
   * Add a new domain alias to the domain.
   *
   * @param string $domain
   *   The domain hostname to add alias to (e.g. example.com).
   * @param string $alias
   *   The alias to register (e.g. www.example.com).
   * @param array $options
   *   The options passed to the command.
   *
   * @option machine_name
   *   Define the machine name for this alias.
   * @option redirect
   *   Set the redirect settings to use.
   * @option environment
   *   Set the environment to assign the alias to.
   *
   * @usage drush domain-alias:add example.com test.example.com
   *   Create alias test.example.com.
   * @usage drush domain-alias:add example.com test.example.com --environment=local
   *   Create alias test.example.com with environment set to local.
   * @usage drush domain-alias:add example.com test.example.com --redirect=301
   *   Create alias test.example.com with redirect attribute set to 301.
   *
   * @command domain-alias:add
   * @aliases domain-alias-add
   *
   * @return string
   *   The entity id of the created domain alias.
   *
   * @throws \Drupal\domain_alias\Drush\Commands\DomainAliasCommandException
   */
  public function add(
    string $domain,
    string $alias,
    array $options = [
      'machine_name' => NULL,
      'redirect' => NULL,
      'environment' => NULL,
    ],
  ) {
    // Issue with Drush 0 Value converted to FALSE.
    if ($options['redirect'] === FALSE) {
      $options['redirect'] = 0;
    }

    // Validate the options provide are acceptable.
    $this->validateOptions($options);

    // Check if the provided domain already exists.
    $aliasDomain = $this->domainStorage()->loadByHostname($domain);
    if (empty($aliasDomain)) {
      throw new DomainAliasCommandException(
        dt(
          'Domain with hostname "!hostname" cannot be found.',
          ['!hostname' => $domain]
        )
      );
    }

    // Check if alias is already being used for a domain.
    $checkIfDomain = $this->domainStorage()->loadByHostname($alias);
    if (!empty($checkIfDomain)) {
      throw new DomainAliasCommandException(dt(
        'Domain alias already exists as a domain !domain',
        ['!domain' => $alias]
      ));
    }

    // Check to see if the domain already.
    $checkIfAlias = $this->domainAliasStorage()->loadByPattern($alias);
    if (!empty($checkIfAlias)) {
      throw new DomainAliasCommandException(
        dt(
          'Domain Alias already exists for the pattern !pattern. Use drush domain-alias:update to update alias.',
          ['!pattern' => $alias]
        )
      );
    }

    $machineName = $options['machine_name'] ?? $this->domainStorage()->createMachineName($alias);
    if (!empty($machineName)) {
      $checkIfAlias = $this->domainAliasStorage()->load($machineName);
      if (!empty($checkIfAlias)) {
        throw new DomainAliasCommandException(
          dt(
            'Domain Alias with machine_name "!machine_name" already exists.',
            ['!machine_name' => $machineName]
          )
        );
      }
    }

    /** @var \Drupal\domain_alias\DomainAliasInterface $aliasEntity */
    $aliasEntity = $this->domainAliasStorage()->create([
      'domain_id' => $aliasDomain->id(),
      'pattern' => $alias,
      'redirect' => $options['redirect'] ?? 0,
      'environment' => $options['environment'] ?? 'default',
      'id' => $machineName,
    ]);

    // Validate via entity constraints.
    $violations = $this->validateAlias($aliasEntity);
    if (count($violations) > 0) {
      $messages = [];
      foreach ($violations as $violation) {
        $messages[] = (string) $violation->getMessage();
      }
      throw new DomainAliasCommandException(
        dt('Alias validation failed. !errors',
          ['!errors' => implode(' ', $messages)])
      );
    }

    try {
      $aliasEntity->save();
    }
    catch (EntityStorageException $e) {
      throw new DomainAliasCommandException('Unable to save domain alias');
    }

    return dt('Created the alias @alias with machine id !id.', [
      '@alias' => $alias,
      '!id' => $machineName,
    ]);
  }

  /**
   * Delete the alias.
   *
   * @param string $alias
   *   The alias to delete.
   *
   * @return string
   *   Return the entity id of the alias deleted.
   *
   * @usage drush domain-alias:delete test.example.com
   *   Delete the test.example.com alias.
   *
   * @command domain-alias:delete
   * @aliases domain-alias-delete
   *
   * @throws \Drupal\domain_alias\Drush\Commands\DomainAliasCommandException
   */
  public function delete(string $alias) {
    $foundAlias = $this->domainAliasStorage()->loadByHostname($alias);
    if (empty($foundAlias)) {
      throw new DomainAliasCommandException(
        dt(
          'Domain Alias with pattern !pattern not found.',
          ['!pattern' => $alias]
        )
      );
    }

    try {
      $foundAlias->delete();
    }
    catch (EntityStorageException $e) {
      throw new DomainAliasCommandException(dt('Unable to delete domain alias'));
    }

    return dt(
      'Domain Alias !pattern with id !id deleted.',
      ['!pattern' => $alias, '!id' => $foundAlias->id()]
    );
  }

  /**
   * Delete alias in bulk with provided details.
   *
   * @param string $domain
   *   Provide the domain's hostname to run actions on.
   * @param array $options
   *   The options passed to the command.
   *
   * @option redirect
   *   Set the redirect settings to use.
   * @option environment
   *   Set the environment to assign the alias to.
   *
   * @usage drush domain-alias:delete-bulk example.com
   *   Delete all the domain aliases for the example.com domain.
   * @usage drush domain-alias:delete-bulk example.com --environment=local
   *   Delete all the domain aliases with local environment.
   * @usage drush domain-alias:delete-bulk example.com --redirect=301
   *   Delete all the domain aliases with redirect 301.
   *
   * @command domain-alias:delete-bulk
   * @aliases domain-alias-delete-bulk
   *
   * @return string
   *   A list of all deleted alias ids.
   *
   * @throws \Drupal\domain_alias\Drush\Commands\DomainAliasCommandException
   */
  public function deleteBulk(
    string $domain,
    array $options = [
      'environment' => NULL,
      'redirect' => NULL,
    ],
  ) {
    // Issue with Drush 0 Value converted to FALSE.
    if ($options['redirect'] === FALSE) {
      $options['redirect'] = 0;
    }

    $this->validateOptions($options);

    $findDomain = $this->domainStorage()->loadByHostname($domain);
    if (empty($findDomain)) {
      throw new DomainAliasCommandException(
        dt('Domain !domain not found.', [
          '!domain' => $domain,
        ])
      );
    }

    $loadProperties = [];
    $loadProperties['domain_id'] = $findDomain->id();
    if (!empty($options['environment'])) {
      $loadProperties['environment'] = $options['environment'];
    }
    if (!empty($options['redirect'])) {
      $loadProperties['redirect'] = $options['redirect'];
    }

    $findAliases = $this->domainAliasStorage()->loadByProperties($loadProperties);
    if (empty($findAliases)) {
      throw new DomainAliasCommandException(
        dt('No Domain Aliases were found.')
      );
    }

    $deletedIds = [];
    foreach ($findAliases as $alias) {
      try {
        $deletedIds[] = dt('(!id) !label', [
          '!id' => $alias->id(),
          '!label' => $alias->get('pattern'),
        ]);
        $alias->delete();
      }
      catch (EntityStorageException $e) {
        throw new DomainAliasCommandException('Error deleting alias !alias', [
          '!alias' => $alias->id(),
        ]);
      }
    }

    return dt('Aliases Deleted Successfully: !items', ['!items' => implode(', ', $deletedIds)]);
  }

  /**
   * Update the provided alias.
   *
   * @param string $alias
   *   Provide the domain aliases hostname to run an update on.
   * @param array $options
   *   The options passed to the command.
   *
   * @option pattern
   *   Change the alias pattern.
   * @option redirect
   *   Set the redirect settings to use.
   * @option environment
   *   Set the environment to assign the alias to.
   *
   * @usage drush domain-alias:update test.example.com --environment=local
   *   Update the environment on the alias to local.
   * @usage drush domain-alias:update test.example.com --pattern=test2.example.com
   *   Update the pattern on the alias to test2.example.com.
   *
   * @command domain-alias:update
   * @aliases domain-alias-update
   *
   * @return string
   *   A message the entity was updated.
   *
   * @throws \Drupal\domain_alias\Drush\Commands\DomainAliasCommandException
   */
  public function update(
    string $alias,
    array $options = [
      'pattern' => NULL,
      'environment' => NULL,
      'redirect' => NULL,
    ],
  ) {
    // Issue with Drush 0 Value converted to FALSE.
    if ($options['redirect'] === FALSE) {
      $options['redirect'] = 0;
    }
    $this->validateOptions($options);

    $findAlias = $this->domainAliasStorage()->loadByHostname($alias);
    if (empty($findAlias)) {
      throw new DomainAliasCommandException(
        dt(
          'Domain Alias cannot be found with hostname !alias',
          ['!alias' => $alias]
        )
      );
    }

    if (!empty($options['pattern'])) {
      $findAlias->set('pattern', $options['pattern']);
    }

    if (!empty($options['environment'])) {
      $findAlias->set('environment', $options['environment']);
    }

    if (!empty($options['redirect'])) {
      $findAlias->set('redirect', $options['redirect']);
    }

    // Validate via entity constraints.
    $violations = $this->validateAlias($findAlias);
    if (count($violations) > 0) {
      $messages = [];
      foreach ($violations as $violation) {
        $messages[] = (string) $violation->getMessage();
      }
      throw new DomainAliasCommandException(
        dt('Alias validation failed. !errors',
          ['!errors' => implode(' ', $messages)])
      );
    }

    try {
      $findAlias->save();
    }
    catch (EntityStorageException $e) {
      throw new DomainAliasCommandException(dt('Unable to update domain alias.'));
    }

    return dt('Domain Alias updated successfully.');
  }

  /**
   * Validate the options provided from the Drush command.
   */
  protected function validateOptions(array $options) {
    // Validate the redirect arg.
    if (!empty($options['redirect']) && !is_numeric($options['redirect'])) {
      throw new DomainAliasCommandException(
        dt('Domain Alias redirect "!redirect" must be a number',
          ['!redirect' => $options['redirect'] ?? ''])
      );
    }

    // Check to see if the redirect matches our available options.
    if (!empty($options['redirect']) && !in_array($options['redirect'], array_keys($this->redirectOptions()))) {
      throw new DomainAliasCommandException(
        dt(
          'Domain Alias redirect option supports the following values: "!values"',
          ['!values' => implode(', ', array_values($this->redirectOptions()))]
        )
      );
    }

    // Check if environment provide is a valid option.
    if (!empty($options['environment']) && !in_array($options['environment'], $this->environmentOptions())) {
      throw new DomainAliasCommandException(
        dt(
          'Domain Alias environment only supports the following values: "!values"',
          ['!values' => implode(', ', array_values($this->environmentOptions()))]
        )
      );
    }
  }

  /**
   * Validates a domain alias via constraint plugins.
   *
   * @param \Drupal\domain_alias\DomainAliasInterface $alias
   *   The domain alias to validate.
   *
   * @return \Symfony\Component\Validator\ConstraintViolationListInterface
   *   The list of constraint violations.
   */
  protected function validateAlias(DomainAliasInterface $alias) {
    return $this->typedConfigManager
      ->createFromNameAndData(
        $alias->getConfigDependencyName(),
        $alias->toArray()
      )
      ->validate();
  }

  /**
   * Gets a domain storage object or throw an exception.
   *
   * Note that domain can run very early in the bootstrap, so we cannot
   * reliably inject this service.
   *
   * @return \Drupal\domain\DomainStorageInterface
   *   The domain storage handler.
   *
   * @throws \Drupal\domain_alias\Drush\Commands\DomainAliasCommandException
   */
  protected function domainStorage() {
    if (!is_null($this->domainStorage)) {
      return $this->domainStorage;
    }

    try {
      $this->domainStorage = $this->entityTypeManager->getStorage('domain');
    }
    catch (PluginNotFoundException $e) {
      throw new DomainAliasCommandException('Unable to get domain: no storage');
    }
    catch (InvalidPluginDefinitionException $e) {
      throw new DomainAliasCommandException('Unable to get domain: bad storage');
    }

    return $this->domainStorage;
  }

  /**
   * Gets a domain_alias storage object or throw an exception.
   *
   * Note that domain can run very early in the bootstrap, so we cannot
   * reliably inject this service.
   *
   * @return \Drupal\domain_alias\DomainAliasStorageInterface
   *   The domain storage handler.
   *
   * @throws \Drupal\domain_alias\Drush\Commands\DomainAliasCommandException
   */
  protected function domainAliasStorage() {
    if (!is_null($this->domainAliasStorage)) {
      return $this->domainAliasStorage;
    }

    try {
      $this->domainAliasStorage = $this->entityTypeManager->getStorage('domain_alias');
    }
    catch (PluginNotFoundException $e) {
      throw new DomainAliasCommandException('Unable to get domain_alias: no storage');
    }
    catch (InvalidPluginDefinitionException $e) {
      throw new DomainAliasCommandException('Unable to get domain_alias: bad storage');
    }

    return $this->domainAliasStorage;
  }

  /**
   * Returns a list of valid environment options for the form.
   *
   * @return array
   *   A list of valid environment options.
   */
  protected function environmentOptions() {
    $list = $this->configFactory->get('domain_alias.settings')->get('environments');
    $environments = [];
    foreach ($list as $item) {
      $environments[$item] = $item;
    }
    return $environments;
  }

  /**
   * Returns a list of valid redirect options for the form.
   *
   * @return array
   *   A list of valid redirect options.
   */
  protected function redirectOptions() {
    return [
      0 => dt('0: Do not redirect'),
      301 => dt('301: Moved Permanently'),
      302 => dt('302: Found'),
    ];
  }

}
