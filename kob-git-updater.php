<?php
/**
 * Plugin Name: Kob Git Updater
 * Description: Install and auto-update plugins & themes from GitHub releases (or branches). Adds a settings page for a GitHub token and managed repos.
 * Version: 1.2.0
 * Author: Monsenhor Filipo
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class GIU_Plugin {
	const OPTION = 'giu_options';
	const VERSION = '1.2.0';
	private const NOTICE_KEY_PREFIX = 'giu_flash_';
	private const LOG_PREFIX = 'kob_git_updater';
	private ?array $current_install = null;

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'admin_post_giu_add_repo', [ $this, 'handle_add_repo' ] );
		add_action( 'admin_post_giu_remove_repo', [ $this, 'handle_remove_repo' ] );
		add_action( 'admin_post_giu_install_repo', [ $this, 'handle_install_repo' ] );
		add_action( 'admin_notices', [ $this, 'render_flash_notices' ] );

		// Inject update metadata for plugins
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'filter_plugin_updates' ] );
		// Inject update metadata for themes
		add_filter( 'pre_set_site_transient_update_themes', [ $this, 'filter_theme_updates' ] );
	}

	public static function get_options() : array {
		$defaults = [
			'token' => '',
			'repos' => [] // keyed by unique id
		];
		$opts = get_option( self::OPTION, [] );
		return wp_parse_args( is_array( $opts ) ? $opts : [], $defaults );
	}

	public static function update_options( array $opts ) : void {
		update_option( self::OPTION, $opts );
	}

	/**
	 * Log error messages if WP_DEBUG_LOG is enabled
	 */
	private function log_error( string $message, array $context = [] ) : void {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$log_message = sprintf( '[%s] %s', self::LOG_PREFIX, $message );
			if ( ! empty( $context ) ) {
				$log_message .= ' Context: ' . wp_json_encode( $context );
			}
			error_log( $log_message );
		}
	}

	/**
	 * Log info messages if WP_DEBUG_LOG is enabled
	 */
	private function log_info( string $message, array $context = [] ) : void {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$log_message = sprintf( '[%s] %s', self::LOG_PREFIX, $message );
			if ( ! empty( $context ) ) {
				$log_message .= ' Context: ' . wp_json_encode( $context );
			}
			error_log( $log_message );
		}
	}

	/**
	 * Convert WP_Error to user-friendly message
	 */
	private function format_error_message( WP_Error $error ) : string {
		$code = $error->get_error_code();
		$message = $error->get_error_message();
		
		switch ( $code ) {
			case 'giu_http':
				return __( 'Failed to connect to GitHub. Please check your internet connection and try again.', 'kob-git-updater' );
			case 'giu_json':
				return __( 'Received invalid response from GitHub. Please try again later.', 'kob-git-updater' );
			case 'giu_fs':
				return __( 'WordPress filesystem error. Please check file permissions.', 'kob-git-updater' );
			case 'giu_move':
				return __( 'Failed to prepare plugin files. Please try again.', 'kob-git-updater' );
			case 'giu_install':
				return __( 'Installation failed. Please check the plugin/theme files are valid.', 'kob-git-updater' );
			case 'giu_validation':
				return $message; // Already user-friendly
			default:
				return sprintf( __( 'An error occurred: %s', 'kob-git-updater' ), $message );
		}
	}

	/**
	 * Validate repository type
	 */
	private function validate_repo_type( string $type ) : string {
		$type = sanitize_text_field( trim( $type ) );
		return in_array( $type, [ 'plugin', 'theme' ], true ) ? $type : 'plugin';
	}

	/**
	 * Validate owner/repo format
	 */
	private function validate_owner_repo( string $owner_repo ) {
		$owner_repo = sanitize_text_field( trim( $owner_repo ) );
		
		if ( empty( $owner_repo ) ) {
			return new WP_Error( 'giu_validation', __( 'Repository name is required.', 'kob-git-updater' ) );
		}
		
		if ( ! preg_match( '/^[a-zA-Z0-9._-]+\/[a-zA-Z0-9._-]+$/', $owner_repo ) ) {
			return new WP_Error( 'giu_validation', __( 'Repository name must be in format "owner/repository" with only letters, numbers, dots, hyphens, and underscores.', 'kob-git-updater' ) );
		}
		
		$parts = explode( '/', $owner_repo, 2 );
		if ( count( $parts ) !== 2 || empty( $parts[0] ) || empty( $parts[1] ) ) {
			return new WP_Error( 'giu_validation', __( 'Repository name must contain both owner and repository name separated by a slash.', 'kob-git-updater' ) );
		}
		
		return array_map( 'trim', $parts );
	}

	/**
	 * Validate WordPress slug based on type
	 */
	private function validate_slug( string $slug, string $type ) {
		$slug = sanitize_text_field( trim( $slug ) );
		
		if ( empty( $slug ) ) {
			return new WP_Error( 'giu_validation', __( 'WordPress slug is required.', 'kob-git-updater' ) );
		}
		
		if ( $type === 'plugin' ) {
			// Plugin format: folder/file.php
			if ( ! preg_match( '/^[a-zA-Z0-9._-]+\/[a-zA-Z0-9._-]+\.php$/', $slug ) ) {
				return new WP_Error( 'giu_validation', __( 'Plugin slug must be in format "folder/file.php" with valid characters only.', 'kob-git-updater' ) );
			}
		} else {
			// Theme format: directory-name
			if ( ! preg_match( '/^[a-zA-Z0-9._-]+$/', $slug ) ) {
				return new WP_Error( 'giu_validation', __( 'Theme slug must contain only letters, numbers, dots, hyphens, and underscores.', 'kob-git-updater' ) );
			}
		}
		
		return $slug;
	}

	/**
	 * Find existing repository by owner/repo name
	 */
	private function find_existing_repo( string $owner, string $repo, array $repos ) : ?string {
		foreach ( $repos as $id => $r ) {
			if ( $r['owner'] === $owner && $r['repo'] === $repo ) {
				return $id;
			}
		}
		return null;
	}

	/**
	 * Safely save options with validation
	 */
	private function save_options_safely( array $opts ) : bool {
		// Validate options structure
		if ( ! isset( $opts['token'], $opts['repos'] ) || ! is_array( $opts['repos'] ) ) {
			$this->log_error( 'Invalid options structure when saving', [ 'keys' => array_keys( $opts ) ] );
			return false;
		}
		
		// Sanitize token
		$opts['token'] = sanitize_text_field( $opts['token'] );
		
		// Validate each repository
		foreach ( $opts['repos'] as $id => $repo ) {
			if ( ! isset( $repo['type'], $repo['owner'], $repo['repo'], $repo['slug'] ) ) {
				$this->log_error( 'Invalid repository structure', [ 'id' => $id, 'repo' => $repo ] );
				return false;
			}
		}
		
		return update_option( self::OPTION, $opts );
	}

	public function enqueue_admin_assets( $hook ) {
		// Only load on our admin pages
		if ( ! in_array( $hook, [ 'toplevel_page_giu-main', 'git-updater_page_giu-settings', 'git-updater_page_giu-docs' ], true ) ) {
			return;
		}
		
		// Enqueue Tailwind CSS from CDN
		wp_enqueue_script( 
			'tailwind-css', 
			'https://cdn.tailwindcss.com', 
			[], 
			self::VERSION, 
			false 
		);
		
		// Add custom CSS for our color palette
		wp_add_inline_style( 'admin-menu', '
			:root {
				--kob-primary: #00B5A3;
				--kob-secondary: #008B7A;
				--kob-light: #E0F2F1;
				--kob-gray: #999999;
			}
			.kob-primary { background-color: var(--kob-primary) !important; }
			.kob-text-primary { color: var(--kob-primary) !important; }
			.kob-secondary { background-color: var(--kob-secondary) !important; }
			.kob-border-primary { border-color: var(--kob-primary) !important; }
		' );
	}

	public function add_menu() {
		// Add main menu item
		add_menu_page(
			'Kob Git Updater',
			'Git Updater',
			'manage_options',
			'giu-main',
			[ $this, 'render_settings' ],
			'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#00B5A3"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>'),
			30
		);
		
		// Add Configuration submenu
		add_submenu_page(
			'giu-main',
			'Configuration',
			'Configuration',
			'manage_options',
			'giu-settings',
			[ $this, 'render_settings' ]
		);
		
		// Add Documentation submenu
		add_submenu_page(
			'giu-main',
			'Documentation',
			'Documentation',
			'manage_options',
			'giu-docs',
			[ $this, 'render_documentation' ]
		);
		
		// Remove the duplicate main menu item that WordPress creates
		remove_submenu_page( 'giu-main', 'giu-main' );
	}

	public function register_settings() {
		register_setting( 'giu', self::OPTION, [ $this, 'sanitize_options' ] );

		add_settings_section( 'giu_main', 'GitHub API', function(){
			echo '<p>Provide a <strong>GitHub personal access token</strong> with permission to read the repositories you manage here. Tokens are stored in WordPress options.</p>';
		}, 'giu' );

		add_settings_field( 'giu_token', 'Token', function() {
			$opts = self::get_options();
			echo '<input type="password" style="width:480px" name="' . esc_attr( self::OPTION ) . '[token]" value="' . esc_attr( $opts['token'] ) . '" placeholder="ghp_xxx or fine-grained token">';
			if ( ! empty( $opts['token'] ) ) {
				echo '<p><em>Token is set.</em></p>';
			}
		}, 'giu', 'giu_main' );
	}

	public function sanitize_options( $input ) {
		$current = self::get_options();
		$next = [
			'token' => $current['token'],
			'repos' => $current['repos'],
		];
		if ( isset( $input['token'] ) ) {
			$next['token'] = trim( (string) $input['token'] );
		}
		if ( isset( $input['repos'] ) && is_array( $input['repos'] ) ) {
			$next['repos'] = $this->sanitize_repos( $input['repos'] );
		}
		return $next;
	}

	private function sanitize_repos( array $repos ) : array {
		$sanitized = [];
		foreach ( $repos as $id => $repo ) {
			if ( ! is_array( $repo ) ) continue;
			$key = sanitize_title( (string) $id );
			if ( $key === '' ) continue;
			$type = isset( $repo['type'] ) && $repo['type'] === 'theme' ? 'theme' : 'plugin';
			$owner = isset( $repo['owner'] ) ? sanitize_text_field( (string) $repo['owner'] ) : '';
			$name = isset( $repo['repo'] ) ? sanitize_text_field( (string) $repo['repo'] ) : '';
			$slug = isset( $repo['slug'] ) ? sanitize_text_field( (string) $repo['slug'] ) : '';
			if ( $owner === '' || $name === '' || $slug === '' ) continue;
			$latest = isset( $repo['latest'] ) ? sanitize_text_field( (string) $repo['latest'] ) : '-';
			if ( $latest === '' ) $latest = '-';
			$sanitized[ $key ] = [
				'type' => $type,
				'owner' => $owner,
				'repo' => $name,
				'slug' => $slug,
				'latest' => $latest,
			];
		}
		return $sanitized;
	}

	private function enqueue_notice( string $type, string $message ) : void {
		$user_id = get_current_user_id();
		if ( ! $user_id || $message === '' ) {
			return;
		}
		$allowed = [
			'success',
			'error',
			'info',
		];
		if ( ! in_array( $type, $allowed, true ) ) {
			$type = 'info';
		}
		$key = self::NOTICE_KEY_PREFIX . $user_id;
		$existing = get_transient( $key );
		if ( ! is_array( $existing ) ) {
			$existing = [];
		}
		$existing[] = [
			'type'    => $type,
			'message' => $message,
		];
		set_transient( $key, $existing, 2 * MINUTE_IN_SECONDS );
	}

	public function render_flash_notices() : void {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}
		$key = self::NOTICE_KEY_PREFIX . $user_id;
		$queue = get_transient( $key );
		if ( ! is_array( $queue ) || $queue === [] ) {
			return;
		}
		delete_transient( $key );
		$classes = [
			'success' => 'notice notice-success',
			'error'   => 'notice notice-error',
			'info'    => 'notice notice-info',
		];
		foreach ( $queue as $notice ) {
			if ( empty( $notice['message'] ) ) {
				continue;
			}
			$type = isset( $notice['type'], $classes[ $notice['type'] ] ) ? $notice['type'] : 'info';
			printf(
				'<div class="%s"><p>%s</p></div>',
				esc_attr( $classes[ $type ] ),
				esc_html( $notice['message'] )
			);
		}
	}

	private function api_get( string $url, int $cache_duration = HOUR_IN_SECONDS ) {
		// Create cache key from URL
		$cache_key = 'giu_api_' . md5( $url );
		
		// Try to get from cache first
		$cached_response = get_transient( $cache_key );
		if ( false !== $cached_response ) {
			$this->log_info( 'Using cached GitHub API response', [ 'url' => $url ] );
			return $cached_response;
		}
		
		$opts = self::get_options();
		$args = [
			'headers' => [
				'Accept'        => 'application/vnd.github+json',
				'User-Agent'    => 'giu-wordpress/' . self::VERSION,
			],
			'timeout' => 30,
		];
		if ( ! empty( $opts['token'] ) ) {
			$args['headers']['Authorization'] = 'token ' . $opts['token'];
		}
		
		$this->log_info( 'Making GitHub API request', [ 'url' => $url ] );
		$res = wp_remote_get( $url, $args );
		
		if ( is_wp_error( $res ) ) {
			$this->log_error( 'GitHub API request failed', [ 'url' => $url, 'error' => $res->get_error_message() ] );
			return $res;
		}
		
		$code = wp_remote_retrieve_response_code( $res );
		$body = wp_remote_retrieve_body( $res );
		
		if ( $code < 200 || $code >= 300 ) {
			$error_message = 'GitHub API error: HTTP ' . $code . ' – ' . substr( $body, 0, 200 );
			$this->log_error( $error_message, [ 'url' => $url, 'status_code' => $code ] );
			return new WP_Error(
				'giu_http',
				$error_message,
				[
					'status' => $code,
				]
			);
		}
		
		$json = json_decode( $body, true );
		if ( null === $json ) {
			$this->log_error( 'Invalid JSON from GitHub API', [ 'url' => $url, 'body_preview' => substr( $body, 0, 200 ) ] );
			return new WP_Error( 'giu_json', 'Invalid JSON from GitHub' );
		}
		
		// Cache successful responses
		set_transient( $cache_key, $json, $cache_duration );
		$this->log_info( 'GitHub API response cached', [ 'url' => $url, 'cache_duration' => $cache_duration ] );
		
		return $json;
	}

	private function latest_release( string $owner, string $repo ) {
		$url = sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', rawurlencode( $owner ), rawurlencode( $repo ) );
		
		// Allow filtering of the GitHub API URL
		$url = apply_filters( 'giu_github_release_url', $url, $owner, $repo );
		
		$response = $this->api_get( $url );
		
		// Allow filtering of the release data
		return apply_filters( 'giu_github_release_data', $response, $owner, $repo );
	}

	private function repo_default_branch_zip( string $owner, string $repo, string $branch = 'main' ) : string {
		return sprintf( 'https://api.github.com/repos/%s/%s/zipball/%s', rawurlencode( $owner ), rawurlencode( $repo ), rawurlencode( $branch ) );
	}

	private function release_zipball( $release ) : ?string {
		if ( is_wp_error( $release ) ) return null;
		return isset( $release['zipball_url'] ) ? $release['zipball_url'] : null;
	}

	private function get_error_status( WP_Error $error ) : ?int {
		$data = $error->get_error_data();
		if ( is_array( $data ) && isset( $data['status'] ) ) {
			$status = (int) $data['status'];
			return $status > 0 ? $status : null;
		}
		if ( is_int( $data ) && $data > 0 ) {
			return $data;
		}
		return null;
	}

	private function get_default_branch( string $owner, string $repo ) {
		$details = $this->api_get(
			sprintf(
				'https://api.github.com/repos/%s/%s',
				rawurlencode( $owner ),
				rawurlencode( $repo )
			)
		);
		if ( is_wp_error( $details ) ) return $details;
		if ( isset( $details['default_branch'] ) && $details['default_branch'] !== '' ) {
			return (string) $details['default_branch'];
		}
		return null;
	}

	private function fallback_branch_zip( string $owner, string $repo ) {
		$branch = $this->get_default_branch( $owner, $repo );
		if ( is_wp_error( $branch ) ) {
			return $branch;
		}
		$branch = $branch ?: 'main';
		return $this->repo_default_branch_zip( $owner, $repo, $branch );
	}

	private function with_github_download_auth( string $download_url, callable $callback ) {
		$opts = self::get_options();
		$token = isset( $opts['token'] ) ? trim( (string) $opts['token'] ) : '';
		$filter = null;
		if ( $token !== '' && str_starts_with( $download_url, 'https://api.github.com/' ) ) {
			$filter = static function( $args, $url ) use ( $download_url, $token ) {
				if ( $url !== $download_url ) return $args;
				$args['headers']['Authorization'] = 'token ' . $token;
				if ( empty( $args['headers']['User-Agent'] ) ) {
					$args['headers']['User-Agent'] = 'giu-wordpress/' . self::VERSION;
				}
				return $args;
			};
			add_filter( 'http_request_args', $filter, 10, 2 );
		}
		try {
			return $callback();
		} finally {
			if ( $filter ) {
				remove_filter( 'http_request_args', $filter, 10 );
			}
		}
	}

	private function with_install_source_override( string $type, string $slug, callable $callback ) {
		$expected = $type === 'plugin' ? trim( dirname( $slug ), '.' . DIRECTORY_SEPARATOR ) : $slug;
		if ( $expected === '' ) {
			return $callback();
		}
		$this->current_install = [
			'type'     => $type,
			'expected' => $expected,
		];
		add_filter( 'upgrader_source_selection', [ $this, 'enforce_source_directory' ], 10, 4 );
		try {
			return $callback();
		} finally {
			remove_filter( 'upgrader_source_selection', [ $this, 'enforce_source_directory' ], 10 );
			$this->current_install = null;
		}
	}

	public function enforce_source_directory( $source, $remote_source, $upgrader, $hook_extra ) {
		if ( empty( $this->current_install ) || ! is_string( $source ) ) {
			return $source;
		}
		if ( empty( $hook_extra['type'] ) || $hook_extra['type'] !== $this->current_install['type'] ) {
			return $source;
		}
		$current = basename( $source );
		$expected = $this->current_install['expected'];
		if ( $current === $expected ) {
			return $source;
		}
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			WP_Filesystem();
		}
		if ( ! $wp_filesystem ) {
			return new WP_Error( 'giu_fs', 'Unable to initialize filesystem.' );
		}
		$target = trailingslashit( dirname( $source ) ) . $expected;
		if ( $wp_filesystem->exists( $target ) ) {
			$wp_filesystem->delete( $target, true );
		}
		if ( ! $wp_filesystem->move( $source, $target, true ) ) {
			return new WP_Error( 'giu_move', 'Unable to prepare package directory.' );
		}
		return trailingslashit( $target );
	}

	public function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$opts = self::get_options();
		$plugin_url = plugin_dir_url( __FILE__ );
		?>
		<script src="https://cdn.tailwindcss.com"></script>
		<div class="min-h-screen bg-gray-50">
			<!-- Header with Logo -->
			<div class="bg-gradient-to-r from-teal-500 to-teal-600 text-white">
				<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
					<div class="flex items-center justify-between h-20">
						<div class="flex items-center">
							<img src="<?php echo esc_url( $plugin_url . 'assets/img/logo_en.jpg' ); ?>" alt="Kob Git Updater" class="h-12 w-auto mr-4">
							<div>
								<h1 class="text-2xl font-bold">Kob Git Updater</h1>
								<p class="text-teal-100">GitHub-based WordPress Plugin & Theme Manager</p>
							</div>
						</div>
						<div class="text-right">
							<span class="text-sm text-teal-100">Version <?php echo esc_html( self::VERSION ); ?></span>
						</div>
					</div>
				</div>
			</div>

			<!-- Navigation -->
			<div class="bg-white shadow-sm border-b">
				<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
					<nav class="flex space-x-8 py-4">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=giu-settings' ) ); ?>" class="bg-teal-100 text-teal-700 px-3 py-2 rounded-md text-sm font-medium">
							<svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
							</svg>
							Configuration
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=giu-docs' ) ); ?>" class="text-gray-600 hover:text-teal-600 px-3 py-2 rounded-md text-sm font-medium transition-colors">
							<svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
							</svg>
							Documentation
						</a>
					</nav>
				</div>
			</div>

			<!-- Main Content -->
			<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
				<div class="space-y-8">
					<!-- GitHub API Settings -->
					<div class="bg-white rounded-lg shadow-sm border">
						<div class="px-6 py-4 border-b border-gray-200">
							<div class="flex items-center">
								<div class="bg-teal-100 p-2 rounded-lg mr-3">
									<svg class="w-5 h-5 text-teal-600" fill="currentColor" viewBox="0 0 20 20">
										<path fill-rule="evenodd" d="M10 0C4.477 0 0 4.484 0 10.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0110 4.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.203 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.942.359.31.678.921.678 1.856 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0020 10.017C20 4.484 15.522 0 10 0z" clip-rule="evenodd"/>
									</svg>
								</div>
								<h2 class="text-xl font-semibold text-gray-900">GitHub API Configuration</h2>
							</div>
						</div>
						<div class="p-6">
							<div class="mb-6">
								<p class="text-gray-600 mb-4">Provide a <strong>GitHub personal access token</strong> with permission to read the repositories you manage here. Tokens are stored securely in WordPress options.</p>
								<div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
									<div class="flex items-center">
										<svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
										</svg>
										<p class="text-blue-700 text-sm">
											<strong>Need help creating a token?</strong> 
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=giu-docs#setup' ) ); ?>" class="underline hover:no-underline">Check our setup guide</a>
										</p>
									</div>
								</div>
							</div>
							<form method="post" action="options.php">
								<?php settings_fields( 'giu' ); ?>
								<div class="space-y-4">
									<div>
										<label for="giu_token" class="block text-sm font-medium text-gray-700 mb-2">GitHub Personal Access Token</label>
										<input 
											type="password" 
											id="giu_token"
											name="<?php echo esc_attr( self::OPTION ); ?>[token]" 
											value="<?php echo esc_attr( $opts['token'] ); ?>" 
											placeholder="ghp_xxx or fine-grained token"
											class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-teal-500 focus:border-teal-500"
										>
										<?php if ( ! empty( $opts['token'] ) ) : ?>
											<p class="mt-2 text-sm text-green-600 flex items-center">
												<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
													<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
												</svg>
												Token is configured and active
											</p>
										<?php endif; ?>
									</div>
								</div>
								<div class="pt-4">
									<button type="submit" class="bg-teal-600 hover:bg-teal-700 text-white font-medium py-2 px-4 rounded-md transition-colors duration-200 flex items-center">
										<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3-3m0 0l-3 3m3-3v12"></path>
										</svg>
										Save Token
									</button>
								</div>
							</form>
						</div>
					</div>

					<!-- Add Repository -->
					<div class="bg-white rounded-lg shadow-sm border">
						<div class="px-6 py-4 border-b border-gray-200">
							<div class="flex items-center">
								<div class="bg-teal-100 p-2 rounded-lg mr-3">
									<svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
									</svg>
								</div>
								<h2 class="text-xl font-semibold text-gray-900">Add Repository</h2>
							</div>
						</div>
						<div class="p-6">
							<p class="text-gray-600 mb-6">Add a GitHub repository to manage and optionally install it now. Updates will be supplied from GitHub Releases.</p>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php wp_nonce_field( 'giu_add_repo' ); ?>
								<input type="hidden" name="action" value="giu_add_repo" />
								<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
									<div>
										<label for="type" class="block text-sm font-medium text-gray-700 mb-2">Type</label>
										<select name="type" id="type" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-teal-500 focus:border-teal-500">
											<option value="plugin">Plugin</option>
											<option value="theme">Theme</option>
										</select>
									</div>
									<div>
										<label for="owner_repo" class="block text-sm font-medium text-gray-700 mb-2">Owner / Repository</label>
										<input 
											type="text" 
											id="owner_repo"
											name="owner_repo" 
											placeholder="username/repository-name" 
											class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-teal-500 focus:border-teal-500" 
											required
										>
									</div>
									<div class="md:col-span-2">
										<label for="slug" class="block text-sm font-medium text-gray-700 mb-2">WordPress Slug</label>
										<input 
											type="text" 
											id="slug"
											name="slug" 
											class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-teal-500 focus:border-teal-500" 
											required 
											placeholder="plugins: folder-name/main-file.php — themes: directory-name"
										>
										<p class="mt-2 text-sm text-gray-500">
											For plugins, enter the <code class="bg-gray-100 px-2 py-1 rounded text-sm">plugin_basename</code> like <code class="bg-gray-100 px-2 py-1 rounded text-sm">my-plugin/my-plugin.php</code>. 
											For themes, enter the directory name.
										</p>
									</div>
									<div class="md:col-span-2">
										<div class="flex items-center">
											<input type="checkbox" id="install_now" name="install_now" value="1" class="h-4 w-4 text-teal-600 focus:ring-teal-500 border-gray-300 rounded">
											<label for="install_now" class="ml-2 text-sm text-gray-700">
												Download & install immediately
											</label>
										</div>
									</div>
								</div>
								<div class="pt-6">
									<button type="submit" class="bg-teal-600 hover:bg-teal-700 text-white font-medium py-2 px-4 rounded-md transition-colors duration-200 flex items-center">
										<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
										</svg>
										Add Repository
									</button>
								</div>
							</form>
						</div>
					</div>

					<!-- Managed Repositories -->
					<div class="bg-white rounded-lg shadow-sm border">
						<div class="px-6 py-4 border-b border-gray-200">
							<div class="flex items-center justify-between">
								<div class="flex items-center">
									<div class="bg-teal-100 p-2 rounded-lg mr-3">
										<svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
										</svg>
									</div>
									<h2 class="text-xl font-semibold text-gray-900">Managed Repositories</h2>
								</div>
								<?php if ( ! empty( $opts['repos'] ) ) : ?>
									<span class="bg-teal-100 text-teal-800 text-xs px-2 py-1 rounded-full"><?php echo count( $opts['repos'] ); ?> repositories</span>
								<?php endif; ?>
							</div>
						</div>
						<div class="p-6">
							<?php $repos = $opts['repos']; ?>
							<?php if ( empty( $repos ) ) : ?>
								<div class="text-center py-12">
									<svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
									</svg>
									<h3 class="text-lg font-medium text-gray-900 mb-2">No repositories added yet</h3>
									<p class="text-gray-500 mb-4">Add your first GitHub repository using the form above to get started.</p>
								</div>
							<?php else : ?>
								<div class="overflow-x-auto">
									<table class="min-w-full divide-y divide-gray-200">
										<thead class="bg-gray-50">
											<tr>
												<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
												<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Repository</th>
												<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">WP Slug</th>
												<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Latest Release</th>
												<th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
											</tr>
										</thead>
										<tbody class="bg-white divide-y divide-gray-200">
											<?php foreach ( $repos as $id => $r ) : ?>
											<tr class="hover:bg-gray-50">
												<td class="px-6 py-4 whitespace-nowrap">
													<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $r['type'] === 'plugin' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'; ?>">
														<?php echo esc_html( ucfirst( $r['type'] ) ); ?>
													</span>
												</td>
												<td class="px-6 py-4 whitespace-nowrap">
													<div class="flex items-center">
														<svg class="w-4 h-4 text-gray-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
															<path fill-rule="evenodd" d="M10 0C4.477 0 0 4.484 0 10.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0110 4.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.203 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.942.359.31.678.921.678 1.856 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0020 10.017C20 4.484 15.522 0 10 0z" clip-rule="evenodd"/>
														</svg>
														<a href="https://github.com/<?php echo esc_attr( $r['owner'] . '/' . $r['repo'] ); ?>" target="_blank" class="text-teal-600 hover:text-teal-700 font-medium">
															<?php echo esc_html( $r['owner'] . '/' . $r['repo'] ); ?>
														</a>
													</div>
												</td>
												<td class="px-6 py-4 whitespace-nowrap">
													<code class="px-2 py-1 bg-gray-100 rounded text-sm"><?php echo esc_html( $r['slug'] ); ?></code>
												</td>
												<td class="px-6 py-4 whitespace-nowrap">
													<?php if ( $r['latest'] !== '-' ) : ?>
														<span class="inline-flex px-2 py-1 text-xs font-semibold bg-green-100 text-green-800 rounded-full">
															<?php echo esc_html( $r['latest'] ); ?>
														</span>
													<?php else : ?>
														<span class="text-gray-400">No releases</span>
													<?php endif; ?>
												</td>
												<td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
													<form style="display:inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mr-2">
														<?php wp_nonce_field( 'giu_install_repo' ); ?>
														<input type="hidden" name="action" value="giu_install_repo">
														<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>">
														<button type="submit" class="bg-teal-600 hover:bg-teal-700 text-white text-xs px-3 py-1 rounded transition-colors duration-200">
															Install/Update
														</button>
													</form>
													<form style="display:inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Remove this repository from management?');">
														<?php wp_nonce_field( 'giu_remove_repo' ); ?>
														<input type="hidden" name="action" value="giu_remove_repo">
														<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>">
														<button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-xs px-3 py-1 rounded transition-colors duration-200">
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
			</div>
		</div>
		<?php
	}

	public function render_documentation() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$plugin_url = plugin_dir_url( __FILE__ );
		?>
		<script src="https://cdn.tailwindcss.com"></script>
		<div class="min-h-screen bg-gray-50">
			<!-- Header with Logo -->
			<div class="bg-gradient-to-r from-teal-500 to-teal-600 text-white">
				<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
					<div class="flex items-center justify-between h-20">
						<div class="flex items-center">
							<img src="<?php echo esc_url( $plugin_url . 'assets/img/logo_en.jpg' ); ?>" alt="Kob Git Updater" class="h-12 w-auto mr-4">
							<div>
								<h1 class="text-2xl font-bold">Kob Git Updater</h1>
								<p class="text-teal-100">GitHub-based WordPress Plugin & Theme Manager</p>
							</div>
						</div>
						<div class="text-right">
							<span class="text-sm text-teal-100">Version <?php echo esc_html( self::VERSION ); ?></span>
						</div>
					</div>
				</div>
			</div>

			<!-- Navigation -->
			<div class="bg-white shadow-sm border-b">
				<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
					<nav class="flex space-x-8 py-4">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=giu-settings' ) ); ?>" class="text-gray-600 hover:text-teal-600 px-3 py-2 rounded-md text-sm font-medium transition-colors">
							<svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
							</svg>
							Configuration
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=giu-docs' ) ); ?>" class="bg-teal-100 text-teal-700 px-3 py-2 rounded-md text-sm font-medium">
							<svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
							</svg>
							Documentation
						</a>
					</nav>
				</div>
			</div>

			<!-- Main Content -->
			<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
				<div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
					<!-- Sidebar -->
					<div class="lg:col-span-1">
						<div class="bg-white rounded-lg shadow-sm border p-6 sticky top-8">
							<h3 class="text-lg font-semibold text-gray-900 mb-4">Contents</h3>
							<nav class="space-y-2">
								<a href="#overview" class="block text-sm text-gray-600 hover:text-teal-600 transition-colors">Overview</a>
								<a href="#features" class="block text-sm text-gray-600 hover:text-teal-600 transition-colors">Features</a>
								<a href="#requirements" class="block text-sm text-gray-600 hover:text-teal-600 transition-colors">Requirements</a>
								<a href="#setup" class="block text-sm text-gray-600 hover:text-teal-600 transition-colors">Setup Guide</a>
								<a href="#usage" class="block text-sm text-gray-600 hover:text-teal-600 transition-colors">Usage</a>
								<a href="#troubleshooting" class="block text-sm text-gray-600 hover:text-teal-600 transition-colors">Troubleshooting</a>
								<a href="#api" class="block text-sm text-gray-600 hover:text-teal-600 transition-colors">API Reference</a>
							</nav>
						</div>
					</div>

					<!-- Documentation Content -->
					<div class="lg:col-span-3">
						<div class="bg-white rounded-lg shadow-sm border">
							<div class="p-8">
								<!-- Overview -->
								<section id="overview" class="mb-12">
									<div class="flex items-center mb-6">
										<div class="bg-teal-100 p-3 rounded-lg mr-4">
											<svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
											</svg>
										</div>
										<h2 class="text-2xl font-bold text-gray-900">Overview</h2>
									</div>
									<div class="prose max-w-none">
										<p class="text-gray-600 mb-4">Kob Git Updater is a WordPress plugin that enables you to install and automatically update WordPress plugins and themes directly from GitHub repositories. It integrates seamlessly with WordPress's built-in update system.</p>
										<div class="bg-teal-50 border border-teal-200 rounded-lg p-4">
											<h4 class="font-semibold text-teal-800 mb-2">Key Benefits</h4>
											<ul class="list-disc list-inside text-teal-700 space-y-1">
												<li>Manage custom plugins and themes from private GitHub repositories</li>
												<li>Automatic updates through WordPress admin interface</li>
												<li>Secure token-based authentication</li>
												<li>Version control integration with GitHub releases</li>
											</ul>
										</div>
									</div>
								</section>

								<!-- Features -->
								<section id="features" class="mb-12">
									<div class="flex items-center mb-6">
										<div class="bg-teal-100 p-3 rounded-lg mr-4">
											<svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
											</svg>
										</div>
										<h2 class="text-2xl font-bold text-gray-900">Features</h2>
									</div>
									<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
										<div class="border border-gray-200 rounded-lg p-4">
											<h3 class="font-semibold text-gray-900 mb-2">GitHub Integration</h3>
											<p class="text-gray-600 text-sm">Install from latest GitHub releases with automatic fallback to default branch</p>
										</div>
										<div class="border border-gray-200 rounded-lg p-4">
											<h3 class="font-semibold text-gray-900 mb-2">Auto Updates</h3>
											<p class="text-gray-600 text-sm">Seamless integration with WordPress update system for plugins and themes</p>
										</div>
										<div class="border border-gray-200 rounded-lg p-4">
											<h3 class="font-semibold text-gray-900 mb-2">Private Repos</h3>
											<p class="text-gray-600 text-sm">Support for private repositories via GitHub personal access tokens</p>
										</div>
										<div class="border border-gray-200 rounded-lg p-4">
											<h3 class="font-semibold text-gray-900 mb-2">Simple UI</h3>
											<p class="text-gray-600 text-sm">Clean admin interface for managing GitHub repositories and tokens</p>
										</div>
									</div>
								</section>

								<!-- Requirements -->
								<section id="requirements" class="mb-12">
									<div class="flex items-center mb-6">
										<div class="bg-teal-100 p-3 rounded-lg mr-4">
											<svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
											</svg>
										</div>
										<h2 class="text-2xl font-bold text-gray-900">Requirements</h2>
									</div>
									<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
										<div>
											<h3 class="font-semibold text-gray-900 mb-3">System Requirements</h3>
											<ul class="space-y-2">
												<li class="flex items-center text-gray-600">
													<svg class="w-4 h-4 text-teal-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
														<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
													</svg>
													WordPress 6.0+
												</li>
												<li class="flex items-center text-gray-600">
													<svg class="w-4 h-4 text-teal-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
														<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
													</svg>
													PHP 8.1+
												</li>
												<li class="flex items-center text-gray-600">
													<svg class="w-4 h-4 text-teal-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
														<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
													</svg>
													Admin privileges
												</li>
											</ul>
										</div>
										<div>
											<h3 class="font-semibold text-gray-900 mb-3">GitHub Requirements</h3>
											<ul class="space-y-2">
												<li class="flex items-center text-gray-600">
													<svg class="w-4 h-4 text-teal-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
														<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
													</svg>
													GitHub account
												</li>
												<li class="flex items-center text-gray-600">
													<svg class="w-4 h-4 text-teal-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
														<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
													</svg>
													Personal access token
												</li>
												<li class="flex items-center text-gray-600">
													<svg class="w-4 h-4 text-teal-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
														<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
													</svg>
													Read access to repositories
												</li>
											</ul>
										</div>
									</div>
								</section>

								<!-- Setup Guide -->
								<section id="setup" class="mb-12">
									<div class="flex items-center mb-6">
										<div class="bg-teal-100 p-3 rounded-lg mr-4">
											<svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4"></path>
											</svg>
										</div>
										<h2 class="text-2xl font-bold text-gray-900">Setup Guide</h2>
									</div>
									<div class="space-y-8">
										<div class="border-l-4 border-teal-500 pl-6">
											<div class="flex items-center mb-3">
												<span class="bg-teal-600 text-white rounded-full w-8 h-8 flex items-center justify-center text-sm font-bold mr-3">1</span>
												<h3 class="text-lg font-semibold text-gray-900">Create GitHub Token</h3>
											</div>
											<div class="text-gray-600">
												<p class="mb-3">Go to <a href="https://github.com/settings/tokens" target="_blank" class="text-teal-600 hover:text-teal-700 underline">GitHub Settings → Personal Access Tokens</a></p>
												<p class="mb-3">Create a new token with these permissions:</p>
												<ul class="list-disc list-inside ml-4 space-y-1">
													<li><code class="bg-gray-100 px-2 py-1 rounded text-sm">repo</code> - Full control of private repositories</li>
													<li><code class="bg-gray-100 px-2 py-1 rounded text-sm">public_repo</code> - Access to public repositories</li>
												</ul>
											</div>
										</div>

										<div class="border-l-4 border-teal-500 pl-6">
											<div class="flex items-center mb-3">
												<span class="bg-teal-600 text-white rounded-full w-8 h-8 flex items-center justify-center text-sm font-bold mr-3">2</span>
												<h3 class="text-lg font-semibold text-gray-900">Configure Plugin</h3>
											</div>
											<div class="text-gray-600">
												<p class="mb-3">Go to <a href="<?php echo esc_url( admin_url( 'admin.php?page=giu-settings' ) ); ?>" class="text-teal-600 hover:text-teal-700 underline">Git Updater → Configuration</a></p>
												<p class="mb-3">Paste your GitHub token and save</p>
											</div>
										</div>

										<div class="border-l-4 border-teal-500 pl-6">
											<div class="flex items-center mb-3">
												<span class="bg-teal-600 text-white rounded-full w-8 h-8 flex items-center justify-center text-sm font-bold mr-3">3</span>
												<h3 class="text-lg font-semibold text-gray-900">Add Repositories</h3>
											</div>
											<div class="text-gray-600">
												<p class="mb-3">Add your repositories using the form in the Configuration page:</p>
												<ul class="list-disc list-inside ml-4 space-y-1">
													<li><strong>Type:</strong> Choose Plugin or Theme</li>
													<li><strong>Owner/Repo:</strong> Format: <code class="bg-gray-100 px-2 py-1 rounded text-sm">username/repository-name</code></li>
													<li><strong>WP Slug:</strong> For plugins: <code class="bg-gray-100 px-2 py-1 rounded text-sm">folder-name/main-file.php</code></li>
													<li><strong>WP Slug:</strong> For themes: <code class="bg-gray-100 px-2 py-1 rounded text-sm">theme-directory-name</code></li>
												</ul>
											</div>
										</div>
									</div>
								</section>

								<!-- Usage -->
								<section id="usage" class="mb-12">
									<div class="flex items-center mb-6">
										<div class="bg-teal-100 p-3 rounded-lg mr-4">
											<svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
											</svg>
										</div>
										<h2 class="text-2xl font-bold text-gray-900">Usage</h2>
									</div>
									<div class="space-y-6">
										<div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
											<h3 class="font-semibold text-blue-800 mb-2">Initial Installation</h3>
											<p class="text-blue-700">When adding a repository, check "Install Now" to immediately download and install the plugin or theme from the latest GitHub release.</p>
										</div>
										<div class="bg-green-50 border border-green-200 rounded-lg p-4">
											<h3 class="font-semibold text-green-800 mb-2">Automatic Updates</h3>
											<p class="text-green-700">Once configured, updates will appear in WordPress admin → Updates page whenever new GitHub releases are published. Version tags like <code class="bg-green-100 px-2 py-1 rounded">v1.2.3</code> are automatically recognized.</p>
										</div>
										<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
											<h3 class="font-semibold text-yellow-800 mb-2">Fallback Behavior</h3>
											<p class="text-yellow-700">If no GitHub releases exist, the plugin will install from the default branch (usually <code class="bg-yellow-100 px-2 py-1 rounded">main</code>). However, automatic updates require GitHub releases.</p>
										</div>
									</div>
								</section>

								<!-- Troubleshooting -->
								<section id="troubleshooting" class="mb-12">
									<div class="flex items-center mb-6">
										<div class="bg-teal-100 p-3 rounded-lg mr-4">
											<svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192L5.636 18.364M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"></path>
											</svg>
										</div>
										<h2 class="text-2xl font-bold text-gray-900">Troubleshooting</h2>
									</div>
									<div class="space-y-6">
										<div class="border border-gray-200 rounded-lg">
											<div class="p-4 border-b border-gray-200">
												<h3 class="font-semibold text-gray-900">GitHub API Rate Limits</h3>
											</div>
											<div class="p-4">
												<p class="text-gray-600 mb-3">GitHub limits API requests:</p>
												<ul class="list-disc list-inside text-gray-600 space-y-1">
													<li>Authenticated: 5,000 requests/hour</li>
													<li>Unauthenticated: 60 requests/hour</li>
												</ul>
												<p class="text-teal-600 mt-2 text-sm"><strong>Solution:</strong> Always use a personal access token</p>
											</div>
										</div>

										<div class="border border-gray-200 rounded-lg">
											<div class="p-4 border-b border-gray-200">
												<h3 class="font-semibold text-gray-900">Installation Fails</h3>
											</div>
											<div class="p-4">
												<p class="text-gray-600 mb-3">Common causes:</p>
												<ul class="list-disc list-inside text-gray-600 space-y-1">
													<li>Incorrect repository name or slug</li>
													<li>Invalid or expired GitHub token</li>
													<li>Private repository without proper permissions</li>
													<li>Directory name mismatch</li>
												</ul>
												<p class="text-teal-600 mt-2 text-sm"><strong>Solution:</strong> Check WordPress debug logs and verify repository settings</p>
											</div>
										</div>

										<div class="border border-gray-200 rounded-lg">
											<div class="p-4 border-b border-gray-200">
												<h3 class="font-semibold text-gray-900">Updates Not Appearing</h3>
											</div>
											<div class="p-4">
												<p class="text-gray-600 mb-3">Possible issues:</p>
												<ul class="list-disc list-inside text-gray-600 space-y-1">
													<li>Plugin/theme version number doesn't match GitHub release</li>
													<li>WordPress update cache needs clearing</li>
													<li>No GitHub releases (only branches)</li>
												</ul>
												<p class="text-teal-600 mt-2 text-sm"><strong>Solution:</strong> Create proper GitHub releases with semantic version tags</p>
											</div>
										</div>
									</div>
								</section>

								<!-- API Reference -->
								<section id="api" class="mb-12">
									<div class="flex items-center mb-6">
										<div class="bg-teal-100 p-3 rounded-lg mr-4">
											<svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
											</svg>
										</div>
										<h2 class="text-2xl font-bold text-gray-900">API Reference</h2>
									</div>
									<div class="space-y-6">
										<div>
											<h3 class="font-semibold text-gray-900 mb-3">GitHub API Endpoints Used</h3>
											<div class="space-y-3">
												<div class="bg-gray-50 rounded-lg p-4">
													<div class="flex items-center mb-2">
														<span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-semibold mr-2">GET</span>
														<code class="text-sm">/repos/{owner}/{repo}/releases/latest</code>
													</div>
													<p class="text-sm text-gray-600">Get the latest release information</p>
												</div>
												<div class="bg-gray-50 rounded-lg p-4">
													<div class="flex items-center mb-2">
														<span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-semibold mr-2">GET</span>
														<code class="text-sm">/repos/{owner}/{repo}</code>
													</div>
													<p class="text-sm text-gray-600">Get repository details and default branch</p>
												</div>
												<div class="bg-gray-50 rounded-lg p-4">
													<div class="flex items-center mb-2">
														<span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-semibold mr-2">GET</span>
														<code class="text-sm">/repos/{owner}/{repo}/zipball/{ref}</code>
													</div>
													<p class="text-sm text-gray-600">Download release or branch archive</p>
												</div>
											</div>
										</div>

										<div>
											<h3 class="font-semibold text-gray-900 mb-3">WordPress Hooks</h3>
											<div class="space-y-3">
												<div class="bg-gray-50 rounded-lg p-4">
													<h4 class="font-semibold mb-2">Update System Integration</h4>
													<ul class="text-sm text-gray-600 space-y-1">
														<li><code>pre_set_site_transient_update_plugins</code> - Inject plugin updates</li>
														<li><code>pre_set_site_transient_update_themes</code> - Inject theme updates</li>
														<li><code>upgrader_source_selection</code> - Directory name enforcement</li>
													</ul>
												</div>
												<div class="bg-gray-50 rounded-lg p-4">
													<h4 class="font-semibold mb-2">Admin Interface</h4>
													<ul class="text-sm text-gray-600 space-y-1">
														<li><code>admin_menu</code> - Add admin pages</li>
														<li><code>admin_init</code> - Register settings</li>
														<li><code>admin_post_*</code> - Handle form submissions</li>
													</ul>
												</div>
											</div>
										</div>
									</div>
								</section>

							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Footer -->
			<div class="bg-gray-800 text-white mt-16">
				<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
					<div class="text-center">
						<p class="text-gray-300">Kob Git Updater v<?php echo esc_html( self::VERSION ); ?> | Made with ❤️ by Kobkob LLC</p>
						<p class="text-gray-400 text-sm mt-2">Licensed under GPL-2.0-or-later</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function handle_add_repo() {
		// Enhanced security checks
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->log_error( 'Unauthorized attempt to add repository', [ 'user_id' => get_current_user_id() ] );
			wp_die( __( 'You do not have permission to perform this action.', 'kob-git-updater' ), 403 );
		}
		
		check_admin_referer( 'giu_add_repo' );
		
		// Validate and sanitize inputs
		$type = $this->validate_repo_type( $_POST['type'] ?? '' );
		$owner_repo = $this->validate_owner_repo( $_POST['owner_repo'] ?? '' );
		$slug = $this->validate_slug( $_POST['slug'] ?? '', $type );
		$install_now = ! empty( $_POST['install_now'] ) && $_POST['install_now'] === '1';
		
		// Check for validation errors
		if ( is_wp_error( $owner_repo ) ) {
			$this->enqueue_notice( 'error', $this->format_error_message( $owner_repo ) );
			$this->log_error( 'Invalid owner/repo format', [ 'input' => $_POST['owner_repo'] ?? '' ] );
			wp_safe_redirect( admin_url( 'admin.php?page=giu-settings' ) );
			exit;
		}
		
		if ( is_wp_error( $slug ) ) {
			$this->enqueue_notice( 'error', $this->format_error_message( $slug ) );
			$this->log_error( 'Invalid slug format', [ 'input' => $_POST['slug'] ?? '', 'type' => $type ] );
			wp_safe_redirect( admin_url( 'admin.php?page=giu-settings' ) );
			exit;
		}
		
		// Extract owner and repo
		list( $owner, $repo ) = $owner_repo;
		
		// Check for duplicate repositories
		$opts = self::get_options();
		$existing_id = $this->find_existing_repo( $owner, $repo, $opts['repos'] );
		if ( $existing_id ) {
			$this->enqueue_notice( 'error', sprintf(
				/* translators: 1: repository name */
				__( 'Repository %s is already being managed.', 'kob-git-updater' ),
				$owner . '/' . $repo
			) );
			wp_safe_redirect( admin_url( 'admin.php?page=giu-settings' ) );
			exit;
		}
		
		// Create repository entry
		$id = sanitize_title( $type . '-' . $owner . '-' . $repo . '-' . $slug );
		$opts['repos'][ $id ] = [
			'type' => $type,
			'owner' => sanitize_text_field( $owner ),
			'repo' => sanitize_text_field( $repo ),
			'slug' => sanitize_text_field( $slug ),
			'latest' => '-',
			'added' => current_time( 'timestamp' ),
		];
		
		// Save options with validation
		if ( ! $this->save_options_safely( $opts ) ) {
			$this->enqueue_notice( 'error', __( 'Failed to save repository settings. Please try again.', 'kob-git-updater' ) );
			wp_safe_redirect( admin_url( 'admin.php?page=giu-settings' ) );
			exit;
		}
		
		$this->log_info( 'Repository added successfully', [ 'repo' => $owner . '/' . $repo, 'type' => $type, 'slug' => $slug ] );
		
		// Fire action hook
		do_action( 'giu_repo_added', $opts['repos'][ $id ], $id );
		
		// Install immediately if requested
		if ( $install_now ) {
			$this->do_install_by_id( $id );
		} else {
			$this->enqueue_notice( 'success', sprintf(
				/* translators: 1: repository name */
				__( 'Repository %s added successfully.', 'kob-git-updater' ),
				$owner . '/' . $repo
			) );
		}
		
		wp_safe_redirect( admin_url( 'admin.php?page=giu-settings' ) );
		exit;
	}

	public function handle_remove_repo() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
		check_admin_referer( 'giu_remove_repo' );
		$id = isset($_POST['id']) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$opts = self::get_options();
		unset( $opts['repos'][ $id ] );
		self::update_options( $opts );
		wp_redirect( admin_url( 'options-general.php?page=giu-settings' ) );
		exit;
	}

	public function handle_install_repo() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
		check_admin_referer( 'giu_install_repo' );
		$id = isset($_POST['id']) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$this->do_install_by_id( $id );
		wp_redirect( admin_url( 'options-general.php?page=giu-settings' ) );
		exit;
	}

	private function do_install_by_id( string $id ) : void {
		$opts = self::get_options();
		if ( empty( $opts['repos'][ $id ] ) ) {
			$this->log_error( 'Attempted to install unknown repository', [ 'id' => $id ] );
			return;
		}
		
		$r = $opts['repos'][ $id ];
		$this->log_info( 'Starting installation', [ 'repo' => $r['owner'] . '/' . $r['repo'], 'type' => $r['type'] ] );
		
		// Fire pre-install action
		do_action( 'giu_before_install', $r, $id );
		
		$download_url = $this->get_download_url( $r['owner'], $r['repo'] );
		if ( is_wp_error( $download_url ) ) {
			$friendly_message = $this->format_error_message( $download_url );
			$this->enqueue_notice( 'error', $friendly_message );
			$this->log_error( 'Failed to get download URL', [ 'repo' => $r['owner'] . '/' . $r['repo'], 'error' => $download_url->get_error_message() ] );
			do_action( 'giu_install_failed', $r, $id, $download_url );
			return;
		}
		
		$ok = $this->install_package( $r['type'], $download_url, $r['slug'] );
		if ( is_wp_error( $ok ) ) {
			$friendly_message = $this->format_error_message( $ok );
			$this->enqueue_notice( 'error', sprintf( __( 'Installation failed: %s', 'kob-git-updater' ), $friendly_message ) );
			$this->log_error( 'Package installation failed', [ 'repo' => $r['owner'] . '/' . $r['repo'], 'error' => $ok->get_error_message() ] );
			do_action( 'giu_install_failed', $r, $id, $ok );
			return;
		}
		
		// Update latest version info
		$release = $this->latest_release( $r['owner'], $r['repo'] );
		if ( ! is_wp_error( $release ) && isset( $release['tag_name'] ) ) {
			$opts['repos'][ $id ]['latest'] = $release['tag_name'];
			self::update_options( $opts );
			$this->log_info( 'Updated version info', [ 'repo' => $r['owner'] . '/' . $r['repo'], 'version' => $release['tag_name'] ] );
		}
		
		$success_message = sprintf(
			/* translators: 1: plugin/theme type, 2: repository owner, 3: repository name */
			__( 'Successfully installed/updated %1$s from %2$s/%3$s.', 'kob-git-updater' ),
			$r['type'],
			$r['owner'],
			$r['repo']
		);
		
		$this->enqueue_notice( 'success', $success_message );
		$this->log_info( 'Installation completed successfully', [ 'repo' => $r['owner'] . '/' . $r['repo'] ] );
		
		// Fire post-install action
		do_action( 'giu_after_install', $r, $id );
	}

	private function get_download_url( string $owner, string $repo ) {
		$release = $this->latest_release( $owner, $repo );
		if ( is_wp_error( $release ) ) {
			$status = $this->get_error_status( $release );
			if ( $status === 404 ) {
				return $this->fallback_branch_zip( $owner, $repo );
			}
			return $release;
		}
		$zip = $this->release_zipball( $release );
		if ( $zip ) {
			return $zip;
		}
		return $this->fallback_branch_zip( $owner, $repo );
	}

	private function install_package( string $type, string $download_url, string $slug ) {
		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		include_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		$skin = new Automatic_Upgrader_Skin();
		$result = $this->with_github_download_auth(
			$download_url,
			function() use ( $type, $download_url, $skin, $slug ) {
				return $this->with_install_source_override(
					$type,
					$slug,
					function() use ( $type, $download_url, $skin ) {
						if ( $type === 'theme' ) {
							$upgrader = new Theme_Upgrader( $skin );
							return $upgrader->install(
								$download_url,
								[
									'overwrite_package' => true,
								]
							);
						}
						$upgrader = new Plugin_Upgrader( $skin );
						return $upgrader->install(
							$download_url,
							[
								'overwrite_package' => true,
							]
						);
					}
				);
			}
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! $result ) {
			return new WP_Error( 'giu_install', 'Upgrader reported failure.' );
		}
		return true;
	}

	public function filter_plugin_updates( $transient ) {
		if ( empty( $transient ) || ! isset( $transient->checked ) ) return $transient;
		$opts = self::get_options();
		foreach ( $opts['repos'] as $r ) {
			if ( $r['type'] !== 'plugin' ) continue;
			$plugin_basename = $r['slug']; // e.g., my-plugin/my-plugin.php
			if ( ! isset( $transient->checked[ $plugin_basename ] ) ) continue; // not installed
			$installed_version = $transient->checked[ $plugin_basename ];
			$release = $this->latest_release( $r['owner'], $r['repo'] );
			if ( is_wp_error( $release ) || empty( $release['tag_name'] ) ) continue;
			$new_version = ltrim( (string) $release['tag_name'], 'v' );
			if ( version_compare( $new_version, $installed_version, '>' ) ) {
				$transient->response[ $plugin_basename ] = (object) [
					'slug'        => dirname( $plugin_basename ),
					'plugin'      => $plugin_basename,
					'new_version' => $new_version,
					'url'         => sprintf( 'https://github.com/%s/%s', $r['owner'], $r['repo'] ),
					'package'     => $this->get_download_url( $r['owner'], $r['repo'] ),
				];
			}
		}
		return $transient;
	}

	public function filter_theme_updates( $transient ) {
		if ( empty( $transient ) || ! isset( $transient->checked ) ) return $transient;
		$opts = self::get_options();
		foreach ( $opts['repos'] as $r ) {
			if ( $r['type'] !== 'theme' ) continue;
			$slug = $r['slug']; // theme directory name
			if ( ! isset( $transient->checked[ $slug ] ) ) continue; // not installed
			$installed_version = $transient->checked[ $slug ];
			$release = $this->latest_release( $r['owner'], $r['repo'] );
			if ( is_wp_error( $release ) || empty( $release['tag_name'] ) ) continue;
			$new_version = ltrim( (string) $release['tag_name'], 'v' );
			if ( version_compare( $new_version, $installed_version, '>' ) ) {
				$transient->response[ $slug ] = [
					'theme'       => $slug,
					'new_version' => $new_version,
					'url'         => sprintf( 'https://github.com/%s/%s', $r['owner'], $r['repo'] ),
					'package'     => $this->get_download_url( $r['owner'], $r['repo'] ),
				];
			}
		}
		return $transient;
	}

	/**
	 * Plugin activation hook
	 */
	public static function on_activation() : void {
		// Set default options if they don't exist
		if ( false === get_option( self::OPTION ) ) {
			add_option( self::OPTION, [
				'token' => '',
				'repos' => [],
			] );
		}
		
		// Clear any existing update transients to force refresh
		delete_transient( 'update_plugins' );
		delete_transient( 'update_themes' );
		
		// Log activation
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[' . self::LOG_PREFIX . '] Plugin activated' );
		}
		
		do_action( 'giu_plugin_activated' );
	}

	/**
	 * Plugin deactivation hook
	 */
	public static function on_deactivation() : void {
		// Clear all plugin-related transients
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_giu_api_%'
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_timeout_giu_api_%'
			)
		);
		
		// Clear update transients
		delete_transient( 'update_plugins' );
		delete_transient( 'update_themes' );
		
		// Log deactivation
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[' . self::LOG_PREFIX . '] Plugin deactivated' );
		}
		
		do_action( 'giu_plugin_deactivated' );
	}

	/**
	 * Plugin uninstall hook
	 */
	public static function on_uninstall() : void {
		// Remove all plugin options
		delete_option( self::OPTION );
		
		// Remove all transients and temporary options
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_giu_%',
				'_transient_timeout_giu_%'
			)
		);
		
		// Clear any user meta related to flash notices
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_giu_flash_%'
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_timeout_giu_flash_%'
			)
		);
		
		// Log uninstall
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[' . self::LOG_PREFIX . '] Plugin uninstalled' );
		}
		
		do_action( 'giu_plugin_uninstalled' );
	}
}

