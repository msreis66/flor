<?php

namespace Drupal\${name}\Hook;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Theme hook suggestions implementations.
 */
final class Suggestions implements ContainerInjectionInterface {

  /**
   * Constructs the theme suggestions hooks.
   */
  public function __construct(
    protected readonly RouteMatchInterface $routeMatch,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_route_match'),
    );
  }

  /**
   * Implements hook_theme_suggestions_menu_alter().
   */
  #[Hook('theme_suggestions_menu_alter')]
  public function menu(array &$suggestions, array $variables): void {
    if (isset($variables['attributes']['data-block']['region'])) {
      $region = $variables['attributes']['data-block']['region'];
      $suggestions[] = $variables['theme_hook_original'] . '__' . $region;
      $suggestions[] = 'menu__' . $region;
    }
  }

  /**
   * Implements hook_theme_suggestions_page_alter().
   */
  #[Hook('theme_suggestions_page_alter')]
  public function page(array &$suggestions, array $variables): void {
    // Add node bundle type suggestions (view/edit pages).
    if (($node = $this->routeMatch->getParameter('node')) ||
      ($node = $this->routeMatch->getParameter('node_preview'))) {
      array_splice($suggestions, 1, 0, 'page__bundle__' . $node->getType());
    }

    // Default "Manage Layout" display, discard changes pages.
    if ($type = $this->routeMatch->getParameter('node_type')) {
      if ($type instanceof NodeTypeInterface) {
        $type = $type->id();
      }
      array_splice($suggestions, 1, 0, 'page__bundle__' . $type);
    }
  }

}
