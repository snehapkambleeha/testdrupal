<?php

namespace Drupal\domain\Drush\Commands;

use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainValidatorInterface;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use GuzzleHttp\Exception\TransferException;

/**
 * Drush commands for the domain module.
 */
class DomainCommands extends DrushCommands implements CustomEventAwareInterface {

  use AutowireTrait;
  use CustomEventAwareTrait;

  /**
   * The domain entity storage service.
   *
   * @var \Drupal\domain\DomainStorageInterface
   */
  protected $domainStorage = NULL;

  /**
   * Local cache of entity field map, kept for performance.
   *
   * @var array
   */
  protected $entityFieldMap = NULL;

  /**
   * Flag set by the --dryrun cli option.
   *
   * If set prevents changes from being made by code in this class.
   *
   * @var bool
   */
  protected $isDryRun = FALSE;

  /**
   * Static array of special-case policies for reassigning field data.
   *
   * @var string[]
   * */
  protected $reassignmentPolicies = ['prompt', 'default', 'ignore'];

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected DomainValidatorInterface $validator,
    protected TypedConfigManagerInterface $typedConfigManager,
  ) {
    parent::__construct();
  }

  /**
   * List active domains for the site.
   *
   * @param array $options
   *   The options passed to the command.
   *
   * @option inactive
   *   Show only the domains that are inactive/disabled.
   * @option active
   *   Show only the domains that are active/enabled.
   * @usage drush domain:list
   *   List active domains for the site.
   * @usage drush domains
   *   List active domains for the site.
   *
   * @command domain:list
   * @aliases domains,domain-list
   *
   * @field-labels
   *   weight: Weight
   *   name: Name
   *   hostname: Hostname
   *   path_prefix: Path prefix
   *   response: HTTP Response
   *   scheme: Scheme
   *   status: Status
   *   is_default: Default
   *   domain_id: Domain Id
   *   id: Machine name
   * @default-fields id,name,hostname,path_prefix,scheme,status,is_default,response
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   Table output.
   *
   * @throws \Drupal\domain\Drush\Commands\DomainCommandException
   */
  public function listDomains(array $options) {
    // Load all domains:
    $domains = $this->domainStorage()->loadMultipleSorted();

    if (count($domains) === 0) {
      $this->logger()->warning(dt('No domains have been created. Use "drush domain:add" to create one.'));
      return new RowsOfFields([]);
    }

    $keys = [
      'weight',
      'name',
      'hostname',
      'path_prefix',
      'response',
      'scheme',
      'status',
      'is_default',
      'domain_id',
      'id',
    ];
    $rows = [];
    /** @var \Drupal\domain\DomainInterface $domain */
    foreach ($domains as $domain) {
      $row = [];
      foreach ($keys as $key) {
        switch ($key) {
          case 'response':
            try {
              $v = $this->checkDomain($domain);
            }
            catch (TransferException $ex) {
              $v = dt('500 - Failed');
            }
            catch (\Exception $ex) {
              $v = dt('500 - Exception');
            }
            if ($v >= 200 && $v <= 299) {
              $v = dt('200 - OK');
            }
            elseif ($v === 500) {
              $v = dt('500 - No server');
            }
            break;

          case 'status':
            $value = (bool) $domain->get($key);
            if (($options['inactive'] && $value) || ($options['active'] && !$value)) {
              continue 3;
            }
            $v = $value ? dt('Active') : dt('Inactive');
            break;

          case 'is_default':
            $value = (bool) $domain->get($key);
            $v = $value ? dt('Default') : '';
            break;

          default:
            $v = $domain->get($key);
            break;
        }

        $row[$key] = Html::escape($v);
      }
      $rows[] = $row;
    }
    return new RowsOfFields($rows);
  }

  /**
   * Replace strings in domain hostnames.
   *
   * This command searches for a string in all domain hostnames and replaces it.
   *
   * @param string $find
   *   The string to search for in hostnames.
   * @param string $replace
   *   The string to replace it with.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @option force
   *   Actually perform the replacement.
   *   Without this flag, the command will do a dry run only.
   *
   * @usage drush domain:replace "domain.com" --replace="quality.domain.com" --force
   *   Replaces "domain.com" with "quality.domain.com" in all domain hostnames.
   *
   * @command domain:replace
   * @aliases domain-replace
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   Table output.
   */
  public function replaceDomainHostnames(
    string $find,
    string $replace,
    array $options = [
      'force' => FALSE,
    ],
  ) {
    $domains = $this->domainStorage()->loadMultipleSorted();

    if (count($domains) === 0) {
      $this->logger()->warning(dt('No domains have been created. Use "drush domain:add" to create one.'));
      return new RowsOfFields([]);
    }

    $apply_changes = (bool) $options['force'];

    $rows = [];

    foreach ($domains as $domain) {
      $name = $domain->get('name');
      $current = $domain->get('hostname');
      $updated = str_replace($find, $replace, $current);

      if ($current === $updated) {
        continue;
      }

      if ($apply_changes) {
        $domain->set('hostname', $updated);
        $domain->save();
        $this->logger()->notice(dt("Updated domain '@name' hostname: @current to @updated", [
          '@name' => $name,
          '@current' => $current,
          '@updated' => $updated,
        ]));
      }

      $rows[] = [
        'name' => Html::escape($name),
        'current' => Html::escape($current),
        'new' => Html::escape($updated),
      ];
    }

    return new RowsOfFields($rows);
  }

  /**
   * List general information about the domains on the site.
   *
   * @usage drush domain:info
   *
   * @command domain:info
   * @aliases domain-info,dinf
   *
   * @return \Consolidation\OutputFormatters\StructuredData\PropertyList
   *   A structured list of domain information.
   *
   * @field-labels
   * count: All Domains
   * count_active: Active Domains
   * default_id: Default Domain ID
   * default_host: Default Domain hostname
   * scheme: Fields in Domain entity
   * domain_admin_entities: Domain admin entities
   * @list-orientation true
   * @format table
   *
   * @throws \Drupal\domain\Drush\Commands\DomainCommandException
   */
  public function infoDomains() {
    $default_domain = $this->domainStorage()->loadDefaultDomain();

    // Load all domains.
    /** @var \Drupal\domain\DomainInterface[] $all_domains */
    $all_domains = $this->domainStorage()->loadMultiple(NULL);
    $active_domains = [];
    foreach ($all_domains as $domain) {
      if ($domain->status()) {
        $active_domains[] = $domain;
      }
    }

    $keys = [
      'count',
      'count_active',
      'default_id',
      'default_host',
      'scheme',
    ];
    $rows = [];
    foreach ($keys as $key) {
      $v = '';
      switch ($key) {
        case 'count':
          $v = count($all_domains);
          break;

        case 'count_active':
          $v = count($active_domains);
          break;

        case 'default_id':
          $v = '-unset-';
          if ($default_domain instanceof DomainInterface) {
            $v = $default_domain->id();
          }
          break;

        case 'default_host':
          $v = '-unset-';
          if ($default_domain instanceof DomainInterface) {
            $v = $default_domain->getHostname();
          }
          break;

        case 'scheme':
          $v = implode(', ', array_keys($this->domainStorage()->loadSchema()));
          break;
      }

      $rows[$key] = $v;
    }

    // Display which entities are enabled for domain by checking for the fields.
    $rows['domain_admin_entities'] = $this->getFieldEntities(DomainInterface::DOMAIN_ADMIN_FIELD);

    return new PropertyList($rows);
  }

  /**
   * Finds entities that reference a specific field.
   *
   * @param string $field_name
   *   The field name to lookup.
   *
   * @return string
   *   A comma-separated list of entities containing a specific field.
   */
  public function getFieldEntities($field_name) {
    $this->ensureEntityFieldMap();
    $domain_entities = [];
    foreach ($this->entityFieldMap as $type => $fields) {
      if (array_key_exists($field_name, $fields)) {
        $domain_entities[] = $type;
      }
    }
    return implode(', ', $domain_entities);
  }

  /**
   * Add a new domain to the site.
   *
   * @param string $hostname
   *   The domain hostname to register (e.g. example.com).
   * @param string $name
   *   The name of the site (e.g. Domain Two).
   * @param array $options
   *   An associative array of optional values.
   *
   * @option inactive
   *   Set the domain to inactive status if set.
   * @option scheme
   *   Use indicated protocol for this domain, defaults to 'https'. Options:
   *    - http: normal http (no SSL).
   *    - https: secure https (with SSL).
   *    - variable: match the scheme used by the request.
   * @option weight
   *   Set the order (weight) of the domain.
   * @option is_default
   *   Set this domain as the default domain.
   * @option validate
   *   Force a check of the URL response before allowing registration.
   * @option path-prefix
   *   Set a path prefix for the domain (e.g. "fr" or "benl").
   *   Multiple domains may share a hostname when they have
   *   different path prefixes.
   *
   * @usage drush domain-add example.com 'My Test Site'
   * @usage drush domain-add example.com 'My Test Site' --scheme=https --inactive
   * @usage drush domain-add example.com 'My Test Site' --weight=10
   * @usage drush domain-add example.com 'My Test Site' --validate
   * @usage drush domain-add example.com 'French Site' --path-prefix=fr
   *
   * @command domain:add
   * @aliases domain-add
   *
   * @return string
   *   The entity id of the created domain.
   *
   * @throws \Drupal\domain\Drush\Commands\DomainCommandException
   */
  public function add(
    $hostname,
    $name,
    array $options = [
      'weight' => NULL,
      'scheme' => NULL,
      'path-prefix' => NULL,
    ],
  ) {
    // Validate the weight arg.
    if (!is_null($options['weight']) && !is_numeric($options['weight'])) {
      throw new DomainCommandException(
        dt('Domain weight "!weight" must be a number',
          ['!weight' => $options['weight'] ?? 'null'])
      );
    }

    // Validate the scheme arg.
    if (!is_null($options['scheme']) &&
      ($options['scheme'] !== 'http' && $options['scheme'] !== 'https' && $options['scheme'] !== 'variable')
    ) {
      throw new DomainCommandException(
        dt('Scheme name "!scheme" not known',
          ['!scheme' => $options['scheme'] ?? 'null'])
      );
    }

    $domains = $this->domainStorage()->loadMultipleSorted();
    $start_weight = count($domains) + 1;
    $values = [
      'hostname' => $hostname,
      'name' => $name,
      'status' => ($options['inactive'] ?? FALSE) ? 0 : 1,
      'scheme' => $options['scheme'] ?? 'http',
      'weight' => $options['weight'] ?? $start_weight,
      'is_default' => $options['is_default'] ?? 0,
      'id' => $this->createDomainMachineName($hostname, $options['path-prefix'] ?? ''),
      'path_prefix' => $options['path-prefix'] ?? '',
    ];
    /** @var \Drupal\domain\DomainInterface */
    $domain = $this->domainStorage()->create($values);

    // Validate via entity constraints.
    $violations = $this->validateDomain($domain);
    if (count($violations) > 0) {
      $messages = [];
      foreach ($violations as $violation) {
        $messages[] = (string) $violation->getMessage();
      }
      throw new DomainCommandException(
        dt('Domain validation failed. !errors',
          ['!errors' => implode(' ', $messages)])
      );
    }
    // Check for id uniqueness (not covered by constraints).
    foreach ($domains as $existing) {
      if ($values['id'] === $existing->id()) {
        throw new DomainCommandException(
          dt('No domain created. Id is a duplicate of !id.',
           ['!id' => $existing->id()])
        );
      }
    }

    $validate_response = (bool) $options['validate'];
    if ($this->createDomain($domain, $validate_response)) {
      return dt('Created the !hostname with machine id !id.',
        ['!hostname' => $values['hostname'], '!id' => $values['id']]);
    }
    else {
      return dt('No domain created.');
    }
  }

  /**
   * Delete a domain from the site.
   *
   * Deletes the domain from the Drupal configuration and optionally reassign
   * content and/or profiles associated with the deleted domain to another.
   * The domain marked as default cannot be deleted: to achieve this goal,
   * mark another, possibly newly created, domain as the default domain, then
   * delete the old default.
   *
   * The usage example descriptions are based on starting with three domains:
   *   - id:19476, machine: example_com, domain: example.com
   *   - id:29389, machine: example_org, domain: example.org  (default)
   *   - id:91736, machine: example_net, domain: example.net
   *
   * @param string $domain_id
   *   The numeric id, machine name, or hostname of the domain to delete. The
   *   value "all" is taken to mean delete all except the default domain.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @usage drush domain:delete example.com
   *   Delete the domain example.com, assigning its content and users to
   *   the default domain, example.org.
   *
   * @usage drush domain:delete --content-assign=ignore example.com
   *   Delete the domain example.com, leaving its content untouched but
   *   assigning its users to the default domain.
   *
   * @usage drush domain:delete --content-assign=example_net --users-assign=example_net
   *   example.com Delete the domain example.com, assigning its content and
   *   users to the example.net domain.
   *
   * @usage drush domain:delete --dryrun 19476
   *   Show the effects of delete the domain example.com and assigning its
   *   content and users to the default domain, example.org, but not doing so.
   *
   * @usage drush domain:delete --chatty example_net
   *   Verbosely Delete the domain example.net and assign its content and users
   *   to the default domain, example.org.
   *
   * @usage drush domain-delete --chatty all
   *   Verbosely Delete the domains example.com and example.net and assign
   *   their content and users to the default domain, example.org.
   *
   * @option chatty
   *   Document each step as it is performed.
   * @option dryrun
   *   Do not do anything, but explain what would be done. Implies --chatty.
   * @option users-assign
   *   Values "prompt", "ignore", "default", <name>, Reassign user accounts
   *   associated with the the domain being deleted to the default domain,
   *   to the domain whose machine name is <name>, or leave the user accounts
   *   alone (and so inaccessible in the normal way). The default value is
   *   'prompt': ask which domain to use.
   *
   * @command domain:delete
   * @aliases domain-delete
   *
   * @throws \Drupal\domain\Drush\Commands\DomainCommandException
   *
   * @see https://github.com/consolidation/annotated-command#option-event-hook
   */
  public function delete(
    $domain_id,
    array $options = [
      'users-assign' => NULL,
      'dryrun' => NULL,
      'chatty' => NULL,
    ],
  ) {

    $message = '';
    $messages = [];
    $domains = [];

    $this->isDryRun = (bool) $options['dryrun'];

    // Get current domain list and perform validation checks.
    $all_domains = $this->domainStorage()->loadMultipleSorted();

    if (count($all_domains) === 0) {
      throw new DomainCommandException('There are no configured domains.');
    }
    if ($domain_id === '') {
      throw new DomainCommandException('You must specify a domain to delete.');
    }

    // Determine which domains to be deleted.
    if ($domain_id === 'all') {
      $domains = $all_domains;
      $really = $this->io()->confirm(dt('This action cannot be undone. Continue?:'), FALSE);
      if (!$really) {
        return;
      }
      $message = dt('All domain records have been deleted.');
    }
    else {
      $domain = $this->getDomainFromArgument($domain_id);
      if ($domain instanceof DomainInterface) {
        if ($domain->isDefault()) {
          throw new DomainCommandException('The primary domain may not be deleted.
          Use drush domain:default to set a new default domain.');
        }
        $domains = [$domain];
        $message = dt('Domain record !domain deleted.',
          ['!domain' => $domain->id()]
        );
      }
    }

    // Set the reassignment policy.
    $policy_users = 'prompt';
    if (isset($options['users-assign']) && $options['users-assign']) {
      if (in_array($options['users-assign'], $this->reassignmentPolicies, TRUE)) {
        $policy_users = $options['users-assign'];
      }
    }

    $delete_options = [
      'entity_filter' => 'user',
      'policy' => $policy_users,
      'field' => DomainInterface::DOMAIN_ADMIN_FIELD,
    ];

    if ($policy_users !== 'ignore') {
      foreach ($domains as $domain) {
        $messages[] = $this->doReassign($domain, $delete_options);
      }
    }

    $this->deleteDomain($domains, $options);

    if (count($messages) > 0) {
      $message .= "\n" . implode("\n", $messages);
    }
    $this->logger()->info($message);

    return $message;
  }

  /**
   * Handles reassignment of entities to another domain.
   *
   * This method includes necessary UI elements if the user is prompted to
   * choose a new domain.
   *
   * @param \Drupal\domain\DomainInterface $target_domain
   *   The domain selected for deletion.
   * @param array $delete_options
   *   A selection of options for deletion, defined in reassignLinkedEntities().
   */
  public function doReassign(DomainInterface $target_domain, array $delete_options) {
    $policy = $delete_options['policy'];
    $default_domain = $this->domainStorage()->loadDefaultDomain();
    $all_domains = $this->domainStorage()->loadMultipleSorted(NULL);

    // Perform the 'prompt' for a destination domain.
    if ($policy === 'prompt') {
      // Make a list of the eligible destination domains in form id -> name.
      $noassign_domain = [$target_domain->id()];

      $reassign_list = $this->filterDomains($all_domains, $noassign_domain);
      $reassign_base = [
        'ignore' => dt('Do not reassign'),
        'default' => dt('Reassign to default domain'),
      ];
      $reassign_list = array_map(
        function (DomainInterface $d) {
          return $d->getHostname();
        },
        $reassign_list
      );
      $reassign_list = array_merge($reassign_base, $reassign_list);
      $policy = $this->io()->choice(dt('Reassign @type field @field data to:',
        [
          '@type' => $delete_options['entity_filter'],
          '@field' => $delete_options['field'],
        ]), $reassign_list);
    }
    elseif ($policy === 'default') {
      $policy = $default_domain->id();
    }
    if ($policy !== 'ignore') {
      $delete_options['policy'] = $policy;
      $target = [$target_domain];
      $count = $this->reassignLinkedEntities($target, $delete_options);
      return dt('@count @type entities updated field @field.',
        [
          '@count' => $count,
          '@type' => $delete_options['entity_filter'],
          '@field' => $delete_options['field'],
        ]
      );
    }
  }

  /**
   * Tests domains for proper response.
   *
   * If run from a subfolder, you must specify the --uri.
   *
   * @param string $domain_id
   *   The machine name or hostname of the domain to make default.
   *
   * @usage drush domain-test
   * @usage drush domain-test example.com
   *
   * @command domain:test
   * @aliases domain-test
   *
   * @field-labels
   *   id: Machine name
   *   url: URL
   *   response: HTTP Response
   * @default-fields id,url,response
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   Tabled output.
   *
   * @throws \Drupal\domain\Drush\Commands\DomainCommandException
   */
  public function test($domain_id = NULL) {
    if (is_null($domain_id)) {
      $domains = $this->domainStorage()->loadMultipleSorted();
    }
    else {
      $domain = $this->getDomainFromArgument($domain_id);
      if ($domain instanceof DomainInterface) {
        $domains = [$domain];
      }
      else {
        throw new DomainCommandException(dt('Domain identifier "@domain" not found.',
          ['@domain' => $domain_id]));
      }
    }

    $rows = [];
    foreach ($domains as $domain) {
      $rows[] = [
        'id' => $domain->id(),
        'url' => $domain->getPath(),
        'response' => $domain->getResponse(),
      ];
    }

    return new RowsOfFields($rows);
  }

  /**
   * Sets the default domain.
   *
   * @param string $domain_id
   *   The machine name or hostname of the domain to make default.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @option validate
   *   Force a check of the URL response before allowing registration.
   * @usage drush domain-default www.example.com
   * @usage drush domain-default example_org
   * @usage drush domain-default www.example.org --validate=1
   *
   * @command domain:default
   * @aliases domain-default
   *
   * @return string
   *   The machine name of the default domain.
   *
   * @throws \Drupal\domain\Drush\Commands\DomainCommandException
   */
  public function defaultDomain(
    $domain_id,
    array $options = [
      'validate' => NULL,
    ],
  ) {
    // Resolve the domain.
    $domain = $this->getDomainFromArgument($domain_id);
    if ($domain instanceof DomainInterface) {
      $validate = ($options['validate']) ? 1 : 0;
      $domain->addProperty('validate_url', $validate);
      if ($error = $this->checkHttpResponse($domain)) {
        throw new DomainCommandException(dt('Unable to verify domain !domain: !error',
          ['!domain' => $domain->getHostname(), '!error' => $error]));
      }
      else {
        $domain->saveDefault();
      }
    }

    // Now, ask for the current default, so we know if it worked.
    $domain = $this->domainStorage()->loadDefaultDomain();
    if ($domain->status()) {
      $this->logger()->info(dt('!domain set to primary domain.',
        ['!domain' => $domain->getHostname()]));
    }
    else {
      $this->logger()->warning(dt('!domain set to primary domain, but is also inactive.',
        ['!domain' => $domain->getHostname()]));
    }
    return $domain->id();
  }

  /**
   * Deactivates the domain.
   *
   * @param string $domain_id
   *   The numeric id or hostname of the domain to disable.
   *
   * @usage drush domain-disable example.com
   * @usage drush domain-disable 1
   *
   * @command domain:disable
   * @aliases domain-disable
   *
   * @return string
   *   Message to print.
   *
   * @throws \Drupal\domain\Drush\Commands\DomainCommandException
   */
  public function disable($domain_id) {
    // Resolve the domain.
    $domain = $this->getDomainFromArgument($domain_id);
    if ($domain instanceof DomainInterface) {
      if ($domain->status()) {
        $domain->disable();
        $this->logger()->info(dt('!domain has been disabled.',
          ['!domain' => $domain->getHostname()]));
        return dt('Disabled !domain.', ['!domain' => $domain->getHostname()]);
      }
      else {
        $this->logger()->info(dt('!domain is already disabled.',
          ['!domain' => $domain->getHostname()]));
        return dt('!domain is already disabled.',
          ['!domain' => $domain->getHostname()]
        );
      }
    }
    return dt('No matching domain record found.');
  }

  /**
   * Activates the domain.
   *
   * @param string $domain_id
   *   The numeric id or hostname of the domain to enable.
   *
   * @usage drush domain-disable example.com
   * @usage drush domain-enable 1
   *
   * @command domain:enable
   * @aliases domain-enable
   *
   * @return string
   *   The message to print.
   *
   * @throws \Drupal\domain\Drush\Commands\DomainCommandException
   */
  public function enable($domain_id) {
    // Resolve the domain.
    $domain = $this->getDomainFromArgument($domain_id);
    if ($domain instanceof DomainInterface) {
      if (!$domain->status()) {
        $domain->enable();
        $this->logger()->info(dt('!domain has been enabled.',
          ['!domain' => $domain->getHostname()]));
        return dt('Enabled !domain.', ['!domain' => $domain->getHostname()]);
      }
      else {
        $this->logger()->info(dt('!domain is already enabled.',
          ['!domain' => $domain->getHostname()]));
        return dt('!domain is already enabled.',
          ['!domain' => $domain->getHostname()]
        );
      }
    }
    return dt('No matching domain record found.');
  }

  /**
   * Changes a domain label.
   *
   * @param string $domain_id
   *   The machine name or hostname of the domain to relabel.
   * @param string $name
   *   The name to use for the domain.
   *
   * @usage drush domain-name example.com Foo
   * @usage drush domain-name 1 Foo
   *
   * @command domain:name
   * @aliases domain-name
   *
   * @return string
   *   The message to print.
   *
   * @throws \Drupal\domain\Drush\Commands\DomainCommandException
   */
  public function renameDomain($domain_id, $name) {
    // Resolve the domain.
    $domain = $this->getDomainFromArgument($domain_id);
    if ($domain instanceof DomainInterface) {
      $domain->saveProperty('name', $name);
      return dt('Renamed !domain to !name.',
        ['!domain' => $domain->getHostname(), '!name' => $domain->label()]);
    }
    return dt('No matching domain record found.');
  }

  /**
   * Changes a domain scheme.
   *
   * @param string $domain_id
   *   The machine name or hostname of the domain to change.
   * @param string $scheme
   *   The scheme to use for the domain: http, https, or variable.
   *
   * @usage drush domain-scheme example.com http
   * @usage drush domain-scheme example_com https
   *
   * @command domain:scheme
   * @aliases domain-scheme
   *
   * @return string
   *   The message to print.
   *
   * @throws \Drupal\domain\Drush\Commands\DomainCommandException
   */
  public function scheme($domain_id, $scheme = NULL) {
    $new_scheme = NULL;

    // Resolve the domain.
    $domain = $this->getDomainFromArgument($domain_id);
    if ($domain instanceof DomainInterface) {
      if (!is_null($scheme)) {
        // Set with a value.
        $new_scheme = $scheme;
      }
      else {
        // Prompt for selection.
        $new_scheme = $this->io()->choice(dt('Select the default http scheme:'),
          [
            'http' => 'http',
            'https' => 'https',
            'variable' => 'variable',
          ]);
      }

      // If we were asked to change scheme, validate the value and do so.
      if (!is_null($new_scheme)) {
        switch ($new_scheme) {
          case 'http':
            $new_scheme = 'http';
            break;

          case 'https':
            $new_scheme = 'https';
            break;

          case 'variable':
            $new_scheme = 'variable';
            break;

          default:
            throw new DomainCommandException(
              dt('Scheme name "!scheme" not known.', ['!scheme' => $new_scheme])
            );
        }
        $domain->saveProperty('scheme', $new_scheme);
      }

      // Return the (new | current) scheme for this domain.
      return dt('Scheme is now to "!scheme." for !domain',
        ['!scheme' => $domain->get('scheme'), '!domain' => $domain->id()]
      );
    }

    // We couldn't find the domain, so fail.
    throw new DomainCommandException(
      dt('Domain name "!domain" not known.', ['!domain' => $domain_id])
    );
  }

  /**
   * Generate domains for testing.
   *
   * @param string $primary
   *   The primary domain to use. This will be created and used for
   *   *.example.com hostnames.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @option count
   *   The count of extra domains to generate. Default is 15.
   * @option empty
   *   Pass empty=1 to truncate the {domain} table before creating records.
   * @option scheme
   *   Options are http | https | variable
   * @option prefix
   *   Generate same-hostname domains with path prefixes
   *   instead of subdomains.
   * @usage drush domain-generate example.com
   * @usage drush domain-generate example.com --count=25
   * @usage drush domain-generate example.com --count=25 --empty=1
   * @usage drush domain-generate example.com --count=25 --empty=1 --scheme=https
   * @usage drush domain-generate example.com --prefix
   * @usage drush gend
   * @usage drush gend --count=25
   * @usage drush gend --count=25 --empty=1
   * @usage drush gend --count=25 --empty=1 --scheme=https
   *
   * @command domain:generate
   * @aliases gend,domgen,domain-generate
   *
   * @return string
   *   The message to print.
   *
   * @throws \Drupal\domain\Drush\Commands\DomainCommandException
   */
  public function generate(
    $primary = 'example.com',
    array $options = [
      'count' => NULL,
      'empty' => NULL,
      'scheme' => 'http',
      'prefix' => FALSE,
    ],
  ) {
    // Check the number of domains to create.
    $count = $options['count'];
    if (is_null($count)) {
      $count = 15;
    }

    $use_prefixes = !empty($options['prefix']);

    $domains = $this->domainStorage()->loadMultiple();
    if (isset($options['empty']) && $options['empty']) {
      $this->domainStorage()->delete($domains);
      $domains = $this->domainStorage()->loadMultiple();
    }

    // Collect existing identifiers to avoid duplicates.
    $existing = [];
    /** @var \Drupal\domain\DomainInterface[] $domains */
    foreach ($domains as $domain) {
      $existing[] = $use_prefixes ? $domain->id() : $domain->getHostname();
    }

    // Set up one.* and so on.
    $names = [
      'one',
      'two',
      'three',
      'four',
      'five',
      'six',
      'seven',
      'eight',
      'nine',
      'ten',
      'foo',
      'bar',
      'baz',
    ];

    if ($use_prefixes) {
      // Prefix mode: first domain has no prefix, then one, two...
      $prepared = [];
      $prefixes = array_merge([''], $names);
      for ($i = 0; $i < $count; $i++) {
        $prefix = $prefixes[$i] ?? 'test' . $i;
        $machine_hostname = ($prefix === '')
          ? $primary
          : $prefix . '.' . $primary;
        $machine_name = $this->createDomainMachineName(
          $machine_hostname,
          $prefix,
        );
        if (!in_array($machine_name, $existing, TRUE)) {
          $prepared[] = $prefix;
        }
      }
    }
    else {
      // Subdomain mode: set the creation array.
      $new = [$primary];
      foreach ($names as $name) {
        $new[] = $name . '.' . $primary;
      }
      // Include a non hostname.
      $new[] = 'my' . $primary;
      // Filter against existing so we can count correctly.
      $prepared = [];
      foreach ($new as $value) {
        if (!in_array($value, $existing, TRUE)) {
          $prepared[] = $value;
        }
      }

      // Add test domains with numeric prefixes.
      $start = 1;
      foreach ($existing as $exists) {
        $name = explode('.', $exists);
        if (substr_count($name[0], 'test') > 0) {
          $num = (int) str_replace('test', '', $name[0]) + 1;
          if ($num > $start) {
            $start = $num;
          }
        }
      }
      $needed = $count - count($prepared) + $start;
      for ($i = $start; $i <= $needed; $i++) {
        $prepared[] = 'test' . $i . '.' . $primary;
      }
    }

    // Get the initial item weight for sorting.
    $start_weight = count($domains);
    $prepared = array_slice($prepared, 0, $count);
    $list = [];

    // Create the domains.
    foreach ($prepared as $key => $item) {
      if ($use_prefixes) {
        $is_default = ($item === '');
        $values = [
          'hostname' => $primary,
          'name' => $is_default
            ? $this->configFactory->get('system.site')->get('name')
            : 'Test ' . ucfirst($item),
          'scheme' => $options['scheme'],
          'status' => 1,
          'weight' => $is_default ? -1 : $key + $start_weight + 1,
          'is_default' => 0,
          'id' => $this->createDomainMachineName(
            $is_default ? $primary : $item . '.' . $primary,
            $item,
          ),
          'path_prefix' => $item,
        ];
        $label = $is_default
          ? $primary
          : $primary . '/' . $item;
      }
      else {
        $hostname = mb_strtolower($item);
        $values = [
          'name' => ($item !== $primary)
            ? ucwords(str_replace(".$primary", '', $item))
            : $this->configFactory->get('system.site')->get('name'),
          'hostname' => $hostname,
          'scheme' => $options['scheme'],
          'status' => 1,
          'weight' => ($item !== $primary) ? $key + $start_weight + 1 : -1,
          'is_default' => 0,
          'id' => $this->domainStorage()->createMachineName($hostname),
        ];
        $label = $hostname;
      }
      $domain = $this->domainStorage()->create($values);
      if ($domain instanceof DomainInterface) {
        $domain->save();
        $list[] = dt('Created @domain.', ['@domain' => $label]);
      }
    }

    // If nothing created, say so.
    if (count($prepared) === 0) {
      return dt('No new domains were created.');
    }
    else {
      return dt("Created @count new domains:\n@list",
        ['@count' => count($prepared), '@list' => implode("\n", $list)]);
    }
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
   * @throws \Drupal\domain\Drush\Commands\DomainCommandException
   */
  protected function domainStorage() {
    if (!is_null($this->domainStorage)) {
      return $this->domainStorage;
    }

    try {
      $this->domainStorage = $this->entityTypeManager->getStorage('domain');
    }
    catch (PluginNotFoundException $e) {
      throw new DomainCommandException('Unable to get domain: no storage', $e);
    }
    catch (InvalidPluginDefinitionException $e) {
      throw new DomainCommandException('Unable to get domain: bad storage', $e);
    }

    return $this->domainStorage;
  }

  /**
   * Creates a machine name from hostname and prefix.
   *
   * When a path prefix is provided, it is appended to the
   * hostname-derived machine name to ensure uniqueness.
   *
   * @param string $hostname
   *   The domain hostname.
   * @param string $prefix
   *   The path prefix (may be empty).
   *
   * @return string
   *   The machine name.
   */
  protected function createDomainMachineName(string $hostname, string $prefix): string {
    $machine_name = $this->domainStorage()->createMachineName($hostname);
    if ($prefix !== '') {
      $machine_name .= '_' . preg_replace('/[^a-z0-9_]/', '_', $prefix);
    }
    return $machine_name;
  }

  /**
   * Loads a domain based on a string identifier.
   *
   * @param string $argument
   *   The machine name or the hostname of an existing domain.
   *
   * @return \Drupal\domain\DomainInterface
   *   The domain entity.
   *
   * @throws \Drupal\domain\Drush\Commands\DomainCommandException
   */
  protected function getDomainFromArgument($argument) {

    // Try loading domain assuming arg is a machine name.
    $domain = $this->domainStorage()->load($argument);
    if (is_null($domain)) {
      // Try loading assuming it is a host name.
      $domain = $this->domainStorage()->loadByHostname($argument);
    }

    // domain_id (an INT) is only used internally because the Node Access
    // system demands the use of numeric keys. It should never be used to load
    // or identify domain records. Use the machine_name or hostname instead.
    if (is_null($domain)) {
      throw new DomainCommandException(
        dt('Domain record could not be found from "!a".', ['!a' => $argument])
      );
    }

    return $domain;
  }

  /**
   * Filters a list of domains by specific exclude list.
   *
   * @param \Drupal\domain\DomainInterface[] $domains
   *   List of domains.
   * @param string[] $exclude
   *   List of domain id to exclude from the list.
   * @param \Drupal\domain\DomainInterface[] $initial
   *   Initial value of list that will be returned.
   *
   * @return array
   *   An array of domains.
   */
  protected function filterDomains(array $domains, array $exclude, array $initial = []) {
    foreach ($domains as $domain) {
      // Exclude unwanted domains.
      // @phpstan-ignore-next-line
      if (!in_array($domain->id(), $exclude, FALSE)) {
        $initial[$domain->id()] = $domain;
      }
    }
    return $initial;
  }

  /**
   * Checks the domain response.
   *
   * @param \Drupal\domain\DomainInterface $domain
   *   The domain to check.
   * @param bool $validate_url
   *   True to validate this domain by performing a URL lookup; False to skip
   *   the checks.
   *
   * @return bool
   *   True if the domain resolves properly, or we are not checking,
   *   False otherwise.
   */
  protected function checkHttpResponse(DomainInterface $domain, $validate_url = FALSE) {
    // Ensure the url is rebuilt.
    if ($validate_url) {
      $code = $this->checkDomain($domain);
      // Some sort of success:
      return ($code >= 200 && $code <= 299);
    }
    // Not validating, return FALSE.
    return FALSE;
  }

  /**
   * Helper function: check a domain is responsive and create it.
   *
   * @param \Drupal\domain\DomainInterface $domain
   *   The (as yet unsaved) domain to create.
   * @param bool $check_response
   *   Indicates that registration should not be allowed unless the server
   *   returns a 200 response.
   *
   * @return bool
   *   TRUE or FALSE indicating success of the action.
   *
   * @throws \Drupal\domain\Drush\Commands\DomainCommandException
   */
  protected function createDomain(DomainInterface $domain, $check_response = FALSE) {
    if ($check_response) {
      $valid = $this->checkHttpResponse($domain, TRUE);
      if (!$valid) {
        throw new DomainCommandException(
          dt('The server did not return a 200 response for !d. Domain creation failed. Remove the --validate flag to save this domain.', ['!d' => $domain->getHostname()])
        );
      }
    }
    else {
      try {
        $domain->save();
      }
      catch (EntityStorageException $e) {
        throw new DomainCommandException('Unable to save domain', $e);
      }

      if ($domain->getDomainId() > 0) {
        $this->logger()->info(dt('Created @name at @domain.',
          ['@name' => $domain->label(), '@domain' => $domain->getHostname()]));
        return TRUE;
      }
      else {
        $this->logger()->error(dt('The request could not be completed.'));
      }
    }
    return FALSE;
  }

  /**
   * Checks if a domain exists by trying to do an http request to it.
   *
   * @param \Drupal\domain\DomainInterface $domain
   *   The domain to validate for syntax and uniqueness.
   *
   * @return int
   *   The server response code for the request.
   *
   * @see domain_validate()
   */
  protected function checkDomain(DomainInterface $domain) {
    return $this->validator->checkResponse($domain);
  }

  /**
   * Validates a domain entity via constraint plugins.
   *
   * @param \Drupal\domain\DomainInterface $domain
   *   The domain to validate.
   *
   * @return \Symfony\Component\Validator\ConstraintViolationListInterface
   *   The list of constraint violations.
   */
  protected function validateDomain(DomainInterface $domain) {
    return $this->typedConfigManager
      ->createFromNameAndData(
        $domain->getConfigDependencyName(),
        $domain->toArray()
      )
      ->validate();
  }

  /**
   * Deletes a domain record.
   *
   * @param \Drupal\domain\DomainInterface[] $domains
   *   The domains to delete.
   * @param array $options
   *   The drush options passed to delete().
   *
   * @throws \Drupal\domain\Drush\Commands\DomainCommandException
   * @throws \UnexpectedValueException
   */
  protected function deleteDomain(array $domains, array $options) {
    foreach ($domains as $domain) {
      $hostname = $domain->getHostname();

      if ($this->isDryRun) {
        $this->logger()->info(dt('DRYRUN: Domain record @domain deleted.',
          ['@domain' => $hostname]));
        continue;
      }

      try {
        // Fire any registered hooks for deletion, passing them current input.
        $handlers = $this->getCustomEventHandlers('domain-delete');
        $messages = [];
        foreach ($handlers as $handler) {
          $messages[] = $handler($domain, $options);
        }

        $domain->delete();
      }
      catch (EntityStorageException $e) {
        throw new DomainCommandException(dt('Unable to delete domain: @domain',
          ['@domain' => $hostname]), $e);
      }
      $this->logger()->info(dt('Domain record @domain deleted.',
        ['@domain' => $hostname]));
    }
  }

  /**
   * Returns a list of the entity types that are domain enabled.
   *
   * A domain-enabled entity is defined here as an entity type that includes
   * the domain access field(s).
   *
   * @param string $using_field
   *   The specific field name to look for.
   *
   * @return string[]
   *   List of entity machine names that support domain references.
   */
  protected function findDomainEnabledEntities($using_field = DomainInterface::DOMAIN_ADMIN_FIELD) {
    $this->ensureEntityFieldMap();
    $entities = [];
    foreach ($this->entityFieldMap as $type => $fields) {
      if (array_key_exists($using_field, $fields)) {
        $entities[] = $type;
      }
    }
    return $entities;
  }

  /**
   * Determines whether or not a given entity is domain-enabled.
   *
   * @param string $entity_type
   *   The machine name of the entity.
   * @param string $field
   *   The name of the field to check for existence.
   *
   * @return bool
   *   True if this type of entity has a domain field.
   */
  protected function entityHasDomainField($entity_type, $field = DomainInterface::DOMAIN_ADMIN_FIELD) {
    // Try to avoid repeated calls to getFieldMap(), assuming it's expensive.
    $this->ensureEntityFieldMap();
    return array_key_exists($field, $this->entityFieldMap[$entity_type]);
  }

  /**
   * Ensure the local entity field map has been defined.
   *
   * Asking for the entity field map cause a lot of lookup, so we lazily
   * fetch it and then remember it to avoid repeated checks.
   */
  protected function ensureEntityFieldMap() {
    // Try to avoid repeated calls to getFieldMap() assuming it's expensive.
    if (is_null($this->entityFieldMap)) {
      $this->entityFieldMap = $this->entityFieldManager->getFieldMap();
    }
  }

  /**
   * Enumerate entity instances of the supplied type and domain.
   *
   * @param string $entity_type
   *   The entity type name, e.g. 'node'.
   * @param string $domain_id
   *   The machine name of the domain to enumerate.
   * @param string $field
   *   The field to manipulate in the entity, e.g.
   *   DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD.
   * @param bool $just_count
   *   Flag to return a count rather than a list.
   *
   * @return int|string[]
   *   List of entity IDs for the selected domain or a count of domains.
   */
  protected function enumerateDomainEntities($entity_type, $domain_id, $field, $just_count = FALSE) {
    if (!$this->entityHasDomainField($entity_type, $field)) {
      $this->logger()->info('Entity type @entity_type does not have field @field, so none found.',
        [
          '@entity_type' => $entity_type,
          '@field' => $field,
        ]
      );
      return [];
    }

    $efq = $this->entityTypeManager->getStorage($entity_type)->getQuery();
    // Don't access check or we wont get all of the possible entities moved.
    $efq->accessCheck(FALSE);
    $efq->condition($field, $domain_id, '=');
    if ($just_count) {
      $efq->count();
    }

    return $efq->execute();
  }

  /**
   * Reassign old_domain entities, of the supplied type, to the new_domain.
   *
   * @param string $entity_type
   *   The entity type name, e.g. 'node'.
   * @param string $field
   *   The field to manipulate in the entity, e.g.
   *   DomainInterface::DOMAIN_ADMIN_FIELD.
   * @param \Drupal\domain\DomainInterface $old_domain
   *   The domain the entities currently belong to. It is not an error for
   *   entity ids to be passed in that are not in this domain, though of course
   *   not very useful.
   * @param \Drupal\domain\DomainInterface $new_domain
   *   The domain the entities should now belong to: When an entity belongs to
   *   the old_domain, this domain replaces it.
   * @param array $ids
   *   List of entity IDs for the selected domain and all of type $entity_type.
   *
   * @return int
   *   A count of the number of entities changed.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function reassignEntities($entity_type, $field, DomainInterface $old_domain, DomainInterface $new_domain, array $ids) {
    $entity_storage = $this->entityTypeManager->getStorage($entity_type);
    $entities = $entity_storage->loadMultiple($ids);

    foreach ($entities as $entity) {
      $changed = FALSE;
      if (!($entity instanceof FieldableEntityInterface) || !$entity->hasField($field)) {
        continue;
      }
      // Multivalue fields are used, so check each one.
      foreach ($entity->get($field)->value as $item) {
        if ($item->target_id === $old_domain->id()) {

          if ($this->isDryRun) {
            $this->logger()->info(dt('DRYRUN: Update domain membership for entity @id to @new.',
                [
                  '@id' => $entity->id(),
                  '@new' => $new_domain->id(),
                ]
              )
            );
            // Don't set changed, so don't save either.
            continue;
          }

          $changed = TRUE;
          $item->target_id = $new_domain->id();
        }
      }
      if ($changed) {
        $entity->save();
      }
    }
    return count($entities);
  }

  /**
   * Return the Domain object corresponding to a policy string.
   *
   * @param string $policy
   *   In general one of 'prompt' | 'default' | 'ignore' or a domain entity
   *   machine name, but this function does not process 'prompt'.
   *
   * @return \Drupal\domain\DomainInterface|null
   *   The requested domain or NULL if not found.
   *
   * @throws \Drupal\domain\Drush\Commands\DomainCommandException
   */
  protected function getDomainInstanceFromPolicy($policy) {
    switch ($policy) {
      // Use the Default Domain machine name.
      case 'default':
        $new_domain = $this->domainStorage()->loadDefaultDomain();
        break;

      // Ask interactively for a Domain machine name.
      case 'prompt':
      case 'ignore':
        return NULL;

      // Use this (specified) Domain machine name.
      default:
        $new_domain = $this->domainStorage()->load($policy);
        break;
    }

    return $new_domain;
  }

  /**
   * Reassign entities of the supplied type to the $policy domain.
   *
   * @param array $domains
   *   Array of domain objects to reassign content away from.
   * @param array $options
   *   Drush options sent to the command. An array such as the following:
   *   [
   *     'entity_filter' => 'node',
   *     'policy' => 'prompt' | 'default' | 'ignore' | {domain_id}
   *     'field' => DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD,
   *   ];
   *   The caller is expected to provide this information.
   *
   * @return int
   *   The count of updated entities.
   *
   * @throws \Drupal\domain\Drush\Commands\DomainCommandException
   */
  protected function reassignLinkedEntities(array $domains, array $options) {
    $count = 0;
    $field = $options['field'];
    $entity_types = $this->findDomainEnabledEntities($field);

    $new_domain = $this->getDomainInstanceFromPolicy($options['policy']);
    if (is_null($new_domain)) {
      throw new DomainCommandException('invalid destination domain');
    }

    // Loop through each entity type.
    $exceptions = FALSE;
    foreach ($entity_types as $name) {
      if (!isset($options['entity_filter']) || $options['entity_filter'] === $name) {

        // For each domain being reassigned from...
        foreach ($domains as $domain) {
          $ids = $this->enumerateDomainEntities($name, $domain->id(), $field);
          if ($ids !== []) {
            try {
              if ($options['chatty']) {
                $this->logger()->info('Reassigning @count @entity_name entities to @domain',
                  [
                    '@entity_name' => '',
                    '@count' => count($ids),
                    '@domain' => $new_domain->id(),
                  ]
                );
              }
              $count = $this->reassignEntities($name, $field, $domain, $new_domain, $ids);
            }
            catch (PluginException $e) {
              $exceptions = TRUE;
              $this->logger()->error('Unable to reassign content to @new_domain: plugin exception: @ex',
                [
                  '@ex' => $e->getMessage(),
                  '@new_domain' => $new_domain->id(),
                ]
              );
            }
            catch (EntityStorageException $e) {
              $exceptions = TRUE;
              $this->logger()->error('Unable to reassign content to @new_domain: storage exception: @ex',
                [
                  '@ex' => $e->getMessage(),
                  '@new_domain' => $new_domain->id(),
                ]
              );
            }
          }
        }
      }
    }
    if ($exceptions) {
      throw new DomainCommandException('Errors encountered during reassign.');
    }

    return $count;
  }

}
