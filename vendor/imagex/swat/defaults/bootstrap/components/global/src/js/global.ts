declare global {
  interface DrupalSettings {
    /**
     * An example Drupal setting with proper typing.
     */
    example?: boolean
  }
}

((Drupal, once) => {
  Drupal.behaviors.${name}Global = {
    /**
     * An example of a global behavior attached to every page.
     *
     * This should be used sparingly. In most cases, behaviors and other JavaScript should be added to specific
     * components with their own library definitions.
     */
    attach(context, settings) {
      once('example', 'header', context).forEach(() => {
        settings.example = true
      })
    },

    /**
     * An example of a global behavior being unloaded.
     *
     * Prefix unused arguments with an underscore or omit them entirely when possible. For example, if the trigger
     * argument was unused, it could be omitted because there are no succeeding required arguments.
     */
    detach(_context, settings, trigger) {
      if (trigger !== 'unload')
        return

      once.remove('example', 'header')
      delete settings.example
    },
  }
})(Drupal, once)
