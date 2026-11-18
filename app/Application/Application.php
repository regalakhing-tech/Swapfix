<?php
declare(strict_types=1);

namespace SwapFix\Application;

use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use SwapFix\Http\CsrfMiddleware;
use SwapFix\Http\SecurityHeaders;
use SwapFix\Http\SessionMiddleware;

final class Application
{
    public static function create(array $config, array $services, ?ClientInterface $http = null): App
    {
        $container = new Container($config, $services, $http);
        $root = dirname(__DIR__, 2);
        $app = AppFactory::create();
        (require $root . '/routes/web.php')($app, $container);
        (require $root . '/routes/api.php')($app, $container);
        // Slim runs the most recently added middleware first.
        $app->add(new CsrfMiddleware($config['url'], $container->isProduction()));
        $app->add(new SessionMiddleware($container));
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();
        $errors = $app->addErrorMiddleware(false, false, false);
        $errors->setDefaultErrorHandler(static fn (ServerRequestInterface $request, \Throwable $exception) => $container->errors()->render($request, $exception));
        $app->add(new SecurityHeaders(self::imageOrigin($services['supabase']['url']), $container->isHttps()));
        return $app;
    }

    private static function imageOrigin(string $supabaseUrl): string
    {
        $parts = parse_url($supabaseUrl);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || preg_match('/^[a-z0-9.-]+$/i', $parts['host']) !== 1) {
            return '';
        }
        return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . (int) $parts['port'] : '');
    }
}
