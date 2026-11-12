<?php

/**
 * @file
 * OneEach Settings.
 */

// Current server roots for all apps.
$oe_root = '/srv/www';
$oe_file_root = '/mnt/shared';

// Only current way to do this since it is defined by whomever creates it.
preg_match('#^' . $oe_root . '/([^/]+)/#', __FILE__, $matches);

// Confirm we have an app directory.
if (isset($matches[1]) && is_dir($oe_root . '/' . $matches[1])) {
  $oe_dir = $matches[1];
  $oe_app_root = $oe_root . '/' . $matches[1];

  // Inform drupal we are behind a reverse proxy load balancer.
  $settings['reverse_proxy'] = TRUE;
  $settings['reverse_proxy_addresses'] = [$_SERVER['REMOTE_ADDR']];

  // Private files.
  $settings['file_private_path'] = $oe_file_root . '/' . $oe_dir . '/files/private';

  // Get env-level config.
  if (file_exists($oe_app_root . '/shared/environment.json')) {
    $oe_config = json_decode(file_get_contents($oe_app_root . '/shared/environment.json'), TRUE);

    // Set env vars.
    if (!empty($oe_config['environment'])) {
      foreach ($oe_config['environment'] as $key => $val) {
        if (!empty($val)) {
          $_ENV[$key] = $val;
        }
      }
    }

    // Make sure we have our env no matter what.
    if (empty($_ENV['OE_ENV'])) {
      $env_found = FALSE;

      // Check based on app name.
      $oe_app_name = $oe_config['app_name'];
      $pos = strrpos($oe_app_name, '_');
      if ($pos !== FALSE) {
        $id = substr($oe_app_name, $pos + 1);

        switch ($id) {
          case 'dev':
          case 'development':
          case 'develop':
            $env_found = TRUE;
            $_ENV['OE_ENV'] = 'dev';
            break;

          case 'stage':
          case 'staging':
          case 'test':
            $env_found = TRUE;
            $_ENV['OE_ENV'] = 'stage';
            break;

          case 'prod':
          case 'production':
          case 'live':
            $env_found = TRUE;
            $_ENV['OE_ENV'] = 'prod';
            break;
        }
      }

      // Last ditch effort, just set dev if we can.
      if (!$env_found && !empty($oe_config['layer'])) {
        $_ENV['OE_ENV'] = $oe_config['layer'] === 'dev' ? 'dev' : 'prod';
      }
    }
  }

  // Database settings.
  if (file_exists($oe_app_root . '/shared/database.json')) {
    $oe_db_config = json_decode(file_get_contents($oe_app_root . '/shared/database.json'), TRUE);
    $databases['default']['default'] = [
      'database' => $oe_db_config['database'],
      'username' => $oe_db_config['username'],
      'password' => $oe_db_config['password'],
      'prefix' => '',
      'host' => $oe_db_config['hostname'],
      'port' => '3306',
      'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
      'driver' => 'mysql',
    ];
  }

}
