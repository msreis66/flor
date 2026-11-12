<?php

namespace Imagex\Swat\Commands;

use Acquia\Drupal\RecommendedSettings\Exceptions\SettingsException;
use Acquia\Drupal\RecommendedSettings\Settings;
use Imagex\Swat\Swat;
use Imagex\Swat\SwatTasks;
use Robo\Exception\TaskException;

/**
 * Main Setup commands.
 *
 * @package Swat
 */
class SetupCommand extends SwatTasks {

  /**
   * Main setup command for swat.
   *
   * @command setup:init
   * @description Setup Hosting, DDEV, Bitbucket, Theme, and Settings.
   * @aliases setup
   *
   * @option no-theme Choose to skip the theme options.
   */
  public function setupInit(array $options = ['no-theme' => FALSE]) {
    $repoRoot = $this->getConfigValue('repo.root');
    // Ensure swat dir exists.
    $this->taskFilesystemStack()
      ->mkdir($repoRoot . '/swat')
      ->run();

    // Update project prefix and grumphp.yml.
    if (!$prefix = $this->ask("Enter your project JIRA project prefix (e.g. 'SWAT123'):")) {
      return;
    }
    $this->saveRepoConfig(['project' => ['prefix' => $prefix]]);
    $this->saveRepoConfig(['swat' => ['version' => Swat::VERSION]]);

    // @todo this could be used elswhere.
    $this->taskFilesystemStack()
      ->copy(
        $this->getConfigValue('swat.root') . '/defaults/deploy/deploy-exclude-additions.txt',
        $this->getConfigValue('repo.root') . '/swat/deploy-exclude-additions.txt'
      )
      ->run();

    $commands = [
      'setup:install_type' => [],
      'setup:settings' => [],
      'setup:validate' => ['--force' => TRUE],
      'hosting' => [],
      'bitbucket:pipelines' => [],
      'ddev:setup' => [],
      'theme:setup' => [],
      'tugboat:setup' => [],
    ];

    if ($options['no-theme']) {
      unset($commands['theme:setup']);
    }

    $this->invokeCommands($commands);

    // Replace grum prefix.
    $this->taskReplaceInFile($repoRoot . '/grumphp.yml')
      ->from('${prefix}')
      ->to($prefix)
      ->run();

    // All done.
    $this->say("Done.");
  }

  /**
   * Initialize Settings from acquia/drupal-recommended-settings.
   *
   * @command setup:settings
   * @description Sets up settings files.
   * @aliases settings
   */
  public function initSettings() {
    // Rename the includes.
    $this->taskFilesystemStack()
      ->copy(
        $this->getConfigValue('docroot') . '/sites/default/settings/default.includes.settings.php',
        $this->getConfigValue('docroot') . '/sites/default/settings/includes.settings.php',
        TRUE
      )
      ->run();
    $this->say("<info>Added includes file!</info>");
  }

  /**
   * Initialize validation config/settings.
   *
   * @command setup:validate
   * @description Sets up validation files.
   *
   * @option force Choose to force install.
   */
  public function initValidate(array $options = ['force' => FALSE]) {
    $repoRoot = $this->getConfigValue('repo.root');
    // Iterate files in swat defaults validate and confirm before copy.
    $files = scandir($this->getConfigValue('swat.root') . '/defaults/validate');
    foreach ($files as $file) {
      if ($file == '.' || $file == '..') {
        continue;
      }
      if (!$options['force'] && file_exists("{$repoRoot}/{$file}") && !$this->confirm("$file exists, replace?")) {
        continue;
      }
      $this->taskFilesystemStack()
        ->copy(
          $this->getConfigValue('swat.root') . '/defaults/validate/' . $file,
          $repoRoot . '/' . $file,
          TRUE
        )
        ->run();
    }

    // Run grumphp git:init to register the hooks.
    $this->taskExecStack()
      ->stopOnFail()
      ->dir($repoRoot)
      ->exec('grumphp git:init')
      ->run();
  }

  /**
   * Sets the install type for this project.
   *
   * @command setup:install_type
   * @description Choose the installation type (standard, yusa, minimal).
   */
  public function setupInstallType() {
    $default = $this->getConfigValue('swat.install_type', 'standard');

    $install_options = [
      'standard' => 'ImageX Standard',
      'yusa' => 'YUSA Standard',
    ];

    $choice = $this->askChoice(
      'Choose your installation type:',
      $install_options,
      $default
    );

    $this->config->set('swat.install_type', $choice);
    $this->saveRepoConfig(['swat' => ['install_type' => $choice]]);

    $this->say("Installation type set to: {$choice}");
  }

  /**
   * Installs a new site using SWAT.
   *
   * @command setup:install
   * @description Installs a new site using SWAT.
   * @aliases install
   * @throws TaskException
   */
  public function setupInstall() {
    $install_type = $this->getConfigValue('swat.install_type') ?? 'standard';
    $repo_root = $this->getConfigValue('repo.root');

    $this->say("Running $install_type install…");

    // Create the files-private/default dir if it doesn't exist.
    $this->taskFilesystemStack()
      ->mkdir($repo_root . '/files-private/default')
      ->run();

    switch ($install_type) {
      case 'yusa':
        $sitename = $this->ask('What is the name of the installation?');

        $this->taskExecStack()
          ->stopOnFail()
          ->dir($repo_root)
          ->exec('./vendor/bin/drush si openy openy_configure_profile.preset=complete openy_theme_select.theme=openy_carnation openy_terms_of_use.agree_openy_terms=1 --account-name=admin --site-name=' . $sitename . ' -vy')
          ->run();
        break;

      case 'standard':
        // @todo this might be better served in recipes or as a global.
        $this->taskComposerRequire()
          ->dir($repo_root)
          ->dependency('imagex/recipe-base')
          ->run();

        $this->taskExecStack()
          ->stopOnFail()
          ->dir($repo_root)
          ->exec("./vendor/bin/drush si ../recipes/recipe-base -y")
          ->run();
        break;

    }

    // Install modules after.
    $this->invokeCommands(['recipes']);

    // If we have a theme, install that too.
    $theme_config = $this->getConfigValue('project.theme');
    if ($theme_config['status']) {
      $this->taskExecStack()->stopOnFail()
        ->dir($repo_root)
        ->exec('drush theme-enable ' . $theme_config['name'] . ' -y')
        ->exec('drush config-set system.theme default ' . $theme_config['name'] . ' -y')
        ->run();

      // Uninstall Stark (from base recipe).
      $this->taskExecStack()->stopOnFail()
        ->dir($repo_root)
        ->exec('drush theme:uninstall stark -y')
        ->run();
    }

    // Show message and ULI.
    $this->yell("SWAT setup complete! You can log in with the following one-time login link:");
    $this->taskExecStack()->stopOnFail()
      ->dir($repo_root)
      ->exec('./vendor/bin/drush uli')
      ->run();
  }

}
