<?php

use Illuminate\Support\Str;

$proxies = env('TRUSTED_PROXIES');

// Panels with https:// APP_URL are almost always behind a reverse proxy. Default to
// trusting proxies when unset so URL generation and scheme detection do not loop.
if ($proxies === null && Str::startsWith((string) env('APP_URL', ''), 'https://')) {
    $proxies = '*';
}

return [
    /*
     * Set trusted proxy IP addresses.
     *
     * Both IPv4 and IPv6 addresses are
     * supported, along with CIDR notation.
     *
     * The "*" character is syntactic sugar
     * within TrustedProxy to trust any proxy
     * that connects directly to your server,
     * a requirement when you cannot know the address
     * of your proxy (e.g. if using Rackspace balancers).
     *
     * The "**" character is syntactic sugar within
     * TrustedProxy to trust not just any proxy that
     * connects directly to your server, but also
     * proxies that connect to those proxies, and all
     * the way back until you reach the original source
     * IP. It will mean that $request->getClientIp()
     * always gets the originating client IP, no matter
     * how many proxies that client's request has
     * subsequently passed through.
     */
    'proxies' => in_array($proxies, ['*', '**'], true)
        ? $proxies
        : array_values(array_filter(array_map('trim', explode(',', (string) $proxies)))),
];
