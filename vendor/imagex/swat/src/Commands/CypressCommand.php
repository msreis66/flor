<?php

namespace Imagex\Swat\Commands;

use Imagex\Swat\SwatTasks;

/**
 * Commands for setting up Cypress testing.
 *
 * @package Swat
 */
class CypressCommand extends SwatTasks {

  /**
   * Copy over cypress configs.
   *
   * @command cypress:setup
   * @description Helper for setting up Cypress testing for projects.
   */
  public function cypressSetup() {
    // Main config.
    $this->taskFilesystemStack()->copy(
      $this->getConfigValue('swat.root') . "/defaults/cypress/cypress.config.js",
      $this->getConfigValue('repo.root') . "/cypress.config.js"
    )->run();

    // Tests.
    $this->taskFilesystemStack()->mirror(
      $this->getConfigValue('swat.root') . "/defaults/cypress/cypress",
      $this->getConfigValue('repo.root') . "/cypress"
    )->run();
  }

}
