<?php

namespace Drupal\domain;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Generic base class for handling hidden field options.
 *
 * Since domain options are restricted for various forms (users, nodes, source)
 * we have a base class for handling common use cases. The details of each
 * implementation are generally handled by a subclass and invoked within a
 * hook_form_alter().
 *
 * This class has some similarities to DomainAccessManager, but only cares
 * about form handling. It can be used as a base class by other modules that
 * show/hide domain options. See the DomainSourceElementManager for a
 * non-default implementation.
 */
class DomainElementManager implements DomainElementManagerInterface {

  use StringTranslationTrait;

  /**
   * The domain storage.
   *
   * @var \Drupal\domain\DomainStorageInterface
   */
  protected $domainStorage;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RendererInterface $renderer,
    protected SelectionPluginManagerInterface $selectPluginManager,
  ) {
    $this->domainStorage = $entityTypeManager->getStorage('domain');
  }

  /**
   * {@inheritdoc}
   */
  public function setFormOptions(array $form, FormStateInterface $form_state, $field_name, $hide_on_disallow = FALSE) {
    // There are cases, such as Entity Browser, where the form is partially
    // invoked, but without our fields.
    if (!isset($form[$field_name])) {
      return $form;
    }
    $fields = $this->fieldList($field_name);
    $empty = FALSE;
    $disallowed = $this->disallowedOptions($form_state, $form[$field_name]);
    $widget = $form[$field_name]['widget'];

    // Select / Checkboxes widget.
    if (isset($widget['#type']) &&
      in_array($widget['#type'], ['select', 'checkboxes'])) {
      $empty = $this->checkSelectCheckboxesIsEmpty($widget);
    }
    // Autocomplete widget.
    elseif (isset($widget['target_id']) &&
      $widget['target_id']['#type'] == 'entity_autocomplete') {
      $empty = $this->checkAutocompleteIsEmpty($widget);
    }

    // If the domain form element is set as a group, and the field is not
    // assigned to another group, then move it. See
    // domain_access_form_node_form_alter().
    if (isset($form['domain']) && !isset($form[$field_name]['#group'])) {
      $form[$field_name]['#group'] = 'domain';
    }
    // If no values and we should hide the element, do so.
    if ($hide_on_disallow && $empty) {
      $form[$field_name]['#access'] = FALSE;
    }
    // Check for domains the user cannot access or the absence of any options.
    if (count($disallowed) > 0 || $empty) {
      // @todo Potentially show this information to users with permission.
      $form[$field_name . '_disallowed'] = [
        '#type' => 'value',
        '#value' => $disallowed,
      ];
      $form['domain_hidden_fields'] = [
        '#type' => 'value',
        '#value' => $fields,
      ];
      if ($hide_on_disallow || $empty) {
        $form[$field_name]['#access'] = FALSE;
      }
      elseif (count($disallowed) > 0) {
        $form[$field_name]['widget']['#description'] .= $this->listDisallowed($disallowed);
      }
      // Call our submit function to merge in values.
      // Account for all the submit buttons on the node form.
      $buttons = ['preview', 'delete'];
      $submit = $this->getSubmitHandler();
      foreach ($form['actions'] as $key => $action) {
        if (!in_array($key, $buttons, TRUE) && isset($form['actions'][$key]['#submit']) && !in_array($submit, $form['actions'][$key]['#submit'], TRUE)) {
          array_unshift($form['actions'][$key]['#submit'], $submit);
        }
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function submitEntityForm(array &$form, FormStateInterface $form_state) {
    $fields = $form_state->getValue('domain_hidden_fields');
    foreach ($fields as $field) {
      $values = NULL;
      $entity_values = [];
      if ($form_state->hasValue($field . '_disallowed')) {
        $values = $form_state->getValue($field . '_disallowed');
        $entity_values = $form_state->getValue($field);

        if (is_array($values)) {
          foreach ($values as $value) {
            $entity_values[]['target_id'] = $value;
          }
        }
        else {
          $entity_values[]['target_id'] = $values;
        }
      }
      // Prevent a fatal error caused by passing a NULL value.
      // See https://www.drupal.org/node/2841962.
      if ($entity_values !== []) {
        // Ensure unique values by target_id (required for content translation).
        // See DomainAccessLanguageSaveTest::testDomainAccessSaveTranslation().
        $unique_values = array_column($entity_values, NULL, 'target_id');
        $form_state->setValue($field, array_values($unique_values));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function disallowedOptions(FormStateInterface $form_state, array $field) {
    $options = [];
    $form = $form_state->getFormObject();
    if ($form instanceof EntityFormInterface) {
      $entity = $form->getEntity();
      $entity_values = $this->getFieldValues($entity, $field['widget']['#field_name']);
      if (isset($field['widget']['#options'])) {
        $options = array_diff_key($entity_values, $field['widget']['#options']);
      }
    }
    return array_keys($options);
  }

  /**
   * {@inheritdoc}
   */
  public function fieldList($field_name) {
    static $fields = [];
    $fields[] = $field_name;
    // Return only unique field names. AJAX requests can result in duplicates.
    // See https://www.drupal.org/project/domain/issues/2930934.
    return array_unique($fields);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldValues(FieldableEntityInterface $entity, $field_name) {
    // @todo static cache.
    $list = [];
    // @todo In tests, $entity is returning NULL.
    if (is_null($entity)) {
      return $list;
    }
    // Get the values of an entity.
    $values = $entity->hasField($field_name) ? $entity->get($field_name) : NULL;
    // Must be at least one item.
    if (!is_null($values)) {
      foreach ($values as $item) {
        $target = $item->getValue();
        if (isset($target['target_id'])) {
          $domain = $this->domainStorage->load($target['target_id']);
          if ($domain instanceof DomainInterface) {
            $list[$domain->id()] = $domain->getDomainId();
          }
        }
      }
    }

    return $list;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubmitHandler() {
    return '\\Drupal\\domain\\DomainElementManager::submitEntityForm';
  }

  /**
   * Lists the disallowed domains in the user interface.
   *
   * @param array $disallowed
   *   An array of domain ids.
   *
   * @return string
   *   A string suitable for display.
   */
  public function listDisallowed(array $disallowed) {
    $domains = $this->domainStorage->loadMultiple($disallowed);
    $string = $this->t('The following domains are currently assigned and cannot be changed:');
    $items = [];
    foreach ($domains as $domain) {
      $items[] = $domain->label();
    }
    $build = [
      '#theme' => 'item_list',
      '#items' => $items,
    ];
    $string .= $this->renderer->render($build);
    return '<div class="disallowed">' . $string . '</div>';
  }

  /**
   * Checks if the select or checkboxes widget is empty.
   *
   * @param array $widget
   *   Widget type.
   *
   * @return bool
   *   Returning True or False.
   */
  protected function checkSelectCheckboxesIsEmpty(array $widget): bool {
    if (count($widget['#options']) === 0 ||
      (count($widget['#options']) === 1 &&
        isset($widget['#options']['_none']))
    ) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Checks if the entity_autocomplete widget is empty.
   *
   * @param array $widget
   *   Widget Type.
   *
   * @return bool
   *   Returning True or False.
   */
  protected function checkAutocompleteIsEmpty(array $widget): bool {
    $plugin = $this->selectPluginManager->createInstance('default:domain', ['target_type' => 'domain']);
    $options = $plugin->countReferenceableEntities();
    return $options === 0;
  }

}
