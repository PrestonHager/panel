<?php

namespace Pterodactyl\Http\Requests\Admin\Plugin;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class UpdatePluginPermissionsRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'approved_permissions' => 'required|array',
            'approved_permissions.*' => 'string',
            'approved_http_hosts' => 'nullable|array',
            'approved_http_hosts.*' => 'string|max:255',
        ];
    }
}
