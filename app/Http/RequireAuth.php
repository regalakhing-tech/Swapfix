<?php
declare(strict_types=1);

namespace SwapFix\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Exception\HttpException;
use SwapFix\Integrations\Supabase\Auth;
use SwapFix\Support\IntegrationException;

final class RequireAuth implements MiddlewareInterface
{
    public function __construct(private readonly Auth $auth) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!preg_match('/^Bearer (\S+)$/i', $request->getHeaderLine('Authorization'), $match)) {
            throw new HttpUnauthorizedException($request);
        }
        try {
            $user = $this->auth->user($match[1]);
        } catch (IntegrationException $exception) {
            if ($exception->upstreamStatus >= 500) {
                throw new HttpException($request, 'Service unavailable.', 503);
            }
            throw new HttpUnauthorizedException($request);
        }
        return $handler->handle($request->withAttribute('user', $user)->withAttribute('access_token', $match[1]));
    }
}
