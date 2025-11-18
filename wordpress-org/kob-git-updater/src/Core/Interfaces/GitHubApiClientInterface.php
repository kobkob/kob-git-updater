<?php
declare(strict_types=1);

namespace KobGitUpdater\Core\Interfaces;

/**
 * GitHub API client interface
 */
interface GitHubApiClientInterface {

	/**
	 * Get latest release for a repository
	 *
	 * @param string $owner Repository owner
	 * @param string $repo Repository name
	 * @return array|\WP_Error
	 */
	public function get_latest_release( string $owner, string $repo );

	/**
	 * Get repository information
	 *
	 * @param string $owner Repository owner
	 * @param string $repo Repository name
	 * @return array|\WP_Error
	 */
	public function get_repository( string $owner, string $repo );

	/**
	 * Download repository archive
	 *
	 * @param string $owner Repository owner
	 * @param string $repo Repository name
	 * @param string $ref Reference (tag, branch, commit)
	 * @return string|\WP_Error Download URL or error
	 */
	public function get_download_url( string $owner, string $repo, string $ref );
}
