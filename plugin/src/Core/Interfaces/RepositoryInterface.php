<?php
declare(strict_types=1);

namespace KobGitUpdater\Core\Interfaces;

/**
 * Repository model interface
 */
interface RepositoryInterface
{
    /**
     * Get repository ID
     */
    public function getId(): string;

    /**
     * Get repository type (plugin|theme)
     */
    public function getType(): string;

    /**
     * Get repository owner
     */
    public function getOwner(): string;

    /**
     * Get repository name
     */
    public function getRepo(): string;

    /**
     * Get WordPress slug
     */
    public function getSlug(): string;

    /**
     * Get latest version
     */
    public function getLatestVersion(): string;

    /**
     * Set latest version
     */
    public function setLatestVersion(string $version): void;

    /**
     * Get repository data as array
     */
    public function toArray(): array;

    /**
     * Create from array data
     */
    public static function fromArray(array $data): self;
}