<?php

namespace KobGitUpdater\Admin;

use KobGitUpdater\Core\Interfaces\GitHubApiClientInterface;
use KobGitUpdater\Repository\RepositoryManager;
use KobGitUpdater\Utils\Logger;

/**
 * Admin Settings Page
 * 
 * Handles the WordPress admin interface for the plugin settings and
 * repository management.
 */
class SettingsPage
{
    /** @var GitHubApiClientInterface */
    private $github_client;

    /** @var RepositoryManager */
    private $repository_manager;

    /** @var Logger */
    private $logger;

    /** @var string */
    private const PAGE_SLUG = 'git-updater';

    /** @var string */
    private const OPTION_GROUP = 'giu_settings';

    /** @var string */
    private const GITHUB_TOKEN_OPTION = 'giu_github_token';

    public function __construct(
        GitHubApiClientInterface $github_client,
        RepositoryManager $repository_manager,
        Logger $logger
    ) {
        $this->github_client = $github_client;
        $this->repository_manager = $repository_manager;
        $this->logger = $logger;
    }

    /**
     * Initialize admin hooks
     */
    public function init(): void
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_add_repository', [$this, 'handle_add_repository']);
        add_action('admin_post_remove_repository', [$this, 'handle_remove_repository']);
        add_action('admin_post_test_github_connection', [$this, 'handle_test_connection']);
        add_action('admin_post_clear_cache', [$this, 'handle_clear_cache']);
        add_action('admin_notices', [$this, 'display_admin_notices']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    /**
     * Add admin menu pages
     */
    public function add_admin_menu(): void
    {
        // Main menu page
        add_menu_page(
            'Kob Git Updater',
            'Kob Git Updater',
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_settings_page'],
            'dashicons-update',
            80
        );

        // Configuration submenu
        add_submenu_page(
            self::PAGE_SLUG,
            'Configuration',
            'Configuration',
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_settings_page']
        );

        // Documentation submenu
        add_submenu_page(
            self::PAGE_SLUG,
            'Documentation',
            'Documentation',
            'manage_options',
            self::PAGE_SLUG . '-docs',
            [$this, 'render_documentation_page']
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings(): void
    {
        register_setting(
            self::OPTION_GROUP,
            self::GITHUB_TOKEN_OPTION,
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            ]
        );

        add_settings_section(
            'giu_github_section',
            'GitHub Configuration',
            [$this, 'render_github_section_description'],
            self::PAGE_SLUG
        );

        add_settings_field(
            'github_token',
            'GitHub Personal Access Token',
            [$this, 'render_github_token_field'],
            self::PAGE_SLUG,
            'giu_github_section'
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts(string $hook): void
    {
        if (strpos($hook, self::PAGE_SLUG) === false) {
            return;
        }

        // Enqueue Tailwind CSS
        wp_enqueue_script(
            'giu-tailwind',
            'https://cdn.tailwindcss.com',
            [],
            '3.3.0',
            false
        );

        // Custom admin script
        wp_enqueue_script(
            'giu-admin',
            plugins_url('assets/js/admin.js', dirname(__DIR__, 2)),
            ['jquery'],
            '1.3.0',
            true
        );

        wp_localize_script('giu-admin', 'giuAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('giu_admin_nonce'),
            'strings' => [
                'confirmRemove' => __('Are you sure you want to remove this repository?', 'kob-git-updater'),
                'testingConnection' => __('Testing connection...', 'kob-git-updater'),
                'connectionSuccess' => __('Connection successful!', 'kob-git-updater'),
                'connectionFailed' => __('Connection failed.', 'kob-git-updater'),
            ]
        ]);
    }

    /**
     * Render the main settings page
     */
    public function render_settings_page(): void
    {
        $github_token = get_option(self::GITHUB_TOKEN_OPTION, '');
        $repositories = $this->repository_manager->get_all();

        ?>
        <div class="wrap" style="--primary-color: #00B5A3; --primary-dark: #008B7A;">
            <script>
                tailwind.config = {
                    theme: {
                        extend: {
                            colors: {
                                'primary': 'var(--primary-color)',
                                'primary-dark': 'var(--primary-dark)',
                            }
                        }
                    }
                }
            </script>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 m-6">
                <div class="flex items-center justify-between p-6 border-b border-gray-200">
                    <div class="flex items-center space-x-4">
                        <img src="/wp-content/plugins/kob-git-updater/assets/img/logo_en.jpg" 
                             alt="Kob Git Updater" class="h-12 w-auto">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 m-0">Kob Git Updater Configuration</h1>
                            <p class="text-gray-600 text-sm mt-1">Manage GitHub repositories for automatic updates</p>
                        </div>
                    </div>
                    <div class="flex space-x-2">
                        <?php submit_button(__('Test Connection', 'kob-git-updater'), 'secondary', 'test_connection', false, [
                            'id' => 'test-connection-btn'
                        ]); ?>
                        <?php submit_button(__('Clear Cache', 'kob-git-updater'), 'secondary', 'clear_cache', false, [
                            'id' => 'clear-cache-btn'
                        ]); ?>
                    </div>
                </div>

                <!-- GitHub Token Configuration -->
                <div class="p-6">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <span class="dashicons dashicons-info text-blue-600"></span>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-blue-800">GitHub Personal Access Token Required</h3>
                                <div class="mt-2 text-sm text-blue-700">
                                    <p>To access private repositories and avoid rate limits, configure your GitHub Personal Access Token.</p>
                                    <a href="https://github.com/settings/personal-access-tokens/new" target="_blank" 
                                       class="text-blue-600 underline">Create new token →</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <form method="post" action="options.php" class="space-y-6">
                        <?php
                        settings_fields(self::OPTION_GROUP);
                        do_settings_sections(self::PAGE_SLUG);
                        ?>
                    </form>
                </div>

                <!-- Repository Management -->
                <div class="border-t border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-lg font-semibold text-gray-900">Repository Management</h2>
                        <button type="button" 
                                class="bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                                onclick="document.getElementById('add-repo-form').classList.toggle('hidden')">
                            Add Repository
                        </button>
                    </div>

                    <!-- Add Repository Form -->
                    <div id="add-repo-form" class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6 hidden">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="space-y-4">
                            <?php wp_nonce_field('add_repository', 'add_repository_nonce'); ?>
                            <input type="hidden" name="action" value="add_repository">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Owner</label>
                                    <input type="text" name="owner" required 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                                           placeholder="e.g., owner">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Repository</label>
                                    <input type="text" name="repo" required 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                                           placeholder="e.g., my-plugin">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                                    <select name="type" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                                        <option value="plugin">Plugin</option>
                                        <option value="theme">Theme</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">WordPress Slug</label>
                                    <input type="text" name="slug" required 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                                           placeholder="e.g., my-plugin">
                                </div>
                            </div>
                            
                            <div class="flex justify-end space-x-2">
                                <button type="button" 
                                        class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50"
                                        onclick="document.getElementById('add-repo-form').classList.add('hidden')">
                                    Cancel
                                </button>
                                <?php submit_button(__('Add Repository', 'kob-git-updater'), 'primary', 'submit', false, [
                                    'class' => 'bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-md text-sm font-medium'
                                ]); ?>
                            </div>
                        </form>
                    </div>

                    <!-- Repositories List -->
                    <?php if (empty($repositories)): ?>
                        <div class="text-center py-8">
                            <span class="dashicons dashicons-portfolio text-gray-400 text-4xl"></span>
                            <h3 class="text-lg font-medium text-gray-900 mt-2">No repositories configured</h3>
                            <p class="text-gray-600">Add your first repository to get started with automatic updates.</p>
                        </div>
                    <?php else: ?>
                        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Repository</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slug</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Branch</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($repositories as $repo): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <span class="dashicons dashicons-<?php echo $repo->is_plugin() ? 'admin-plugins' : 'admin-appearance'; ?> text-gray-400 mr-2"></span>
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <a href="<?php echo esc_url($repo->get_github_url()); ?>" 
                                                               target="_blank" class="hover:text-primary">
                                                                <?php echo esc_html($repo->get_display_name()); ?>
                                                            </a>
                                                        </div>
                                                        <div class="text-sm text-gray-500">
                                                            Added <?php echo esc_html($repo->get_time_since_added()); ?> ago
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-<?php echo $repo->is_plugin() ? 'blue' : 'purple'; ?>-100 text-<?php echo $repo->is_plugin() ? 'blue' : 'purple'; ?>-800">
                                                    <?php echo esc_html($repo->get_type_label()); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <code class="bg-gray-100 px-2 py-1 rounded text-xs"><?php echo esc_html($repo->get_slug()); ?></code>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo esc_html($repo->get_default_branch()); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($repo->is_private()): ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                        <span class="dashicons dashicons-lock text-xs mr-1"></span>
                                                        Private
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        <span class="dashicons dashicons-unlock text-xs mr-1"></span>
                                                        Public
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="inline-block">
                                                    <?php wp_nonce_field('remove_repository', 'remove_repository_nonce'); ?>
                                                    <input type="hidden" name="action" value="remove_repository">
                                                    <input type="hidden" name="repository_key" value="<?php echo esc_attr($repo->get_key()); ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-900 text-sm"
                                                            onclick="return confirm('<?php echo esc_js(__('Are you sure you want to remove this repository?', 'kob-git-updater')); ?>')">
                                                        Remove
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Hidden forms for AJAX actions -->
        <form id="test-connection-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:none;">
            <?php wp_nonce_field('test_github_connection', 'test_connection_nonce'); ?>
            <input type="hidden" name="action" value="test_github_connection">
        </form>

        <form id="clear-cache-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:none;">
            <?php wp_nonce_field('clear_cache', 'clear_cache_nonce'); ?>
            <input type="hidden" name="action" value="clear_cache">
        </form>

        <script>
            document.getElementById('test-connection-btn').addEventListener('click', function() {
                document.getElementById('test-connection-form').submit();
            });
            document.getElementById('clear-cache-btn').addEventListener('click', function() {
                document.getElementById('clear-cache-form').submit();
            });
        </script>
        <?php
    }

    /**
     * Render GitHub section description
     */
    public function render_github_section_description(): void
    {
        echo '<p>Configure your GitHub Personal Access Token to enable private repository access and higher API rate limits.</p>';
    }

    /**
     * Render GitHub token field
     */
    public function render_github_token_field(): void
    {
        $token = get_option(self::GITHUB_TOKEN_OPTION, '');
        $masked_token = $token ? str_repeat('*', strlen($token) - 6) . substr($token, -6) : '';
        
        ?>
        <div class="space-y-2">
            <input type="password" 
                   name="<?php echo esc_attr(self::GITHUB_TOKEN_OPTION); ?>" 
                   value="<?php echo esc_attr($token); ?>"
                   class="regular-text code"
                   placeholder="ghp_xxxxxxxxxxxxxxxxxxxx" />
            <?php if ($token): ?>
                <p class="description">
                    Current token: <code><?php echo esc_html($masked_token); ?></code>
                    <br><em>Leave empty to keep current token unchanged.</em>
                </p>
            <?php endif; ?>
            <p class="description">
                <a href="https://github.com/settings/personal-access-tokens/new" target="_blank">
                    Generate a new Personal Access Token
                </a> with <strong>Contents: Read</strong> permissions.
            </p>
        </div>
        <?php
        
        if ($token) {
            submit_button(__('Update Token', 'kob-git-updater'), 'secondary', 'submit', false, [
                'style' => 'margin-top: 10px;'
            ]);
        } else {
            submit_button(__('Save Token', 'kob-git-updater'), 'primary', 'submit', false, [
                'style' => 'margin-top: 10px;'
            ]);
        }
    }

    /**
     * Handle add repository form submission
     */
    public function handle_add_repository(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('add_repository', 'add_repository_nonce');

        $owner = sanitize_text_field($_POST['owner'] ?? '');
        $repo = sanitize_text_field($_POST['repo'] ?? '');
        $type = sanitize_text_field($_POST['type'] ?? '');
        $slug = sanitize_text_field($_POST['slug'] ?? '');

        if (empty($owner) || empty($repo) || empty($type) || empty($slug)) {
            $this->add_admin_notice('error', 'All fields are required.');
            wp_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG));
            exit;
        }

        // Ensure GitHub client has the latest token before verification
        $token = get_option(self::GITHUB_TOKEN_OPTION, '');
        $this->github_client->set_token($token);

        $success = $this->repository_manager->add($owner, $repo, $type, $slug);

        if ($success) {
            $this->add_admin_notice('success', "Repository {$owner}/{$repo} added successfully.");
        } else {
            $this->add_admin_notice('error', 'Failed to add repository. Please check the details and try again.');
        }

        wp_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG));
        exit;
    }

    /**
     * Handle remove repository form submission
     */
    public function handle_remove_repository(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('remove_repository', 'remove_repository_nonce');

        $repository_key = sanitize_text_field($_POST['repository_key'] ?? '');

        if (empty($repository_key)) {
            $this->add_admin_notice('error', 'Invalid repository key.');
            wp_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG));
            exit;
        }

        $success = $this->repository_manager->remove($repository_key);

        if ($success) {
            $this->add_admin_notice('success', "Repository {$repository_key} removed successfully.");
        } else {
            $this->add_admin_notice('error', 'Failed to remove repository.');
        }

        wp_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG));
        exit;
    }

    /**
     * Handle test connection form submission
     */
    public function handle_test_connection(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('test_github_connection', 'test_connection_nonce');

        $token = get_option(self::GITHUB_TOKEN_OPTION, '');
        $this->github_client->set_token($token);

        $connection_test = $this->github_client->test_connection();

        if ($connection_test) {
            $this->add_admin_notice('success', 'GitHub connection test successful!');
        } else {
            $this->add_admin_notice('error', 'GitHub connection test failed. Please check your token.');
        }

        wp_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG));
        exit;
    }

    /**
     * Handle clear cache form submission
     */
    public function handle_clear_cache(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('clear_cache', 'clear_cache_nonce');

        $this->repository_manager->clear_caches();
        $this->add_admin_notice('success', 'All caches cleared successfully.');

        wp_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG));
        exit;
    }

    /**
     * Render documentation page
     */
    public function render_documentation_page(): void
    {
        ?>
        <div class="wrap" style="--primary-color: #00B5A3; --primary-dark: #008B7A;">
            <script>
                tailwind.config = {
                    theme: {
                        extend: {
                            colors: {
                                'primary': 'var(--primary-color)',
                                'primary-dark': 'var(--primary-dark)',
                            }
                        }
                    }
                }
            </script>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 m-6">
                <div class="flex items-center justify-between p-6 border-b border-gray-200">
                    <div class="flex items-center space-x-4">
                        <img src="/wp-content/plugins/kob-git-updater/assets/img/logo_en.jpg" 
                             alt="Kob Git Updater" class="h-12 w-auto">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 m-0">Kob Git Updater Documentation</h1>
                            <p class="text-gray-600 text-sm mt-1">Complete guide for managing GitHub-based plugin and theme updates</p>
                        </div>
                    </div>
                    <div class="text-sm text-gray-500">
                        Version <?php echo esc_html(defined('KGU_VERSION') ? KGU_VERSION : '1.3.1'); ?>
                    </div>
                </div>

                <div class="p-6 space-y-8">
                    <!-- Quick Start -->
                    <section>
                        <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                            <span class="dashicons dashicons-admin-tools text-primary mr-2"></span>
                            Quick Start Guide
                        </h2>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                            <h3 class="font-medium text-blue-900 mb-2">1. Setup GitHub Token</h3>
                            <p class="text-blue-700 text-sm mb-2">Create a Personal Access Token at <a href="https://github.com/settings/personal-access-tokens/new" target="_blank" class="underline">GitHub Settings</a></p>
                            <p class="text-blue-700 text-sm">Required permissions: <code>Contents: Read</code> for public repositories, <code>Contents: Read</code> + <code>Metadata: Read</code> for private repositories.</p>
                        </div>
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                            <h3 class="font-medium text-green-900 mb-2">2. Add Repository</h3>
                            <p class="text-green-700 text-sm mb-2">Use the Configuration tab to add your GitHub repositories.</p>
                            <p class="text-green-700 text-sm">Format: <code>owner/repository</code> (e.g., <code>kobkob/my-plugin</code>)</p>
                        </div>
                        <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                            <h3 class="font-medium text-purple-900 mb-2">3. Automatic Updates</h3>
                            <p class="text-purple-700 text-sm">Updates will appear in WordPress Admin → Updates alongside core WordPress updates.</p>
                        </div>
                    </section>

                    <!-- Repository Management -->
                    <section>
                        <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                            <span class="dashicons dashicons-portfolio text-primary mr-2"></span>
                            Repository Management
                        </h2>
                        <div class="grid md:grid-cols-2 gap-6">
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h3 class="font-medium text-gray-900 mb-2">Adding Repositories</h3>
                                <ul class="text-sm text-gray-600 space-y-1">
                                    <li>• <strong>Owner:</strong> GitHub username or organization</li>
                                    <li>• <strong>Repository:</strong> Repository name</li>
                                    <li>• <strong>Type:</strong> Plugin or Theme</li>
                                    <li>• <strong>Slug:</strong> WordPress directory name</li>
                                </ul>
                            </div>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h3 class="font-medium text-gray-900 mb-2">Update Detection</h3>
                                <ul class="text-sm text-gray-600 space-y-1">
                                    <li>• Checks for GitHub releases (tags)</li>
                                    <li>• Falls back to latest commit</li>
                                    <li>• Respects semantic versioning</li>
                                    <li>• Caches API responses (1 hour)</li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    <!-- GitHub Integration -->
                    <section>
                        <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                            <span class="dashicons dashicons-admin-links text-primary mr-2"></span>
                            GitHub Integration
                        </h2>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 border border-gray-200 rounded-lg">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Feature</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Public Repos</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Private Repos</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rate Limit</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr>
                                        <td class="px-4 py-2 text-sm text-gray-900">No Token</td>
                                        <td class="px-4 py-2 text-sm text-green-600">✓ Supported</td>
                                        <td class="px-4 py-2 text-sm text-red-600">✗ Not Available</td>
                                        <td class="px-4 py-2 text-sm text-yellow-600">60/hour</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-2 text-sm text-gray-900">With Token</td>
                                        <td class="px-4 py-2 text-sm text-green-600">✓ Supported</td>
                                        <td class="px-4 py-2 text-sm text-green-600">✓ Supported</td>
                                        <td class="px-4 py-2 text-sm text-green-600">5,000/hour</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- Troubleshooting -->
                    <section>
                        <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                            <span class="dashicons dashicons-sos text-primary mr-2"></span>
                            Troubleshooting
                        </h2>
                        <div class="space-y-4">
                            <details class="border border-gray-200 rounded-lg">
                                <summary class="px-4 py-2 font-medium text-gray-900 cursor-pointer bg-gray-50 hover:bg-gray-100">
                                    Updates not showing up
                                </summary>
                                <div class="px-4 py-3 text-sm text-gray-600 space-y-2">
                                    <p>1. Check that your repository has proper version tags (e.g., v1.0.0)</p>
                                    <p>2. Verify the plugin/theme slug matches your WordPress directory</p>
                                    <p>3. Use the "Test Connection" button to verify GitHub access</p>
                                    <p>4. Clear caches using the "Clear Cache" button</p>
                                </div>
                            </details>
                            <details class="border border-gray-200 rounded-lg">
                                <summary class="px-4 py-2 font-medium text-gray-900 cursor-pointer bg-gray-50 hover:bg-gray-100">
                                    Rate limit exceeded
                                </summary>
                                <div class="px-4 py-3 text-sm text-gray-600 space-y-2">
                                    <p>1. Add a GitHub Personal Access Token to increase limit to 5,000/hour</p>
                                    <p>2. Wait for the rate limit to reset (shown in connection test)</p>
                                    <p>3. Reduce the number of repositories if necessary</p>
                                </div>
                            </details>
                            <details class="border border-gray-200 rounded-lg">
                                <summary class="px-4 py-2 font-medium text-gray-900 cursor-pointer bg-gray-50 hover:bg-gray-100">
                                    Private repository access denied
                                </summary>
                                <div class="px-4 py-3 text-sm text-gray-600 space-y-2">
                                    <p>1. Ensure your Personal Access Token has <code>Contents: Read</code> permission</p>
                                    <p>2. Verify the token belongs to a user with repository access</p>
                                    <p>3. Check if the repository name and owner are correct</p>
                                    <p>4. For organization repos, ensure proper access rights</p>
                                </div>
                            </details>
                        </div>
                    </section>

                    <!-- Developer Information -->
                    <section>
                        <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                            <span class="dashicons dashicons-editor-code text-primary mr-2"></span>
                            Developer Information
                        </h2>
                        <div class="grid md:grid-cols-2 gap-6">
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h3 class="font-medium text-gray-900 mb-2">Plugin Structure</h3>
                                <pre class="text-xs text-gray-600 bg-gray-50 p-2 rounded overflow-x-auto">your-plugin/
├── your-plugin.php     # Main file
├── composer.json       # Dependencies
├── src/               # Source code
└── README.md          # Documentation</pre>
                            </div>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h3 class="font-medium text-gray-900 mb-2">Required Headers</h3>
                                <pre class="text-xs text-gray-600 bg-gray-50 p-2 rounded overflow-x-auto"><?php echo esc_html('<?php
/*
Plugin Name: Your Plugin
Version: 1.0.0
Description: Plugin description
*/'); ?></pre>
                            </div>
                        </div>
                        <div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <h3 class="font-medium text-yellow-900 mb-2">GitHub Release Best Practices</h3>
                            <ul class="text-sm text-yellow-700 space-y-1">
                                <li>• Use semantic versioning (v1.0.0, v1.1.0, v2.0.0)</li>
                                <li>• Include release notes in GitHub release descriptions</li>
                                <li>• Tag releases from stable commits</li>
                                <li>• Update version number in plugin/theme headers</li>
                            </ul>
                        </div>
                    </section>

                    <!-- Support -->
                    <section>
                        <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                            <span class="dashicons dashicons-heart text-primary mr-2"></span>
                            Support & Resources
                        </h2>
                        <div class="grid md:grid-cols-3 gap-4">
                            <a href="https://github.com/kobkob/kob-git-updater" target="_blank" 
                               class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                <span class="dashicons dashicons-external text-primary mr-3"></span>
                                <div>
                                    <div class="font-medium text-gray-900">GitHub Repository</div>
                                    <div class="text-sm text-gray-600">Source code & issues</div>
                                </div>
                            </a>
                            <a href="https://kobkob.org/support" target="_blank" 
                               class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                <span class="dashicons dashicons-admin-users text-primary mr-3"></span>
                                <div>
                                    <div class="font-medium text-gray-900">Support Center</div>
                                    <div class="text-sm text-gray-600">Get help & documentation</div>
                                </div>
                            </a>
                            <a href="https://github.com/settings/personal-access-tokens/new" target="_blank" 
                               class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                <span class="dashicons dashicons-admin-network text-primary mr-3"></span>
                                <div>
                                    <div class="font-medium text-gray-900">Create Token</div>
                                    <div class="text-sm text-gray-600">GitHub Personal Access Token</div>
                                </div>
                            </a>
                        </div>
                    </section>
                </div>

                <!-- Footer -->
                <div class="border-t border-gray-200 px-6 py-4 bg-gray-50">
                    <div class="flex items-center justify-between text-sm text-gray-600">
                        <div>
                            Plugin by <a href="https://kobkob.org" target="_blank" class="text-primary hover:underline">Kobkob LLC</a>
                        </div>
                        <div>
                            Version <?php echo esc_html(defined('KGU_VERSION') ? KGU_VERSION : '1.3.1'); ?> • 
                            <a href="https://github.com/kobkob/kob-git-updater/releases" target="_blank" class="text-primary hover:underline">Release Notes</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Add admin notice to display on next page load
     */
    private function add_admin_notice(string $type, string $message): void
    {
        $notices = get_transient('giu_admin_notices') ?: [];
        $notices[] = ['type' => $type, 'message' => $message];
        set_transient('giu_admin_notices', $notices, 60);
    }

    /**
     * Display admin notices
     */
    public function display_admin_notices(): void
    {
        $notices = get_transient('giu_admin_notices');
        
        if (!$notices) {
            return;
        }

        foreach ($notices as $notice) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($notice['type']),
                esc_html($notice['message'])
            );
        }

        delete_transient('giu_admin_notices');
    }
}