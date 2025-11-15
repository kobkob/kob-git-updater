<?php
declare(strict_types=1);

namespace KobGitUpdater\Core;

use InvalidArgumentException;

/**
 * Simple dependency injection container
 */
class Container
{
    /**
     * @var array<string, mixed>
     */
    private array $services = [];

    /**
     * @var array<string, callable>
     */
    private array $factories = [];

    /**
     * @var array<string, mixed>
     */
    private array $singletons = [];

    /**
     * Register a service factory
     *
     * @param string $id Service identifier
     * @param callable $factory Factory function
     * @param bool $singleton Whether to return the same instance
     */
    public function register(string $id, callable $factory, bool $singleton = true): void
    {
        $this->factories[$id] = $factory;
        if ($singleton && isset($this->singletons[$id])) {
            unset($this->singletons[$id]);
        }
    }

    /**
     * Register a service instance
     *
     * @param string $id Service identifier
     * @param mixed $service Service instance
     */
    public function set(string $id, $service): void
    {
        $this->services[$id] = $service;
    }

    /**
     * Get a service
     *
     * @param string $id Service identifier
     * @return mixed
     * @throws InvalidArgumentException If service not found
     */
    public function get(string $id)
    {
        // Return direct service if exists
        if (isset($this->services[$id])) {
            return $this->services[$id];
        }

        // Return singleton if cached
        if (isset($this->singletons[$id])) {
            return $this->singletons[$id];
        }

        // Create from factory
        if (isset($this->factories[$id])) {
            $service = $this->factories[$id]($this);
            
            // Cache as singleton if applicable
            $this->singletons[$id] = $service;
            
            return $service;
        }

        throw new InvalidArgumentException("Service '{$id}' not found");
    }

    /**
     * Check if service exists
     *
     * @param string $id Service identifier
     */
    public function has(string $id): bool
    {
        return isset($this->services[$id]) || 
               isset($this->factories[$id]) || 
               isset($this->singletons[$id]);
    }

    /**
     * Get all registered service IDs
     *
     * @return array<string>
     */
    public function getServiceIds(): array
    {
        return array_unique(array_merge(
            array_keys($this->services),
            array_keys($this->factories),
            array_keys($this->singletons)
        ));
    }
}