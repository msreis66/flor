<?php

namespace Imagex\Swat\Commands;

use Imagex\Swat\SwatTasks;

/**
 * Commands for setting up frontend build tools.
 *
 * @package Swat
 */
class FrontendCommand extends SwatTasks {

  /**
   * Remove outdated or deprecated frontend files.
   */
  private function frontendCleanup() {
    $files = [
      '.eslintrc',
      '.eslintrc.json',
      '.stylelintrc',
      '.stylelintrc.json',
      '.yarnrc.yml',
      'FRONTEND.md',
      'eslint.config.js',
      'lerna.json',
      'nx.json',
      'package.json',
      'stylelint.config.js',
      'svgo.config.js',
      'tsconfig.json',
      'turbo.json',
      'vite.config.js',
      'webpack.config.js',
    ];

    foreach ($files as $file) {
      $this->taskFilesystemStack()
        ->remove($this->getConfigValue('repo.root') . '/' . $file)
        ->run();
    }
  }

  /**
   * Sets up the frontend tools.
   *
   * @command frontend:setup
   */
  public function frontendSetup() {
    $this->frontendCleanup();

    $this->taskFilesystemStack()
      ->mirror(
        $this->getConfigValue('swat.root') . "/defaults/frontend",
        $this->getConfigValue('repo.root')
      )
      ->run();

    $this->frontendInstall();
  }

  /**
   * Install frontend package dependencies.
   *
   * @command frontend:install
   */
  public function frontendInstall() {
    $this->invokeHooks(['frontend-install']);
  }

  /**
   * Build frontend.
   *
   * @command frontend:build
   * @aliases frontend
   */
  public function frontendBuild() {
    $this->invokeHooks(['frontend-install', 'frontend-build']);
  }

}
