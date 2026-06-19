<?php

namespace Drupal\domain\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Utility\Token;

/**
 * Theme hook implementations for domain.
 */
class DomainThemeHooks {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected Token $token,
  ) {}

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme() {
    return [
      'domain_nav_block' => [
        'render element' => 'items',
        'initial preprocess' => static::class . ':preprocessDomainNavBlock',
      ],
    ];
  }

  /**
   * Prepares variables for domain nav block templates.
   *
   * @param array $variables
   *   An associative array containing block items.
   */
  public function preprocessDomainNavBlock(array &$variables): void {
    $variables['items'] = $variables['items']['#items'];
  }

  /**
   * Implements hook_preprocess_HOOK() for html.html.twig.
   */
  #[Hook('preprocess_html')]
  public function preprocessHtml(array &$variables) {
    // Add class to body tag, if set.
    $config = $this->configFactory->get('domain.settings');
    $string = $config->get('css_classes') ?? NULL;
    if (!is_null($string)) {
      // Prepare the classes properly, with one class per string.
      $classes = explode(' ', trim($string));
      foreach ($classes as $class) {
        // Ensure no leading or trailing space.
        $class = trim($class);
        if (strlen($class) > 0) {
          $variables['attributes']['class'][] = Html::getClass($this->token->replace($class));
        }
      }
    }
  }

}
