<?php
declare(strict_types=1);

namespace SwapFix\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SwapFix\Application\Container;
use SwapFix\Http\Json;

final class HealthController
{
    public function __construct(private readonly Container $app) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $mail = $this->app->services['mail'];
        return Json::respond($response, [
            'status' => 'ok',
            'application' => 'SwapFix',
            'integrations' => [
                'supabase' => ['configured' => $this->app->ready(), 'connection' => 'not_checked'],
                'mail' => ['configured' => $mail['host'] !== '' && $mail['from_address'] !== '', 'delivery' => 'not_checked'],
            ],
        ]);
    }
}
