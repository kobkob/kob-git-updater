<?php

namespace KobGitUpdater\Repository;

use KobGitUpdater\Core\Interfaces\GitHubApiClientInterface;
use KobGitUpdater\Repository\Models\Repository;
use KobGitUpdater\Utils\Logger;

/**
 * Repository Manager
 *
 * Manages plugin and theme repositories, handles CRUD operations,
 * and provides repository-specific functionality.
 */
class RepositoryManager {

	/** @var GitHubApiClientInterface */
	private $github_client;

	/** @var Logger */
	private $logger;

	/** @var string WordPress option name for storing repositories */
	private const OPTION_NAME = 'giu_repositories';

	public function __construct( GitHubApiClientInterface $github_client, Logger $logger ) {
		$this->github_client = $github_client;
		$this->logger        = $logger;
	}

	/**
	 * Get all repositories
	 *
	 * @return Repository[]
	 */
	public function get_all(): array {
		$repositories_data = get_option( self::OPTION_NAME, array() );
		$repositories      = array();

		foreach ( $repositories_data as $repo_data ) {
			try {
				$repositories[] = Repository::from_array( $repo_data );
			} catch ( \InvalidArgumentException $e ) {
				$this->logger->error( 'Invalid repository data: ' . $e->getMessage() );
				continue;
			}
		}

		return $repositories;
	}

	/**
	 * Get repository by key (owner/repo format)
	 */
	public function get( string $key ): ?Repository {
		$repositories = $this->get_all();

		foreach ( $repositories as $repository ) {
			if ( $repository->get_key() === $key ) {
				return $repository;
			}
		}

		return null;
	}

	/**
	 * Add a new repository
	 */
	public function add( string $owner, string $repo, string $type, string $slug ): bool {
		// Validate repository type
		if ( ! in_array( $type, array( 'plugin', 'theme' ), true ) ) {
			$this->logger->error( "Invalid repository type: {$type}" );
			return false;
		}

		// Validate owner/repo format
		if ( ! $this->validate_owner_repo( $owner, $repo ) ) {
			return false;
		}

		// Check if repository already exists
		$key = "{$owner}/{$repo}";
		if ( $this->get( $key ) !== null ) {
			$this->logger->error( "Repository {$key} already exists" );
			return false;
		}

		// Verify repository exists on GitHub
		$repo_info = $this->github_client->get_repository( $owner, $repo );
		if ( $repo_info === null ) {
			$this->logger->error( "Failed to verify repository {$key} on GitHub" );
			return false;
		}

		// Create repository object
		$repository = new Repository(
			$owner,
			$repo,
			$type,
			$slug,
			$repo_info['default_branch'] ?? 'main',
			$repo_info['private'] ?? false
		);

		// Add to stored repositories
		$repositories_data   = get_option( self::OPTION_NAME, array() );
		$repositories_data[] = $repository->to_array();

		$success = update_option( self::OPTION_NAME, $repositories_data );

		if ( $success ) {
			$this->logger->info( "Added repository: {$key} (type: {$type}, slug: {$slug})" );

			// Fire action hook for extensions
			do_action( 'giu_repo_added', $repository );
		} else {
			$this->logger->error( "Failed to save repository {$key} to database" );
		}

		return $success;
	}

	/**
	 * Update an existing repository
	 */
	public function update( string $key, array $updates ): bool {
		$repositories = $this->get_all();
		$found        = false;

		foreach ( $repositories as $index => $repository ) {
			if ( $repository->get_key() === $key ) {
				// Apply updates
				if ( isset( $updates['slug'] ) ) {
					$repository->set_slug( $updates['slug'] );
				}
				if ( isset( $updates['default_branch'] ) ) {
					$repository->set_default_branch( $updates['default_branch'] );
				}

				$found = true;
				break;
			}
		}

		if ( ! $found ) {
			$this->logger->error( "Repository {$key} not found for update" );
			return false;
		}

		// Save updated repositories
		$repositories_data = array_map( fn( $repo ) => $repo->to_array(), $repositories );
		$success           = update_option( self::OPTION_NAME, $repositories_data );

		if ( $success ) {
			$this->logger->info( "Updated repository: {$key}" );
		} else {
			$this->logger->error( "Failed to update repository {$key}" );
		}

		return $success;
	}

