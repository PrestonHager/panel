<?php

namespace Pterodactyl\Plugins;

final readonly class PluginSettingsSchema
{
    /**
     * @param PluginSettingsField[] $fields
     */
    public function __construct(
        public array $fields,
        public bool $fromFallback = false,
    ) {
    }

    public function isEmpty(): bool
    {
        return empty($this->fields);
    }

    /**
     * @return PluginSettingsField[]
     */
    public function forSurface(string $surface): array
    {
        return array_values(array_filter(
            $this->fields,
            fn (PluginSettingsField $field) => $field->onSurface($surface)
        ));
    }

    /**
     * @return PluginSettingsField[]
     */
    public function forSurfaceAndStorage(string $surface, string $storage): array
    {
        return array_values(array_filter(
            $this->fields,
            fn (PluginSettingsField $field) => $field->onSurface($surface) && $field->storage === $storage
        ));
    }

    public function find(string $key): ?PluginSettingsField
    {
        foreach ($this->fields as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }

        return null;
    }

    public function clientSettingsPermission(): ?string
    {
        $permissions = [];
        foreach ($this->forSurface('client') as $field) {
            if ($field->permission) {
                $permissions[$field->permission] = true;
            }
        }

        if (empty($permissions)) {
            return null;
        }

        return array_key_first($permissions);
    }

    public function hasClientSettings(): bool
    {
        return !empty($this->forSurface('client'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(string $surface): array
    {
        return [
            'fields' => array_map(
                fn (PluginSettingsField $field) => $field->toArray(),
                $this->forSurface($surface)
            ),
        ];
    }
}
