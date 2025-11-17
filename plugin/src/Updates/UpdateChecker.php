<?php

namespace KobGitUpdater\Updates;

use KobGitUpdater\Repository\RepositoryManager;
use KobGitUpdater\GitHub\GitHubApiClient;
use KobGitUpdater\Utils\Logger;

/**
 * WordPress Update System Integration
 *
 * Integrates with WordPress's update system to show available updates
 * for plugins and themes from GitHub repositories.
 */
class UpdateChecker {

	/** @var RepositoryManager */
	private $repository_manager;

	/** @var GitHubApiClient */
	private $github_client;

	/** @var Logger */
	private $logger;

	public function __construct( RepositoryManager $repository_manager, GitHubApiClient $github_client, Logger $logger ) {
		$this->repository_manager = $repository_manager;
		$this->github_client      = $github_client;
		$this->logger             = $logger;
	}

	/**
	 * Initialize update checker hooks
	 */
	public function init(): void {
		// Hook into WordPress update checks
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_plugin_updates' ) );
		add_filter( 'pre_set_site_transient_update_themes', array( $this, 'check_theme_updates' ) );

		// Add download/install functionality
		add_action( 'wp_ajax_kgu_install_repository', array( $this, 'handle_install_repository' ) );
		add_action( 'wp_ajax_kgu_update_repository', array( $this, 'handle_update_repository' ) );

		// Add admin notices for updates
		add_action( 'admin_notices', array( $this, 'show_update_notices' ) );
	}

	/**
	 * Check for plugin updates
	 */
	public function check_plugin_updates( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new \stdClass();
		}

		if ( ! isset( $transient->response ) ) {
			$transient->response = array();
		}

		// Ensure GitHub client has the latest token before checking for updates
		$token = get_option( 'giu_github_token', '' );
		$this->github_client->set_token( $token );
		$this->logger->info( 'Checking plugin updates with token: ' . ( ! empty( $token ) ? 'YES' : 'NO' ) );

		$plugin_repositories = $this->repository_manager->get_by_type( 'plugin' );

		foreach ( $plugin_repositories as $repository ) {
			$plugin_file = $this->get_plugin_file( $repository->get_slug() );

			if ( ! $plugin_file ) {
				continue; // Plugin not installed
			}

			$update_info = $this->repository_manager->check_repository_update( $repository );

			if ( $update_info ) {
				$transient->response[ $plugin_file ] = (object) array(
					'slug'          => $repository->get_slug(),
					'plugin'        => $plugin_file,
					'new_version'   => $update_info['version'],
					'url'           => "https://github.com/{$repository->get_owner()}/{$repository->get_repo()}",
					'package'       => $update_info['download_url'],
					'icons'         => array(
						'default' => '/wp-content/plugins/kob-git-updater/assets/img/logo_en.jpg',
					),
					'banners'       => array(),
					'banners_rtl'   => array(),
					'tested'        => '6.4',
					'requires_php'  => '8.1',
					'compatibility' => new \stdClass(),
				);

				$this->logger->info( "Plugin update available: {$repository->get_key()} v{$update_info['version']}" );
			}
		}

