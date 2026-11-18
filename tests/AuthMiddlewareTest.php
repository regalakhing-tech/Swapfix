<?php
declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Slim\Exception\HttpException;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use SwapFix\Http\RequireAuth;
use SwapFix\Integrations\Supabase\Auth;
use SwapFix\Integrations\Supabase\SupabaseClient;

final class AuthMiddlewareTest extends TestCase
{
    public function testInvalidTokenProducesUnauthorized(): void
    {
        $this->expectAuthStatus(new Response(401, [], '{}'), 401);
    }

    public function testAuthOutageProducesServiceUnavailable(): void
    {
        $this->expectAuthStatus(new ConnectException('connection unavailable', new Request('GET', 'https://example.supabase.co/auth/v1/user')), 503);
    }

    private function expectAuthStatus(mixed $upstream, int $expected): void
    {
        $http = new Client(['handler' => HandlerStack::create(new MockHandler([$upstream]))]);
        $auth = new Auth(new SupabaseClient('https://example.supabase.co', 'sb_publishable_test', $http));
        $app = AppFactory::create();
        $app->get('/private', static fn ($request, $response) => $response)->add(new RequireAuth($auth));
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/private')->withHeader('Authorization', 'Bearer token');
        try {
            $app->handle($request);
            self::fail('The request must not reach the protected handler.');
        } catch (HttpException $exception) {
            self::assertSame($expected, $exception->getCode());
        }
    }
}
