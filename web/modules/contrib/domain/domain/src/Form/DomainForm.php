<?php

namespace Drupal\domain\Form;

use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\domain\DomainStorageInterface;
use Drupal\domain\DomainValidatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base form for domain edit forms.
 */
class DomainForm extends EntityForm {

  public function __construct(
    protected DomainStorageInterface $domainStorage,
    protected DomainValidatorInterface $validator,
    protected TypedConfigManagerInterface $typedConfigManager,
    EntityTypeManagerInterface $entityTypeManager,
    MessengerInterface $messenger,
    protected bool $pathPrefixEnabled = FALSE,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('domain'),
      $container->get('domain.validator'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
      $container->get('messenger'),
      $container->getParameter('domain.path_prefix'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\domain\Entity\Domain $domain */
    $domain = $this->entity;

    // Create defaults if this is the first domain.
    $count_existing = $this->domainStorage->getQuery()->accessCheck(FALSE)->count()->execute();
    if ($count_existing === 0) {
      $domain->addProperty('hostname', $this->domainStorage->createHostname());
      $domain->addProperty('name', $this->config('system.site')->get('name'));
    }
    $form['domain_id'] = [
      '#type' => 'value',
      '#value' => $domain->getDomainId(),
    ];
    $form['hostname'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Hostname'),
      '#size' => 40,
      '#maxlength' => 80,
      '#default_value' => $domain->getCanonical(),
      '#description' => $this->t('The canonical hostname, using the full <em>subdomain.example.com</em> format. Leave off the http:// and the trailing slash and do not include any paths.<br />If this domain uses a custom http(s) port, you should specify it here, e.g.: <em>subdomain.example.com:1234</em><br />The hostname may contain only lowercase alphanumeric characters, dots, dashes, and a colon (if using alternative ports).'),
    ];
    $non_ascii = (bool) $this->config('domain.settings')
      ->get('allow_non_ascii');
    $form['path_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path prefix'),
      '#size' => 40,
      '#maxlength' => 64,
      '#default_value' => $domain->getPathPrefix(),
      '#description' => $this->t('Optional URL path segment that identifies this domain when multiple domains share the same hostname.<br />For example, with prefix <em>myprefix</em>, this domain handles requests to <em>example.com/myprefix/...</em> while the unprefixed domain handles <em>example.com/...</em>.<br />Use only lowercase letters, numbers, hyphens, and underscores. Do not include slashes.'),
      '#access' => $this->pathPrefixEnabled,
    ];
    if (!$non_ascii) {
      $form['path_prefix']['#pattern'] = '[a-z0-9][a-z0-9_\-]*';
    }
    $form['#attached']['library'][] = 'domain/machine-name-source';
    // Hidden element combining hostname and path prefix for
    // the machine name widget. JS updates this when either
    // hostname or path_prefix changes.
    $source_value = $domain->getCanonical();
    if ($domain->getPathPrefix() !== '') {
      $source_value .= '.' . $domain->getPathPrefix();
    }
    $form['machine_name_source'] = [
      '#type' => 'hidden',
      '#default_value' => $source_value,
      '#attributes' => ['id' => 'edit-machine-name-source'],
    ];
    $id = $domain->id() ?? NULL;
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $id,
      '#disabled' => !is_null($id),
      '#machine_name' => [
        'source' => ['machine_name_source'],
        'exists' => [$this->domainStorage, 'load'],
        'standalone' => TRUE,
      ],
    ];
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#size' => 40,
      '#maxlength' => 80,
      '#default_value' => $domain->label(),
      '#description' => $this->t('The human-readable name is shown in domain lists and may be used as the title tag.'),
    ];
    $form['scheme'] = [
      '#type' => 'radios',
      '#title' => $this->t('Domain URL scheme'),
      '#options' => [
        'http' => 'http://',
        'https' => 'https://',
        'variable' => 'Variable',
      ],
      '#default_value' => $domain->getRawScheme(),
      '#description' => $this->t('This URL scheme will be used when writing links and redirects to this domain and its resources. Selecting <strong>Variable</strong> will inherit the current scheme of the web request.'),
    ];
    $form['status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Domain status'),
      '#options' => [1 => $this->t('Active'), 0 => $this->t('Inactive')],
      '#default_value' => (int) $domain->status(),
      '#description' => $this->t('"Inactive" domains are only accessible to user roles with that assigned permission.'),
    ];
    $form['weight'] = [
      '#type' => 'weight',
      '#title' => $this->t('Weight'),
      '#delta' => $count_existing + 1,
      '#default_value' => $domain->getWeight(),
      '#description' => $this->t('The sort order for this record. Lower values display first.'),
    ];
    $form['is_default'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Default domain'),
      '#default_value' => $domain->isDefault(),
      '#description' => $this->t('If a URL request fails to match a domain record, the settings for this domain will be used. Only one domain can be default.'),
    ];
    $form['validate_url'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Test server response'),
      '#default_value' => TRUE,
      '#description' => $this->t('Validate that  url of the host is accessible to Drupal before saving.'),
    ];
    $required = $this->validator->getRequiredFields();
    foreach ($form as $key => $element) {
      if (in_array($key, $required, TRUE)) {
        $form[$key]['#required'] = TRUE;
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\domain\DomainInterface $entity */
    $entity = $this->entity;

    // Validate via config schema constraints.
    $violations = $this->typedConfigManager
      ->createFromNameAndData(
        $entity->getConfigDependencyName(),
        $entity->toArray()
      )
      ->validate();

    // Map violations to form elements by property path.
    foreach ($violations as $violation) {
      $property_path = $violation->getPropertyPath();
      if (isset($form[$property_path])) {
        $form_state->setErrorByName(
          $property_path,
          $violation->getMessage()
        );
      }
    }

    // Validate path prefix characters. When non-ASCII domains
    // are allowed, Unicode lowercase letters are also accepted.
    $prefix = trim($form_state->getValue('path_prefix') ?? '');
    if ($prefix !== '') {
      $non_ascii = (bool) $this->config('domain.settings')
        ->get('allow_non_ascii');
      $pattern = $non_ascii
        ? '/^[\p{L}\p{N}][\p{L}\p{N}_\-]*$/u'
        : '/^[a-z0-9][a-z0-9_\-]*$/';
      if (!preg_match($pattern, $prefix)) {
        $form_state->setErrorByName('path_prefix', $this->t('The path prefix may only contain lowercase letters, numbers, hyphens, and underscores, and must start with a letter or number.'));
      }
    }

    // Is validate_url set?
    $check = (bool) $form_state->getValue('validate_url');
    if ($check) {
      // Check the domain response. First, clear the path value.
      $entity->setPath();
      // Check the response.
      $response = $this->validator->checkResponse($entity);
      // If validate_url is set, then we must receive a 200 response.
      if ($response !== 200) {
        $form_state->setErrorByName('hostname', $this->t('The server request to @url returned a @response response. To proceed, disable the <em>Test server response</em> in the form.', [
          '@url' => $entity->getBasePath(),
          '@response' => $response,
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $status = parent::save($form, $form_state);
    if ($status === SAVED_NEW) {
      $this->messenger->addMessage($this->t('Domain record created.'));
    }
    else {
      $this->messenger->addMessage($this->t('Domain record updated.'));
    }
    $form_state->setRedirect('domain.admin');

    return $status;
  }

}
