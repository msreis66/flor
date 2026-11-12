((Drupal, drupalSettings, once) => {
  /**
   * UI Kit: Copy URL button.
   */
  Drupal.behaviors.bootstrapUiKitCopyIcon = {
    attach(context) {
      once("ui-kit-copy-icon", ".btn-copy__icon", context).forEach((element) => {
        let tooltip;

        async function copyTargetText(text) {
          try {
            await navigator.clipboard.writeText(text);
          } catch (err) {
            console.error("Failed to copy (make sure your accessing the site using https): ", err);
          }
        }

        element.addEventListener("click", (e) => {
          copyTargetText(element.lastElementChild.innerHTML);

          if (tooltip) {
            tooltip.dispose();
          }
          tooltip = new Tooltip(element, {
            title: Drupal.t("Name copied!"),
            trigger: "manual",
            placement: "bottom",
          });
          tooltip.show();

          setTimeout(function () {
            tooltip.hide();
          }, 2000)
        });

      });
    },
  };

  /**
   * UI Kit: Icon search filter.
   */
  Drupal.behaviors.bootstrapUiKitIconSearch = {
    attach(context) {
      const searchInput = context.querySelector('#icon-search-input');
      const iconList = context.querySelector('#iconography-list');
      if (searchInput && iconList) {
        searchInput.addEventListener('input', function () {
          const query = this.value.trim().toLowerCase();
          iconList.querySelectorAll('[data-icon-name]').forEach(iconEl => {
            const iconName = iconEl.getAttribute('data-icon-name').toLowerCase();
            if (!query || iconName.includes(query)) {
              iconEl.classList.remove('d-none');
            } else {
              iconEl.classList.add('d-none');
            }
          });
        });
      }
    }
  };
})(Drupal, drupalSettings, once);
