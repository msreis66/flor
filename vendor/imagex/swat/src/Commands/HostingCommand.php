<?php

namespace Imagex\Swat\Commands;

use Imagex\Swat\SwatTasks;
use Robo\Exception\TaskException;

/**
 * Commands for setting up hosting.
 *
 * @package ImageX\Swat\Commands
 */
class HostingCommand extends SwatTasks {

  /**
   * Sets host-specific settings.
   *
   * @command hosting
   * @description Setup Hosting for SWAT tasks.
   * @throws \Exception
   */
  public function setupHosting() {
    $default = $this->getConfigValue('swat.host');

    $provider_options = [
      'acquia' => 'Acquia',
      'pantheon' => 'Pantheon',
      'platformsh' => 'Platform.sh',
      'oneeach' => 'OneEach (shared)',
      'oneeach_ded' => 'OneEach Dedicated',
      'oneeach_k8s' => 'OneEach Kubernetes',
      'eddie' => 'Uncle Ed\'s Premium Webhosting',
      '_none' => 'None',
    ];

    $config['swat']['host'] = $this->askChoice('Choose a Hosting provider:', $provider_options, $default);
    $this->config->set('swat.host', $config['swat']['host']);

    // @todo Make funnier :p.
    if ($config['swat']['host'] === 'eddie') {
      $this->yell("Better Take A Rain Check On That, Art - He's Got A Lip Fungus They Ain't Identified Yet.");
      die();
    }

    $repo_root = $this->getConfigValue('repo.root');
    $swat_root = $this->getConfigValue('swat.root');

    switch ($config['swat']['host']) {
      case 'pantheon':
        $config['docroot_name'] = 'web';
        $this->config->set('docroot_name', 'web');
        $this->alternateDocroot();

        // @todo this should probably come straight from pantheon.
        $this->taskFilesystemStack()
          ->copy($swat_root . '/config/pantheon.yml', $repo_root . '/pantheon.yml')
          ->run();

        $files = $this->findHostingFile('deploy');
        $this->copyDefaults($repo_root . '/web/private/scripts', $files);

        break;

      case 'platformsh':
        $config['docroot_name'] = 'web';
        $this->config->set('docroot_name', 'web');
        $this->alternateDocroot();

        // @todo this should probably come straight from platformsh.
        $this->taskFilesystemStack()
          ->copy($swat_root . '/config/platform/.platform.app.yaml', $repo_root . '/.platform.app.yaml')
          ->run();

        // Copy over .environment and deploy scripts.
        $this->taskFilesystemStack()
          ->copy($swat_root . '/config/platform/.environment', $repo_root . '/.environment')
          ->run();

        // Copy .platform folder.
        $platform_dir = $repo_root . '/.platform';
        $platform_config = $swat_root . '/config/platform/.platform';
        $copy = $this->taskCopyDir([$platform_config => $platform_dir])
          ->run();
        if (!$copy->wasSuccessful()) {
          throw new \Exception("Unable to create .platform directory.");
        }

        // Copy drush folder.
        $drush_dir = $repo_root . '/drush';
        $platform_drush = $swat_root . '/config/platform/drush';
        if (is_dir($drush_dir)) {
          // Copy only the platformsh_generate_drush_yml.php file.
          $this->taskFilesystemStack()
            ->copy($platform_drush . '/platformsh_generate_drush_yml.php', $repo_root . '/drush/platformsh_generate_drush_yml.php')
            ->run();
        }
        else {
          $copy = $this->taskCopyDir([$platform_drush => $drush_dir])
            ->run();
          if (!$copy->wasSuccessful()) {
            throw new \Exception("Unable to create drush directory.");
          }
        }

        // Add composer dependency.
        $this->taskComposerRequire()
          ->dependency('platformsh/config-reader', '^2.4')
          ->dir($repo_root)
          ->option('--no-update')
          ->run();
        $this->runComposerUpdate();

        break;

      default:
        $this->hostDeploy();
    }

    // Handle any default script files.
    if ($config['swat']['host'] === 'oneeach_ded') {
      // Specific to oneeach dedicated.
      $config['swat']['scripts']['dir'] = '/scripts/aws';
      $config['swat']['theme']['name'] = $this->askDefault("What is the machine name for your theme?", 'none');
      $this->config->set('swat.theme.name', $config['swat']['theme']['name']);
    }
    else {
      $config['swat']['scripts']['dir'] = '/scripts';
    }
    // Set the scripts directory and then write to local.
    $this->config->set('swat.scripts.dir', $config['swat']['scripts']['dir']);
    $this->saveRepoConfig($config);
    $this->hostScripts();

    // Handle any files that belong in the root dir.
    if ($config['swat']['host'] == 'oneeach_ded') {
      $config['swat']['appName'] = $this->askDefault("What is your app name?", 'none');
      $this->config->set('swat.appName', $config['swat']['appName']);
    }
    if ($config['swat']['host'] == 'oneeach_k8s') {
      $config['swat']['command-hooks'] = [
        'frontend-install' => [
          'dir' => '${repo.root}',
          'command' => null,
        ],
        'frontend-build' => [
          'dir' => '${repo.root}',
          'command' => 'composer build',
        ],
      ];
      $this->taskFilesystemStack()
        ->copy(
          $this->getConfigValue('swat.root') . '/defaults/ddev/commands/web/oneeach_k8s.sync',
          $this->getConfigValue('repo.root') . '/.ddev/commands/web/sync',
        )
        ->copy(
          $this->getConfigValue('swat.root') . '/defaults/ddev/web-build/oneeach_k8s.k8s.cron',
          $this->getConfigValue('repo.root') . '/.ddev/web-build/k8s.cron',
        )
        ->touch(
          [
            $this->getConfigValue('repo.root') . 'values-dev.yaml',
            $this->getConfigValue('repo.root') . 'values-stg.yaml',
            $this->getConfigValue('repo.root') . 'values-prd.yaml',
          ]
        )
      ->run();
      $this->say("Be sure to run `ddev add-on get froboy/ddev-tsh` to complete setup.");
    }
    $this->saveRepoConfig($config);
    $this->hostRoot();

    // Set in current config and write to local.
    $this->saveRepoConfig($config);
    $this->hostSettings();

    $this->say("Hosting info updated.");
  }

