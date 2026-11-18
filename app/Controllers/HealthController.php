<?php
declare(strict_types=1);

namespace SwapFix\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SwapFix\Http\Json;

final class HealthController
{
    public function __construct(private readonly array $services) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $supabase = $this->services['supabase'];
        $mail = $this->services['mail'];
        return Json::respond($response, [
            'status' => 'ok',
            'application' => 'SwapFix',
            'stage' => 'starter',
            'integrations' => [
                'supabase' => ['configured' => $supabase['url'] !== '' && $supabase['publishable_key'] !== '', 'connection' => 'not_checked'],
                'mail' => ['configured' => $mail['host'] !== '' && $mail['from_address'] !== '', 'delivery' => 'not_checked'],
            ],
        ]);
    }
}
