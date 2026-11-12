<?php

namespace Imagex\Swat\Commands;

use Imagex\Swat\SwatTasks;
use Robo\Exception\TaskException;

/**
 * Commands for validating/linting code.
 *
 * @package Swat
 */
class ValidateCommand extends SwatTasks {

  /**
   * Validate code.
   *
   * @command validate
   */
  public function validate() {
    $commands = [
      'validate:update',
      'frontend:install',
      'validate:code',
    ];
    $this->invokeCommands($commands);
  }

  /**
   * Validate using Grum.
   *
   * @command validate:code
   * @throws TaskException
   */
  public function codeValidate() {
    // @todo this likely needs some love.
    $repo_root = $this->getConfigValue('repo.root');
    return $this->taskExecStack()
      ->stopOnFail()
      ->dir($repo_root)
      ->exec('./vendor/bin/grumphp run')
      ->run();
  }

}
