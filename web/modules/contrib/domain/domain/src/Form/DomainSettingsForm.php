<?php

namespace Drupal\domain\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\RedundantEditableConfigNamesTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for the Domain module.
 *
 * @package Drupal\domain\Form
 */
class DomainSettingsForm extends ConfigFormBase {

  use RedundantEditableConfigNamesTrait;

  /**
   * The domain token handler.
   *
   * @var \Drupal\domain\DomainToken
   */
  protected $domainTokens;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->domainTokens = $container->get('domain.token');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'domain_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['allow_non_ascii'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow non-ASCII characters in domains, aliases, and prefixes'),
      '#config_target' => 'domain.settings:allow_non_ascii',
      '#description' => $this->t('Domains and path prefixes may use international character sets. Note that not all DNS servers respect non-ASCII characters.'),
    ];
    $form['www_prefix'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ignore www prefix when negotiating domains'),
      '#config_target' => 'domain.settings:www_prefix',
      '#description' => $this->t('Domain negotiation will ignore any www prefixes for all requests.'),
    ];
    // Get the usable tokens for this field.
    $patterns = [];
    foreach ($this->domainTokens->getCallbacks() as $key => $callback) {
      $patterns[] = "[domain:$key]";
    }
    $form['css_classes'] = [
      '#type' => 'textfield',
      '#size' => 80,
      '#title' => $this->t('Custom CSS classes'),
      '#config_target' => 'domain.settings:css_classes',
      '#description' => $this->t('Enter any CSS classes that should be added to the &lt;body&gt; tag. Available replacement patterns are: @patterns', [
        '@patterns' => implode(', ', $patterns),
      ]),
    ];
    $form['login_paths'] = [
      '#type' => 'textarea',
      '#rows' => 5,
      '#columns' => 40,
      '#title' => $this->t('Paths that should be accessible for inactive domains'),
      '#config_target' => 'domain.settings:login_paths',
      '#description' => $this->t('Inactive domains are only accessible to users with permission.
        Enter any paths that should be accessible, one per line. Normally, only the
        login path will be allowed.'),
    ];
    $form['experimental'] = [
      '#type' => 'details',
      '#title' => $this->t('Experimental features'),
      '#open' => FALSE,
    ];
    $form['experimental']['path_prefix'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable path prefix support'),
      '#config_target' => 'domain.settings:path_prefix',
      '#description' => $this->t(
        'When enabled, multiple domains can share the same hostname and be differentiated by a path prefix (e.g. <em>example.com/benl</em> vs <em>example.com/befr</em>). This overrides core\'s language URL negotiation plugin to account for the domain prefix. <br />See issue <a href="https://www.drupal.org/i/3575947">#3575947</a> for details.'
      ),
    ];
    $form['experimental']['language_negotiation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable language negotiation for cross-domain URLs'),
      '#config_target' => 'domain.settings:language_negotiation',
      '#description' => $this->t(
        'If enabled, outbound URLs will be processed using the language negotiation settings of their target domain. This is required if different domains use different language path prefixes or negotiation methods. <br /><strong>Note:</strong> Enabling this option will trigger an additional language-negotiation pass for URLs whose target domain differs from the current domain. <br />See issue <a href="https://www.drupal.org/i/3570178">#3570178</a> for details.'
      ),
    ];
    $form['experimental']['allow_destination_domain'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow domain-scoped destination redirects'),
      '#config_target' => 'domain.settings:allow_destination_domain',
      '#description' => $this->t(
        // phpcs:ignore Drupal.Semantics.FunctionT.Concat
        'When enabled, cross-domain links that include a destination parameter will also include a domain tracking parameter. This ensures that users are redirected back to the correct domain after completing an action on a different domain (like updating a content or logging in).<br>' .
        'See issue <a href="https://www.drupal.org/i/3570210">#3570210</a> for details.'
      ),
    ];
    return parent::buildForm($form, $form_state);
  }

}
