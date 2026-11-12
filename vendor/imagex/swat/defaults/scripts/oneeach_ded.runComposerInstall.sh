#!/usr/bin/env bash

export COMPOSER_HOME=/home/ec2-user

# Fetch the current deployment directory
CURRENT_DEPLOYMENT_DIRECTORY="$(dirname "${BASH_SOURCE[0]}")"

# Move to application directory
cd "$CURRENT_DEPLOYMENT_DIRECTORY"
cd ../../

# Install dependencies using composer
composer self-update --no-interaction && composer install --no-interaction --optimize-autoloader