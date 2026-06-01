<?php

namespace Pterodactyl\Plugins\Http;

final readonly class PluginHttpResponse
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public array $data,
        public int $status = 200,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function json(array $data, int $status = 200): self
    {
        return new self($data, $status);
    }
}
