<?php
declare(strict_types=1);

namespace KobGitUpdater\Core\Interfaces;

/**
 * Main plugin interface
 */
interface PluginInterface
{
    /**
     * Initialize the plugin
     */
    public function init(): void;

    /**
     * Get plugin version
     */
    public function getVersion(): string;

    /**
     * Get plugin options
     */
    public function getOptions(): array;
}