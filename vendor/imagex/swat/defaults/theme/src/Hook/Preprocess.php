<?php

namespace Drupal\${name}\Hook;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class Preprocess implements ContainerInjectionInterface {

  /**
   * Constructs the theme related preprocess hooks.
   */
  public function __construct(
    protected readonly ThemeExtensionList $themeExtensionList,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('extension.list.theme')
    );
  }

  /**
   * Implements hook_preprocess_HOOK() for html templates.
   */
  #[Hook('preprocess_html')]
  public function html(array &$variables): void {
    // Load the SVG icons sprite if it exists.
    $icon_file = $this->themeExtensionList->getPath('${name}') . '/components/global/public/assets/icons-sprite.svg';
    if (!file_exists($icon_file)) {
      return;
    }

    $icons = file_get_contents($icon_file);
    $variables['page_bottom']['icons'] = [
      '#type' => 'inline_template',
      '#template' => '<span class="d-none icons-sprite">' . $icons . '</span>',
    ];
  }

  /**
   * Implements hook_preprocess_HOOK() for links__media_library_menu templates.
   */
  #[Hook('preprocess_links__media_library_menu')]
  public function linksMediaLibraryMenu(array &$variables): void {
    foreach ($variables['links'] as &$link) {
      // Add a class to the Media Library menu items.
      $link['attributes']->addClass('media-library-menu__item');
      $link['link']['#options']['attributes']['class'][] = 'media-library-menu__link';
    }
  }

}
