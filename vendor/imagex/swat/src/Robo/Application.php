<?php

namespace Imagex\Swat\Robo;

use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Main application class.
 *
 * @package Swat;
 */
class Application extends ConsoleApplication {

  /**
   * This command is identical to its parent, but public rather than protected.
   * @throws \Throwable
   */
  public function runCommand(Command $command, InputInterface $input, OutputInterface $output) {
    return $this->doRunCommand($command, $input, $output);
  }

}
