<?php

namespace KobGitUpdater\GitHub;

use KobGitUpdater\Core\Interfaces\GitHubApiClientInterface;
use KobGitUpdater\Utils\Logger;
use WP_Error;

/**
 * GitHub API Client
 *
 * Handles all GitHub API interactions with caching, rate limiting,
 * and proper error handling.
 */
class GitHubApiClient implements GitHubApiClientInterface {

	/** @var Logger */
	private $logger;

	/** @var string|null */
	private $github_token;

	/** @var int Cache expiration time in seconds */
	private const CACHE_EXPIRATION = 3600; // 1 hour

	/** @var array<string, string> */
	private const API_ENDPOINTS = array(
		'releases'   => 'https://api.github.com/repos/{owner}/{repo}/releases/latest',
		'repository' => 'https://api.github.com/repos/{owner}/{repo}',
		'download'   => 'https://api.github.com/repos/{owner}/{repo}/zipball/{ref}',
		'rate_limit' => 'https://api.github.com/rate_limit',
	);

	public function __construct( Logger $logger, ?string $github_token = null ) {
		$this->logger       = $logger;
		$this->github_token = $github_token;
	}

	/**
	 * Set GitHub authentication token
	 */
	public function set_token( ?string $token ): void {
		$old_token = $this->github_token;
		$this->github_token = $token;
		
		if ( ! empty( $token ) ) {
			$masked_token = substr( $token, 0, 6 ) . '...' . substr( $token, -4 );
			$this->logger->info( "GitHub token updated: {$masked_token}" );
		} else {
			$this->logger->info( 'GitHub token cleared' );
		}
		
		// Clear API cache if token changed to avoid using cached responses from different auth state
		if ( $old_token !== $token ) {
			$this->clear_api_cache();
			$this->logger->info( 'API cache cleared due to token change' );
		}
	}

	/**
	 * Get latest release information for a repository
	 */
	public function get_latest_release( string $owner, string $repo ): ?array {
		
		$url      = $this->build_api_url(
			'releases',
			array(
				'owner' => $owner,
				'repo'  => $repo,
			)
		);
		$response = $this->make_cached_request( $url );

		if ( is_wp_error( $response ) ) {
			$this->logger->error( "Failed to get latest release for {$owner}/{$repo}: " . $response->get_error_message() );
			return null;
		}

		return $response;
	}

	/**
	 * Get repository information
	 */
	public function get_repository( string $owner, string $repo ): ?array {
		$url      = $this->build_api_url(
			'repository',
			array(
				'owner' => $owner,
				'repo'  => $repo,
			)
		);
		$response = $this->make_cached_request( $url );

		if ( is_wp_error( $response ) ) {
			$this->logger->error( "Failed to get repository info for {$owner}/{$repo}: " . $response->get_error_message() );
			return null;
		}

		return $response;
	}

	/**
	 * Get download URL for a specific reference (tag, branch, commit)
	 */
	public function get_download_url( string $owner, string $repo, string $ref = 'main' ): string {
		return $this->build_api_url(
			'download',
			array(
				'owner' => $owner,
				'repo'  => $repo,
				'ref'   => $ref,
			)
		);
	}

