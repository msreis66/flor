<?php

namespace Drupal\bootstrap_ui_kit\TwigExtension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;


class FileGetContents extends AbstractExtension {

  public function getName() {
    return 'bootstrap_ui_kit.file_get_contents';
  }

  public function getFunctions() {
    return [
      new TwigFunction('fileGetContents', $this->fileGetContents(...)),
    ];
  }

  public function fileGetContents(array $files = []) {
    $module_extension_list = \Drupal::service('extension.list.module')->getList();
    $theme_extension_list = \Drupal::service('extension.list.theme')->getList();

    foreach ($files as $file) {
      $content = false;

      // Namespaced themes/modules.
      if (strpos($file, '@') === 0) {
        // Load a file from a module or theme.
        list($extension_name, $file_path) = explode('/', substr($file, 1), 2);

        $modules = array_filter($module_extension_list, function($extension) use ($extension_name) {
          return $extension->getName() === $extension_name;
        });

        $themes = array_filter($theme_extension_list, function($extension) use ($extension_name) {
          return $extension->getName() === $extension_name;
        });

        $extensions = array_merge($modules, $themes);
        if (!empty($extensions)) {
          $path = reset($extensions)->getPath();
          $full_file_path = $path . '/templates/' . $file_path;
          if (file_exists($full_file_path)) {
            $content = file_get_contents($full_file_path);
          }
        }
      } else {
        // Load a regular file.
        if (file_exists($file)) {
          $content = file_get_contents($file);
        }
      }

      if ($content !== false) {
        return $content;
      }
    }

    // Error messaging.
    return sprintf('None of the following files could be found: %s', implode(', ', $files));
  }
}
