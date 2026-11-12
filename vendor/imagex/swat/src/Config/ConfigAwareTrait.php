<?php

namespace Imagex\Swat\Config;

use Imagex\Swat\Robo\YamlMunge;
use Robo\Common\ConfigAwareTrait as RoboConfigAwareTrait;

/**
 * Adds custom methods to RoboConfigAwareTrait.
 */
trait ConfigAwareTrait {

  use RoboConfigAwareTrait;

  /**
   * Gets a config value for a given key.
   *
   * @param string $key
   *   The config key.
   * @param mixed|null $default
   *   The default value if the key does not exist in config.
   *
   * @return mixed|null
   *   The config value, or else the default value if they key does not exist.
   */
  protected function getConfigValue($key, $default = NULL) {
    if (!$this->getConfig()) {
      return $default;
    }
    return $this->getConfig()->get($key, $default);
  }

  /**
   * Merges config into the local repo file.
   *
   * @param array $config
   *   The config to merge.
   */
  protected function saveRepoConfig(array $config) {
    YamlMunge::mergeArrayIntoFile($config, $this->getConfigValue('repo.root') . '/swat/swat.yml');
  }

  // @todo add hasConfigValue().
}
