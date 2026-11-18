<?php
declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as Upstream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use SwapFix\Application\Container;
use SwapFix\Http\SessionMiddleware;
use SwapFix\Modules\Accounts\AuthController;

final class SessionCookiePrecedenceTest extends TestCase
{
    private const USER = '00000000-0000-4000-8000-000000000001';

    private function container(array $queue): Container
    {
        return new Container(
            ['environment' => 'production', 'url' => 'https://swapfix.example.test', 'key' => str_repeat('x', 32)],
            ['supabase' => ['url' => 'https://example.supabase.test', 'publishable_key' => 'sb_publishable_test', 'upload_max_bytes' => 5242880]],
            new Client(['handler' => HandlerStack::create(new MockHandler($queue))]),
        );
    }

    private function tokens(): array
    {
        return ['access_token' => 'new-access-test', 'refresh_token' => 'new-refresh-test', 'expires_at' => time() + 3600, 'user' => ['email' => 'person@example.test']];
    }

    private function profile(): array
    {
        return ['user_id' => self::USER, 'profile' => ['id' => self::USER, 'account_type' => 'customer', 'display_name' => 'Person']];
    }

    private function runAction(Container $app, string $action): string
    {
        $old = $app->crypto()->encrypt(['a' => 'old-access-test', 'r' => 'old-refresh-test', 'e' => time() - 1, 'm' => 'person@example.test'], 'session');
        $request = (new ServerRequestFactory())->createServerRequest('POST', 'https://swapfix.example.test/' . $action)
            ->withCookieParams([$app->cookies()->name('session') => $old])
            ->withParsedBody(['email' => 'person@example.test', 'password' => 'valid-test-password']);
        $handler = new class($app, $action) implements RequestHandlerInterface {
            public function __construct(private Container $app, private string $action) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return (new AuthController($this->app))->{$this->action}($request, new Response());
            }
        };
        $response = (new SessionMiddleware($app))->process($request, $handler);
        $cookies = array_values(array_filter($response->getHeader('Set-Cookie'), fn(string $header): bool => str_starts_with($header, $app->cookies()->name('session') . '=')));
        self::assertNotEmpty($cookies);
        return $cookies[array_key_last($cookies)];
    }

    public function testLogoutClearWinsOverMiddlewareRefresh(): void
    {
        $app = $this->container([new Upstream(200, [], json_encode($this->tokens())), new Upstream(200, [], json_encode($this->profile())), new Upstream(200, [], json_encode(['id' => self::USER])), new Upstream(204)]);
        self::assertStringContainsString('; Max-Age=0;', $this->runAction($app, 'logout'));
    }

    public function testNewLoginWinsOverInvalidOldSessionDeletion(): void
    {
        $app = $this->container([new Upstream(400, [], '{"error_code":"refresh_token_not_found"}'), new Upstream(200, [], json_encode($this->tokens())), new Upstream(200, [], json_encode($this->profile()))]);
        $last = $this->runAction($app, 'login');
        $value = rawurldecode(explode(';', explode('=', $last, 2)[1], 2)[0]);
        $state = $app->crypto()->decrypt($value, 'session');
        self::assertNotNull($state);
        self::assertSame('new-access-test', $state['a']);
    }

    public function testPendingSignupChoicesDoNotApplyToAnotherIdentity(): void
    {
        $app = $this->container([
            new Upstream(200, [], json_encode($this->tokens())),
            new Upstream(200, [], json_encode(['user_id' => self::USER, 'profile' => null])),
        ]);
        $pending = $app->crypto()->encrypt(['t' => 'technician', 'n' => 'Alice', 'l' => 'Manila', 'm' => 'alice@example.test'], 'pending');
        $request = (new ServerRequestFactory())->createServerRequest('POST', 'https://swapfix.example.test/login')
            ->withCookieParams([$app->cookies()->name('pending') => $pending])
            ->withParsedBody(['email' => 'person@example.test', 'password' => 'valid-test-password']);
        $response = (new AuthController($app))->login($request, new Response());
        self::assertSame('/onboarding', $response->getHeaderLine('Location'));
        self::assertStringContainsString($app->cookies()->name('pending') . '=;', implode('\n', $response->getHeader('Set-Cookie')));
    }
}
