import { Tooltip } from '@imagexmedia/bootstrap'

window.Tooltip = Tooltip;

((Drupal, once) => {
  document.addEventListener('drupal.ajax.success', () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => {
      // Hide tooltip after successful Ajax requests.
      Tooltip.getInstance(element)?.hide()
    })
  })

  Drupal.behaviors.${name}Tooltip = {
    attach(context) {
      if (context.parentNode)
        context = context.parentNode as HTMLElement

      once('theme-tooltip', '[data-bs-toggle="tooltip"]', context).forEach((element) => {
        if (!element.hasAttribute('role'))
          element.setAttribute('role', 'tooltip')

        return new Tooltip(element)
      })
    },
  }
})(Drupal, once)
