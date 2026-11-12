<?php

namespace ImageX\Swat\Config;

use Consolidation\Config\Loader\ConfigProcessor;
use Consolidation\Config\Loader\YamlConfigLoader;
use Robo\Config\Config;

/**
 * Initialize swat configurations.
 *
 * @package ImageX\Swat\Config
 */
class SwatConfigInit {

  /**
   * Config loader.
   */
  public $config;

  /**
   * Yaml Config Loader.
   *
   * @var YamlConfigLoader
   */
  public YamlConfigLoader $loader;

  /**
   * Config processor.
   *
   * @var ConfigProcessor
   */
  public ConfigProcessor $processor;

  /**
   * Constructor.
   *
   * @param string $repo_root
   *   Repo root.
   */
  public function __construct($repo_root) {
    $this->config = new Config();
    $this->config->set('repo.root', $repo_root);
    // @todo possibly detect this.
    $this->config->set('swat.root', $repo_root . '/vendor/imagex/swat');
    $this->config->set('tmp.dir', sys_get_temp_dir());

    $this->loader = new YamlConfigLoader();
    $this->processor = new ConfigProcessor();
  }

  /**
   * Grabs config from defaults then from local.
   *
   * @return \Robo\Config\Config
   *   The config obj.
   */
  public function initializeConfig() {
    $this->processor->add($this->config->export());
    $this->processor->extend($this->loader->load($this->config->get('swat.root') . '/config/swat.yml'));
    $this->processor->extend($this->loader->load($this->config->get('repo.root') . '/swat/swat.yml'));

    $this->config->replace($this->processor->export());

    return $this->config;
  }

}
