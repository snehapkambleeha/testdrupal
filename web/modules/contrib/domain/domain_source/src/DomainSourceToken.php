<?php

namespace Drupal\domain_source;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\domain\DomainInterface;
use Drupal\node\NodeInterface;

/**
 * Token handler for Domain Source.
 *
 * TokenAPI still uses procedural code, but we have moved it to a class for
 * easier refactoring.
 */
class DomainSourceToken {

  use StringTranslationTrait;

  /**
   * Implements hook_token_info().
   */
  public function getTokenInfo() {
    return [
      'tokens' => [
        'node' => [
          'canonical-source-domain-url' => [
            'name' => $this->t('Canonical Source Domain URL'),
            'description' => $this->t("The canonical URL from the source domain for this node."),
            'type' => 'node',
          ],
        ],
      ],
    ];
  }

  /**
   * Implements hook_tokens().
   */
  public function getTokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
    $replacements = [];

    if ($type !== 'node') {
      return $replacements;
    }

    foreach ($tokens as $name => $original) {
      if ($name !== 'canonical-source-domain-url') {
        continue;
      }
      if (!isset($data['node']) || !$data['node'] instanceof NodeInterface) {
        continue;
      }
      $node = $data['node'];
      $url = $node->toUrl('canonical');

      // If the node has a source domain, set it as the domain
      // option so DomainPathProcessor rewrites the URL.
      // @phpstan-ignore-next-line
      if ($node->hasField('field_domain_source') && !$node->field_domain_source->isEmpty()) {
        // @phpstan-ignore-next-line
        $source = $node->field_domain_source->entity;
        if ($source instanceof DomainInterface) {
          $url->setOption('domain', $source);
        }
      }

      $generated_url = $url->setAbsolute()->toString(TRUE);
      $replacements[$original] = $generated_url->getGeneratedUrl();
      $bubbleable_metadata->merge($generated_url);
    }

    return $replacements;
  }

}
