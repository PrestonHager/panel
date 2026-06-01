<?php

namespace Pterodactyl\Plugins\Accessors;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Plugins\PermissionGate;
use Pterodactyl\Plugins\Permissions;
use Pterodactyl\Plugins\Dto\ServerSummary;
use Pterodactyl\Plugins\Dto\NetworkSummary;
use Pterodactyl\Plugins\Dto\AllocationSummary;
use Pterodactyl\Plugins\Exceptions\PluginException;

class ServerAccessor
{
    public function __construct(
        private readonly PermissionGate $gate,
    ) {
    }

    public function find(int $serverId): ServerSummary
    {
        $this->gate->authorize(Permissions::SERVER_READ);

        $server = Server::query()->find($serverId);
        if (is_null($server)) {
            throw new PluginException(sprintf('Server with ID %d was not found.', $serverId));
        }

        return $this->toSummary($server);
    }

    public function findByUuid(string $uuid): ServerSummary
    {
        $this->gate->authorize(Permissions::SERVER_READ);

        $server = Server::query()->where('uuid', $uuid)->first();
        if (is_null($server)) {
            throw new PluginException(sprintf('Server with UUID %s was not found.', $uuid));
        }

        return $this->toSummary($server);
    }

    public function getNetworkSummary(int $serverId): NetworkSummary
    {
        $this->gate->authorize(Permissions::SERVER_READ);
        $this->gate->authorize(Permissions::ALLOCATION_READ);

        $server = Server::query()->with('allocations')->find($serverId);
        if (is_null($server)) {
            throw new PluginException(sprintf('Server with ID %d was not found.', $serverId));
        }

        $allocations = $server->allocations->map(function (Allocation $allocation) use ($server) {
            return new AllocationSummary(
                id: $allocation->id,
                ip: $allocation->ip,
                port: $allocation->port,
                isPrimary: $allocation->id === $server->allocation_id,
                notes: $allocation->notes,
            );
        })->all();

        return new NetworkSummary(
            server: $this->toSummary($server),
            allocations: $allocations,
        );
    }

    private function toSummary(Server $server): ServerSummary
    {
        return new ServerSummary(
            id: $server->id,
            uuid: $server->uuid,
            name: $server->name,
            status: $server->status,
            nodeId: $server->node_id,
            ownerId: $server->owner_id,
            allocationId: $server->allocation_id,
            externalId: $server->external_id,
        );
    }
}
