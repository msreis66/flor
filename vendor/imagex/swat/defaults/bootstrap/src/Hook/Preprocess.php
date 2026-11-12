<?php

namespace Drupal\${name}\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class Preprocess implements ContainerInjectionInterface {

  /**
   * Constructs the theme related preprocess hooks.
   */
  public function __construct(
    protected readonly ThemeExtensionList $themeExtensionList,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('extension.list.theme'),
      $container->get('entity_type.manager'),
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

  /**
   * Implements hook_preprocess_HOOK() for image templates.
   */
  #[Hook('preprocess_image')]
  public function image(array &$variables): void {
    $variables['attributes']['class'][] = 'img-fluid';
  }

  /**
   * Implements hook_preprocess_HOOK() for table templates.
   */
  #[Hook('preprocess_table')]
  public function table(array &$variables): void {
    $variables['attributes']['class'][] = 'table';
  }

  /**
   * Implements hook_preprocess_HOOK() for views_view_table templates.
   */
  #[Hook('preprocess_views_view_table')]
  public function viewsViewTable(array &$variables): void {
    $variables['attributes']['class'][] = 'table';
    $variables['attributes']['class'][] = 'table-striped';
  }

  /**
   * Implements hook_preprocess_HOOK() for input templates.
   */
  #[Hook('preprocess_input')]
  public function input(array &$variables): void {
    if ($variables['element']['#type'] === 'submit') {
      if ($variables['element']['#value'] === 'Preview') {
        $variables['attributes']['class'][] = 'btn-secondary';
      }
      if (!in_array('btn', $variables['attributes']['class'])) {
        $variables['attributes']['class'][] = 'btn';
      }

      if (empty(array_intersect([
        'btn-primary',
        'btn-primary-lighter',
        'btn-secondary',
        'btn-success',
        'btn-warning',
        'btn-danger',
        'btn-link',
        'btn-info',
      ], $variables['attributes']['class']))) {
        $variables['attributes']['class'][] = 'btn-secondary';
      }
    }
    else {
      $type_api = $variables['element']['#type'];
      $type_html = $variables['attributes']['type'];
      $text_types_html = [
        'text',
        'email',
        'tel',
        'number',
        'search',
        'password',
        'date',
        'time',
        'file',
        'color',
        'datetime-local',
        'url',
        'month',
        'week',
      ];

      if (in_array($type_html, $text_types_html)) {
        $variables['attributes']['class'][] = 'form-element';
        $variables['attributes']['class'][] = Html::getClass('form-element--type-' . $type_html);
        $variables['attributes']['class'][] = Html::getClass('form-element--api-' . $type_api);
      }

      if (in_array($type_html, ['checkbox', 'radio'])) {
        $variables['attributes']['class'][] = 'form-boolean';
        $variables['attributes']['class'][] = Html::getClass('form-boolean--type-' . $type_html);
      }
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for select templates.
   */
  #[Hook('preprocess_select')]
  public function select(array &$variables): void {
    $variables['attributes']['class'][] = 'form-element';
    $variables['attributes']['class'][] = $variables['element']['#multiple'] ?
      'form-element--type-select-multiple' :
      'form-element--type-select';
  }

  /**
   * Implements hook_preprocess_HOOK() for block templates.
   */
  #[Hook('preprocess_block')]
  public function block(array &$variables): void {
    if (isset($variables['elements']['#id'])) {
      $block = $this->entityTypeManager->getStorage('block')->load($variables['elements']['#id']);
      if (!$block) {
        return;
      }

      $region = $block->getRegion();
      if (!empty($region)) {
        $variables['content']['#attributes']['data-block']['region'] = $region;
      }
    }
  }

}
