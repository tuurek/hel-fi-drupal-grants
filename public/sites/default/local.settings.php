<?php
/**
 * @file
 * An example of Drupal 9 development environment configuration file.
 */
$settings['cache']['bins']['render'] = 'cache.backend.null';
$settings['cache']['bins']['page'] = 'cache.backend.null';
$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';

$settings['skip_permissions_hardening'] = TRUE;

$config['system.performance']['css']['preprocess'] = 0;
$config['system.performance']['js']['preprocess'] = 0;
$config['system.logging']['error_level'] = 'some';
