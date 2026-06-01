<?php

namespace Pterodactyl\Http\Requests\Admin\Plugin;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class InstallPluginRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'github_url' => 'required|string|max:500',
            'source_ref' => 'nullable|string|max:191',
        ];
    }
}
