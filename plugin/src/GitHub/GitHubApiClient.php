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
class GitHubApiClient implements GitHubApiClientInterface
{
    /** @var Logger */
    private $logger;

    /** @var string|null */
    private $github_token;

    /** @var int Cache expiration time in seconds */
    private const CACHE_EXPIRATION = 3600; // 1 hour

    /** @var array<string, string> */
    private const API_ENDPOINTS = [
        'releases' => 'https://api.github.com/repos/{owner}/{repo}/releases/latest',
        'repository' => 'https://api.github.com/repos/{owner}/{repo}',
        'download' => 'https://api.github.com/repos/{owner}/{repo}/zipball/{ref}',
        'rate_limit' => 'https://api.github.com/rate_limit'
    ];

    public function __construct(Logger $logger, ?string $github_token = null)
    {
        $this->logger = $logger;
        $this->github_token = $github_token;
    }

    /**
     * Set GitHub authentication token
     */
    public function set_token(?string $token): void
    {
        $this->github_token = $token;
    }

    /**
     * Get latest release information for a repository
     */
    public function get_latest_release(string $owner, string $repo): ?array
    {
        $url = $this->build_api_url('releases', ['owner' => $owner, 'repo' => $repo]);
        $response = $this->make_cached_request($url);

        if (is_wp_error($response)) {
            $this->logger->log_error("Failed to get latest release for {$owner}/{$repo}: " . $response->get_error_message());
            return null;
        }

        return $response;
    }

    /**
     * Get repository information
     */
    public function get_repository_info(string $owner, string $repo): ?array
    {
        $url = $this->build_api_url('repository', ['owner' => $owner, 'repo' => $repo]);
        $response = $this->make_cached_request($url);

        if (is_wp_error($response)) {
            $this->logger->log_error("Failed to get repository info for {$owner}/{$repo}: " . $response->get_error_message());
            return null;
        }

        return $response;
    }

    /**
     * Get download URL for a specific reference (tag, branch, commit)
     */
    public function get_download_url(string $owner, string $repo, string $ref = 'main'): string
    {
        return $this->build_api_url('download', ['owner' => $owner, 'repo' => $repo, 'ref' => $ref]);
    }

    /**
     * Check API rate limit status
     */
    public function get_rate_limit_info(): ?array
    {
        $url = $this->build_api_url('rate_limit');
        $response = $this->make_request($url); // Don't cache rate limit info

        if (is_wp_error($response)) {
            $this->logger->log_error("Failed to get rate limit info: " . $response->get_error_message());
            return null;
        }

        return $response;
    }

    /**
     * Test API connection and authentication
     */
    public function test_connection(): bool
    {
        $rate_limit_info = $this->get_rate_limit_info();
        
        if ($rate_limit_info === null) {
            return false;
        }

        // Log current rate limit status
        $core = $rate_limit_info['resources']['core'] ?? [];
        $remaining = $core['remaining'] ?? 0;
        $limit = $core['limit'] ?? 0;
        
        $this->logger->log_info("GitHub API rate limit: {$remaining}/{$limit} remaining");
        
        return true;
    }

    /**
     * Make a cached API request
     */
    private function make_cached_request(string $url): array|WP_Error
    {
        $cache_key = 'giu_api_' . md5($url . ($this->github_token ?? ''));
        $cached_response = get_transient($cache_key);

        if ($cached_response !== false) {
            $this->logger->log_info("Using cached response for: {$url}");
            return $cached_response;
        }

        $response = $this->make_request($url);

        if (!is_wp_error($response)) {
            set_transient($cache_key, $response, self::CACHE_EXPIRATION);
            $this->logger->log_info("Cached API response for: {$url}");
        }

        return $response;
    }

    /**
     * Make an API request to GitHub
     */
    private function make_request(string $url): array|WP_Error
    {
        $args = [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => $this->get_user_agent(),
                'Accept' => 'application/vnd.github.v3+json',
            ],
        ];

        // Add authentication if token is available
        if (!empty($this->github_token)) {
            $args['headers']['Authorization'] = 'token ' . $this->github_token;
        }

        $this->logger->log_info("Making GitHub API request to: {$url}");
        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            $error_message = $this->parse_error_response($body, $response_code);
            return new WP_Error('github_api_error', $error_message, ['status' => $response_code]);
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_decode_error', 'Failed to decode JSON response');
        }

        // Log rate limit headers if available
        $this->log_rate_limit_headers($response);

        return $data;
    }

    /**
     * Build API URL from template and parameters
     */
    private function build_api_url(string $endpoint, array $params = []): string
    {
        if (!isset(self::API_ENDPOINTS[$endpoint])) {
            throw new \InvalidArgumentException("Unknown API endpoint: {$endpoint}");
        }

        $url = self::API_ENDPOINTS[$endpoint];

        foreach ($params as $key => $value) {
            $url = str_replace('{' . $key . '}', urlencode($value), $url);
        }

        return $url;
    }

    /**
     * Get User-Agent string for API requests
     */
    private function get_user_agent(): string
    {
        global $wp_version;
        
        return sprintf(
            'WordPress/%s; %s; Kob-Git-Updater/%s',
            $wp_version ?? 'unknown',
            home_url(),
            defined('KGU_VERSION') ? KGU_VERSION : '1.3.0-dev'
        );
    }

    /**
     * Parse error response from GitHub API
     */
    private function parse_error_response(string $body, int $status_code): string
    {
        $error_data = json_decode($body, true);
        
        if (json_last_error() === JSON_ERROR_NONE && isset($error_data['message'])) {
            $message = $error_data['message'];
            
            // Add additional context for common errors
            if ($status_code === 404) {
                $message .= ' (Repository not found or private)';
            } elseif ($status_code === 401) {
                $message .= ' (Invalid or missing authentication token)';
            } elseif ($status_code === 403) {
                if (strpos($message, 'rate limit') !== false) {
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
     * Log rate limit information from response headers
     */
    private function log_rate_limit_headers(array $response): void
    {
        $headers = wp_remote_retrieve_headers($response);
        
        $limit = $headers['x-ratelimit-limit'] ?? null;
        $remaining = $headers['x-ratelimit-remaining'] ?? null;
        $reset = $headers['x-ratelimit-reset'] ?? null;

        if ($limit && $remaining) {
            $reset_time = $reset ? date('H:i:s', intval($reset)) : 'unknown';
            $this->logger->log_info("Rate limit: {$remaining}/{$limit} remaining, resets at {$reset_time}");
            
            // Warn if getting close to rate limit
            if (intval($remaining) < 100) {
                $this->logger->log_error("GitHub API rate limit is low: {$remaining}/{$limit} remaining");
            }
        }
    }

    /**
     * Clear cached API responses
     */
    public function clear_cache(?string $owner = null, ?string $repo = null): void
    {
        global $wpdb;

        if ($owner && $repo) {
            // Clear cache for specific repository
            $pattern = '%giu_api_' . md5("https://api.github.com/repos/{$owner}/{$repo}%");
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $pattern
                )
            );
            $this->logger->log_info("Cleared API cache for {$owner}/{$repo}");
        } else {
            // Clear all API cache
            $wpdb->query(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_giu_api_%' OR option_name LIKE '_transient_timeout_giu_api_%'"
            );
            $this->logger->log_info("Cleared all GitHub API cache");
        }
    }
}