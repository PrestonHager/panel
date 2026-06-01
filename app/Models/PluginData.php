<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $plugin_id
 * @property string $subject_type
 * @property int $subject_id
 * @property string $key
 * @property array $value
 */
class PluginData extends Model
{
    protected $table = 'plugin_data';

    protected $fillable = [
        'plugin_id',
        'subject_type',
        'subject_id',
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public static array $validationRules = [
        'plugin_id' => 'required|string|exists:plugins,id',
        'subject_type' => 'required|string|max:64',
        'subject_id' => 'required|integer|min:1',
        'key' => 'required|string|max:191',
        'value' => 'required|array',
    ];

    public function plugin(): BelongsTo
    {
        return $this->belongsTo(Plugin::class, 'plugin_id', 'id');
    }
}
