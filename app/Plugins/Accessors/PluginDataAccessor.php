<?php

namespace Pterodactyl\Plugins\Accessors;

use Pterodactyl\Models\PluginData;
use Pterodactyl\Plugins\PermissionGate;
use Pterodactyl\Plugins\Permissions;
use Pterodactyl\Plugins\Exceptions\PluginException;

class PluginDataAccessor
{
    public function __construct(
        private readonly string $pluginId,
        private readonly PermissionGate $gate,
    ) {
    }

    public function get(string $subjectType, int $subjectId, string $key, mixed $default = null): mixed
    {
        $record = PluginData::query()
            ->where('plugin_id', $this->pluginId)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('key', $key)
            ->first();

        return $record?->value ?? $default;
    }

    public function set(string $subjectType, int $subjectId, string $key, array $value): void
    {
        $this->gate->authorize(Permissions::SERVER_METADATA_WRITE);

        if ($subjectType !== 'server' && $subjectType !== 'user' && $subjectType !== 'global') {
            throw new PluginException('Unsupported subject type. Supported: server, user, global.');
        }

        PluginData::query()->updateOrCreate(
            [
                'plugin_id' => $this->pluginId,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'key' => $key,
            ],
            ['value' => $value],
        );
    }

    public function delete(string $subjectType, int $subjectId, string $key): void
    {
        $this->gate->authorize(Permissions::SERVER_METADATA_WRITE);

        PluginData::query()
            ->where('plugin_id', $this->pluginId)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('key', $key)
            ->delete();
    }
}