		return $transient;
	}

	/**
	 * Check for theme updates
	 */
	public function check_theme_updates( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new \stdClass();
		}

		if ( ! isset( $transient->response ) ) {
			$transient->response = array();
		}

		// Ensure GitHub client has the latest token before checking for updates
		$token = get_option( 'giu_github_token', '' );
		$this->github_client->set_token( $token );
		$this->logger->info( 'Checking theme updates with token: ' . ( ! empty( $token ) ? 'YES' : 'NO' ) );

		$theme_repositories = $this->repository_manager->get_by_type( 'theme' );

		foreach ( $theme_repositories as $repository ) {
			$theme_slug = $repository->get_slug();
			$theme      = wp_get_theme( $theme_slug );

			if ( ! $theme->exists() ) {
				// Theme not installed - we'll handle this in the UI
				continue;
			}

			$update_info = $this->repository_manager->check_repository_update( $repository );

			if ( $update_info ) {
				$transient->response[ $theme_slug ] = array(
					'theme'       => $theme_slug,
					'new_version' => $update_info['version'],
					'url'         => "https://github.com/{$repository->get_owner()}/{$repository->get_repo()}",
					'package'     => $update_info['download_url'],
				);

				$this->logger->info( "Theme update available: {$repository->get_key()} v{$update_info['version']}" );
			}
		}

		return $transient;
	}

	/**
	 * Handle repository installation via AJAX
	 */
	public function handle_install_repository(): void {
		check_ajax_referer( 'kgu_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_die( 'Insufficient permissions' );
		}

		$repository_key = sanitize_text_field( $_POST['repository_key'] ?? '' );
		$repository     = $this->repository_manager->get( $repository_key );

		if ( ! $repository ) {
			wp_send_json_error( 'Repository not found' );
			return;
		}

		// Ensure GitHub client has the latest token before making requests
		$token = get_option( 'giu_github_token', '' );
		$this->github_client->set_token( $token );
		$this->logger->info( "Installing repository {$repository_key} with token: " . ( ! empty( $token ) ? 'YES' : 'NO' ) );

		// Get latest release or fallback to branch with enhanced error handling
		$this->logger->info( "Fetching latest release for {$repository->get_owner()}/{$repository->get_repo()}" );
		$latest_release = $this->github_client->get_latest_release( $repository->get_owner(), $repository->get_repo() );

		$download_url = null;
		$version      = null;

		if ( $latest_release ) {
			$this->logger->info( "Found latest release: {$latest_release['tag_name']}" );
			$download_url = $this->github_client->get_download_url(
				$repository->get_owner(),
				$repository->get_repo(),
				$latest_release['tag_name']
			);
			$version      = ltrim( $latest_release['tag_name'], 'v' );
		} else {
			$this->logger->info( 'No releases found, trying default branches' );

			// Try multiple common default branch names
			$default_branches = array( $repository->get_default_branch() ?: 'main', 'main', 'master', 'develop' );
			$default_branches = array_unique( array_filter( $default_branches ) ); // Remove duplicates and empty values

			foreach ( $default_branches as $branch ) {
				$this->logger->info( "Trying branch: {$branch}" );
				$test_download_url = $this->github_client->get_download_url(
					$repository->get_owner(),
					$repository->get_repo(),
					$branch
				);

				// For repositories with access issues, we'll trust that the configured default branch exists
				// This avoids false negatives when HEAD requests fail due to authentication/permission issues
				if ( $branch === $repository->get_default_branch() || $branch === 'main' ) {
					$this->logger->info( "Using trusted branch: {$branch}" );
					$download_url = $test_download_url;
					$version      = $branch;
					break;
				}

				// For other branches, test if they're accessible
				if ( $this->test_download_url( $test_download_url ) ) {
					$download_url = $test_download_url;
					$version      = $branch;
					$this->logger->info( "Successfully found branch: {$branch}" );
					break;
				} else {
					$this->logger->info( "Branch {$branch} not accessible via HEAD request" );
				}
			}

			if ( ! $download_url ) {
				$this->logger->error( "No accessible branches found for {$repository->get_owner()}/{$repository->get_repo()}" );
				$error_message = $this->build_repository_error_message( $repository, $token );
				wp_send_json_error( $error_message );
				return;
			}
		}

		// Final check: if we still don't have a download URL, something is wrong
		if ( ! $download_url ) {
			$this->logger->error( "Unable to determine download URL for {$repository->get_owner()}/{$repository->get_repo()}" );
			$error_message = $this->build_repository_error_message( $repository, $token );
			wp_send_json_error( $error_message );
			return;
		}

		$result = $this->install_from_github( $repository, $download_url, $version );

		if ( $result['success'] ) {
			wp_send_json_success( $result['message'] );
		} else {
			wp_send_json_error( $result['message'] );
		}
	}

	/**
	 * Handle repository update via AJAX
	 */
	public function handle_update_repository(): void {
		// Update is the same as install - we overwrite the existing installation
		$this->handle_install_repository();
	}

	/**
	 * Install plugin/theme from GitHub
	 */
	private function install_from_github( $repository, $download_url, $version ): array {
		try {
			// Initialize WordPress filesystem
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			// Force filesystem method to 'direct' for Docker environment
			$credentials = array(
				'method'   => 'direct',
				'hostname' => 'localhost',
				'username' => 'www-data',
			);

			// Try to initialize filesystem with direct method
			$filesystem_init = WP_Filesystem( $credentials );
			if ( ! $filesystem_init ) {
				// Fallback to default method
				$filesystem_init = WP_Filesystem();
			}

			if ( ! $filesystem_init ) {
				return array(
					'success' => false,
					'message' => 'Could not initialize WordPress filesystem. Please check file permissions.',
				);
			}

			$this->logger->info( 'WordPress filesystem initialized successfully' );

			// Download the archive with authentication
			$temp_file = $this->download_with_auth( $download_url );

			if ( is_wp_error( $temp_file ) ) {
				return array(
					'success' => false,
					'message' => 'Failed to download: ' . $temp_file->get_error_message(),
				);
			}

			// Create temporary extraction directory
			$temp_extract_dir = wp_tempnam();
			@unlink( $temp_extract_dir ); // Remove file, we need directory

			$this->logger->info( "Creating temp extraction directory: {$temp_extract_dir}" );

			// Use WordPress filesystem API with fallback to native PHP
			global $wp_filesystem;
			if ( $wp_filesystem && ! $wp_filesystem->mkdir( $temp_extract_dir, 0755 ) ) {
				// Fallback to native PHP
				if ( ! mkdir( $temp_extract_dir, 0755, true ) ) {
					@unlink( $temp_file );
					return array(
						'success' => false,
						'message' => 'Failed to create temporary extraction directory',
					);
				}
				$this->logger->info( 'Used native PHP mkdir as fallback' );
			} elseif ( ! $wp_filesystem ) {
				// WordPress filesystem not available, use native PHP
				if ( ! mkdir( $temp_extract_dir, 0755, true ) ) {
					@unlink( $temp_file );
					return array(
						'success' => false,
						'message' => 'Failed to create temporary extraction directory',
					);
				}
				$this->logger->info( 'Used native PHP mkdir (WP filesystem unavailable)' );
			}

			// Extract archive to temp directory first
			$this->logger->info( "Extracting {$temp_file} to {$temp_extract_dir}" );
			$result = unzip_file( $temp_file, $temp_extract_dir );

			if ( is_wp_error( $result ) ) {
				@unlink( $temp_file );
				$this->cleanup_directory( $temp_extract_dir );
				$error_message = $result->get_error_message();
				$this->logger->error( "Extraction failed: {$error_message}" );
				return array(
					'success' => false,
					'message' => 'Failed to extract: ' . $error_message,
				);
			}

			$this->logger->info( 'Archive extracted successfully' );

			// GitHub archives have a root directory like "repo-name-commit-hash"
			// Find the actual content directory
			$extracted_items = scandir( $temp_extract_dir );
			$content_dir     = null;

			foreach ( $extracted_items as $item ) {
				if ( $item !== '.' && $item !== '..' && is_dir( $temp_extract_dir . '/' . $item ) ) {
					$content_dir = $temp_extract_dir . '/' . $item;
					break;
				}
			}

			if ( ! $content_dir || ! is_dir( $content_dir ) ) {
				@unlink( $temp_file );
				$this->cleanup_directory( $temp_extract_dir );
				return array(
					'success' => false,
					'message' => 'Archive does not contain expected directory structure',
				);
			}

			// Determine final installation path
			if ( $repository->get_type() === 'plugin' ) {
				$install_path = WP_PLUGIN_DIR . '/' . $repository->get_slug();
			} else {
				$install_path = WP_CONTENT_DIR . '/themes/' . $repository->get_slug();
			}

			// Remove existing installation if it exists
			if ( $wp_filesystem->exists( $install_path ) ) {
				$this->logger->info( "Removing existing installation at {$install_path}" );
				if ( ! $wp_filesystem->delete( $install_path, true ) ) {
					@unlink( $temp_file );
					$this->cleanup_directory( $temp_extract_dir );
					return array(
						'success' => false,
						'message' => 'Failed to remove existing installation',
					);
				}
			}

			// Create parent directory
			$parent_dir = dirname( $install_path );
			if ( ! $wp_filesystem->exists( $parent_dir ) ) {
				$this->logger->info( "Creating parent directory: {$parent_dir}" );
				if ( ! $wp_filesystem->mkdir( $parent_dir, 0755 ) ) {
					@unlink( $temp_file );
					$this->cleanup_directory( $temp_extract_dir );
					return array(
						'success' => false,
						'message' => 'Failed to create parent directory',
					);
				}
			}

			// Move extracted content to final location with robust error handling
			$this->logger->info( "Moving {$content_dir} to {$install_path}" );

			$move_success  = false;
			$error_details = array();

			// Method 1: Try WordPress filesystem move
			if ( $wp_filesystem && $wp_filesystem->exists( $content_dir ) ) {
				$this->logger->info( 'Attempting WordPress filesystem move' );
				$move_success = $wp_filesystem->move( $content_dir, $install_path );
				if ( ! $move_success ) {
					$error_details[] = 'WordPress filesystem move failed';
					$this->logger->info( 'WordPress filesystem move failed, checking permissions' );

					// Debug permission information
					$parent_perms  = $wp_filesystem->exists( dirname( $install_path ) ) ?
						substr( sprintf( '%o', fileperms( dirname( $install_path ) ) ), -4 ) : 'N/A';
					$content_perms = file_exists( $content_dir ) ?
						substr( sprintf( '%o', fileperms( $content_dir ) ), -4 ) : 'N/A';

					$this->logger->info( "Permissions - Parent dir: {$parent_perms}, Content dir: {$content_perms}" );
				}
			}

			// Method 2: Try WordPress filesystem recursive copy (fix for directory copy issue)
			if ( ! $move_success && $wp_filesystem && $wp_filesystem->exists( $content_dir ) ) {
				$this->logger->info( 'Attempting WordPress filesystem recursive copy-and-delete method' );

				// WordPress filesystem copy() doesn't handle directories properly, so use our recursive method
				if ( $this->wp_filesystem_recursive_copy( $content_dir, $install_path, $wp_filesystem ) ) {
					if ( $wp_filesystem->delete( $content_dir, true ) ) {
						$move_success = true;
						$this->logger->info( 'WordPress filesystem recursive copy-and-delete method succeeded' );
					} else {
						// Copy succeeded but delete failed - this is still success
						$move_success = true;
						$this->logger->info( 'WordPress filesystem recursive copy succeeded, delete of temp failed (acceptable)' );
					}
				} else {
					$error_details[] = 'WordPress filesystem recursive copy failed';
				}
			}

			// Method 3: Native PHP fallback with recursive copy
			if ( ! $move_success && file_exists( $content_dir ) ) {
				$this->logger->info( 'Attempting native PHP recursive copy' );
				if ( $this->recursive_copy( $content_dir, $install_path ) ) {
					$move_success = true;
					$this->logger->info( 'Native PHP recursive copy succeeded' );
					// Clean up source directory
					$this->cleanup_directory( $content_dir );
				} else {
					$error_details[] = 'Native PHP recursive copy failed';
				}
			}

			// Method 4: Last resort - exec/shell commands if available
			if ( ! $move_success && function_exists( 'exec' ) && file_exists( $content_dir ) ) {
				$this->logger->info( 'Attempting shell command fallback' );
				$escaped_source = escapeshellarg( $content_dir );
				$escaped_dest   = escapeshellarg( $install_path );

				// Try cp -r first
				$output      = array();
				$return_code = 0;
				exec( "cp -r {$escaped_source} {$escaped_dest} 2>&1", $output, $return_code );

				if ( $return_code === 0 && is_dir( $install_path ) ) {
					$move_success = true;
					$this->logger->info( 'Shell cp command succeeded' );
					$this->cleanup_directory( $content_dir );
				} else {
					$error_details[] = 'Shell cp command failed: ' . implode( ', ', $output );

					// Try mv as final attempt
					$output      = array();
					$return_code = 0;
					exec( "mv {$escaped_source} {$escaped_dest} 2>&1", $output, $return_code );

					if ( $return_code === 0 && is_dir( $install_path ) ) {
						$move_success = true;
						$this->logger->info( 'Shell mv command succeeded' );
					} else {
						$error_details[] = 'Shell mv command failed: ' . implode( ', ', $output );
					}
				}
			}

			if ( ! $move_success ) {
				@unlink( $temp_file );
				$this->cleanup_directory( $temp_extract_dir );

				$error_message = 'Failed to move extracted files to installation directory';
				if ( ! empty( $error_details ) ) {
					$error_message .= '. Attempted methods: ' . implode( '; ', $error_details );
				}

				$this->logger->error( 'All move methods failed: ' . implode( '; ', $error_details ) );

				return array(
					'success' => false,
					'message' => $error_message,
				);
			}

			// Clean up temp files
			@unlink( $temp_file );
			$this->cleanup_directory( $temp_extract_dir );

			// Fix permissions
			$this->fix_installation_permissions( $install_path );

			$this->logger->info( "Successfully installed {$repository->get_key()} v{$version} to {$install_path}" );

			return array(
				'success' => true,
				'message' => ucfirst( $repository->get_type() ) . " {$repository->get_key()} v{$version} installed successfully!",
			);

		} catch ( Exception $e ) {
			$this->logger->error( "Installation failed for {$repository->get_key()}: " . $e->getMessage() );
			return array(
				'success' => false,
				'message' => 'Installation failed: ' . $e->getMessage(),
			);
		}
	}

	/**
	 * Fix file permissions after installation
	 */
	private function fix_installation_permissions( string $path ): void {
		if ( function_exists( 'chmod' ) ) {
			// Set directory permissions
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $path ),
				\RecursiveIteratorIterator::SELF_FIRST
			);

			foreach ( $iterator as $item ) {
				if ( $item->isDir() ) {
					chmod( $item->getPathname(), 0755 );
				} else {
					chmod( $item->getPathname(), 0644 );
				}
			}
		}
	}

	/**
	 * Get plugin file path from slug
	 */
	private function get_plugin_file( string $slug ): ?string {
		$plugins = get_plugins();

		foreach ( $plugins as $plugin_file => $plugin_data ) {
			if ( strpos( $plugin_file, $slug . '/' ) === 0 ) {
				return $plugin_file;
			}
		}

		return null;
	}

	/**
	 * Show update notices in admin
	 */
	public function show_update_notices(): void {
		// Ensure GitHub client has the latest token before checking for updates
		$token = get_option( 'giu_github_token', '' );
		$this->github_client->set_token( $token );

		$updates_available = $this->repository_manager->check_for_updates();

		if ( empty( $updates_available ) ) {
			return;
		}

		foreach ( $updates_available as $update ) {
			$repository  = $update['repository'];
			$update_info = $update['update_info'];

			$is_installed = $repository->get_type() === 'plugin'
				? (bool) $this->get_plugin_file( $repository->get_slug() )
				: wp_get_theme( $repository->get_slug() )->exists();

			if ( ! $is_installed ) {
				echo '<div class="notice notice-info is-dismissible">';
				echo '<p><strong>Kob Git Updater:</strong> ';
				echo esc_html( ucfirst( $repository->get_type() ) ) . ' ';
				echo '<strong>' . esc_html( $repository->get_key() ) . '</strong> ';
				echo 'is configured but not installed. ';
				echo '<a href="' . esc_url( admin_url( 'admin.php?page=git-updater' ) ) . '">Install now</a>';
				echo '</p></div>';
			}
		}
	}

	/**
	 * Download file with GitHub authentication
	 */
	private function download_with_auth( string $url ) {
		$args = array(
			'timeout' => 300, // 5 minutes for large files
			'headers' => array(
				'User-Agent' => $this->get_user_agent(),
			),
		);

		// Add authentication if token is available
		$token = get_option( 'giu_github_token', '' );
		if ( ! empty( $token ) ) {
			$args['headers']['Authorization'] = 'token ' . $token;
			$this->logger->info( 'Downloading with authentication token' );
		} else {
			$this->logger->info( 'Downloading without authentication token' );
		}

		$this->logger->info( "Downloading from: {$url}" );

		// Use wp_remote_get to download with custom headers
		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code !== 200 ) {
			$body          = wp_remote_retrieve_body( $response );
			$error_data    = json_decode( $body, true );
			$error_message = isset( $error_data['message'] ) ? $error_data['message'] : "HTTP {$response_code}";

			if ( $response_code === 404 ) {
				$error_message .= ' (Repository not found or private)';
			} elseif ( $response_code === 401 ) {
				$error_message .= ' (Invalid or missing authentication token)';
			}

			return new \WP_Error( 'download_failed', $error_message );
		}

		// Get the file content
		$body = wp_remote_retrieve_body( $response );

		// Create temporary file
		$temp_file = wp_tempnam();
		if ( ! $temp_file ) {
			return new \WP_Error( 'temp_file_failed', 'Could not create temporary file' );
		}

		// Write content to temp file
		$bytes_written = file_put_contents( $temp_file, $body );
		if ( $bytes_written === false ) {
			@unlink( $temp_file );
			return new \WP_Error( 'file_write_failed', 'Could not write to temporary file' );
		}

		$this->logger->info( "Downloaded {$bytes_written} bytes to {$temp_file}" );

		return $temp_file;
	}

	/**
	 * Get User-Agent string for requests
	 */
	private function get_user_agent(): string {
		global $wp_version;

		return sprintf(
			'WordPress/%s; %s; Kob-Git-Updater/%s',
			$wp_version ?? 'unknown',
			home_url(),
			defined( 'KGU_VERSION' ) ? KGU_VERSION : '1.3.1'
		);
	}

	/**
	 * WordPress filesystem recursive copy (proper way to copy directories)
	 */
	private function wp_filesystem_recursive_copy( string $source, string $dest, $wp_filesystem ): bool {
		try {
			if ( ! $wp_filesystem->exists( $source ) || ! $wp_filesystem->is_dir( $source ) ) {
				return false;
			}

			// Create destination directory
			if ( ! $wp_filesystem->exists( $dest ) ) {
				if ( ! $wp_filesystem->mkdir( $dest, 0755 ) ) {
					$this->logger->error( "Failed to create destination directory via wp_filesystem: {$dest}" );
					return false;
				}
			}

			$files = $wp_filesystem->dirlist( $source, true, true );
			if ( empty( $files ) ) {
				$this->logger->error( "No files found in source directory: {$source}" );
				return false;
			}

			foreach ( $files as $file_name => $file_info ) {
				$source_path = trailingslashit( $source ) . $file_name;
				$dest_path   = trailingslashit( $dest ) . $file_name;

				if ( $file_info['type'] === 'd' ) {
					// Recursively copy subdirectory
					if ( ! $this->wp_filesystem_recursive_copy( $source_path, $dest_path, $wp_filesystem ) ) {
						return false;
					}
				} else {
					// Copy file
					if ( ! $wp_filesystem->copy( $source_path, $dest_path, false, 0644 ) ) {
						$this->logger->error( "Failed to copy file: {$source_path} to {$dest_path}" );
						return false;
					}
				}
			}

			return true;

		} catch ( Exception $e ) {
			$this->logger->error( 'WordPress filesystem recursive copy failed: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Test if a download URL is accessible
	 */
	private function test_download_url( string $url ): bool {
		$args = array(
			'method'  => 'HEAD',
			'timeout' => 10,
			'headers' => array(
				'User-Agent' => $this->get_user_agent(),
			),
		);

		// Add authentication if token is available
		$token = get_option( 'giu_github_token', '' );
		if ( ! empty( $token ) ) {
			$args['headers']['Authorization'] = 'token ' . $token;
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->logger->info( 'HEAD request failed: ' . $response->get_error_message() );
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		return $response_code === 200;
	}

	/**
	 * Build detailed error message for repository access issues
	 */
	private function build_repository_error_message( $repository, string $token ): string {
		$repo_url = "https://github.com/{$repository->get_owner()}/{$repository->get_repo()}";
		$message  = "Unable to access repository: {$repository->get_owner()}/{$repository->get_repo()}\n\n";

		// Repository details
		$message .= "Repository Details:\n";
		$message .= "- URL: {$repo_url}\n";
		$message .= "- Type: {$repository->get_type()}\n";
		$message .= "- Slug: {$repository->get_slug()}\n";
		$message .= '- Is Private: ' . ( $repository->is_private() ? 'Yes' : 'No' ) . "\n";
		$message .= '- Default Branch: ' . ( $repository->get_default_branch() ?: 'Not specified' ) . "\n\n";

		// Token status
		$message .= "Authentication Status:\n";
		if ( empty( $token ) ) {
			$message .= "- GitHub Token: NOT PROVIDED\n";
			$message .= "- Public repositories only are accessible\n\n";
		} else {
			$message .= '- GitHub Token: PROVIDED (' . strlen( $token ) . " characters)\n";
			$message .= '- Token prefix: ' . substr( $token, 0, 7 ) . "...\n\n";
		}

		// Possible causes and solutions
		$message .= "Possible Causes:\n";
		if ( empty( $token ) ) {
			$message .= "1. Repository is private and requires authentication\n";
			$message .= "2. Repository does not exist or was renamed\n";
			$message .= "3. Repository owner/name is incorrect\n\n";
			$message .= "Solutions:\n";
			$message .= "1. If private: Add GitHub token in plugin settings\n";
			$message .= "2. Verify repository exists: {$repo_url}\n";
			$message .= "3. Check repository owner/name spelling\n";
		} else {
			$message .= "1. Repository does not exist or was renamed\n";
			$message .= "2. Repository owner/name is incorrect\n";
			$message .= "3. GitHub token lacks required permissions\n";
			$message .= "4. GitHub token has expired or been revoked\n\n";
			$message .= "Solutions:\n";
			$message .= "1. Verify repository exists: {$repo_url}\n";
			$message .= "2. Check repository owner/name spelling\n";
			$message .= "3. Verify token has 'repo' or 'contents:read' permissions\n";
			$message .= "4. Generate a new GitHub token if needed\n";
		}

		return $message;
	}

	/**
	 * Recursively copy directory contents (fallback for wp_filesystem issues)
	 */
	private function recursive_copy( string $source, string $dest ): bool {
		try {
			if ( ! is_dir( $source ) ) {
				return false;
			}

			// Create destination directory
			if ( ! is_dir( $dest ) ) {
				if ( ! mkdir( $dest, 0755, true ) ) {
					$this->logger->error( "Failed to create destination directory: {$dest}" );
					return false;
				}
			}

			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $source, \RecursiveDirectoryIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::SELF_FIRST
			);

			foreach ( $iterator as $item ) {
				$target_path = $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName();

				if ( $item->isDir() ) {
					if ( ! is_dir( $target_path ) ) {
						if ( ! mkdir( $target_path, 0755, true ) ) {
							$this->logger->error( "Failed to create directory: {$target_path}" );
							return false;
						}
					}
				} else {
					// Ensure parent directory exists
					$parent_dir = dirname( $target_path );
					if ( ! is_dir( $parent_dir ) ) {
						if ( ! mkdir( $parent_dir, 0755, true ) ) {
							$this->logger->error( "Failed to create parent directory: {$parent_dir}" );
							return false;
						}
					}

					if ( ! copy( $item->getPathname(), $target_path ) ) {
						$this->logger->error( "Failed to copy file: {$item->getPathname()} to {$target_path}" );
						return false;
					}

					// Set file permissions
					chmod( $target_path, 0644 );
				}
			}

			// Set directory permissions
			chmod( $dest, 0755 );

			return true;

		} catch ( \Exception $e ) {
			$this->logger->error( 'Recursive copy failed: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Recursively remove directory and its contents
	 */
	private function cleanup_directory( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $item ) {
			if ( $item->isDir() ) {
				@rmdir( $item->getPathname() );
			} else {
				@unlink( $item->getPathname() );
			}
		}

		@rmdir( $dir );
	}
}
