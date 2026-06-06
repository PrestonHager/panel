<?php

namespace Pterodactyl\Http\Requests\Admin\Plugin;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class UpgradeSelectedPluginsRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'plugins' => 'required|array|min:1',
            'plugins.*' => 'required|string|exists:plugins,id',
        ];
    }
}
