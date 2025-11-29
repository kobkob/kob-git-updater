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
        add_action('admin_post_force_update_repository', [$this, 'handle_force_update_repository']);
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

        // Enqueue Bootstrap CSS for better UI components
        wp_enqueue_style(
            'giu-bootstrap',
            plugins_url('assets/css/bootstrap.min.css', dirname(__DIR__, 2)),
            [],
            '5.3.3'
        );
        
        // Enqueue custom admin CSS (complements Bootstrap)
        wp_enqueue_style(
            'giu-admin',
            plugins_url('assets/css/admin.css', dirname(__DIR__, 2)),
            ['giu-bootstrap'],
            '1.3.2'
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
                'confirmForceUpdate' => __('Force update check for this repository? This will clear the cache and check for new versions.', 'kob-git-updater'),
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
        <div class="container-fluid mt-4">

            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <img src="/wp-content/plugins/kob-git-updater/assets/img/logo_en.jpg" 
                                 alt="Kob Git Updater" class="me-3" style="height: 48px;">
                            <div>
                                <h1 class="h3 mb-0 fw-bold text-dark">Kob Git Updater Configuration</h1>
                                <p class="text-muted small mb-0 mt-1">Manage GitHub repositories for automatic updates</p>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" id="test-connection-btn" class="btn btn-outline-secondary btn-sm">
                                <i class="dashicons dashicons-admin-tools me-1"></i>
                                <?php _e('Test Connection', 'kob-git-updater'); ?>
                            </button>
                            <button type="button" id="clear-cache-btn" class="btn btn-outline-secondary btn-sm">
                                <i class="dashicons dashicons-update me-1"></i>
                                <?php _e('Clear Cache', 'kob-git-updater'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- GitHub Token Configuration -->
                <div class="card-body">
                    <div class="alert alert-info d-flex align-items-start">
                        <i class="dashicons dashicons-info text-primary me-3 mt-1"></i>
                        <div>
                            <h6 class="alert-heading mb-2">GitHub Personal Access Token Required</h6>
                            <p class="mb-2">To access private repositories and avoid rate limits, configure your GitHub Personal Access Token.</p>
                            <a href="https://github.com/settings/personal-access-tokens/new" target="_blank" 
                               class="btn btn-sm btn-outline-primary">
                                <i class="dashicons dashicons-external"></i>
                                Create new token
                            </a>
                        </div>
                    </div>

                    <form method="post" action="options.php">
                        <?php
                        settings_fields(self::OPTION_GROUP);
                        do_settings_sections(self::PAGE_SLUG);
                        ?>
                    </form>
                </div>

            </div>
            
            <!-- Repository Management -->
            <div class="card shadow-sm mt-4">
                <div class="card-header bg-white">
                    <div class="d-flex align-items-center justify-content-between">
                        <h2 class="h5 mb-0 fw-semibold">Repository Management</h2>
                        <button type="button" 
                                class="btn btn-primary btn-sm"
                                onclick="document.getElementById('add-repo-form').classList.toggle('d-none')">
                            <i class="dashicons dashicons-plus-alt me-1"></i>
                            Add Repository
                        </button>
                    </div>
                </div>
                <div class="card-body">

                    <!-- Add Repository Form -->
                    <div id="add-repo-form" class="border rounded-3 p-4 mb-4 bg-light d-none">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('add_repository', 'add_repository_nonce'); ?>
                            <input type="hidden" name="action" value="add_repository">
                            
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label for="owner" class="form-label fw-medium">Owner</label>
                                    <input type="text" id="owner" name="owner" required 
                                           class="form-control form-control-sm"
                                           placeholder="e.g., username">
                                </div>
                                <div class="col-md-3">
                                    <label for="repo" class="form-label fw-medium">Repository</label>
                                    <input type="text" id="repo" name="repo" required 
                                           class="form-control form-control-sm"
                                           placeholder="e.g., my-plugin">
                                </div>
                                <div class="col-md-3">
                                    <label for="type" class="form-label fw-medium">Type</label>
                                    <select id="type" name="type" required class="form-select form-select-sm">
                                        <option value="plugin">Plugin</option>
                                        <option value="theme">Theme</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="slug" class="form-label fw-medium">WordPress Slug</label>
                                    <input type="text" id="slug" name="slug" required 
                                           class="form-control form-control-sm"
                                           placeholder="e.g., my-plugin">
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end gap-2 mt-3">
                                <button type="button" 
                                        class="btn btn-outline-secondary btn-sm"
                                        onclick="document.getElementById('add-repo-form').classList.add('d-none')">
                                    Cancel
                                </button>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="dashicons dashicons-plus-alt me-1"></i>
                                    <?php _e('Add Repository', 'kob-git-updater'); ?>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Repositories List -->
                    <?php if (empty($repositories)): ?>
                        <div class="text-center py-5">
                            <i class="dashicons dashicons-portfolio text-muted" style="font-size: 3rem;"></i>
                            <h5 class="mt-3 fw-medium">No repositories configured</h5>
                            <p class="text-muted">Add your first repository to get started with automatic updates.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Repository</th>
                                        <th scope="col">Type</th>
                                        <th scope="col">Slug</th>
                                        <th scope="col">Branch</th>
                                        <th scope="col">Status</th>
                                        <th scope="col" class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($repositories as $repo): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <i class="dashicons dashicons-<?php echo $repo->is_plugin() ? 'admin-plugins' : 'admin-appearance'; ?> text-muted me-2"></i>
                                                    <div>
                                                        <div class="fw-medium">
                                                            <a href="<?php echo esc_url($repo->get_github_url()); ?>" 
                                                               target="_blank" class="text-decoration-none text-primary">
                                                                <?php echo esc_html($repo->get_display_name()); ?>
                                                            </a>
                                                        </div>
                                                        <small class="text-muted">
                                                            Added <?php echo esc_html($repo->get_time_since_added()); ?> ago
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $repo->is_plugin() ? 'primary' : 'secondary'; ?>">
                                                    <?php echo esc_html($repo->get_type_label()); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <code class="bg-light px-2 py-1 rounded"><?php echo esc_html($repo->get_slug()); ?></code>
                                            </td>
                                            <td>
                                                <span class="text-muted"><?php echo esc_html($repo->get_default_branch()); ?></span>
                                            </td>
                                            <td>
                                                <?php if ($repo->is_private()): ?>
                                                    <span class="badge bg-warning text-dark">
                                                        <i class="dashicons dashicons-lock" style="font-size: 12px;"></i>
                                                        Private
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">
                                                        <i class="dashicons dashicons-unlock" style="font-size: 12px;"></i>
                                                        Public
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-group" role="group">
                                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="d-inline">
                                                        <?php wp_nonce_field('force_update_repository', 'force_update_repository_nonce'); ?>
                                                        <input type="hidden" name="action" value="force_update_repository">
                                                        <input type="hidden" name="repository_key" value="<?php echo esc_attr($repo->get_key()); ?>">
                                                        <button type="submit" class="btn btn-outline-primary btn-sm"
                                                                onclick="return confirm('<?php echo esc_js(__('Force update check for this repository? This will clear the cache and check for new versions.', 'kob-git-updater')); ?>')" 
                                                                title="Force Update">
                                                            <i class="dashicons dashicons-update"></i>
                                                            <span class="d-none d-md-inline ms-1">Force Update</span>
                                                        </button>
                                                    </form>
                                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="d-inline">
                                                        <?php wp_nonce_field('remove_repository', 'remove_repository_nonce'); ?>
                                                        <input type="hidden" name="action" value="remove_repository">
                                                        <input type="hidden" name="repository_key" value="<?php echo esc_attr($repo->get_key()); ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm"
                                                                onclick="return confirm('<?php echo esc_js(__('Are you sure you want to remove this repository?', 'kob-git-updater')); ?>')" 
                                                                title="Remove">
                                                            <i class="dashicons dashicons-trash"></i>
                                                            <span class="d-none d-lg-inline ms-1">Remove</span>
                                                        </button>
                                                    </form>
                                                </div>
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

        $owner = sanitize_text_field(wp_unslash($_POST['owner'] ?? ''));
        $repo = sanitize_text_field(wp_unslash($_POST['repo'] ?? ''));
        $type = sanitize_text_field(wp_unslash($_POST['type'] ?? ''));
        $slug = sanitize_text_field(wp_unslash($_POST['slug'] ?? ''));

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

        $repository_key = sanitize_text_field(wp_unslash($_POST['repository_key'] ?? ''));

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
     * Handle force update repository form submission
     */
    public function handle_force_update_repository(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('force_update_repository', 'force_update_repository_nonce');

        $repository_key = sanitize_text_field(wp_unslash($_POST['repository_key'] ?? ''));

        if (empty($repository_key)) {
            $this->add_admin_notice('error', 'Invalid repository key.');
            wp_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG));
            exit;
        }

        $repository = $this->repository_manager->get($repository_key);
        if (!$repository) {
            $this->add_admin_notice('error', "Repository {$repository_key} not found.");
            wp_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG));
            exit;
        }

        // Clear cache for this specific repository
        $token = get_option(self::GITHUB_TOKEN_OPTION, '');
        $this->github_client->set_token($token);
        $this->github_client->clear_cache($repository->get_owner(), $repository->get_repo());
        
        // Force WordPress to check for updates by deleting the update transients
        if ($repository->is_plugin()) {
            delete_site_transient('update_plugins');
        } else {
            delete_site_transient('update_themes');
        }

        $this->add_admin_notice('success', "Force update triggered for {$repository_key}. Check WordPress Updates page for available updates.");

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
        <div class="kgu-wrap">

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