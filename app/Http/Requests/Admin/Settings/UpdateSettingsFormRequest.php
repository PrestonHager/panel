<?php

namespace Pterodactyl\Http\Requests\Admin\Settings;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class UpdateSettingsFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'pterodactyl:update:repository' => 'required|string|max:191|regex:/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/',
            'pterodactyl:update:mode' => 'required|in:auto,release,git',
            'pterodactyl:update:branch' => 'required|string|max:191',
            'pterodactyl:update:release' => 'nullable|string|max:32',
            'pterodactyl:update:git_remote' => 'required|string|max:64',
        ];
    }
}
