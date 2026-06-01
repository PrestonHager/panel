<?php

namespace Pterodactyl\Http\Requests\Admin\Plugin;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class UpdatePluginSettingsRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'config' => 'nullable|array',
        ];
    }
}
