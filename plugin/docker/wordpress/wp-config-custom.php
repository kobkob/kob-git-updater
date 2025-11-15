<?php
/**
 * Custom WordPress configuration for Kob Git Updater development
 * This file is loaded by the Docker WordPress container
 */

// Development debugging
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);
define('WP_ENVIRONMENT_TYPE', 'development');

// Disable file editing in admin
define('DISALLOW_FILE_EDIT', true);

// Increase memory limit for development
ini_set('memory_limit', '512M');

// Enable WordPress automatic updates for core
define('WP_AUTO_UPDATE_CORE', true);

// Disable caching during development
define('WP_CACHE', false);

// Custom error logging
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/wordpress/php_errors.log');

// Plugin development constants
define('KGU_DEV_MODE', true);
define('KGU_VERSION', '1.3.0-dev');

// Force plugin activation (helpful for development)
if (!function_exists('activate_kob_git_updater_on_init')) {
    function activate_kob_git_updater_on_init() {
        if (!is_plugin_active('kob-git-updater/kob-git-updater.php')) {
            activate_plugin('kob-git-updater/kob-git-updater.php');
        }
    }
    add_action('init', 'activate_kob_git_updater_on_init');
}

// MailCatcher configuration for email testing
define('SMTP_HOST', 'mailcatcher');
define('SMTP_PORT', 1025);
define('SMTP_SECURE', '');
define('SMTP_AUTH', false);

// Configure WordPress to use MailCatcher
add_action('phpmailer_init', function($phpmailer) {
    $phpmailer->isSMTP();
    $phpmailer->Host = 'mailcatcher';
    $phpmailer->Port = 1025;
    $phpmailer->SMTPAuth = false;
});

// Redis object cache configuration (optional)
define('WP_REDIS_HOST', 'redis');
define('WP_REDIS_PORT', 6379);
define('WP_REDIS_DATABASE', 0);

// Development database configuration
if (getenv('WORDPRESS_DB_HOST')) {
    define('DB_HOST', getenv('WORDPRESS_DB_HOST'));
    define('DB_NAME', getenv('WORDPRESS_DB_NAME'));
    define('DB_USER', getenv('WORDPRESS_DB_USER'));
    define('DB_PASSWORD', getenv('WORDPRESS_DB_PASSWORD'));
}

// Custom uploads directory for testing
define('UPLOADS', 'wp-content/uploads');

// Plugin development helpers
if (defined('KGU_DEV_MODE') && KGU_DEV_MODE) {
    // Log all plugin errors
    add_action('wp_loaded', function() {
        if (class_exists('KobGitUpdater\Utils\Logger')) {
            $logger = new KobGitUpdater\Utils\Logger();
            $logger->set_level('debug');
        }
    });
    
    // Show admin notices for development
    add_action('admin_notices', function() {
        if (current_user_can('manage_options')) {
            echo '<div class="notice notice-info"><p>';
            echo '<strong>Development Mode:</strong> Kob Git Updater is running in development mode.';
            echo '</p></div>';
        }
    });
}

// Development tools integration
if (function_exists('xdebug_info')) {
    // Xdebug is available
    define('KGU_XDEBUG_ENABLED', true);
}

// Custom admin footer for development environment
add_filter('admin_footer_text', function($text) {
    return 'Kob Git Updater Development Environment | ' . $text;
});

// Development menu additions
if (defined('KGU_DEV_MODE') && KGU_DEV_MODE) {
    add_action('admin_menu', function() {
        add_submenu_page(
            'tools.php',
            'Dev Tools',
            'Dev Tools',
            'manage_options',
            'kgu-dev-tools',
            function() {
                echo '<div class="wrap">';
                echo '<h1>Kob Git Updater - Development Tools</h1>';
                echo '<h2>Environment Information</h2>';
                echo '<ul>';
                echo '<li>PHP Version: ' . PHP_VERSION . '</li>';
                echo '<li>WordPress Version: ' . get_bloginfo('version') . '</li>';
                echo '<li>Plugin Version: ' . (defined('KGU_VERSION') ? KGU_VERSION : 'Unknown') . '</li>';
                echo '<li>Xdebug: ' . (function_exists('xdebug_info') ? 'Enabled' : 'Disabled') . '</li>';
                echo '</ul>';
                echo '</div>';
            }
        );
    });
}