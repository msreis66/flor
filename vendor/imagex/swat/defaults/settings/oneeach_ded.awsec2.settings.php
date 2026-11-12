<?php

/**
 * @file
 * AWS EC2 Hosting Settings.
 */

// Current server roots for all apps.
$db_config_file = '/home/apache/awsec2.config.ini';
$site_root = '/var/www/html/{APP_NAME}/';

// Confirm we have an app directory.
if (file_exists($site_root)) {
  // Database settings.
  if (file_exists($db_config_file)) {

    $config = parse_ini_file($db_config_file);
    $databases['default']['default'] = [
      'database' => $config['database'],
      'username' => $config['username'],
      'password' => $config['password'],
      'prefix' => '',
      'host' => $config['hostname'],
      'port' => '3306',
      'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
      'driver' => 'mysql',
    ];

    $settings['file_private_path'] = $site_root . 'files-private';
  }
}
