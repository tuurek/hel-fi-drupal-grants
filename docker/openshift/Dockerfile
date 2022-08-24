ARG DRUPAL_DOCKER_TAG=8.0
FROM ghcr.io/city-of-helsinki/drupal-docker-base:${DRUPAL_DOCKER_TAG}

COPY / /var/www/html/
WORKDIR /var/www/html
RUN composer install --no-progress --profile --prefer-dist --no-interaction --no-dev --optimize-autoloader

# Copy deploy script
COPY docker/openshift/entrypoints/20-deploy.sh /entrypoints
RUN chmod +x /entrypoints/20-deploy.sh

# Copy cron scripts
RUN mkdir /crons
COPY docker/openshift/crons/ /crons
RUN chmod +x /crons/*

# Copy nginx overrides.
COPY docker/openshift/custom.locations /etc/nginx/conf.d/custom.locations

# Make files writable on testing environments. Small edit to deploy.
# TODO: Remove in production
RUN chgrp -R 0 /var/www/html/public/modules && chmod g+w -R /var/www/html/public/modules
RUN chgrp -R 0 /var/www/html/public/core/modules && chmod g+w -R /var/www/html/public/core/modules

# Helper to run commands
# RUN alias ll='ls -lah'
