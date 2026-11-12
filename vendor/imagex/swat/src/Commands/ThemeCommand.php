<?php

namespace Imagex\Swat\Commands;

use Imagex\Swat\SwatTasks;

/**
 * Commands related to setting up a custom theme.
 *
 * @package Swat
 */
class ThemeCommand extends SwatTasks {

  /**
   * Sets up a custom shell theme.
   *
   * @command theme:setup
   * @description Helper for setting up a basic theme.
   * @throws \Exception
   */
  public function themeSetup() {
    if (!$this->confirm("Do you want to create a new theme?")) {
      return;
    }
    $this->invokeCommands(['frontend:setup', 'theme:create']);
  }

  /**
   * Adds theme support from basic SWAT setup.
   *
   * @command theme:create
   * @description Helper for setting up a basic theme.
   * @throws \Exception
   */
  public function themeCreate() {
    $docroot = $this->getConfigValue('docroot');

    // Check for previous theme.
    if ($theme = $this->loadTheme()) {
      $name = $theme['human_name'];
      if (!$this->confirm("A theme ($name) is already configured, are you sure you want to create another?")) {
        return;
      }
    }

    // Set human readable theme name.
    $answers['human_name'] = $this->askDefault("Theme name (human readable):", 'custom');
    $default_machine_name = $this->convertStringToMachineSafe($answers['human_name']);

    // Set theme machine name.
    $answers['name'] = $this->askDefault("Theme machine name:", $default_machine_name);

    // Set theme description.
    $answers['description'] = 'Custom theme for ' . $answers['human_name'] . '.';

    // For config.
    $answers['dir'] = '${docroot}/themes/custom/' . $answers['name'];
    $full_dir_path = $docroot . '/themes/custom/' . $answers['name'];

    if (is_dir($full_dir_path)) {
      throw new \Exception('Theme already exists!');
    }

    // Also set in active config and local file.
    $answers['status'] = TRUE;
    $this->saveRepoConfig(['project' => ['theme' => $answers]]);

    // Because we're using an expanded property, update to expanded dir.
    $answers['dir'] = $full_dir_path;
    $this->config->set('project.theme', $answers);

    // Copy theme file defaults.
    $dir = $this->getConfigValue('project.theme.dir');
    $this->taskFilesystemStack()
      ->mkdir($dir)
      ->mirror($this->getConfigValue('swat.root') . '/defaults/theme', $dir)
      ->run();
    $this->processThemeRename();

    // Add theme configuration.
    $this->addThemeConfig();

    // Add Bootstrap framework.
    $this->addBootstrap();

    // Replace special theme variables.
    $this->processThemeReplacements();

    // Build theme assets.
    $this->invokeCommands(['frontend:build']);

    // Run post-theme commands.
    $this->postTheme();
  }

  /**
   * Adds config to the theme dir for block placement.
   */
  protected function addThemeConfig() {
    $theme = $this->loadTheme();
    $theme_default = $this->getConfigValue('swat.root') . "/defaults/theme";
    $this->taskFilesystemStack()->mirror(
      $theme_default . '/config',
      $theme['dir'] . '/config'
    )->run();

    // Rename all the files.
    $this->processThemeRename('config/optional');
  }

  /**
   * Adds Bootstrap to theme.
   *
   * @command theme:bootstrap
   * @description Helper for adding Bootstrap to theme.
   */
  public function addBootstrap() {
    if (!$theme = $this->loadTheme()) {
      return;
    }
    if (!$this->confirm('Do you want to include Bootstrap 5?')) {
      return;
    }
    $defaults = $this->getConfigValue('swat.root') . "/defaults/bootstrap";

    // Copy Bootstrap defaults. Overriding previous defaults.
    $this->taskFilesystemStack()->mirror(
      $defaults,
      $theme['dir'],
      NULL,
      ['override' => TRUE]
    )->run();
    $this->processThemeRename();
  }

  /**
   * Replaces special variables with theme information.
   */
  protected function processThemeReplacements() {
    $theme = $this->loadTheme();
    foreach ($this->getDirContents($theme['dir']) as $file) {
      if (!is_dir($file)) {
        $this->taskReplaceInFile($file)
          ->from([
            '${human_name}',
            '${name}',
            '${description}',
          ])
          ->to([
            $theme['human_name'],
            $theme['name'],
            $theme['description'],
          ])
          ->run();
      }
    }
  }

  /**
   * Rename files based on the theme name.
   *
   * @param string|null $folder
   *   Relative folder to the theme dir.
   */
  protected function processThemeRename($folder = NULL) {
    $theme = $this->loadTheme();
    $stack = $this->taskFilesystemStack();
    foreach ($this->getDirContents("{$theme['dir']}/$folder") as $file) {
      if (!is_dir($file)) {
        $filename = str_replace('${name}', $theme['name'], $file);
        if ($filename !== $file) {
          $stack->rename($file, $filename, TRUE);
        }
      }
    }
    $stack->run();
  }

  /**
   * Loads theme config from swat.yml and verifies existence.
   *
   * @return mixed|null
   *   The theme config.
   */
  protected function loadTheme() {
    $theme_config = $this->getConfigValue('project.theme');

    if (!$theme_config['status']) {
      return FALSE;
    }

    $dist_theme_dir = $theme_config['dir'];
    $theme_machine_name = $theme_config['name'];

    if (empty($theme_machine_name) || !is_dir($dist_theme_dir)) {
      return FALSE;
    }

    return $theme_config;
  }

  /**
   * Convert to machine name.
   *
   * @param string $identifier
   *   The identifier.
   * @param array $filter
   *   The filter.
   *
   * @return mixed
   *   The filtered string.
   */
  public static function convertStringToMachineSafe($identifier, array $filter = [
    ' ' => '_',
    '-' => '_',
    '/' => '_',
    '[' => '_',
    ']' => '',
  ]) {
    $identifier = str_replace(array_keys($filter), array_values($filter), $identifier);
    // Valid characters are:
    // - a-z (U+0030 - U+0039)
    // - A-Z (U+0041 - U+005A)
    // - the underscore (U+005F)
    // - 0-9 (U+0061 - U+007A)
    // - ISO 10646 characters U+00A1 and higher
    // We strip out any character not in the above list.
    $identifier = preg_replace(
      '/[^\x{0030}-\x{0039}\x{0041}-\x{005A}\x{005F}\x{0061}-\x{007A}\x{00A1}-\x{FFFF}]/u',
      '',
      $identifier);
    // Identifiers cannot start with a digit, two hyphens, or a hyphen followed
    // by a digit.
    $identifier = preg_replace([
      '/^[0-9]/',
      '/^(-[0-9])|^(--)/',
    ], ['_', '__'], $identifier);
    return strtolower($identifier);
  }

  /**
   * Recursively scan a dir.
   *
   * @param string $dir
   *   The dir.
   * @param array $results
   *   The results of previous.
   *
   * @return array
   *   The files of the dir.
   */
  protected function getDirContents($dir, array &$results = []) {
    $files = scandir($dir);

    foreach ($files as $value) {
      $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
      if (!is_dir($path)) {
        $results[] = $path;
      }
      elseif ($value != "." && $value != "..") {
        $this->getDirContents($path, $results);
        $results[] = $path;
      }
    }

    return $results;
  }

  /**
   * Runs post-theme commands.
   */
  protected function postTheme() {
    $this->say("Running Post-theme commands.");
    $this->invokeCommands([
      'ddev:theme',
    ]);
  }

}