  /**
   * Handle moving files to the root from the defaults.
   */
  protected function hostRoot() {
    $files = $this->findHostingFile('root');
    $this->copyDefaults($this->getConfigValue('repo.root'), $files);
    $this->hostStringReplacements($files, $this->getConfigValue('repo.root'));
  }

  /**
   * Helper to replace strings with input values.
   *
   * @param array $replacements
   *   Key value pair array for the string replacements.
   * @param array $filepaths
   *   Collection of file paths we want to do our replacements in.
   */
  protected function hostFileStringReplacements(array $replacements, array $filepaths) {
    foreach ($filepaths as $filepath) {
      // New copied filepath.
      foreach ($replacements as $from => $to) {
        $this->taskReplaceInFile($filepath)
          ->from($from)
          ->to($to)
          ->run();
      }
    }
  }

  /**
   * Move deployment scripts.
   */
  protected function hostScripts() {
    $files = $this->findHostingFile('scripts');
    $dir = $this->getConfigValue('repo.root') . $this->getConfigValue('swat.scripts.dir');
    $this->copyDefaults($dir, $files);
    $this->hostStringReplacements($files, $dir);
  }

  /**
   * Move deployment defaults.
   */
  protected function hostDeploy() {
    $files = $this->findHostingFile('deploy');
    $path = $this->getConfigValue('repo.root') . '/deploy';
    $this->copyDefaults($path, $files);
    $this->hostStringReplacements($files, $path);
  }

  /**
   * Helper to add some prompt replacements to the default files.
   *
   * @return array
   *   Contains string replacements for settings files.
   */
  protected function getHostReplacementsArray() {
    return [
      "{APP_NAME}" => $this->getConfigValue('swat.appName'),
      "{THEME_NAME}" => $this->getConfigValue('swat.theme.name'),
    ];
  }

  /**
   * Helper to use the string replacements in the default files.
   *
   * @param array $files
   *   Array containing the filenames and pre-copy filepaths.
   * @param string $path
   *   The base folder for the files we'll be replacing.
   */
  protected function hostStringReplacements(array $files, $path) {
    // Replacements for the moved files.
    $filepaths = [];
    foreach ($files as $file => $filepath) {
      $filepaths[] = $path . '/' . $file;
    }
    $this->hostFileStringReplacements($this->getHostReplacementsArray(), $filepaths);
  }

  /**
   * Move Settings files and include into settings.php.
   */
  protected function hostSettings() {
    $files = $this->findHostingFile('settings');
    $this->copyDefaults($this->getConfigValue('docroot') . '/sites/default/settings', $files);

    // Replacements for the moved files.
    $this->hostStringReplacements($files, $this->getConfigValue('docroot') . '/sites/default/settings');

    if (!empty($files)) {
      $this->say("Add the settings to includes.settings.php");
    }
  }

  /**
   * Helper for alternative docroot locations.
   *
   * @param string $folder
   *   The alternate docroot location.
   * @throws TaskException
   * @throws \Exception
   */
  protected function alternateDocroot($folder = 'web') {
    $repo_root = $this->getConfigValue('repo.root');

    // Save to main.
    $this->saveRepoConfig(['docroot' => '${repo.root}/' . $folder]);

    // Set in current config since we use expanded properties.
    $this->config->set('docroot', $repo_root . '/' . $folder);

    // Rename docroot folder itself.
    if (!is_dir($repo_root . '/' . $folder)) {
      $result = $this->taskFilesystemStack()
        ->rename($repo_root . '/docroot', $repo_root . '/' . $folder)
        ->run();

      if (!$result->wasSuccessful()) {
        throw new \Exception("Unable to rename docroot.");
      }
    }

    // Replace in composer.json.
    $composer = $repo_root . '/composer.json';
    $result = $this->taskReplaceInFile($composer)
      ->from('docroot')
      ->to($folder)
      ->run();

    if (!$result->wasSuccessful()) {
      throw new \Exception("Unable to replace in composer.json");
    }

    // Replace in gitignore.
    $ignore = $repo_root . '/.gitignore';
    $result = $this->taskReplaceInFile($ignore)
      ->from('docroot')
      ->to($folder)
      ->run();

    if (!$result->wasSuccessful()) {
      throw new \Exception("Unable to replace in composer.json");
    }

    // Fix autoloader.
    $this->taskExecStack()
      ->dir($repo_root)
      ->exec('composer update --lock')
      ->run();

    // Delete docroot.
    $this->taskFilesystemStack()
      ->remove($repo_root . '/docroot')
      ->run();

    // Re-run composer install.
    $this->taskExecStack()
      ->dir($repo_root)
      ->exec('composer install')
      ->run();
  }

}
