<?php
declare(strict_types=1);

namespace SwapFix\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SecurityHeaders implements MiddlewareInterface
{
    public function __construct(private readonly string $imageOrigin = '', private readonly bool $https = false) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $images = trim("'self' data: blob: " . $this->imageOrigin);
        $response = $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Referrer-Policy', 'same-origin')
            ->withHeader('Cache-Control', $response->getHeaderLine('Cache-Control') ?: 'no-store')
            ->withHeader('Content-Security-Policy', "default-src 'self'; style-src 'self'; img-src {$images}; base-uri 'self'; frame-ancestors 'none'; form-action 'self'; object-src 'none'");
        return $this->https ? $response->withHeader('Strict-Transport-Security', 'max-age=31536000') : $response;
    }
}
