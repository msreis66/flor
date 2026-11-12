<?php

namespace Imagex\Swat\Commands;

use Imagex\Swat\SwatTasks;

/**
 * Commands for setting up bitbucket pipelines.
 *
 * @package Swat
 */
class BitbucketCommand extends SwatTasks {

  /**
   * Inits Bitbucket pipeline.
   *
   * @command bitbucket:pipelines
   * @description Helper for bitbucket pipelines.
   */
  public function bitbucketPipelines() {
    $files = $this->findHostingFile('bitbucket');
    if (!empty($files)) {
      $this->copyDefaults($this->getConfigValue('repo.root'), $files);
      $this->say("A pre-configured bitbucket-pipelines.yml file was copied to your repository root.");
    }
    else {
      $this->say("<comment>Pipelines not supported for your current hosting.</comment>");
    }
  }

}