	/**
	 * Remove a repository
	 */
	public function remove( string $key ): bool {
		$repositories          = $this->get_all();
		$filtered_repositories = array();
		$found                 = false;

		foreach ( $repositories as $repository ) {
			if ( $repository->get_key() === $key ) {
				$found = true;
				continue; // Skip this repository (remove it)
			}
			$filtered_repositories[] = $repository;
		}

		if ( ! $found ) {
			$this->logger->error( "Repository {$key} not found for removal" );
			return false;
		}

		// Save filtered repositories
		$repositories_data = array_map( fn( $repo ) => $repo->to_array(), $filtered_repositories );
		$success           = update_option( self::OPTION_NAME, $repositories_data );

		if ( $success ) {
			$this->logger->info( "Removed repository: {$key}" );

			// Fire action hook for extensions
			do_action( 'giu_repo_removed', $key );
		} else {
			$this->logger->error( "Failed to remove repository {$key}" );
		}

		return $success;
	}

	/**
	 * Get repositories by type
	 *
	 * @return Repository[]
	 */
	public function get_by_type( string $type ): array {
		$all_repositories = $this->get_all();

		return array_filter( $all_repositories, fn( $repo ) => $repo->get_type() === $type );
	}

	/**
	 * Check for available updates across all repositories
	 *
	 * @return array Array of repositories with available updates
	 */
	public function check_for_updates(): array {
		$repositories      = $this->get_all();
		$updates_available = array();

		foreach ( $repositories as $repository ) {
			$update_info = $this->check_repository_update( $repository );
			if ( $update_info !== null ) {
				$updates_available[] = array(
					'repository'  => $repository,
					'update_info' => $update_info,
				);
			}
		}

		return $updates_available;
	}

	/**
	 * Check if a specific repository has updates
	 */
	public function check_repository_update( Repository $repository ): ?array {
		$current_version = $this->get_current_version( $repository );
		if ( $current_version === null ) {
			$this->logger->info( "No current version found for {$repository->get_key()}, skipping update check" );
			return null;
		}

		// Ensure GitHub client has the latest token
		$token = get_option( 'giu_github_token', '' );
		$this->github_client->set_token( $token );

		$this->logger->info( "Checking updates for {$repository->get_key()} (current: {$current_version}) with token: " . ( ! empty( $token ) ? 'YES' : 'NO' ) );

		// Try to get latest release first
		$latest_release = $this->github_client->get_latest_release(
			$repository->get_owner(),
			$repository->get_repo()
		);

		if ( $latest_release !== null ) {
			$latest_version = ltrim( $latest_release['tag_name'], 'v' );

			if ( version_compare( $latest_version, $current_version, '>' ) ) {
				return array(
					'version'       => $latest_version,
					'download_url'  => $this->github_client->get_download_url(
						$repository->get_owner(),
						$repository->get_repo(),
						$latest_release['tag_name']
					),
					'release_notes' => $latest_release['body'] ?? '',
					'release_date'  => $latest_release['published_at'] ?? '',
				);
			}
		}

		// If no releases, check if this is a branch-based repository
		// For branch-based repositories without releases, only show updates when explicitly needed
		$this->logger->info( "No releases found for {$repository->get_key()}, checking branch-based update logic" );

		// Get repository info to verify it exists and is accessible
		$repo_info = $this->github_client->get_repository(
			$repository->get_owner(),
			$repository->get_repo()
		);

		if ( $repo_info !== null ) {
			// For branch-based repositories, we need smarter update detection
			$branch_version = 'dev-' . $repository->get_default_branch();

			$this->logger->info( "Comparing versions for {$repository->get_key()}: current='{$current_version}' vs branch='{$branch_version}'" );

			// Case 1: For repositories without releases, don't show updates for stable versions
			// unless we can determine the branch has been updated since the installed version
			if ( ! empty( $current_version ) && strpos( $current_version, 'dev-' ) !== 0 ) {
				// Current version appears to be a release version (e.g., "0.1.0", "1.2.3")
				// For repositories without releases, this likely means the theme/plugin
				// was installed with a version tag in its header but the repository
				// doesn't use GitHub releases. In this case, don't show updates
				// unless the user explicitly wants to switch to development versions.
				$this->logger->info( "Repository {$repository->get_key()} has stable version {$current_version} but no releases. Not showing update to avoid false positives." );
				return null;
			}

			// Case 2: Repository uses branch-only development (no update unless current version differs)
			if ( $current_version === $branch_version ) {
				$this->logger->info( 'Current version matches branch version, no update needed' );
				return null;
			}

			// Case 3: First install or version mismatch - allow update
			if ( empty( $current_version ) ) {
				$this->logger->info( 'First installation of branch-based repository' );
			} else {
				$this->logger->info( 'Version mismatch detected, update available' );
			}

			return array(
				'version'       => $branch_version,
				'download_url'  => $this->github_client->get_download_url(
					$repository->get_owner(),
					$repository->get_repo(),
					$repository->get_default_branch()
				),
				'release_notes' => 'Development version from ' . $repository->get_default_branch() . ' branch',
				'release_date'  => $repo_info['updated_at'] ?? '',
			);
		} else {
			// Repository is not accessible - provide detailed diagnostic information
			$this->logger->error( "Repository {$repository->get_key()} is not accessible - both releases and repository info failed" );

			// Log diagnostic information for troubleshooting
			$this->logger->error( "Diagnostic info for {$repository->get_key()}:" );
			$this->logger->error( "- Repository URL: https://github.com/{$repository->get_owner()}/{$repository->get_repo()}" );
			$this->logger->error( "- Repository Type: {$repository->get_type()}" );
			$this->logger->error( "- Repository Slug: {$repository->get_slug()}" );
			$this->logger->error( '- Is Private: ' . ( $repository->is_private() ? 'Yes' : 'No' ) );
			$this->logger->error( '- Default Branch: ' . ( $repository->get_default_branch() ?: 'Not specified' ) );
			$this->logger->error( '- Token Provided: ' . ( ! empty( $token ) ? 'Yes (' . strlen( $token ) . ' chars)' : 'No' ) );

			if ( ! empty( $token ) ) {
				$this->logger->error( '- Token Prefix: ' . substr( $token, 0, 7 ) . '...' );
				$this->logger->error( "- Possible causes: Token lacks permissions, expired, or repository doesn't exist" );
			} else {
				$this->logger->error( "- Possible causes: Repository is private, doesn't exist, or requires authentication" );
			}
		}

		return null;
	}

