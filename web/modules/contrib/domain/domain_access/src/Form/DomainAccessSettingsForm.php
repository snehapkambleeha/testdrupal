<?php

namespace Drupal\domain_access\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\RedundantEditableConfigNamesTrait;
use Drupal\Core\Url;

/**
 * Settings for the module.
 *
 * @package Drupal\domain_access\Form
 */
class DomainAccessSettingsForm extends ConfigFormBase {

  use RedundantEditableConfigNamesTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'domain_access_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['node_advanced_tab'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Move Domain Access fields to advanced node settings'),
      '#config_target' => 'domain_access.settings:node_advanced_tab',
      '#description' => $this->t('When checked the Domain Access fields will be shown as a tab in the advanced settings on node edit form. However, if you have placed the fields in a field group already, they will not be moved.'),
    ];
    $form['node_advanced_tab_open'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open the Domain Access details'),
      '#description' => $this->t('Set the details tab to be open by default.'),
      '#config_target' => 'domain_access.settings:node_advanced_tab_open',
      '#states' => [
        'visible' => [
          ':input[name="node_advanced_tab"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['access_fields_removal'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow removal of Domain Access fields (Experimental)'),
      '#config_target' => 'domain_access.settings:access_fields_removal',
      '#description' => $this->t(
        // phpcs:ignore Drupal.Semantics.FunctionT.Concat
        'When enabled, Domain Access fields can be safely removed from entity types.<br>' .
        'Be sure to <a href=":node_access_rebuild">rebuild permissions</a> after enabling or disabling this feature.<br>' .
        'See issue <a href="https://www.drupal.org/i/3408521">#3408521</a> for details.', [
          ':node_access_rebuild' => Url::fromRoute('node.configure_rebuild_confirm')->toString(),
        ]
      ),
    ];
    $form['per_bundle_grants'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable per-bundle node access grants (Experimental)'),
      '#config_target' => 'domain_access.settings:per_bundle_grants',
      '#description' => $this->t(
        // phpcs:ignore Drupal.Semantics.FunctionT.Concat
        'Generates node access grants based on the node’s content type. This provides more granular access control while preserving the default global grants.<br>' .
        'Be sure to <a href=":node_access_rebuild">rebuild permissions</a> after enabling or disabling this feature.<br>' .
        'See issue <a href="https://www.drupal.org/i/3554767">#3554767</a> for details.', [
          ':node_access_rebuild' => Url::fromRoute('node.configure_rebuild_confirm')->toString(),
        ]
      ),
    ];
    return parent::buildForm($form, $form_state);
  }

}
