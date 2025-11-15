<?php
/**
 * PHPUnit test bootstrap file
 */

// Require Composer autoloader first
require_once __DIR__ . '/../vendor/autoload.php';

// Define WordPress constants before initializing Brain Monkey
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
}

if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', '/tmp/wp-content/plugins');
}

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}

if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', true);
}

if (!defined('KGU_VERSION')) {
    define('KGU_VERSION', '1.3.0-dev');
}

// Initialize Brain Monkey for WordPress function mocking
\Brain\Monkey\setUp();

// Set up a shutdown function to clean up Brain Monkey
register_shutdown_function(function () {
    \Brain\Monkey\tearDown();
});
