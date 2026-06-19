/**
 * @file
 * Combines hostname and path prefix into the machine name source.
 */

((Drupal, once) => {
  /**
   * Updates the hidden machine-name-source field.
   *
   * Watches the hostname and path_prefix fields and combines
   * their values into a hidden element that the machine name
   * widget uses as its source.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.domainMachineNameSource = {
    attach(context) {
      const source = once(
        'domain-machine-name-source',
        '#edit-machine-name-source',
        context,
      );
      if (!source.length) {
        return;
      }
      const hidden = source[0];
      const hostname = document.querySelector(
        '[data-drupal-selector="edit-hostname"]',
      );
      const prefix = document.querySelector(
        '[data-drupal-selector="edit-path-prefix"]',
      );
      if (!hostname) {
        return;
      }

      function update() {
        const { value } = hostname;
        hidden.value =
          prefix && prefix.value ? `${value}.${prefix.value}` : value;
        hidden.dispatchEvent(new Event('change', { bubbles: true }));
      }

      hostname.addEventListener('input', update);
      if (prefix) {
        prefix.addEventListener('input', update);
      }
    },
  };
})(Drupal, once);
