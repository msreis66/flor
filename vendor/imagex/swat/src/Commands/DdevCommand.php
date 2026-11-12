<?php

namespace Imagex\Swat\Commands;

use Imagex\Swat\SwatTasks;

/**
 * Defines commands in the "custom" namespace.
 */
class DdevCommand extends SwatTasks {

  /**
   * Setup ddev extras.
   *
   * @command ddev:setup
   * @description Setup ddev extras.
   * @throws \Exception
   */
  public function setup() {
    $repo_root = $this->getConfigValue('repo.root');
    $ddev_dir = $repo_root . '/.ddev';

    // Create .ddev dir with defaults.
    $this->say("<info>Setting up ddev defaults...</info>");
    $ddev_defaults = $this->getConfigValue('swat.root') . '/defaults/ddev';
    $copy = $this->taskCopyDir([$ddev_defaults => $ddev_dir])
      ->run();
    if (!$copy->wasSuccessful()) {
      throw new \Exception("Unable to create .ddev directory.");
    }

    // Docroot.
    $ddevConfig = $repo_root . '/.ddev/config.yaml';
    $docroot_name = $this->getConfigValue('docroot_name', 'docroot');
    $result = $this->taskReplaceInFile($ddevConfig)
      ->from('docroot: docroot')
      ->to("docroot: $docroot_name")
      ->run();
    if (!$result->wasSuccessful()) {
      throw new \Exception("Unable to replace in $ddevConfig");
    }
  }

  /**
   * Updates or removes the ddev theme helpers.
   *
   * @command ddev:theme
   * @description Updates or removes the ddev theme helpers.
   */
  public function themeAddition() {
    $theme_config = $this->getConfigValue('project.theme');
    $theme_command = $this->getConfigValue('repo.root') . '/.ddev/commands/web';

    // Reset.
    if (is_dir($theme_command)) {
      $ddev_defaults = $this->getConfigValue('swat.root') . '/defaults/ddev/commands/web';
      $this->taskCopyDir([$ddev_defaults => $theme_command])
        ->run();
    }

    if (!$theme_config['status']) {
      $this->taskDeleteDir($theme_command)->run();
    }
  }

}
