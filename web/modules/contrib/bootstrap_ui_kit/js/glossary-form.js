(function ($, Drupal) {
  'use strict';

  function findAjaxInstanceForElement(el) {
    if (!Drupal.ajax || !Drupal.ajax.instances) return null;
    for (const key in Drupal.ajax.instances) {
      if (!Object.prototype.hasOwnProperty.call(Drupal.ajax.instances, key)) continue;
      const inst = Drupal.ajax.instances[key];
      if (inst && inst.element === el) return inst;
    }
    return null;
  }

  function executeAjaxOnElement($el) {
    const el = $el.get(0);
    if (!el) return false;

    // Try direct data reference (Drupal >=10 stores on element sometimes)
    const dataInst = $el.data('drupalAjax') || findAjaxInstanceForElement(el);
    if (dataInst) {
      if (console && console.debug) console.debug('[Glossary] Executing existing Drupal.ajax instance for', el.name || el.id);
      // Execute request directly.
      dataInst.eventResponse(el, $.Event('click'));
      return true;
    }
    // Fallback: simulate user activation.
    if (console && console.debug) console.debug('[Glossary] No Drupal.ajax instance found; falling back to synthetic click for', el.name || el.id);
    $el.trigger('mousedown').trigger('click');
    return false;
  }

  function bindHandlersOnce() {
    if (Drupal._glossaryHandlersBound) return; // Prevent duplicate binding across AJAX refreshes.
    Drupal._glossaryHandlersBound = true;

    // Cancel button inside modal.
    $(document).on('click.glossaryCancelRemove', '.glossary-cancel-remove', function (e) {
      e.preventDefault();
      const $dialog = $(this).closest('.ui-dialog-content');
      if ($dialog.length) {
        if (console && console.debug) console.debug('[Glossary] Cancel removal, closing dialog');
        $dialog.dialog('close');
      }
    });

    // Confirm removal button inside modal.
    $(document).on('click.glossaryConfirmRemove', '.glossary-confirm-remove', function (e) {
      e.preventDefault();
      const $btn = $(this);
      if ($btn.prop('disabled')) return;

      const groupKey = $btn.attr('data-group-key');
      if (!groupKey) {
        console.warn('[Glossary] Missing data-group-key on confirm button');
        return;
      }

      const selector = 'input[name="remove_group_direct_' + groupKey + '"]';
      // Search first within wrapper for isolation.
      let $hidden = $('#glossary-form-wrapper').find(selector);
      if (!$hidden.length) {
        $hidden = $(selector);
      }
      if (!$hidden.length) {
        console.error('[Glossary] Hidden removal submit not found for group', groupKey);
        return;
      }

      if (console && console.debug) console.debug('[Glossary] Confirming removal of group', groupKey, '-> triggering hidden submit', $hidden.get(0));

      $btn.prop('disabled', true).addClass('is-disabled');

      // Close dialog first.
      const $dialog = $btn.closest('.ui-dialog-content');
      if ($dialog.length) {
        $dialog.dialog('close');
      }

      // Defer execution to allow dialog teardown & focus restoration.
      setTimeout(function () {
        executeAjaxOnElement($hidden);
      }, 10);
    });
  }

  Drupal.behaviors.glossaryFormConfirmation = {
    attach: function (context) {
      // Always attempt to bind (guarded internally).
      bindHandlersOnce();
    }
  };
})(jQuery, Drupal);
