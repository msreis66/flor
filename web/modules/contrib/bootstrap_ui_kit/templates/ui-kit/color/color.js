/*
* Documentation: https://www.aremycolorsaccessible.com/api-page
*/
((Drupal, drupalSettings, once) => {
  /**
   * Color section helpers.
   */
  Drupal.behaviors.bootstrapUiKitColor = {
    attach: function attach(context, settings) {

      // Swatches (HEX replacement).
      once("uikit-color-swatch", "[data-color-swatch]", context).forEach((element) => {
        const target = element.parentNode.nextElementSibling.querySelector('[data-swatch-hex] .placeholder-glow');
        const swatchName = element.dataset.colorSwatch;
        const hex = getComputedStyle(document.documentElement).getPropertyValue('--bs-' + swatchName).trim();
        target.replaceWith(hex ? hex : '#HEX');
      });

      // Dynamic accessibility checker.
      once("uikit-color-check", "[data-ui-kit-font-size]", context).forEach((element) => {
        const target = element.querySelector('.placeholder-glow');
        const fontSize = getComputedStyle(element).getPropertyValue('font-size');
        target.replaceWith(fontSize ? fontSize : '❌');
      });

      // Dynamic accessibility checker.
      once("uikit-color-check", "[data-color-check]", context).forEach((element) => {
        const colors = element.dataset.colorCheck.split(',');
        const foreground = getComputedStyle(document.documentElement).getPropertyValue('--bs-' + colors[0]).trim();
        const background = getComputedStyle(document.documentElement).getPropertyValue('--bs-' + colors[1]).trim();

        // Send our background/foreground text to the api.
        const getResult = (foreground, background) => {
          fetch("https://www.aremycolorsaccessible.com/api/are-they", {
            mode: "cors",
            method: "POST",
            body: JSON.stringify({ colors: [foreground, background] })
          })
          .then((response) => response.json())
          .then((json) => setResult(json));
        };
        getResult(foreground, background);

        const setResult = (json) => {

          // Map our response data to an element.
          const els = {
            contrast: {
              selector: element.querySelector("[data-color-contrast] .placeholder-glow"),
              data: json.contrast,
            },
            smallAA: {
              selector: element.querySelector("[data-color-contrast-small-text-aa] .placeholder-glow"),
              data: json.small,
              pass: ["AAA", "AA"],
            },
            smallAAA: {
              selector: element.querySelector("[data-color-contrast-small-text-aaa] .placeholder-glow"),
              data: json.small,
              pass: ["AAA"],
            },
            largeAA: {
              selector: element.querySelector("[data-color-contrast-large-text-aa] .placeholder-glow"),
              data: json.large,
              pass: ["AAA", "AA"],
            },
            largeAAA: {
              selector: element.querySelector("[data-color-contrast-large-text-aaa] .placeholder-glow"),
              data: json.large,
              pass: ["AAA"],
            },
          };

          // Error state.
          if (typeof json === 'undefined' || json === false) {
            Object.values(els).forEach(function (placeholder) {
              placeholder.selector.innerHTML = '🚘💥️🔥';
            });
            return;
          }
          else {
            // Set contrast, pass & fails.
            Object.entries(els).forEach(entry => {
              const [key, value] = entry;

              switch (key) {
                // Contrast.
                case 'contrast':
                  var returnVal = value.data.split(': ');
                  const returnValTrimmed =  Math.trunc(returnVal[0]*100)/100;
                  returnVal = [returnValTrimmed, returnVal[1]].join(': ');
                  els.contrast.selector.innerHTML = returnVal;
                  break;

                // Default
                default:
                  if (value.pass.includes(value.data))  {
                    value.selector.innerHTML = '✅'
                  }
                  else {
                    value.selector.innerHTML = '❌'
                  }
                  break;
              }
            });
          }
        };
      });
    },
  };
})(Drupal, drupalSettings, once);
