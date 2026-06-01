<?php

namespace Pterodactyl\Plugins\Exceptions;

class PluginPermissionDeniedException extends PluginException
{
    public function __construct(string $pluginId, string $permission)
    {
        parent::__construct(sprintf(
            'Plugin "%s" does not have the "%s" permission.',
            $pluginId,
            $permission
        ));
    }
}
