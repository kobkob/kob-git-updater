<?php
declare(strict_types=1);

namespace KobGitUpdater\Core\Interfaces;

/**
 * GitHub API client interface
 */
interface GitHubApiClientInterface
{
    /**
     * Get latest release for a repository
     *
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @return array|\WP_Error
     */
    public function getLatestRelease(string $owner, string $repo);

    /**
     * Get repository information
     *
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @return array|\WP_Error
     */
    public function getRepository(string $owner, string $repo);

    /**
     * Download repository archive
     *
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param string $ref Reference (tag, branch, commit)
     * @return string|\WP_Error Download URL or error
     */
    public function getDownloadUrl(string $owner, string $repo, string $ref);
}