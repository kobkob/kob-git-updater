<?php
declare(strict_types=1);

namespace KobGitUpdater\Core;

use KobGitUpdater\Core\Interfaces\PluginInterface;
use KobGitUpdater\Admin\SettingsPage;
use KobGitUpdater\GitHub\GitHubApiClient;
use KobGitUpdater\Repository\RepositoryManager;
use KobGitUpdater\Utils\Logger;

/**
 * Main plugin class - acts as a bootstrap and service coordinator
 */
class Plugin implements PluginInterface
{
    private const OPTION_KEY = 'giu_options';
    private const VERSION = '1.3.0';

    private Container $container;
    private ?array $options = null;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Initialize the plugin
     */
    public function init(): void
    {
        // Register all services
        $this->registerServices();
        
        // Initialize WordPress hooks
        $this->initWordPressHooks();
        
        // Initialize admin interface
        if (is_admin()) {
            $this->initAdmin();
        }
    }

    /**
     * Get plugin version
     */
    public function getVersion(): string
    {
        return self::VERSION;
    }

    /**
     * Get plugin options
     */
    public function getOptions(): array
    {
        if ($this->options === null) {
            $defaults = [
                'token' => '',
                'repos' => [],
            ];
            
            $options = get_option(self::OPTION_KEY, []);
            $this->options = wp_parse_args(is_array($options) ? $options : [], $defaults);
        }
        
        return $this->options;
    }

    /**
     * Update plugin options
     */
    public function updateOptions(array $options): bool
    {
        $this->options = $options;
        return update_option(self::OPTION_KEY, $options);
    }

    /**
     * Register all services in the container
     */
    private function registerServices(): void
    {
        // Logger
        $this->container->register('logger', function () {
            return new Logger('kob_git_updater');
        });

        // GitHub API Client
        $this->container->register('github.client', function ($container) {
            // Prefer token from dedicated option if present, fallback to legacy options array
            $saved_token = get_option('giu_github_token', '');
            $token = !empty($saved_token) ? $saved_token : ($this->getOptions()['token'] ?? '');
            return new GitHubApiClient(
                $container->get('logger'),
                $token
            );
        });

        // Repository Manager
        $this->container->register('repository.manager', function ($container) {
            return new RepositoryManager(
                $container->get('github.client'),
                $container->get('logger')
            );
        });

        // Settings Page (Admin)
        if (is_admin()) {
            $this->container->register('admin.settings', function ($container) {
                return new SettingsPage(
                    $container->get('github.client'),
                    $container->get('repository.manager'),
                    $container->get('logger')
                );
            });
        }
    }

    /**
     * Initialize WordPress hooks
     */
    private function initWordPressHooks(): void
    {
        // Plugin lifecycle hooks
        register_activation_hook($this->getPluginFile(), [$this, 'onActivation']);
        register_deactivation_hook($this->getPluginFile(), [$this, 'onDeactivation']);
        register_uninstall_hook($this->getPluginFile(), [self::class, 'onUninstall']);
    }

    /**
     * Initialize admin interface
     */
    private function initAdmin(): void
    {
        /** @var SettingsPage $settingsPage */
        $settingsPage = $this->container->get('admin.settings');
        $settingsPage->init();
    }

    /**
     * Plugin activation hook
     */
    public function onActivation(): void
    {
        // Ensure services are registered for activation hooks
        $this->registerServices();
        
        // Set default options if they don't exist
        if (false === get_option(self::OPTION_KEY)) {
            add_option(self::OPTION_KEY, [
                'token' => '',
                'repos' => [],
            ]);
        }

        // Clear update transients
        delete_transient('update_plugins');
        delete_transient('update_themes');

        /** @var Logger $logger */
        $logger = $this->container->get('logger');
        $logger->info('Plugin activated');

        do_action('giu_plugin_activated');
    }

    /**
     * Plugin deactivation hook
     */
    public function onDeactivation(): void
    {
        // Ensure services are registered for deactivation hooks
        if (!$this->container->has('logger')) {
            $this->registerServices();
        }
        
        // Clear plugin transients
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_giu_%'
            )
        );

        /** @var Logger $logger */
        $logger = $this->container->get('logger');
        $logger->info('Plugin deactivated');

        do_action('giu_plugin_deactivated');
    }

    /**
     * Plugin uninstall hook
     */
    public static function onUninstall(): void
    {
        // Remove all plugin options and transients
        delete_option(self::OPTION_KEY);

        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_giu_%',
                '_transient_timeout_giu_%'
            )
        );

        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[kob_git_updater] Plugin uninstalled');
        }

        do_action('giu_plugin_uninstalled');
    }

    /**
     * Get the main plugin file path
     */
    private function getPluginFile(): string
    {
        return dirname(dirname(__DIR__)) . '/kob-git-updater.php';
    }
}