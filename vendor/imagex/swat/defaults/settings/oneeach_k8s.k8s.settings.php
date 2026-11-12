<?php

/**
 * @file
 * OneEach k8s Hosting Settings.
 */

// Current server roots for all apps.
$site_root = '/usr/src/app/';

// Confirm we have an app directory.
if (file_exists($site_root)) {
  // Database settings.

  $databases['default']['default'] = [
    'database' => getenv('DATABASE_NAME'),
    'username' => getenv('DATABASE_USER'),
    'password' => getenv('DATABASE_PASSWORD'),
    'prefix' => '',
    'host' => getenv('DATABASE_HOST'),
    'port' => '3306',
    'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
    'driver' => 'mysql',
  ];

  $settings['trusted_host_patterns'] = array(
    '^www\.exampleymca\.org$',
    '^exampleymca\.org$',
    '^.+\.k8s.oneeach.net$',
  );

  $settings['skip_permissions_hardening'] = FALSE;
  $settings['file_private_path'] = $site_root . 'files-private';
  $settings['file_temp_path'] = '/tmp';

  $settings['reverse_proxy'] = TRUE;
  $settings['reverse_proxy_addresses'] = [$_SERVER['REMOTE_ADDR']];
  $_SERVER['HTTPS'] = 'on';
}
