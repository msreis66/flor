<?php

namespace Imagex\Swat;

use Consolidation\AnnotatedCommand\CommandFileDiscovery;
use Imagex\Swat\Robo\Application;
use League\Container\ContainerAwareInterface;
use League\Container\ContainerAwareTrait;
use Robo\Common\ConfigAwareTrait;
use Robo\Config\Config;
use Robo\Robo;
use Robo\Runner as RoboRunner;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Main SWAT initialization.
 *
 * @package Swat
 */
class Swat implements ContainerAwareInterface {

  use ConfigAwareTrait;
  use ContainerAwareTrait;

  /**
   * The robo runner.
   *
   * @var \Robo\Runner
   */
  private $runner;

  const NAME = 'Swat';

  const VERSION = '7.0.3';

  /**
   * Swat constructor.
   *
   * @param \Robo\Config\Config $config
   *   The config object.
   * @param \Symfony\Component\Console\Input\InputInterface|null $input
   *   The input interface.
   * @param \Symfony\Component\Console\Output\OutputInterface|null $output
   *   The output interface.
   */
  public function __construct(Config $config, InputInterface $input = NULL, OutputInterface $output = NULL) {
    // Create application.
    $this->setConfig($config);

    $application = new Application(self::NAME, self::VERSION);
    $application->getDefinition()->addOptions([
      new InputOption(
        '--yes',
        '-y',
        InputOption::VALUE_NONE,
        'Automatically respond "yes" to all confirmation questions.'
      ),
    ]);

    // Create and configure container.
    $container = Robo::createDefaultContainer($input, $output, $application, $config);
    $this->setContainer($container);

    $discovery = new CommandFileDiscovery();
    $discovery->setSearchPattern('*Command.php');
    $commandClasses = $discovery->discover(__DIR__ . '/Commands', 'Imagex\Swat\Commands');

    // Instantiate Robo Runner.
    $this->runner = new RoboRunner([]);
    $this->runner->setContainer($container);
    $this->runner->registerCommandClasses($application, $commandClasses);
  }

  /**
   * Runs a new instance of SWAT.
   *
   * @param \Symfony\Component\Console\Input\InputInterface|null $input
   *   The input interface.
   * @param \Symfony\Component\Console\Output\OutputInterface|null $output
   *   The output interface.
   *
   * @return int
   *   The status code.
   */
  public function run(InputInterface $input, OutputInterface $output) {
    $statusCode = $this->runner->run($input, $output);
    return $statusCode;
  }

}
