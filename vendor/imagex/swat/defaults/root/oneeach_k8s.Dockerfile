FROM ddev/ddev-webserver:v1.24.4 AS build

# Build arguments
ARG PIPELINE_ENV=dev

# Copy files into the container
COPY . /var/www/html

# Environment setup
RUN cd /var/www/html && \
    export PHP_VERSION=$(yq '.php_version // "8.4"' .ddev/config.yaml) && \
    export COMPOSER_VERSION=$(yq '.composer_version // "2.8.8"' .ddev/config.yaml) && \
    export NODE_VERSION=$(yq '.nodejs_version // "22"' .ddev/config.yaml) && \
    export BIN_DIR=$BITBUCKET_CLONE_DIR/vendor/bin && \
    . ~/.profile && \
    git config --global --add safe.directory /opt/atlassian/pipelines/agent/build && \
    update-alternatives --set php /usr/bin/php${PHP_VERSION} && \
    curl -fsSL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash - && \
    apt-get update && \
    apt-get install -y nodejs && \
    ln -sf /usr/bin/node /usr/local/bin/node && \
    ln -sf /usr/bin/npm /usr/local/bin/npm && \
    composer self-update ${COMPOSER_VERSION}

# Run just enough composer to get swat installed
RUN cd /var/www/html/ && composer install --no-interaction --optimize-autoloader --no-scripts
# Build the artifact into /tmp/swat-deploy
RUN cd /var/www/html/ && export PIPELINE_ENV=${PIPELINE_ENV} && vendor/bin/swat artifact:build --no-interaction --no-scripts

RUN rsync -a --no-g --delete --delete-excluded --exclude-from='/var/www/html/vendor/imagex/swat/defaults/deploy/.gitignore' '/tmp/swat-deploy/' '/tmp/deploy/'

FROM public.ecr.aws/e2a8p8m7/oneeach-entimx-base-image-php:8.3

COPY --from=build /tmp/deploy /usr/src/app

# Set up symlinks for shared files
RUN ln -sfT /mnt/shared/default/files /usr/src/app/docroot/sites/default/files;
RUN ln -sfT /mnt/shared/default/files-private /usr/src/app/files-private;

# Add additional mounts here if needed for a multisite.
# RUN ln -sfT /mnt/shared/site2/files /usr/src/app/docroot/sites/site2/files;

# Copy cron jobs into the container
COPY .ddev/web-build/k8s.cron /etc/cron.d/

# Set drupal permissions.
RUN cd /usr/src/app/docroot && \
    ../vendor/imagex/swat/defaults/scripts/autofix-drupal-perms.sh
