<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string $version
 * @property string $source_url
 * @property string $source_ref
 * @property string|null $commit_sha
 * @property bool $enabled
 * @property array $permissions
 * @property array|null $client_permissions
 * @property array|null $ui_config
 * @property array|null $config
 * @property \Illuminate\Support\Carbon $installed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Plugin extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'plugins';

    protected $fillable = [
        'id',
        'name',
        'version',
        'source_url',
        'source_ref',
        'commit_sha',
        'enabled',
        'permissions',
        'client_permissions',
        'ui_config',
        'config',
        'installed_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'permissions' => 'array',
        'client_permissions' => 'array',
        'ui_config' => 'array',
        'config' => 'encrypted:array',
        'installed_at' => 'datetime',
    ];

    public static array $validationRules = [
        'id' => 'required|string|regex:/^[a-z][a-z0-9-]*(\.[a-z][a-z0-9-]*)+$/|max:191',
        'name' => 'required|string|max:191',
        'version' => 'required|string|max:32',
        'source_url' => 'required|string|max:500',
        'source_ref' => 'required|string|max:191',
        'commit_sha' => 'nullable|string|max:64',
        'enabled' => 'boolean',
        'permissions' => 'required|array',
        'client_permissions' => 'nullable|array',
        'ui_config' => 'nullable|array',
        'config' => 'nullable|array',
    ];

    public function data(): HasMany
    {
        return $this->hasMany(PluginData::class, 'plugin_id', 'id');
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
