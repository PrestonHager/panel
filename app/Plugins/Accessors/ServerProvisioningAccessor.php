<?php

namespace Pterodactyl\Plugins\Accessors;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Plugins\PermissionGate;
use Pterodactyl\Plugins\Permissions;
use Pterodactyl\Plugins\Exceptions\PluginException;
use Pterodactyl\Models\Objects\DeploymentObject;
use Pterodactyl\Services\Servers\ServerCreationService;
use Pterodactyl\Services\Servers\ServerDeletionService;

class ServerProvisioningAccessor
{
    public function __construct(
        private readonly PermissionGate $gate,
        private readonly ServerCreationService $serverCreationService,
        private readonly ServerDeletionService $serverDeletionService,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $deployment
     * @return array{id: int, uuid: string, identifier: string, name: string}
     */
    public function create(int $ownerId, array $data, ?array $deployment = null): array
    {
        $this->gate->authorize(Permissions::SERVER_CREATE);

        $owner = User::query()->find($ownerId);
        if (is_null($owner)) {
            throw new PluginException('Owner user not found.');
        }

        $data['owner_id'] = $ownerId;

        $deploymentObject = null;
        if (!is_null($deployment)) {
            $deploymentObject = (new DeploymentObject())
                ->setDedicated((bool) ($deployment['dedicated'] ?? false))
                ->setLocations((array) ($deployment['locations'] ?? []))
                ->setPorts((array) ($deployment['ports'] ?? []));
        }

        $server = $this->serverCreationService->handle($data, $deploymentObject);

        return $this->toSummary($server);
    }

    public function delete(int $ownerId, int $serverId): void
    {
        $this->gate->authorize(Permissions::SERVER_DELETE);

        $server = Server::query()->find($serverId);
        if (is_null($server)) {
            throw new PluginException('Server not found.');
        }

        if ($server->owner_id !== $ownerId) {
            throw new PluginException('You may only delete servers you own.');
        }

        $this->serverDeletionService->handle($server);
    }

    /**
     * @return array{id: int, uuid: string, identifier: string, name: string}
     */
    private function toSummary(Server $server): array
    {
        return [
            'id' => $server->id,
            'uuid' => $server->uuid,
            'identifier' => $server->uuidShort,
            'name' => $server->name,
        ];
    }
}
