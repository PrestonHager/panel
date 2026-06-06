<?php

namespace Pterodactyl\Http\Controllers\Plugins;

use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Plugin;
use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Plugins\PluginContextFactory;
use Pterodactyl\Services\Plugins\PluginApiRouter;
use Pterodactyl\Services\Plugins\PluginRegistry;
use Pterodactyl\Services\Plugins\ManifestValidator;
use Pterodactyl\Plugins\Http\PluginHttpRequest;
use Pterodactyl\Plugins\Http\PluginHttpResponse;
use Pterodactyl\Plugins\PluginClientPermissionGate;
use Pterodactyl\Plugins\Contracts\PluginInterface;
use Pterodactyl\Plugins\Exceptions\PluginException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PluginApiDispatcher
{
    public function __construct(
        private readonly PluginApiRouter $router,
        private readonly PluginContextFactory $contextFactory,
        private readonly PluginClientPermissionGate $clientPermissionGate,
        private readonly ManifestValidator $manifestValidator,
    ) {
    }

    public function dispatch(Request $request, string $plugin, string $path, string $expectedAuth): JsonResponse
    {
        $pluginModel = Plugin::query()->find($plugin);
        if (is_null($pluginModel) || !$pluginModel->enabled) {
            throw new NotFoundHttpException();
        }

        $directory = PluginRegistry::directoryFor($plugin);
        $manifest = $this->manifestValidator->readFromDirectory($directory);

        $match = $this->router->match($manifest, $request->method(), $path, $expectedAuth);
        if (is_null($match)) {
            throw new NotFoundHttpException();
        }

        $route = $match['route'];
        $params = $match['params'];

        $user = $request->user();
        $userId = $user instanceof User ? $user->id : null;

        if ($expectedAuth === 'client' && is_null($userId)) {
            throw new AccessDeniedHttpException();
        }

        if ($expectedAuth === 'admin') {
            if (is_null($user) || !$user->root_admin) {
                throw new AccessDeniedHttpException();
            }
        }

        $serverId = null;
        if (isset($params['server'])) {
            $server = Server::query()->where('uuid', $params['server'])->first();
            if (is_null($server)) {
                throw new NotFoundHttpException();
            }

            if ($expectedAuth === 'client' && $user instanceof User) {
                if ($user->id !== $server->owner_id && !$user->root_admin) {
                    if (!$server->subusers()->where('user_id', $user->id)->exists()) {
                        throw new NotFoundHttpException();
                    }
                }
            }

            $serverId = $server->id;
            $serverModel = $server;

            if ($expectedAuth === 'client' && !empty($route['permission']) && $user instanceof User) {
                $this->clientPermissionGate->authorize($user, $serverModel, $pluginModel, $route['permission']);
            }
        }

        $pluginRequest = new PluginHttpRequest(
            userId: $userId,
            routeParams: $params,
            query: $request->query(),
            body: $request->json()->all(),
            serverId: $serverId,
            rawBody: $request->getContent(),
        );

        $context = $this->contextFactory->make($pluginModel);
        $result = $this->dispatchHandler($route['handler'], $context, $pluginRequest);

        if ($result instanceof PluginHttpResponse) {
            return response()->json($result->data, $result->status);
        }

        if (is_array($result)) {
            return response()->json($result);
        }

        throw new PluginException('Plugin handler must return an array or PluginHttpResponse.');
    }

    private function dispatchHandler(string $handler, $context, PluginHttpRequest $request): mixed
    {
        if (str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);
            $instance = new $class();

            return $instance->{$method}($context, $request);
        }

        $instance = new $handler();
        if ($instance instanceof PluginInterface) {
            throw new PluginException('Plugin entry class cannot be used as an API handler.');
        }

        if (is_callable($instance)) {
            return $instance($context, $request);
        }

        if (method_exists($instance, 'handle')) {
            return $instance->handle($context, $request);
        }

        throw new PluginException(sprintf('Handler "%s" is not invokable.', $handler));
    }
}
