<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $subuser_id
 * @property string $plugin_id
 * @property string $permission
 */
class SubuserPluginPermission extends Model
{
    protected $table = 'subuser_plugin_permissions';

    protected $fillable = [
        'subuser_id',
        'plugin_id',
        'permission',
    ];

    public static array $validationRules = [
        'subuser_id' => 'required|integer|exists:subusers,id',
        'plugin_id' => 'required|string|exists:plugins,id',
        'permission' => 'required|string|max:191',
    ];

    public function subuser(): BelongsTo
    {
        return $this->belongsTo(Subuser::class);
    }

    public function plugin(): BelongsTo
    {
        return $this->belongsTo(Plugin::class, 'plugin_id', 'id');
    }
}
