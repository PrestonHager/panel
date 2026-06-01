<?php

namespace Pterodactyl\Transformers\Api\Client;

use Pterodactyl\Models\Subuser;
use Pterodactyl\Services\Plugins\SubuserPluginPermissionService;

class SubuserTransformer extends BaseClientTransformer
{
    /**
     * Return the resource name for the JSONAPI output.
     */
    public function getResourceName(): string
    {
        return Subuser::RESOURCE_NAME;
    }

    /**
     * Transforms a subuser into a model that can be shown to a front-end user.
     *
     * @throws \Pterodactyl\Exceptions\Transformer\InvalidTransformerLevelException
     */
    public function transform(Subuser $model): array
    {
        $pluginPermissions = app(SubuserPluginPermissionService::class)->forSubuser(
            $model->relationLoaded('pluginPermissions') ? $model : $model->load('pluginPermissions')
        );

        return array_merge(
            $this->makeTransformer(UserTransformer::class)->transform($model->user),
            [
                'permissions' => $model->permissions,
                'plugin_permissions' => $pluginPermissions,
            ]
        );
    }
}
