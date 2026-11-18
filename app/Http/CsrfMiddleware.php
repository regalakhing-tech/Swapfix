<?php
declare(strict_types=1);

namespace SwapFix\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpForbiddenException;

/** Requires a same-origin request and a valid token on every cookie-authenticated write. */
final class CsrfMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly string $appUrl, private readonly bool $production) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true) || str_starts_with($path, '/api/') || str_starts_with($path, '/internal/')) {
            return $handler->handle($request);
        }
        $csrf = $request->getAttribute('csrf');
        if (!$csrf instanceof CsrfTokens) {
            return $handler->handle($request); // Sessions are unavailable until the app is configured.
        }
        $body = $request->getParsedBody();
        $token = is_array($body) ? ($body['_csrf'] ?? null) : null;
        if (!$this->sameOrigin($request) || !$csrf->valid($token)) {
            throw new HttpForbiddenException($request, 'This form expired. Go back, refresh and try again.');
        }
        return $handler->handle($request);
    }

    private function sameOrigin(ServerRequestInterface $request): bool
    {
        $source = $request->getHeaderLine('Origin') ?: $request->getHeaderLine('Referer');
        if ($source === '') {
            return true; // Browsers send Origin on form posts; the token still has to match.
        }
        $origin = self::origin($source);
        if ($origin !== null && $origin === self::origin($this->appUrl)) {
            return true;
        }
        // Local development may run on another loopback host or port than APP_URL.
        return !$this->production && $origin !== null && parse_url($origin, PHP_URL_HOST) !== null
            && strtolower(preg_replace('~^https?://~', '', $origin)) === strtolower($request->getHeaderLine('Host'));
    }

    private static function origin(string $url): ?string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }
        return strtolower($parts['scheme'] . '://' . $parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }
}
