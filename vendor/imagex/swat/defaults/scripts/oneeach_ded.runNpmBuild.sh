#!/usr/bin/env bash

THEME={THEME_NAME}

#Fetch the current deployment directory
CURRENT_DEPLOYMENT_DIRECTORY="$(dirname "${BASH_SOURCE[0]}")"

. /home/ec2-user/.nvm/nvm.sh

#Move to application directory
cd "$CURRENT_DEPLOYMENT_DIRECTORY"
cd ../../docroot/themes/custom
sudo chmod 777 ${THEME}
cd ${THEME}
mkdir node_modules
sudo chmod 777 node_modules
npm i && npm run build