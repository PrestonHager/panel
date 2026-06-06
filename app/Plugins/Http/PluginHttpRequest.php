<?php

namespace Pterodactyl\Plugins\Http;

final readonly class PluginHttpRequest
{
    /**
     * @param array<string, string> $routeParams
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     */
    public function __construct(
        public ?int $userId,
        public array $routeParams,
        public array $query,
        public array $body,
        public ?int $serverId = null,
        public string $rawBody = '',
    ) {
    }

    public function route(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function isAuthenticated(): bool
    {
        return !is_null($this->userId);
    }
}
