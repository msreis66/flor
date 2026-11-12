<?php

/**
 * @file
 * Post-code-update script.
 */

// Run db updates.
echo "Running database updates...\n";
passthru('drush update-db -y');
echo "Database updates complete.\n";

// Import all config changes.
echo "Importing configuration from yml files...\n";
passthru('drush config-import -y');

// Do this twice to get past core view mode issue.
passthru('drush config-import -y');
echo "Import of configuration complete.\n";

// Clear all cache.
echo "Rebuilding cache.\n";
passthru('drush cr');
echo "Rebuilding cache complete.\n";
