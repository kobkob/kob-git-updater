<?php
declare(strict_types=1);

namespace KobGitUpdater\Core\Interfaces;

/**
 * Repository model interface
 */
interface RepositoryInterface {

	/**
	 * Get repository ID
	 */
	public function get_id(): string;

	/**
	 * Get repository type (plugin|theme)
	 */
	public function get_type(): string;

	/**
	 * Get repository owner
	 */
	public function get_owner(): string;

	/**
	 * Get repository name
	 */
	public function get_repo(): string;

	/**
	 * Get WordPress slug
	 */
	public function get_slug(): string;

	/**
	 * Get latest version
	 */
	public function get_latest_version(): string;

	/**
	 * Set latest version
	 */
	public function set_latest_version( string $version ): void;

	/**
	 * Get repository data as array
	 */
	public function to_array(): array;

	/**
	 * Create from array data
	 */
	public static function from_array( array $data ): self;
}
