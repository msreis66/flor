<?php

namespace Imagex\Swat\Commands;

use Imagex\Swat\SwatTasks;

/**
 * Commands related to setting up tugboat.
 *
 * @package Swat
 */
class TugboatCommand extends SwatTasks {

  /**
   * Copy over tugboat configs.
   *
   * @command tugboat:setup
   * @description Helper for setting up frontend linter and build tools.
   */
  public function tugboatSetup() {
    $this->taskFilesystemStack()->mirror(
      $this->getConfigValue('swat.root') . "/defaults/tugboat",
      $this->getConfigValue('repo.root') . '/.tugboat'
    )->run();
  }

}
