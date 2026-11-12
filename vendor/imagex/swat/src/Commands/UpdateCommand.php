<?php

namespace Imagex\Swat\Commands;

use Imagex\Swat\Swat;
use Imagex\Swat\Update\Updates;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Input\InputOption;

/**
 * Commands for updating existing projects.
 *
 * @package Swat
 */
class UpdateCommand extends Updates {

  /**
   * Runs the update process.
   *
   * @params array $options
   *  Set --since to an earlier version (6.1.10) to re-run updates.
   *
   * @command update
   * @aliases upgrade
   * @description Update SWAT to the latest version.
   */
  public function update(array $options = ['since' => InputOption::VALUE_REQUIRED]) {
    // @todo validate since values from cli.
    $version = $this->getCurrentSchemaVersion($options['since']);
    if ($updates = $this->getUpdates($version)) {
      $this->say("<comment>The following SWAT updates are outstanding:</comment>");
      $this->printUpdates($updates);
      $confirm = $this->confirm('Would you like to perform the listed updates?', TRUE);
      if ($confirm) {
        try {
          $this->executeUpdates($updates);
          // @todo move to independent method updates so failures can be handled.
          $this->saveRepoConfig(['swat' => ['version' => Swat::VERSION]]);
        }
        catch (\Exception $e) {
          $this->logger->error($e->getMessage());
        }
      }
    }
  }

  /**
   * Get the current SWAT schema version.
   *
   * @param string|bool $version
   *   The SWAT version.
   *
   * @return string
   *   The SWAT schema version.
   */
  protected function getCurrentSchemaVersion($version = FALSE) {
    if (!$version) {
      $version = $this->getConfigValue('swat.version', '6.1.14');
    }
    // Convert to 8-digit integer.
    $parts = explode('.', $version);
    $parts[0] = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
    $parts[1] = str_pad($parts[1], 3, '0', STR_PAD_LEFT);
    $parts[2] = str_pad($parts[2], 3, '0', STR_PAD_LEFT);
    return implode('', $parts);
  }

  /**
   * Gets all applicable updates for a given version delta.
   *
   * @param string $since
   *   The starting version, e.g., 8005000.
   *
   * @return array
   *   An array of applicable update methods, keyed by method name. Each row
   *   contains the metadata from the Update annotation.
   */
  public function getUpdates($since) {
    $updates = [];
    $update_methods = $this->getAllUpdateMethods();
    foreach ($update_methods as $method_name => $metadata) {
      $version = $metadata->version;
      if (($version > $since)) {
        $updates[$method_name] = $metadata;
      }
    }
    return $updates;
  }

  /**
   * Gather an array of all available update methods.
   *
   * This will only return methods using the Update attribute.
   *
   * @see drupal_get_schema_versions()
   */
  public function getAllUpdateMethods() {
    $update_methods = [];
    $methods = get_class_methods($this);
    foreach ($methods as $method_name) {
      if (substr($method_name, 0, 7) !== 'update_') {
        continue;
      }
      $reflectionMethod = new \ReflectionMethod($this, $method_name);
      $attributes = $reflectionMethod->getAttributes(\Imagex\Swat\Attributes\Update::class);
      if (!empty($attributes)) {
        $update_methods[$method_name] = $attributes[0]->newInstance();
      }
    }
    return $update_methods;
  }

  /**
   * Prints a human-readable list of update methods to the screen.
   *
   * @param \Imagex\Swat\Attributes\Update[] $updates
   *   List of updates.
   */
  public function printUpdates(array $updates) {
    foreach ($updates as $method_name => $update) {
      $status = $update->required ? 'REQUIRED' : 'OPTIONAL';
      $this->output->writeln("<comment>($status)</comment> - $method_name: {$update->description}");
    }
    $this->output->writeln('');
  }

  /**
   * Executes an array of updates.
   *
   * @param \Imagex\Swat\Attributes\Update[] $updates
   *   List of updates.
   */
  public function executeUpdates(array $updates) {
    $this->output->getFormatter()->setStyle('ice', new OutputFormatterStyle('white', 'blue'));
    $formater = new FormatterHelper();

    /** @var Updates $updates_object */
    foreach ($updates as $method_name => $update) {
      if (!$update->required) {
        $this->say("<comment>The following update is optional and should be reviewed carefully, please confirm:</comment>");
        if (!$this->confirm("-> $method_name: {$update->description}", TRUE)) {
          continue;
        }
      }
      $fomattedBlock = $formater->formatBlock("Executing Update -> $method_name: {$update->description}", 'ice', TRUE);
      $this->output->writeln($fomattedBlock);
      $this->$method_name();
      $this->say("<info>Update complete.</info>");
    }
  }

  /**
   * Validate pending SWAT updates.
   *
   * @command validate:update
   *
   * @throws \Exception
   */
  public function updateValidate() {
    $version = $this->getCurrentSchemaVersion();
    $updates = $this->getUpdates($version);
    if (empty($updates)) {
      return;
    }

    foreach ($updates as $method_name => $update) {
      if ($update->required) {
        throw new \Exception("Required update found: $method_name. Run swat update to apply.");
      }
      else {
        $this->logger->warning("Optional update found: $method_name. Run swat update to apply.");
      }
    }
  }

}
