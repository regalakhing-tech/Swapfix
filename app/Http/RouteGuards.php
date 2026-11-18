<?php
declare(strict_types=1);

namespace SwapFix\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpForbiddenException;
use Slim\Psr7\Response;
use SwapFix\Application\Container;
use SwapFix\Application\SetupRequired;

/** Route middleware for configuration, sign-in, finished profiles and technician access. */
final class RouteGuards
{
    public function __construct(private readonly Container $app) {}

    public function configured(): callable
    {
        return function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
            if (!$this->app->ready()) {
                throw new SetupRequired($this->app->missingSettings());
            }
            return $handler->handle($request);
        };
    }

    public function signedIn(): callable
    {
        return function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
            if (!$request->getAttribute('session') instanceof UserSession) {
                $uri = $request->getUri();
                $next = $request->getMethod() === 'GET' ? $uri->getPath() . ($uri->getQuery() !== '' ? '?' . $uri->getQuery() : '') : '';
                return self::redirect('/login' . ($next !== '' ? '?next=' . rawurlencode($next) : ''));
            }
            return $handler->handle($request);
        };
    }

    public function hasProfile(): callable
    {
        return static function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
            $session = $request->getAttribute('session');
            if ($session instanceof UserSession && $session->profile === null) {
                return self::redirect('/onboarding');
            }
            return $handler->handle($request);
        };
    }

    public function technician(): callable
    {
        return static function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
            $session = $request->getAttribute('session');
            if (!$session instanceof UserSession || !$session->isTechnician()) {
                throw new HttpForbiddenException($request, 'This area is for technician accounts.');
            }
            return $handler->handle($request);
        };
    }

    private static function redirect(string $location): ResponseInterface
    {
        return (new Response(303))->withHeader('Location', $location);
    }
}
