((Drupal, once) => {

  /**
   * UI Kit: Hash navigation support and tab activation.
   */
  Drupal.behaviors.bootstrapUiKitNav = {
    attach: function attach(context, settings) {
      // Initialize all tabs with Bootstrap Tab API
      once("bootstrap-ui-kit-tabs", '[data-bs-toggle="tab"]', context).forEach((element) => {
        // Initialize Bootstrap Tab
        if (window.bootstrap?.Tab) {
          new bootstrap.Tab(element);
        }

        // Handle tab shown event for URL updates
        element.addEventListener("shown.bs.tab", (event) => {
          const target = event.target.getAttribute('data-bs-target') || event.target.getAttribute('href');
          if (target) {
            window.history.replaceState(
              window.history.state,
              '',
              target
            );
          }
        });
      });

      // Activate tab from hash on page load
      once("bootstrap-ui-kit-hash-activation", '.bootstrap-ui-kit', context).forEach(() => {
        function activateDesiredTab() {
          const hash = location.hash.replace(/^#/, "");
          let triggerEl = null;

          if (hash) {
            // Try different selector patterns for tab triggers with hash
            const selectors = [
              `#${hash}-tab`,
              `[data-bs-target="#${hash}"]`,
              `[href="#${hash}"]`,
              `button[data-bs-target="#${hash}"]`,
              `a[href="#${hash}"]`
            ];

            for (const selector of selectors) {
              const candidate = document.querySelector(selector);
              if (candidate?.hasAttribute('data-bs-toggle')) { triggerEl = candidate; break; }
            }
          } else {
            // No hash - activate the first tab
            triggerEl = document.querySelector('[data-bs-toggle="tab"]');
          }

          if (!triggerEl) { return false; }

          // Clear existing (in case server rendered first active or previous state retained)
          document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
            tab.classList.remove('active');
            tab.setAttribute('aria-selected', 'false');
          });
          document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('show','active'));

          // Use Bootstrap's Tab API to show the tab
          if (window.bootstrap?.Tab) {
            bootstrap.Tab.getOrCreateInstance(triggerEl).show();
          } else {
            // Fallback: manually activate tab and pane
            triggerEl.classList.add('active');
            triggerEl.setAttribute('aria-selected','true');

            const targetId = triggerEl.getAttribute('data-bs-target') || triggerEl.getAttribute('href');
            if (targetId) {
              const pane = document.querySelector(targetId);
              pane?.classList.add('show','active');
            }
          }
          return true;
        }

        // Wait until tabs + (optionally) Bootstrap are actually ready instead of fixed delay.
        function waitForTabs(maxFrames = 60) { // ~1s @60fps
          const haveTabs = document.querySelector('[data-bs-toggle="tab"]');
          const bootstrapReady = !!window.bootstrap?.Tab; // may be false if relying on fallback
          if (haveTabs && (bootstrapReady || maxFrames < 60)) { // if bootstrap not yet ready we still try; else keep looping briefly
            if (activateDesiredTab()) { return; }
          }
          if (maxFrames > 0) {
            requestAnimationFrame(() => waitForTabs(maxFrames - 1));
          }
        }

        // Activate tab on initial load
        if (document.readyState === 'loading') {
          document.addEventListener('DOMContentLoaded', () => waitForTabs());
        } else {
          waitForTabs();
        }

        // Activate tab on hash change
        window.addEventListener('hashchange', () => activateDesiredTab());
      });
    },
  };

  /**
   * UI Kit: Copy URL button.
   */
  Drupal.behaviors.bootstrapUiKitCopyUrl = {
    attach(context) {
      once("ui-kit-copy-url", ".btn-copy__link", context).forEach((element) => {
        let tooltip;

        async function copyTargetText(text) {
          try {
            await navigator.clipboard.writeText(text);
          } catch (err) {
            console.error("Failed to copy (make sure you're accessing the site using https): ", err);
          }
        }

        element.addEventListener("click", (e) => {
          e.preventDefault();
          copyTargetText(window.location.href);

          // Dispose existing tooltip
          if (tooltip) {
            tooltip.dispose();
          }

          // Create new tooltip (check if Bootstrap Tooltip is available)
          if (window.bootstrap && window.bootstrap.Tooltip) {
            tooltip = new bootstrap.Tooltip(element, {
              title: Drupal.t("URL Copied!"),
              trigger: "manual",
              placement: "auto",
            });
            tooltip.show();

            setTimeout(function () {
              tooltip.hide();
              // Clean up tooltip after hiding
              setTimeout(() => {
                if (tooltip) {
                  tooltip.dispose();
                  tooltip = null;
                }
              }, 150);
            }, 2000);
          } else {
            // Fallback for when Bootstrap Tooltip is not available
            console.log("URL copied to clipboard");
          }
        });
      });
    },
  };

})(Drupal, once);
