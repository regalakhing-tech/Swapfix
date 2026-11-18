<?php
declare(strict_types=1);

use Slim\App;
use SwapFix\Application\Container;
use SwapFix\Application\SetupRequired;
use SwapFix\Controllers\ApiController;
use SwapFix\Controllers\HealthController;
use SwapFix\Http\RouteGuards;
use SwapFix\Http\RequireAuth;

return static function (App $app, Container $container): void {
    $api = new ApiController($container);
    $guards = new RouteGuards($container);
    $app->get('/api/health', new HealthController($container));
    $app->get('/api/listings', $api->listings(...))->add($guards->configured());
    // Bearer-token clients only. Browser pages use the encrypted session cookie instead.
    $app->get('/api/me', $api->me(...))->add(static function ($request, $handler) use ($container) {
        if (!$container->ready()) {
            throw new SetupRequired($container->missingSettings());
        }
        return (new RequireAuth($container->auth()))->process($request, $handler);
    });
    $app->map(['GET', 'POST'], '/internal/mail/dispatch', $api->dispatchMail(...));
};
