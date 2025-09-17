<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\ValueObjects;

use JsonSerializable;

readonly class ModuleInfo implements JsonSerializable
{
    public function __construct(
        public string $name,
        public string $displayName,
        public string $description,
        public string $version,
        public string $author,
        public array $dependencies,
        public array $optionalDependencies,
        public array $conflicts,
        public array $provides,
        public string $path,
        public ModuleState $state,
        public ?string $namespace = null,
        public array $config = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            displayName: $data['display_name'] ?? $data['name'],
            description: $data['description'] ?? '',
            version: $data['version'] ?? '1.0.0',
            author: $data['author'] ?? '',
            dependencies: $data['dependencies'] ?? [],
            optionalDependencies: $data['optional_dependencies'] ?? [],
            conflicts: $data['conflicts'] ?? [],
            provides: $data['provides'] ?? [],
            path: $data['path'],
            state: $data['state'] instanceof ModuleState ? $data['state'] : ModuleState::from($data['state'] ?? 'installed'),
            namespace: $data['namespace'] ?? ('Modules\\' . $data['name']),
            config: $data['config'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'display_name' => $this->displayName,
            'description' => $this->description,
            'version' => $this->version,
            'author' => $this->author,
            'dependencies' => $this->dependencies,
            'optional_dependencies' => $this->optionalDependencies,
            'conflicts' => $this->conflicts,
            'provides' => $this->provides,
            'path' => $this->path,
            'state' => $this->state->value,
            'namespace' => $this->namespace,
            'config' => $this->config,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function hasDependency(string $moduleName): bool
    {
        return in_array($moduleName, $this->dependencies, true);
    }

    public function hasOptionalDependency(string $moduleName): bool
    {
        return in_array($moduleName, $this->optionalDependencies, true);
    }

    public function conflictsWith(string $moduleName): bool
    {
        return in_array($moduleName, $this->conflicts, true);
    }

    public function provides(string $service): bool
    {
        return in_array($service, $this->provides, true);
    }

    public function isInstalled(): bool
    {
        return !$this->state->equals(ModuleState::NotInstalled);
    }

    public function isEnabled(): bool
    {
        return $this->state->equals(ModuleState::Enabled);
    }

    public function withState(ModuleState $state): self
    {
        return new self(
            name: $this->name,
            displayName: $this->displayName,
            description: $this->description,
            version: $this->version,
            author: $this->author,
            dependencies: $this->dependencies,
            optionalDependencies: $this->optionalDependencies,
            conflicts: $this->conflicts,
            provides: $this->provides,
            path: $this->path,
            state: $state,
            namespace: $this->namespace,
            config: $this->config,
        );
    }
}
