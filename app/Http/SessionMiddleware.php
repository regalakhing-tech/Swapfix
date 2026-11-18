<?php
declare(strict_types=1);

namespace SwapFix\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SwapFix\Application\Container;
use SwapFix\Support\IntegrationException;

/** Attaches session, flash and CSRF state, and writes any cookie changes to the response. */
final class SessionMiddleware implements MiddlewareInterface
{
    private const FLASH = 'flash';
    private const CSRF = 'csrf';

    public function __construct(private readonly Container $app) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (str_starts_with($request->getUri()->getPath(), '/api/') || str_starts_with($request->getUri()->getPath(), '/internal/')) {
            return $handler->handle($request);
        }
        if (!$this->app->ready()) {
            return $handler->handle($request->withAttribute('flash', new Flash()));
        }
        $cookies = $this->app->cookies();
        $crypto = $this->app->crypto();
        $params = $request->getCookieParams();
        $set = [];

        $session = null;
        try {
            [$session, $sessionCookie] = $this->app->sessions()->restore($params[$cookies->name(SessionManager::COOKIE)] ?? null);
            if ($sessionCookie !== null) {
                $set[] = $sessionCookie;
            }
        } catch (IntegrationException $exception) {
            // Public pages stay usable during an Auth outage; protected routes will ask to sign in.
            error_log('SwapFix session restore failed: upstream ' . $exception->upstreamStatus);
        }

        $flashCookie = $params[$cookies->name(self::FLASH)] ?? null;
        $incoming = is_string($flashCookie) ? ($crypto->decrypt($flashCookie, self::FLASH) ?? []) : [];
        $flash = new Flash($incoming);
        $csrfSeed = $params[$cookies->name(self::CSRF)] ?? null;
        $csrf = new CsrfTokens($crypto, is_string($csrfSeed) ? $csrfSeed : null, $session?->id);

        $request = $request->withAttribute('session', $session)->withAttribute('flash', $flash)->withAttribute('csrf', $csrf);
        try {
            $response = $handler->handle($request);
        } catch (\Throwable $exception) {
            // Render here so error pages keep the signed-in shell and pending cookies.
            $response = $this->app->errors()->render($request, $exception);
        }

        if ($csrf->newSeed() !== null) {
            $set[] = $cookies->set(self::CSRF, $csrf->newSeed(), 43200);
        }
        if ($flash->outgoing() !== []) {
            $set[] = $cookies->set(self::FLASH, $crypto->encrypt($flash->outgoing(), self::FLASH), 60);
        } elseif ($incoming !== [] && !$response->hasHeader('Location')) {
            $set[] = $cookies->clear(self::FLASH);
        }
        // A controller may replace or clear a restored session (login/logout). Its
        // explicit cookies must be last, because browsers keep the last same-name cookie.
        $explicitCookies = $response->getHeader('Set-Cookie');
        $response = $response->withoutHeader('Set-Cookie');
        foreach (array_merge($set, $explicitCookies) as $value) {
            $response = $response->withAddedHeader('Set-Cookie', $value);
        }
        return $response;
    }
}
