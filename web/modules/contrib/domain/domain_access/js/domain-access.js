/**
 * @file
 * Attaches behaviors for the Domain module.
 */

(function ($, Drupal) {
  /**
   * Provides the summary information for the block settings vertical tabs.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior for the block settings summaries.
   */
  Drupal.behaviors.domainAccessSettingsSummaries = {
    attach() {
      // The drupalSetSummary method required for this behavior is not available
      // on the Blocks administration page, so we need to make sure this
      // behavior is processed only if drupalSetSummary is defined.
      if (typeof $.fn.drupalSetSummary === 'undefined') {
        return;
      }

      // There may be an easier way to do this. Right now, we just copy code
      // from block module.
      function checkboxesSummary(context) {
        const checkboxLabels = Array.from(
          context.querySelectorAll('input[type="checkbox"]:checked + label'),
        );
        const values = checkboxLabels.map((label) => label.textContent);
        if (values.length === 0) {
          values.push(Drupal.t('Not restricted'));
        }
        return values.join(', ');
      }

      $(
        '[data-drupal-selector="edit-visibility-domain-access"]',
      ).drupalSetSummary(checkboxesSummary);
    },
  };
})(jQuery, Drupal);
