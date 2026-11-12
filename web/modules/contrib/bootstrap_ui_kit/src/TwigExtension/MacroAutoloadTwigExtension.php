<?php

namespace Drupal\bootstrap_ui_kit\TwigExtension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\Environment;

class MacroAutoloadTwigExtension extends AbstractExtension {

  public function getName() {
    return 'bootstrap_ui_kit.macro_autoload';
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new TwigFunction('macro_*', $this->twigRenderMacro(...), [
        'needs_environment' => TRUE,
        'is_safe' => ['html'],
        'is_variadic' => TRUE,
        // 'needs_context' => TRUE,
      ]),
    ];
  }

  // @todo: try to make this work somehow.
  public function twigRenderMacro(Environment $env, string $macro, array $args = []) {
    list($name, $func) = explode('_', $macro);
    $module_handler = \Drupal::service('module_handler')->getModule('bootstrap_ui_kit')->getPath();
    $template = $env->loadTemplate(sprintf($module_handler . '/templates/%s/%s.macro.twig', $name, $name));
    return $template->{$func}(...$args); // this fails although $template has everything.
  }
}
