<?php

namespace Imagex\Swat;

use Consolidation\AnnotatedCommand\CommandResult;
use Imagex\Swat\Config\ConfigAwareTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Robo\Contract\ConfigAwareInterface;
use Robo\Contract\VerbosityThresholdInterface;
use Robo\Exception\TaskException;
use Robo\Tasks;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Common Tasks used within SWAT.
 *
 * @package Imagex\Swat
 */
class SwatTasks extends Tasks implements ConfigAwareInterface, LoggerAwareInterface {

  use ConfigAwareTrait;
  use LoggerAwareTrait;

  /**
   * The depth of command invocations, used by invokeCommands().
   *
   * E.g., this would be 1 if invokeCommands() called a method that itself
   * called invokeCommands().
   *
   * @var int
   */
  protected $invokeDepth = 0;

  /**
   * Invokes an array of Symfony commands.
   *
   * @param array $commands
   *   An array of Symfony commands to invoke, e.g., 'tests:behat:run'.
   * @throws \Exception
   */
  protected function invokeCommands(array $commands) {
    foreach ($commands as $key => $value) {
      if (is_numeric($key)) {
        $command = $value;
        $args = [];
      }
      else {
        $command = $key;
        $args = $value;
      }
      $this->invokeCommand($command, $args);
    }
  }

  /**
   * Invokes a single Symfony command.
   *
   * @param string $command_name
   *   The name of the command, e.g., 'tests:behat:run'.
   * @param array $args
   *   An array of arguments to pass to the command.
   *
   * @throws \Exception
   * @throws \Throwable
   */
  protected function invokeCommand($command_name, array $args = []) {
    $this->invokeDepth++;

    /** @var \Imagex\Swat\Robo\Application $application */
    $application = $this->getContainer()->get('application');
    $command = $application->find($command_name);

    $input = new ArrayInput($args);
    $input->setInteractive($this->input()->isInteractive());
    $prefix = str_repeat(">", $this->invokeDepth);
    $this->output->writeln("<comment>$prefix $command_name</comment>");
    $exit_code = $application->runCommand($command, $input, $this->output());
    $this->invokeDepth--;

    // The application will catch any exceptions thrown in the executed
    // command. We must check the exit code and throw our own exception. This
    // obviates the need to check the exit code of every invoked command.
    if ($exit_code) {
      throw new \Exception("Command `$command_name {$input->__toString()}` exited with code $exit_code.");
    }
  }

  /**
   * Asks the user a multiple-choice question.
   *
   * @param string $question
   *   The question text.
   * @param array $options
   *   An array of available options.
   * @param mixed $default
   *   Default.
   * @param bool $multiple
   *   True if this questions allows multiple answers.
   *
   * @return string|array
   *   The chosen option.
   */
  protected function askChoice($question, array $options, $default = NULL, $multiple = FALSE) {
    $question = new ChoiceQuestion($this->formatQuestion($question), $options, $default);
    if ($multiple) {
      $question->setMultiselect(TRUE);
    }
    return $this->doAsk($question);
  }

  /**
   * Format text as a question.
   *
   * @param string $message
   *   The question text.
   *
   * @return string
   *   The formatted question text.
   */
  protected function formatQuestion($message) {
    return "<question> $message</question> ";
  }

  /**
   * Helper to copy files from defaults.
   *
   * @param string $dir
   *   The directory to copy to relative to repo root.
   * @param array $files
   *   The files array to copy.
   */
  protected function copyDefaults($dir, array $files) {
    foreach ($files as $file => $filepath) {
      $to = $dir . '/' . $file;

      // Move the file.
      $this->taskFilesystemStack()
        ->remove($to)
        ->copy($filepath, $to)
        ->run();
    }
  }

  /**
   * Finds hosting files inside defaults.
   *
   * @param string $dir
   *   The directory to scan.
   *
   * @return array|bool
   *   The files or false.
   */
  protected function findHostingFile($dir) {
    $host = $this->getConfigValue('swat.host');
    $search = $this->getConfigValue('swat.root') . '/defaults/' . $dir;

    $this->say("<info>Looking for files in $search</info>");
    $files = preg_grep('/^' . $host . '\..*/', scandir($search));

    $fileset = [];
    foreach ($files as $key => $file) {
      $prefix = $host . '.';
      $name = str_starts_with($file, $prefix) ? substr($file, strlen($prefix)) : $file;
      $fileset[$name] = $search . '/' . $file;
    }

    return $fileset;
  }

  /**
   * Run composer update in appropriate environment.
   * @throws TaskException
   */
  protected function runComposerUpdate() {
    $this->taskExecStack()->exec('composer update')->run();
  }

  /**
   * Writes a deployment_identifier to ${repo.root}/deployment_identifier.
   *
   * @command swat:deployment-identifier
   * @throws \Exception
   */
  public function createDeployId($options = ['id' => InputOption::VALUE_REQUIRED]) {
    if (!$options['id']) {
      $options['id'] = $this->randomString(8);
    }
    $deployment_identifier_file = $this->getConfigValue('repo.root') . '/deployment_identifier';
    $this->say("Generating deployment identifier...");
    $result = $this->taskWriteToFile($deployment_identifier_file)
      ->line($options['id'])
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();

    if (!$result->wasSuccessful()) {
      throw new \Exception("Unable to write deployment identifier.");
    }
  }

  /**
   * Generates a random string of ASCII characters of codes 32 to 126.
   *
   * The generated string includes alpha-numeric characters and common
   * miscellaneous characters.
   *
   * @param int $length
   *   Length of random string to generate.
   *
   * @return string
   *   Randomly generated string.
   *
   * @see \Drupal\Component\Utility\Random::name()
   */
  protected function randomString($length = 8) {
    $characters_array = [];
    $str = '';
    for ($i = 0; $i < $length; $i++) {
      if ($characters_array) {
        $position = mt_rand(0, count($characters_array) - 1);
        $str .= $characters_array[$position];
      }
      else {
        $str .= chr(mt_rand(32, 126));
      }
    }
    return $str;
  }

  /**
   * Invokes command hooks defined in configuration.
   *
   * @param array $hooks
   *   An array of hook names to invoke.
   *
   * @return int
   *   The status code.
   *
   * @throws \Exception
   */
  protected function invokeHooks(array $hooks) {
    foreach ($hooks as $hook) {
      if ($this->getConfig()->has("command-hooks.$hook.command")
        && $this->getConfigValue("command-hooks.$hook.command")) {
        $this->say("Executing $hook target hook...");
        $result = $this->taskExecStack()
          ->exec($this->getConfigValue("command-hooks.$hook.command"))
          ->dir($this->getConfigValue("command-hooks.$hook.dir"))
          ->interactive($this->input()->isInteractive())
          ->printOutput(TRUE)
          ->printMetadata(TRUE)
          ->stopOnFail()
          ->run();

        if (!$result->wasSuccessful()) {
          throw new \Exception("Executing target-hook $hook failed.");
        }
      }
      else {
        $this->logger->info("Skipped $hook target hook. No hook is defined.");
      }
    }
    return 0;
  }

}
