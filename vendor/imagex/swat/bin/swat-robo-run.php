<?php

/**
 * @file
 * Execute SWAT commands via Robo.
 */

use Imagex\Swat\Config\SwatConfigInit;
use Imagex\Swat\Swat;
use Robo\Common\TimeKeeper;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

// Start Timer.
$timer = new TimeKeeper();
$timer->start();

// Initialize input and output.
$input = new ArgvInput($_SERVER['argv']);
$output = new ConsoleOutput();

// Write Swat version for debugging.
if ($output->isVerbose()) {
  $output->writeln("<comment>Swat version " . Swat::VERSION . "</comment>");
}

// Initialize configuration.
$config_init = new SwatConfigInit($repo_root);
$config = $config_init->initializeConfig();

// Execute command.
$swat = new Swat($config, $input, $output);
$status_code = (int) $swat->run($input, $output);

// Stop timer.
$timer->stop();
if ($output->isVerbose()) {
  $output->writeln("<comment>" . $timer->formatDuration($timer->elapsed()) . "</comment> total time elapsed.");
}

exit($status_code);
