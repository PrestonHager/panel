<?php

namespace Pterodactyl\Plugins;

final readonly class PluginManifest
{
    /**
     * @param string[] $permissions
     * @param array<string, string> $hooks
     * @param array<string, mixed> $configSchema
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $version,
        public string $entry,
        public array $permissions,
        public array $hooks,
        public array $configSchema = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            name: (string) $data['name'],
            version: (string) $data['version'],
            entry: (string) $data['entry'],
            permissions: array_values($data['permissions'] ?? []),
            hooks: $data['hooks'] ?? [],
            configSchema: $data['config']['schema'] ?? [],
        );
    }

    public function namespacePrefix(): string
    {
        $parts = explode('\\', $this->entry);
        array_pop($parts);

        return implode('\\', $parts) . '\\';
    }
}
