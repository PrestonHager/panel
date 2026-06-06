<?php

namespace Pterodactyl\Plugins;

final readonly class PluginSettingsField
{
    public const PASSWORD_MASK = '********';

    public const TYPES = [
        'string',
        'text',
        'password',
        'boolean',
        'integer',
        'number',
        'select',
        'multiselect',
        'json',
    ];

    public const STORAGES = ['config', 'server', 'global'];

    public const SURFACES = ['admin', 'client'];

    /**
     * @param string[] $surfaces
     * @param array<int, array{value: string, label: string}> $options
     */
    public function __construct(
        public string $key,
        public string $type,
        public string $label,
        public array $surfaces,
        public string $storage,
        public ?string $description = null,
        public ?string $placeholder = null,
        public mixed $default = null,
        public bool $required = false,
        public ?string $permission = null,
        public array $options = [],
        public ?int $min = null,
        public ?int $max = null,
        public bool $sensitive = false,
        public bool $audit = false,
        public bool $ownerOnly = false,
    ) {
    }

    public function onSurface(string $surface): bool
    {
        return in_array($surface, $this->surfaces, true);
    }

    public function isPassword(): bool
    {
        return $this->type === 'password' || $this->sensitive;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'key' => $this->key,
            'type' => $this->type,
            'label' => $this->label,
            'description' => $this->description,
            'placeholder' => $this->placeholder,
            'surfaces' => $this->surfaces,
            'storage' => $this->storage,
            'required' => $this->required ?: null,
            'default' => $this->default,
            'permission' => $this->permission,
            'options' => $this->options ?: null,
            'min' => $this->min,
            'max' => $this->max,
            'sensitive' => $this->sensitive ?: null,
            'ownerOnly' => $this->ownerOnly ?: null,
        ], fn ($value) => !is_null($value));
    }
}
