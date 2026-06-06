<?php

namespace Pterodactyl\Plugins\Accessors;

use Pterodactyl\Models\User;
use Pterodactyl\Plugins\PermissionGate;
use Pterodactyl\Plugins\Permissions;
use Pterodactyl\Plugins\Exceptions\PluginException;
use Pterodactyl\Services\Users\UserCreationService;

class UserAccessor
{
    public function __construct(
        private readonly PermissionGate $gate,
        private readonly UserCreationService $userCreationService,
    ) {
    }

    /**
     * @return array{id: int, uuid: string, email: string, username: string, first_name: string, last_name: string}
     */
    public function read(int $userId): array
    {
        $this->gate->authorize(Permissions::USER_READ);

        $user = User::query()->find($userId);
        if (is_null($user)) {
            throw new PluginException('User not found.');
        }

        return $this->toSummary($user);
    }

    /**
     * @param array{email: string, username: string, first_name: string, last_name: string, password?: string} $data
     * @return array{id: int, uuid: string, email: string, username: string, first_name: string, last_name: string}
     */
    public function create(array $data): array
    {
        $this->gate->authorize(Permissions::USER_CREATE);

        foreach (['email', 'username', 'first_name', 'last_name'] as $field) {
            if (empty($data[$field])) {
                throw new PluginException(sprintf('Field "%s" is required to create a user.', $field));
            }
        }

        $user = $this->userCreationService->handle([
            'email' => $data['email'],
            'username' => $data['username'],
            'name_first' => $data['first_name'],
            'name_last' => $data['last_name'],
            'password' => $data['password'] ?? null,
        ]);

        return $this->toSummary($user);
    }

    /**
     * @return array{id: int, uuid: string, email: string, username: string, first_name: string, last_name: string}
     */
    private function toSummary(User $user): array
    {
        return [
            'id' => $user->id,
            'uuid' => $user->uuid,
            'email' => $user->email,
            'username' => $user->username,
            'first_name' => $user->name_first,
            'last_name' => $user->name_last,
        ];
    }
}