	/**
	 * Get current version of installed plugin/theme
	 */
	private function get_current_version( Repository $repository ): ?string {
		if ( $repository->get_type() === 'plugin' ) {
			return $this->get_plugin_version( $repository->get_slug() );
		} else {
			return $this->get_theme_version( $repository->get_slug() );
		}
	}

	/**
	 * Get current plugin version
	 */
	private function get_plugin_version( string $plugin_slug ): ?string {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_file = WP_PLUGIN_DIR . '/' . $plugin_slug;

		// Try different plugin file locations
		$possible_files = array(
			$plugin_file . '.php',
			$plugin_file . '/' . basename( $plugin_slug ) . '.php',
			$plugin_file . '/index.php',
		);

		foreach ( $possible_files as $file ) {
			if ( file_exists( $file ) ) {
				$plugin_data = get_plugin_data( $file );
				return $plugin_data['Version'] ?? null;
			}
		}

		return null;
	}

	/**
	 * Get current theme version
	 */
	private function get_theme_version( string $theme_slug ): ?string {
		$theme = wp_get_theme( $theme_slug );

		if ( $theme->exists() ) {
			return $theme->get( 'Version' );
		}

		return null;
	}

	/**
	 * Validate owner and repository name format
	 */
	private function validate_owner_repo( string $owner, string $repo ): bool {
		// GitHub username/organization rules: 1-39 chars, alphanumeric + hyphens, can't start/end with hyphen
		if ( ! preg_match( '/^[a-zA-Z0-9]([a-zA-Z0-9-]{0,37}[a-zA-Z0-9])?$/', $owner ) ) {
			$this->logger->error( "Invalid GitHub owner format: {$owner}" );
			return false;
		}

		// Repository name rules: similar to username but allows dots and underscores
		if ( ! preg_match( '/^[a-zA-Z0-9._-]+$/', $repo ) ) {
			$this->logger->error( "Invalid GitHub repository format: {$repo}" );
			return false;
		}

		return true;
	}

	/**
	 * Refresh repository information from GitHub
	 */
	public function refresh_repository_info( string $key ): bool {
		$repository = $this->get( $key );
		if ( $repository === null ) {
			return false;
		}

		$repo_info = $this->github_client->get_repository(
			$repository->get_owner(),
			$repository->get_repo()
		);

		if ( $repo_info === null ) {
			return false;
		}

		// Update repository with fresh information
		return $this->update(
			$key,
			array(
				'default_branch' => $repo_info['default_branch'] ?? 'main',
			)
		);
	}

	/**
	 * Clear all repository caches
	 */
	public function clear_caches(): void {
		$repositories = $this->get_all();

		foreach ( $repositories as $repository ) {
			$this->github_client->clear_cache(
				$repository->get_owner(),
				$repository->get_repo()
			);
		}

		$this->logger->info( 'Cleared all repository caches' );
	}
}
