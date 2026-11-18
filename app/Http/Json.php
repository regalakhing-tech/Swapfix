<?php
declare(strict_types=1);

namespace SwapFix\Http;

use Psr\Http\Message\ResponseInterface;

final class Json
{
    public static function respond(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