	/**
	 * Check API rate limit status
	 */
	public function get_rate_limit_info(): ?array {
		$url      = $this->build_api_url( 'rate_limit' );
		$response = $this->make_request( $url ); // Don't cache rate limit info

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'Failed to get rate limit info: ' . $response->get_error_message() );
			return null;
		}

		return $response;
	}

	/**
	 * Test API connection and authentication
	 */
	public function test_connection(): bool {
		$rate_limit_info = $this->get_rate_limit_info();

		if ( $rate_limit_info === null ) {
			return false;
		}

		// Log current rate limit status
		$core      = $rate_limit_info['resources']['core'] ?? array();
		$remaining = $core['remaining'] ?? 0;
		$limit     = $core['limit'] ?? 0;

		$this->logger->info( "GitHub API rate limit: {$remaining}/{$limit} remaining" );

		return true;
	}


	/**
	 * Make a cached API request
	 */
	private function make_cached_request( string $url ): array|WP_Error {
		$cache_key       = 'giu_api_' . md5( $url . ( $this->github_token ?? '' ) );
		$cached_response = get_transient( $cache_key );

		if ( $cached_response !== false ) {
			$this->logger->info( "Using cached response for: {$url}" );
			return $cached_response;
		}

		$response = $this->make_request( $url );

		if ( ! is_wp_error( $response ) ) {
			set_transient( $cache_key, $response, self::CACHE_EXPIRATION );
			$this->logger->info( "Cached API response for: {$url}" );
		}

		return $response;
	}

	/**
	 * Make an API request to GitHub
	 */
	private function make_request( string $url ): array|WP_Error {
		$args = array(
			'timeout' => 15,
			'headers' => array(
				'User-Agent' => $this->get_user_agent(),
				'Accept'     => 'application/vnd.github.v3+json',
			),
		);

		// Add authentication if token is available
		if ( ! empty( $this->github_token ) ) {
			$args['headers']['Authorization'] = 'token ' . $this->github_token;
			$masked_token                     = substr( $this->github_token, 0, 6 ) . '...' . substr( $this->github_token, -4 );
			$this->logger->error( "🔐 Making AUTHENTICATED GitHub API request to: {$url} with token: {$masked_token}" );
		} else {
			$this->logger->error( "🚫 Making UNAUTHENTICATED GitHub API request to: {$url} (NO TOKEN AVAILABLE)" );
		}
		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = wp_remote_retrieve_body( $response );

		// DEBUG: Log the response we got
		$this->logger->error( "💬 GitHub API Response: HTTP {$response_code}" );
		if ( $response_code !== 200 ) {
			$this->logger->error( "⚠️ Response body: " . substr( $body, 0, 200 ) . ( strlen( $body ) > 200 ? '...' : '' ) );
		}

		if ( $response_code !== 200 ) {
			$error_message = $this->parse_error_response( $body, $response_code );
			return new WP_Error( 'github_api_error', $error_message, array( 'status' => $response_code ) );
		}

		$data = json_decode( $body, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'json_decode_error', 'Failed to decode JSON response' );
		}

		// Log rate limit headers if available
		$this->log_rate_limit_headers( $response );

		return $data;
	}

	/**
	 * Build API URL from template and parameters
	 */
	private function build_api_url( string $endpoint, array $params = array() ): string {
		if ( ! isset( self::API_ENDPOINTS[ $endpoint ] ) ) {
			throw new \InvalidArgumentException( "Unknown API endpoint: {$endpoint}" );
		}

		$url = self::API_ENDPOINTS[ $endpoint ];

		foreach ( $params as $key => $value ) {
			$url = str_replace( '{' . $key . '}', urlencode( $value ), $url );
		}

		return $url;
	}

	/**
	 * Get User-Agent string for API requests
	 */
	private function get_user_agent(): string {
		global $wp_version;

		return sprintf(
			'WordPress/%s; %s; Kob-Git-Updater/%s',
			$wp_version ?? 'unknown',
			home_url(),
			defined( 'KGU_VERSION' ) ? KGU_VERSION : '1.3.0-dev'
		);
	}

	/**
	 * Parse error response from GitHub API
	 */
	private function parse_error_response( string $body, int $status_code ): string {
		$error_data = json_decode( $body, true );

		if ( json_last_error() === JSON_ERROR_NONE && isset( $error_data['message'] ) ) {
			$message = $error_data['message'];

			// Add additional context for common errors
			if ( $status_code === 404 ) {
				$message .= ' (Repository not found or private)';
			} elseif ( $status_code === 401 ) {
				$message .= ' (Invalid or missing authentication token)';
			} elseif ( $status_code === 403 ) {
				if ( strpos( $message, 'rate limit' ) !== false ) {
					$message .= ' (API rate limit exceeded)';
				} else {
					$message .= ' (Access forbidden)';
				}
			}

			return $message;
		}

		return "GitHub API error (HTTP {$status_code})";
	}

	/**
	 * Clear API response cache
	 */
	private function clear_api_cache(): void {
		// Get all transients that start with our cache prefix
		global $wpdb;
		
		// Delete transients that start with 'giu_api_'
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_giu_api_%',
				'_transient_timeout_giu_api_%'
			)
		);
		
		$this->logger->info( 'Cleared all API cache transients' );
	}
	
	/**
	 * Log rate limit information from response headers
	 */
	private function log_rate_limit_headers( array $response ): void {
		$headers = wp_remote_retrieve_headers( $response );

		$limit     = $headers['x-ratelimit-limit'] ?? null;
		$remaining = $headers['x-ratelimit-remaining'] ?? null;
		$reset     = $headers['x-ratelimit-reset'] ?? null;

		if ( $limit && $remaining ) {
			$reset_time = $reset ? date( 'H:i:s', intval( $reset ) ) : 'unknown';
			$this->logger->info( "Rate limit: {$remaining}/{$limit} remaining, resets at {$reset_time}" );

			// Warn if getting close to rate limit
			if ( intval( $remaining ) < 100 ) {
				$this->logger->warning( "GitHub API rate limit is low: {$remaining}/{$limit} remaining" );
			}
		}
	}

	/**
	 * Clear cached API responses
	 */
	public function clear_cache( ?string $owner = null, ?string $repo = null ): void {
		global $wpdb;

		if ( $owner && $repo ) {
			// Clear cache for specific repository
			$pattern = '%giu_api_' . md5( "https://api.github.com/repos/{$owner}/{$repo}%" );
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					$pattern
				)
			);
			$this->logger->info( "Cleared API cache for {$owner}/{$repo}" );
		} else {
			// Clear all API cache
			$wpdb->query(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_giu_api_%' OR option_name LIKE '_transient_timeout_giu_api_%'"
			);
			$this->logger->info( 'Cleared all GitHub API cache' );
		}
	}
}
