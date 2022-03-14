#!/bin/bash

sleep 10
drush locale:check || true
drush locale:update || true
drush helfi:locale-import helfi_platform_config || true
drush helfi:locale-import grants_profile || true
drush helfi:locale-import grants_handler || true
drush helfi:locale-import grants_metadata || true
drush helfi:locale-import grants_attachments || true
