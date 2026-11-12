((Drupal, drupalSettings, once) => {
  /**
   * Typography section helpers.
   */
  Drupal.behaviors.swat_storybookMisc = {
    attach: function attach(context, settings) {
      once("uikit-typography", "[data-typography-level]", context).forEach((element) => {
        const computedStyles = getComputedStyle(element);
        const target = element.parentNode.previousElementSibling;

        // Dynamically generated font information.
        var font = document.createElement('p');
        font.innerHTML =
          '<strong>' + Drupal.t('Font family: ') + '</strong>' + computedStyles.fontFamily + '<br/>' +
          '<strong>' + Drupal.t('Font weight: ') + '</strong>' + computedStyles.fontWeight + '<br/>' +
          '<strong>' + Drupal.t('Font size: ') + '</strong>' + rounded(computedStyles.fontSize) + '<br/>' +
          '<strong>' + Drupal.t('Line height: ') + '</strong>' + rounded(computedStyles.lineHeight);
        target.append(font);

      });
    },
  };

  // Round a given number for cleaner UI.
  function rounded(computedItem) {
    let fontSize = parseFloat(computedItem);
    let roundedFontSize = Math.round(fontSize);
    return roundedFontSize + 'px';
  }
})(Drupal, drupalSettings, once);
