<?php

namespace Imagex\Swat\Update;

use Imagex\Swat\Attributes\Update;
use Imagex\Swat\Robo\YamlMunge;
use Imagex\Swat\SwatTasks;

/**
 * Defines scripted updates for specific version deltas of SWAT.
 *
 * Versions are defined as 8-digit integers, where the first two digits are the
 * major version, the second three digits are the minor version, and the last
 * three digits are the patch version. For example, version 10.6.11 would be
 * represented as 10006011.
 */
class Updates extends SwatTasks {

  /**
   * Version 6.1.0.
   */
  #[Update(
    version: "06001000",
    description: "6.1.0 - Test of optional updates."
  )]
  protected function update_06001000() {
    $this->say('Option test update.');
  }

  /**
   * Version 6.1.15.
   */
  #[Update(
    version: "06001015",
    required: TRUE,
    description: "6.1.15 - Removing BLT and updating PHPCS standards."
  )]
  protected function update_06001015() {
    // Replace grum prefix.
    if (!$prefix = $this->ask("Enter your project JIRA project prefix (e.g. 'SWAT123'):")) {
      return;
    }
    $repoRoot = $this->getConfigValue('repo.root');
    $this->invokeCommands(['setup:validate']);
    $this->taskReplaceInFile($repoRoot . '/grumphp.yml')
      ->from('${prefix}')
      ->to($prefix)
      ->run();

    // Move config and replace blt.
    if (file_exists($repoRoot . '/blt/blt.yml')) {
      $this->taskFilesystemStack()
        ->stopOnFail()
        ->mkdir($repoRoot . '/swat')
        ->copy($repoRoot . '/blt/blt.yml', $repoRoot . '/swat/swat.yml')
        ->copy($repoRoot . '/blt/deploy-exclude-additions.txt', $repoRoot . '/swat/deploy-exclude-additions.txt')
        ->run();
    }

    // Remove BLT.
    $this->taskComposerRemove()
      ->arg('acquia/blt')
      ->run();

    // Munge in the original swat.yml.
    $swatRoot = $this->getConfigValue('repo.root') . '/swat.yml';
    if (file_exists($swatRoot)) {
      $originalSwat = YamlMunge::parseFile($swatRoot);
      YamlMunge::mergeArrayIntoFile($originalSwat, $repoRoot . '/swat/swat.yml');
      // Remove the original.
      $this->taskFilesystemStack()
        ->remove($swatRoot)
        ->run();
    }
    $this->yell("You're not done yet! Review changes and Update your bitbucket pipelines to use swat instead of blt.");
  }

  /**
   * Version 6.1.22.
   */
  #[Update(
    version: "06001022",
    required: TRUE,
    description: "6.1.22 - Ensure eslint and stylelint tasks are present in grumphp.yml."
  )]
  protected function update_06001022() {
    $repoRoot = $this->getConfigValue('repo.root');
    $swatRoot = $this->getConfigValue('swat.root');
    $grumphpPath = "$repoRoot/grumphp.yml";
    $defaultGrumphpPath = "$swatRoot/defaults/validate/grumphp.yml";

    // Check if grumphp.yml exists
    if (!file_exists($grumphpPath)) {
      $this->yell("grumphp.yml not found at {$grumphpPath}. Skipping update.");
      return;
    }

    // Parse the default grumphp.yml
    if (!file_exists($defaultGrumphpPath)) {
      $this->yell("Default grumphp.yml not found at {$defaultGrumphpPath}. Cannot add missing tasks.");
      return;
    }

    // No webpack = No Grum change.
    if (!file_exists("$repoRoot/webpack.config.js") || !file_exists("$repoRoot/yarn.lock")) {
      $this->yell("You do not use webpack on this project. Skipping update.");
      return;
    }

    // Rename eslint and stylelint config files to match new grumphp.yml.
    $this->taskFilesystemStack()
      ->rename("$repoRoot/.eslintrc.json", "$repoRoot/.eslintrc")
      ->rename("$repoRoot/.stylelintrc.json", "$repoRoot/.stylelintrc")
      ->run();

    if (!file_exists("$repoRoot/.eslintrc") || !file_exists("$repoRoot/.stylelintrc")) {
      $this->yell("You do not use eslint/styllint on this project. Skipping update.");
      return;
    }

    $this->say('Adding missing eslint and stylelint tasks to grumphp.yml.');
    $defaultGrumphp = YamlMunge::parseFile($defaultGrumphpPath);
    YamlMunge::mergeArrayIntoFile($defaultGrumphp, $grumphpPath, $overwrite = FALSE, $inline = 6);
    $this->say('Successfully added missing tasks to grumphp.yml.');
    $this->yell('Please review the changes to grumphp.yml to ensure they meet your project requirements.');
  }

  /**
   * Version 6.2.3.
   */
  #[Update(
    version: "06002003",
    required: TRUE,
    description: "6.2.3 - Ensure ignoring SRC on old themes."
  )]
  protected function update_06002003() {
    $repoRoot = $this->getConfigValue('repo.root');
    $swatDir = $repoRoot . '/swat';
    if (!is_dir($swatDir)) {
      $this->yell("SWAT directory not found at {$swatDir}. Skipping update.");
      return;
    }

    $targetFile = $swatDir . '/deploy-exclude-additions.txt';
    if (!file_exists($targetFile)) {
      $this->say("Creating deploy exclude additions file at {$targetFile}.");
      if (!is_dir($swatDir) && !mkdir($swatDir, 0775, TRUE) && !is_dir($swatDir)) {
        $this->yell("Failed to create SWAT directory at {$swatDir}. Aborting update.");
        return;
      }
      file_put_contents($targetFile, '');
    }

    $pattern = '/*/themes/custom/*/src';
    $lines = @file($targetFile, FILE_IGNORE_NEW_LINES);
    if ($lines !== FALSE) {
      foreach ($lines as $line) {
        if (trim($line) === $pattern) {
          $this->say('No update required: pattern already present in ' . basename($targetFile) . '.');
          return;
        }
      }
    }

    $existingContent = file_get_contents($targetFile);
    $prefixNewline = ($existingContent !== '' && !str_ends_with($existingContent, "\n")) ? "\n" : '';
    if (file_put_contents($targetFile, $prefixNewline . $pattern . "\n", FILE_APPEND) === FALSE) {
      $this->yell('Failed to append pattern to ' . $targetFile . '.');
      return;
    }

    $this->yell("Added '{$pattern}' to " . basename($targetFile) . '. Review and commit this change.');
  }

  /**
   * Version 7.0.0.
   */
  #[Update(
    version: "07000000",
    required: TRUE,
    description: "Notify of fronend change and command hooks."
  )]
  protected function update_07000000() {
    $this->say(
      'SWAT 7.0.0 has updated the frontend build system to use Vite. \n' .
      'If you have not yet converted, update swat.yml with command-hooks to match your setup, similar to:' .
      "\n\n" .
      "\033[35m" .
      "command-hooks:\n" .
      "  frontend-install:\n" .
      "    dir: '\${repo.root}'\n" .
      "    command: 'yarn install --check-resolutions'\n" .
      "  frontend-build:\n" .
      "    dir: '\${repo.root}'\n" .
      "    command: 'yarn build:production --ui=stream'\n" .
      "\033[0m\n\n" .
      'Please see the SWAT documentation for more details.'
    );
  }

}
