FROM ghcr.io/city-of-helsinki/drupal-docker-base:8.0

COPY / /var/www/html/
WORKDIR /var/www/html
RUN composer install --no-progress --profile --prefer-dist --no-interaction --no-dev

# Copy deploy script
COPY docker/openshift/entrypoints/90-deploy.sh /entrypoints
RUN chmod +x /entrypoints/90-deploy.sh

# Make files writable on testing environments. Small edit to deploy.
# TODO: Remove in production
RUN chgrp -R 0 /var/www/html/public/modules && chmod g+w -R /var/www/html/public/modules

# Helper to run commands
RUN alias ll='ls -lah'

