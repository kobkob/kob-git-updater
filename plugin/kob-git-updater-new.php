<?php
/**
 * Plugin Name: Kob Git Updater
 * Plugin URI: https://kobkob.org/plugins/kob-git-updater
 * Description: Enables automatic updates for WordPress plugins and themes hosted on GitHub repositories. Supports both public and private repositories with GitHub Personal Access Token authentication.
 * Version: 1.3.1
 * Author: Kobkob LLC
 * Author URI: https://kobkob.org
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kob-git-updater
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.4
 * Requires PHP: 8.1
 * Network: false
 * 
 * @package KobGitUpdater
 * @author Kobkob LLC
 * @copyright 2024 Kobkob LLC
 * @license GPL-2.0-or-later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('KGU_VERSION', '1.3.1');
define('KGU_PLUGIN_FILE', __FILE__);
define('KGU_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KGU_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KGU_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Require Composer autoloader
$autoloader = KGU_PLUGIN_DIR . 'vendor/autoload.php';
if (!file_exists($autoloader)) {
    add_action('admin_notices', function() {
        echo '<div class="error notice"><p>';
        echo '<strong>Kob Git Updater:</strong> ';
        echo 'Composer dependencies not installed. Please run <code>composer install</code> in the plugin directory.';
        echo '</p></div>';
    });
    return;
}
require_once $autoloader;

// Use the Plugin class from the Core namespace
use KobGitUpdater\Core\Plugin;

/**
 * Get the main plugin instance
 * 
 * @return Plugin
 */
function kob_git_updater(): Plugin {
    static $plugin = null;
    
    if ($plugin === null) {
        $plugin = new Plugin();
    }
    
    return $plugin;
}

/**
 * Initialize the plugin
 */
function kob_git_updater_init(): void {
    try {
        // Initialize the plugin
        kob_git_updater()->init();
        
        // Log successful initialization
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Kob Git Updater v' . KGU_VERSION . ' initialized successfully');
        }
    } catch (Exception $e) {
        // Handle initialization errors gracefully
        add_action('admin_notices', function() use ($e) {
            echo '<div class="error notice"><p>';
            echo '<strong>Kob Git Updater:</strong> ';
            echo 'Failed to initialize. Error: ' . esc_html($e->getMessage());
            echo '</p></div>';
        });
        
        // Log the error
        error_log('Kob Git Updater initialization error: ' . $e->getMessage());
    }
}

/**
 * Plugin activation hook
 */
function kob_git_updater_activate(): void {
    try {
        // Ensure plugin is loaded
        kob_git_updater()->activate();
        
        // Clear any caches
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Log activation
        error_log('Kob Git Updater v' . KGU_VERSION . ' activated successfully');
        
    } catch (Exception $e) {
        // Log activation errors
        error_log('Kob Git Updater activation error: ' . $e->getMessage());
        
        // Prevent activation if there's a critical error
        wp_die(
            'Kob Git Updater activation failed: ' . esc_html($e->getMessage()),
            'Plugin Activation Error',
            ['back_link' => true]
        );
    }
}

/**
 * Plugin deactivation hook
 */
function kob_git_updater_deactivate(): void {
    try {
        kob_git_updater()->deactivate();
        
        // Clear any caches
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Log deactivation
        error_log('Kob Git Updater v' . KGU_VERSION . ' deactivated successfully');
        
    } catch (Exception $e) {
        // Log deactivation errors but don't prevent deactivation
        error_log('Kob Git Updater deactivation error: ' . $e->getMessage());
    }
}

/**
 * Plugin uninstall hook (handled by separate uninstall.php file)
 */
function kob_git_updater_uninstall(): void {
    // This function is called when the plugin is deleted
    // The actual uninstall logic is in uninstall.php
}

// Register WordPress hooks
register_activation_hook(__FILE__, 'kob_git_updater_activate');
register_deactivation_hook(__FILE__, 'kob_git_updater_deactivate');

// Initialize the plugin when WordPress is ready
add_action('plugins_loaded', 'kob_git_updater_init', 10);

// Add plugin links
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        esc_url(admin_url('admin.php?page=git-updater')),
        esc_html__('Settings', 'kob-git-updater')
    );
    array_unshift($links, $settings_link);
    
    return $links;
});

// Add plugin meta links
add_filter('plugin_row_meta', function($links, $file) {
    if ($file === plugin_basename(__FILE__)) {
        $links[] = sprintf(
            '<a href="%s" target="_blank">%s</a>',
            'https://github.com/kobkob/kob-git-updater',
            esc_html__('GitHub', 'kob-git-updater')
        );
        $links[] = sprintf(
            '<a href="%s" target="_blank">%s</a>',
            'https://kobkob.org/support',
            esc_html__('Support', 'kob-git-updater')
        );
    }
    return $links;
}, 10, 2);

/**
 * Load plugin textdomain for translations
 */
add_action('plugins_loaded', function() {
    load_plugin_textdomain(
        'kob-git-updater',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
});

// Backwards compatibility for existing installations
// Check if the old monolithic plugin is still active and show migration notice
add_action('admin_notices', function() {
    $old_plugin_file = plugin_dir_path(__FILE__) . 'kob-git-updater.php';
    
    if (file_exists($old_plugin_file)) {
        // Check if this is the modular version by looking for the Plugin class
        if (!class_exists('KobGitUpdater\\Core\\Plugin')) {
            echo '<div class="notice notice-info"><p>';
            echo '<strong>Kob Git Updater:</strong> ';
            echo 'This plugin has been updated to a new modular architecture. ';
            echo 'Your settings and repositories will be preserved automatically.';
            echo '</p></div>';
        }
    }
});

/**
 * Handle database migrations if needed
 */
add_action('admin_init', function() {
    $current_version = get_option('kgu_version', '0.0.0');
    
    if (version_compare($current_version, KGU_VERSION, '<')) {
        // Run migrations
        try {
            kob_git_updater()->migrate($current_version, KGU_VERSION);
            update_option('kgu_version', KGU_VERSION);
        } catch (Exception $e) {
            error_log('Kob Git Updater migration error: ' . $e->getMessage());
        }
    }
});

/**
 * Add CLI commands if WP-CLI is available
 */
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('git-updater', 'KobGitUpdater\\CLI\\Commands');
}