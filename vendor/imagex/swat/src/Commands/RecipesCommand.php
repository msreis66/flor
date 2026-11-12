<?php

namespace Imagex\Swat\Commands;

use Imagex\Swat\SwatTasks;
use Robo\Exception\TaskException;
use Symfony\Component\Yaml\Yaml;

/**
 * Command references for managing recipes
 *
 * @package Imagex\Swat\Commands
 */
class RecipesCommand extends SwatTasks {

  /**
   * Recipe selection, composer installation, and application.
   *
   * @command recipes
   * @description Select and Apply project recipes.
   */
  public function recipes() {
    $availableRecipes = $this->loadSwatRecipes();

    $choices = [];
    foreach ($availableRecipes as $machine_name => $data) {
      $choices[$machine_name] = sprintf(
        '%s - %s: %s',
        ucfirst($data['provider']),
        $data['label'],
        $data['description']
      );
    }
    // Add none option.
    $choices['_none'] = 'Nothing, leave as is.';
    $applyRecipes = $this->askChoice('Choose recipes to apply (comma separated)', $choices, "_none", TRUE);

    // Strip out any _none selections.
    $applyRecipes = array_filter($applyRecipes, function($item) {
      return $item !== '_none';
    });

    $this->requireRecipes($applyRecipes);
    $this->applyRecipes($applyRecipes);
  }

  /**
   * Require recipes using Composer.
   *
   * @param array $recipes
   *   Array of recipe package names (e.g., 'imagex/recipe-base').
   */
  protected function requireRecipes(array $recipes): void {
    $repoRoot = $this->getConfigValue('repo.root');
    $composer = $this->taskComposerRequire()->dir($repoRoot);

    // @todo we may allow bypassing this in the future.
    $composer->dependency('imagex/recipe-standard');

    foreach ($recipes as $recipe) {
      if ($recipeData = $this->loadSwatRecipes($recipe)) {
        $dependency = "{$recipeData['provider']}/{$recipeData['package']}";
        $composer->dependency($dependency);
      }
    }

    $composer->run();
  }

  /**
   * Apply recipes using Drush.
   *
   * @param array $recipes
   *   Array of recipe package names (e.g., 'imagex/recipe-base').
   */
  protected function applyRecipes(array $recipes): void {
    $repoRoot = $this->getConfigValue('repo.root');

    // @todo we may allow bypassing this in the future.
    $this->taskExec("./vendor/bin/drush recipe ../recipes/recipe-standard")
          ->dir($repoRoot)
          ->run();

    foreach ($recipes as $recipe) {
      if ($recipeData = $this->loadSwatRecipes($recipe)) {
        $recipePath = "../recipes/{$recipeData['package']}";

        // Apply the recipe with Drush.
        $this->taskExec("./vendor/bin/drush recipe {$recipePath}")
          ->dir($repoRoot)
          ->run();
      }
    }
  }

  /**
   * Load SWAT's built-in recipes from YAML configuration.
   *
   * @param string|null $recipeName
   *   Optional recipe machine name to return a specific recipe.
   *
   * @return array|null
   *   An associative array of available SWAT recipes, or a specific recipe
   *   array if $recipeName is provided. Returns NULL if recipe not found.
   */
  protected function loadSwatRecipes(?string $recipeName = NULL): ?array {
    static $recipes = NULL;

    if ($recipes === NULL) {
      $swatRoot = $this->getConfigValue('swat.root');
      $recipes = Yaml::parse(file_get_contents("{$swatRoot}/config/recipes.yml"));
    }

    if ($recipeName !== NULL) {
      return $recipes[$recipeName] ?? NULL;
    }

    return $recipes;
  }

}
