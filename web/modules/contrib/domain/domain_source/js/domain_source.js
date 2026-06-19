/**
 * @file
 * Attaches behaviors for the Domain Source module.
 *
 * If Domain Access is present, we show/hide selected publishing domains. This approach
 * currently only works with a select field.
 */
(function (Drupal, document) {
  /**
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.domainSourceAllowed = {
    attach() {
      const select = document.getElementById('edit-field-domain-source');
      if (!select) {
        return;
      }

      // Get the initial setting so that it can be reset.
      const initialOption = select.value;

      // Based on selected domains, show/hide the selection options.
      function setOptions(domains) {
        const options = select.querySelectorAll('option');
        options.forEach((option) => {
          if (option.value !== '_none' && !domains.includes(option.value)) {
            // If the current selection is removed, reset the selection to _none.
            if (select.value === option.value) {
              select.value = '_none';
            }
            option.hidden = true;
          } else {
            option.hidden = false;
            // If we reselected the initial value, reset the select option.
            if (option.value === initialOption) {
              select.value = option.value;
            }
          }
        });
      }

      // Get the domains selected by the domain access field.
      function getDomains() {
        const domains = [];
        const checked = document.querySelectorAll(
          '#edit-field-domain-access input:checked',
        );
        checked.forEach((input) => {
          domains.push(input.value);
        });
        setOptions(domains);
      }

      // Onload, fire initial show/hide.
      getDomains();

      // When the selections change, recalculate the select options.
      const accessContainer = document.getElementById(
        'edit-field-domain-access',
      );
      if (accessContainer) {
        accessContainer.addEventListener('change', getDomains);
      }
    },
  };
})(Drupal, document);