// Initialize plugin
add_action( 'plugins_loaded', function(){ new GIU_Plugin(); } );

// Plugin activation hook
register_activation_hook( __FILE__, [ 'GIU_Plugin', 'on_activation' ] );

// Plugin deactivation hook
register_deactivation_hook( __FILE__, [ 'GIU_Plugin', 'on_deactivation' ] );

// Plugin uninstall hook
register_uninstall_hook( __FILE__, [ 'GIU_Plugin', 'on_uninstall' ] );

/*
USAGE
-----
1) Install this plugin like any other.
2) Go to Settings → GitHub Installer:
   - Paste a GitHub personal access token (fine‑grained is recommended; read access to the repos).
   - Add a repo: choose Plugin or Theme, enter "owner/repo" and the WordPress slug:
       * Plugins: plugin_basename, e.g. my-plugin/my-plugin.php
       * Themes: directory name, e.g. twentytwentyfive
   - Optionally tick "Install Now" to download and install from the latest Release.
3) Updates: When the repo publishes a new Release (tag), WordPress will see it on the next update check and offer to update. The tag name (e.g. v1.2.3) is used as version (v is stripped). Ensure your plugin/theme header Version matches your latest tag after the first install.

Notes
-----
• If a repo has no releases, the installer falls back to the default branch (main). Updates are only automatically offered for Releases.
• Private repos require the token.
• GitHub API rate limits apply; token raises limits.
*/
