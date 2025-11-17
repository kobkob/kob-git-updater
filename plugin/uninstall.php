<?php
/**
 * Uninstall script for Kob Git Updater
 * 
 * This file is executed when the plugin is uninstalled (deleted) from WordPress.
 * It cleans up all plugin data, options, and cached information.
 * 
 * @package KobGitUpdater
 * @author Kobkob LLC
 * @copyright 2024 Kobkob LLC
 * @license GPL-2.0-or-later
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Security check - ensure this is being called by WordPress
if (!current_user_can('delete_plugins')) {
    exit;
}

/**
 * Clean up plugin options and data
 */
function kob_git_updater_uninstall_cleanup() {
    // List of plugin options to remove
    $options_to_remove = [
        'giu_github_token',           // GitHub token (legacy)
        'giu_repositories',           // Repository list (legacy) 
        'giu_options',               // Main options (legacy)
        'kob_git_updater_options',   // Current options
        'kob_git_updater_version',   // Plugin version
        'kob_git_updater_repositories', // Repository data
    ];
    
    // Remove plugin options
    foreach ($options_to_remove as $option) {
        delete_option($option);
        // Also remove from multisite if applicable
        delete_site_option($option);
    }
    
    // Clean up transients (cached data)
    global $wpdb;
    
    // Remove plugin-specific transients
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             OR option_name LIKE %s 
             OR option_name LIKE %s",
            '_transient_kgu_%',
            '_transient_timeout_kgu_%',
            '_site_transient_kgu_%'
        )
    );
    
    // For multisite, also clean network transients
    if (is_multisite()) {
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->sitemeta} 
                 WHERE meta_key LIKE %s 
                 OR meta_key LIKE %s 
                 OR meta_key LIKE %s",
                '_site_transient_kgu_%',
                '_site_transient_timeout_kgu_%',
                '_transient_kgu_%'
            )
        );
    }
    
    // Clean up any scheduled events
    wp_clear_scheduled_hook('kob_git_updater_check_updates');
    wp_clear_scheduled_hook('kgu_check_updates');
    
    // Remove any custom database tables if they were created
    // (Currently this plugin doesn't create custom tables, but keeping for future)
    
    // Clear any cached data
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
    
    // Log the uninstall (only if debug logging is enabled)
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log('Kob Git Updater: Plugin data cleaned up during uninstall');
    }
}

/**
 * Cleanup user meta (for user-specific settings)
 */
function kob_git_updater_cleanup_user_meta() {
    global $wpdb;
    
    // Remove any user meta related to the plugin
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->usermeta} 
             WHERE meta_key LIKE %s",
            'kgu_%'
        )
    );
}

/**
 * Remove plugin files (in case of manual deletion)
 * This is mainly for cleanup if files were left behind
 */
function kob_git_updater_cleanup_files() {
    // Get plugin directory
    $plugin_dir = dirname(__FILE__);
    
    // Don't remove files during normal uninstall as WordPress handles this
    // This function is here for reference and extreme cleanup scenarios
    
    // Log files to remove if debug is enabled
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        $files_to_check = [
            $plugin_dir . '/vendor/',
            $plugin_dir . '/assets/cache/',
            $plugin_dir . '/.htaccess',
        ];
        
        foreach ($files_to_check as $file) {
            if (file_exists($file)) {
                error_log("Kob Git Updater: Found leftover file during uninstall: {$file}");
            }
        }
    }
}

// Execute cleanup
try {
    // Main cleanup
    kob_git_updater_uninstall_cleanup();
    
    // User meta cleanup
    kob_git_updater_cleanup_user_meta();
    
    // File cleanup check
    kob_git_updater_cleanup_files();
    
    // Final cache flush
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
    
    // Success log
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log('Kob Git Updater: Successfully uninstalled and cleaned up all data');
    }
    
} catch (Exception $e) {
    // Log any errors during uninstall
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log('Kob Git Updater: Error during uninstall - ' . $e->getMessage());
    }
    
    // Don't let uninstall errors prevent plugin deletion
    // WordPress should still be able to remove the plugin files
}