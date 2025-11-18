<?php

namespace KobGitUpdater\Repository\Models;

use KobGitUpdater\Core\Interfaces\RepositoryInterface;

/**
 * Repository Model
 *
 * Represents a GitHub repository configuration for plugins/themes.
 */
class Repository implements RepositoryInterface {

	/** @var string */
	private $owner;

	/** @var string */
	private $repo;

	/** @var string 'plugin' or 'theme' */
	private $type;

	/** @var string WordPress slug for the plugin/theme */
	private $slug;

	/** @var string Default branch name */
	private $default_branch;

	/** @var bool Whether the repository is private */
	private $is_private;

	/** @var string ISO 8601 timestamp when repository was added */
	private $date_added;

	/** @var string Latest version from GitHub */
	private $latest_version = '';

	public function __construct(
		string $owner,
		string $repo,
		string $type,
		string $slug,
		string $default_branch = 'main',
		bool $is_private = false,
		?string $date_added = null
	) {
		$this->validate_and_set_properties( $owner, $repo, $type, $slug, $default_branch );
		$this->is_private = $is_private;
		$this->date_added = $date_added ?? current_time( 'mysql', true );
	}

	/**
	 * Create Repository from array data
	 */
	public static function from_array( array $data ): self {
		if ( ! isset( $data['owner'], $data['repo'], $data['type'], $data['slug'] ) ) {
			throw new \InvalidArgumentException( 'Missing required repository data fields' );
		}

		return new self(
			$data['owner'],
			$data['repo'],
			$data['type'],
			$data['slug'],
			$data['default_branch'] ?? 'main',
			$data['is_private'] ?? false,
			$data['date_added'] ?? null
		);
	}

	/**
	 * Convert Repository to array
	 */
	public function to_array(): array {
		return array(
			'owner'          => $this->owner,
			'repo'           => $this->repo,
			'type'           => $this->type,
			'slug'           => $this->slug,
			'default_branch' => $this->default_branch,
			'is_private'     => $this->is_private,
			'date_added'     => $this->date_added,
		);
	}

	/**
	 * Get repository key in "owner/repo" format
	 */
	public function get_key(): string {
		return "{$this->owner}/{$this->repo}";
	}

	/**
	 * Get GitHub repository URL
	 */
	public function get_github_url(): string {
		return "https://github.com/{$this->owner}/{$this->repo}";
	}

	/**
	 * Get repository display name
	 */
	public function get_display_name(): string {
		return $this->get_key();
	}

	// Getters
	/**
	 * Get unique repository ID
	 */
	public function get_id(): string {
		return $this->get_key();
	}

	public function get_owner(): string {
		return $this->owner;
	}

	public function get_repo(): string {
		return $this->repo;
	}

	public function get_type(): string {
		return $this->type;
	}

	public function get_slug(): string {
		return $this->slug;
	}

	public function get_default_branch(): string {
		return $this->default_branch;
	}

	public function is_private(): bool {
		return $this->is_private;
	}

	public function get_date_added(): string {
		return $this->date_added;
	}

	public function get_latest_version(): string {
		return $this->latest_version;
	}

	// Setters (with validation)
	public function set_slug( string $slug ): void {
		if ( empty( trim( $slug ) ) ) {
			throw new \InvalidArgumentException( 'Slug cannot be empty' );
		}
		$this->slug = sanitize_text_field( $slug );
	}

	public function set_default_branch( string $default_branch ): void {
		if ( empty( trim( $default_branch ) ) ) {
			throw new \InvalidArgumentException( 'Default branch cannot be empty' );
		}
		$this->default_branch = sanitize_text_field( $default_branch );
	}

	public function set_is_private( bool $is_private ): void {
		$this->is_private = $is_private;
	}

	public function set_latest_version( string $version ): void {
		$this->latest_version = sanitize_text_field( $version );
	}

	/**
	 * Check if repository is a plugin
	 */
	public function is_plugin(): bool {
		return $this->type === 'plugin';
	}

	/**
	 * Check if repository is a theme
	 */
	public function is_theme(): bool {
		return $this->type === 'theme';
	}

	/**
	 * Get repository type label for display
	 */
	public function get_type_label(): string {
		return ucfirst( $this->type );
	}

	/**
	 * Get formatted date added for display
	 */
	public function get_formatted_date_added(): string {
		return wp_date( 'Y-m-d H:i:s', strtotime( $this->date_added ) );
	}

	/**
	 * Get time since repository was added
	 */
	public function get_time_since_added(): string {
		return human_time_diff( strtotime( $this->date_added ), current_time( 'timestamp' ) );
	}

	/**
	 * Validate and set core properties
	 */
	private function validate_and_set_properties(
		string $owner,
		string $repo,
		string $type,
		string $slug,
		string $default_branch
	): void {
		// Validate owner
		$owner = trim( $owner );
		if ( empty( $owner ) ) {
			throw new \InvalidArgumentException( 'Repository owner cannot be empty' );
		}
		if ( ! preg_match( '/^[a-zA-Z0-9]([a-zA-Z0-9-]{0,37}[a-zA-Z0-9])?$/', $owner ) ) {
			throw new \InvalidArgumentException( 'Invalid repository owner format' );
		}
		$this->owner = $owner;

		// Validate repo
		$repo = trim( $repo );
		if ( empty( $repo ) ) {
			throw new \InvalidArgumentException( 'Repository name cannot be empty' );
		}
		if ( ! preg_match( '/^[a-zA-Z0-9._-]+$/', $repo ) ) {
			throw new \InvalidArgumentException( 'Invalid repository name format' );
		}
		$this->repo = $repo;

		// Validate type
		if ( ! in_array( $type, array( 'plugin', 'theme' ), true ) ) {
			throw new \InvalidArgumentException( 'Repository type must be "plugin" or "theme"' );
		}
		$this->type = $type;

		// Validate slug
		$slug = trim( $slug );
		if ( empty( $slug ) ) {
			throw new \InvalidArgumentException( 'Repository slug cannot be empty' );
		}
		$this->slug = sanitize_text_field( $slug );

		// Validate default branch
		$default_branch = trim( $default_branch );
		if ( empty( $default_branch ) ) {
			throw new \InvalidArgumentException( 'Default branch cannot be empty' );
		}
		$this->default_branch = sanitize_text_field( $default_branch );
	}

	/**
	 * Compare repositories for sorting
	 */
	public function compare_to( Repository $other ): int {
		// First sort by type
		$type_comparison = strcmp( $this->type, $other->get_type() );
		if ( $type_comparison !== 0 ) {
			return $type_comparison;
		}

		// Then sort by owner/repo
		return strcmp( $this->get_key(), $other->get_key() );
	}

	/**
	 * Check if this repository equals another
	 */
	public function equals( Repository $other ): bool {
		return $this->get_key() === $other->get_key() &&
				$this->type === $other->get_type() &&
				$this->slug === $other->get_slug();
	}

	/**
	 * Get repository as JSON string
	 */
	public function to_json(): string {
		return json_encode( $this->to_array(), JSON_PRETTY_PRINT );
	}

	/**
	 * Create Repository from JSON string
	 */
	public static function from_json( string $json ): self {
		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new \InvalidArgumentException( 'Invalid JSON: ' . json_last_error_msg() );
		}

		return self::from_array( $data );
	}

	/**
	 * String representation for debugging
	 */
	public function __toString(): string {
		return sprintf(
			'%s (%s): %s [%s]',
			$this->get_key(),
			$this->type,
			$this->slug,
			$this->default_branch
		);
	}
}
