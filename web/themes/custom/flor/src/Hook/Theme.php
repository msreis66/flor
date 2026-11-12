<?php

namespace Drupal\flor\Hook;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Theme hook implementations.
 */
final class Theme implements ContainerInjectionInterface {

  /**
   * Constructs the theme hooks.
   */
  public function __construct(
    protected readonly ThemeExtensionList $themeExtensionList,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('extension.list.theme'),
    );
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(array $existing, string $type, string $theme, string $path): array {
    $themePath = $this->themeExtensionList->getPath('flor') . '/templates';

    return [
      'icon' => [
        'variables' => [
          'name' => NULL,
          'classes' => [],
          'extra' => NULL,
        ],
        'path' => $themePath . '/components',
      ],
    ];
  }

}
