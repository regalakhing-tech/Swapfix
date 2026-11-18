<?php
declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as Upstream;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use SwapFix\Application\Application;
use SwapFix\Application\Container;

final class FeatureHttpTest extends TestCase
{
    private function config(): array
    {
        return ['environment' => 'production', 'url' => 'https://swapfix.example.test', 'timezone' => 'Asia/Manila', 'key' => str_repeat('x', 32)];
    }
    private function services(): array
    {
        return ['supabase' => ['url' => 'https://example.supabase.test', 'publishable_key' => 'sb_publishable_test', 'upload_max_bytes' => 5242880]];
    }
    private function app(array $queue = []): \Slim\App
    {
        return Application::create($this->config(), $this->services(), new Client(['handler' => HandlerStack::create(new MockHandler($queue))]));
    }
    private function request(string $method, string $path): \Psr\Http\Message\ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest($method, 'https://swapfix.example.test' . $path);
    }

    public function testSignInRendersSecureCsrfFormAndRejectsCrossOriginWrites(): void
    {
        $app = $this->app();
        $response = $app->handle($this->request('GET', '/login'));
        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        self::assertStringContainsString('/assets/css/workspace.css?', $html);
        preg_match('/name="_csrf" value="([^"]+)"/', $html, $match);
        self::assertNotEmpty($match[1]);
        $cookies = [];
        foreach ($response->getHeader('Set-Cookie') as $cookie) {
            self::assertStringContainsString('; Secure', $cookie);
            self::assertStringContainsString('; HttpOnly', $cookie);
            [$name, $value] = explode('=', explode(';', $cookie, 2)[0], 2);
            $cookies[$name] = rawurldecode($value);
        }
        $post = $this->request('POST', '/login')->withCookieParams($cookies)
            ->withHeader('Origin', 'https://unrelated.example.test')
            ->withParsedBody(['_csrf' => $match[1], 'email' => 'person@example.test', 'password' => 'valid-password']);
        $rejected = $app->handle($post);
        self::assertSame(403, $rejected->getStatusCode());
        self::assertStringContainsString('This form expired', (string) $rejected->getBody());
    }

    public function testUnauthenticatedPublishingReturnsToSignIn(): void
    {
        $response = $this->app()->handle($this->request('GET', '/market/new'));
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/login?next=%2Fmarket%2Fnew', $response->getHeaderLine('Location'));
    }

    public function testSignupCodeResendsAndVerifiesBeforeCreatingProfile(): void
    {
        $email = 'new-user@example.test';
        $userId = '00000000-0000-4000-8000-000000000003';
        $app = $this->app([
            new Upstream(200, [], json_encode(['id' => $userId, 'email' => $email])),
            static function ($request) use ($email): Upstream {
                self::assertSame('/auth/v1/resend', $request->getUri()->getPath());
                $body = json_decode((string) $request->getBody(), true);
                self::assertSame('signup', $body['type']);
                self::assertSame($email, $body['email']);
                return new Upstream(200, [], '{}');
            },
            new Upstream(403, [], '{"error_code":"otp_expired","message":"Upstream details"}'),
            static function ($request) use ($email): Upstream {
                self::assertSame('/auth/v1/verify', $request->getUri()->getPath());
                self::assertSame(['email' => $email, 'token' => '000042', 'type' => 'signup'], json_decode((string) $request->getBody(), true));
                return new Upstream(200, [], json_encode([
                    'access_token' => 'test-access', 'refresh_token' => 'test-refresh',
                    'user' => ['email' => $email],
                ]));
            },
            new Upstream(200, [], json_encode(['user_id' => $userId, 'profile' => null])),
            static function ($request): Upstream {
                self::assertSame('/rest/v1/rpc/sf_create_profile', $request->getUri()->getPath());
                self::assertSame(['p_account_type' => 'technician', 'p_display_name' => 'Test Tech', 'p_location' => 'Manila'], json_decode((string) $request->getBody(), true));
                return new Upstream(200, [], '{}');
            },
        ]);

        $cookies = [];
        $saveCookies = static function ($response) use (&$cookies): void {
            foreach ($response->getHeader('Set-Cookie') as $cookie) {
                [$name, $value] = explode('=', explode(';', $cookie, 2)[0], 2);
                $cookies[$name] = rawurldecode($value);
            }
        };
        $register = $app->handle($this->request('GET', '/register'));
        $saveCookies($register);
        preg_match('/name="_csrf" value="([^"]+)"/', (string) $register->getBody(), $match);
        self::assertNotEmpty($match[1]);
        $post = function (string $path, array $fields = []) use ($app, &$cookies, $match) {
            return $app->handle($this->request('POST', $path)
                ->withCookieParams($cookies)->withHeader('Origin', 'https://swapfix.example.test')
                ->withParsedBody($fields + ['_csrf' => $match[1]]));
        };
        $signedUp = $post('/register', ['account_type' => 'technician', 'display_name' => 'Test Tech', 'location' => 'Manila', 'email' => $email, 'password' => 'test-password']);
        self::assertSame('/verify-email', $signedUp->getHeaderLine('Location'));
        self::assertStringNotContainsString('__Host-swapfix_session=', $signedUp->getHeaderLine('Set-Cookie'));
        $saveCookies($signedUp);
        $screen = $app->handle($this->request('GET', '/verify-email')->withCookieParams($cookies));
        self::assertSame(200, $screen->getStatusCode());
        self::assertSame('no-store', $screen->getHeaderLine('Cache-Control'));
        self::assertStringContainsString('autocomplete="one-time-code"', (string) $screen->getBody());

        self::assertSame('/verify-email', $post('/verify-email/resend')->getHeaderLine('Location'));
        $invalid = $post('/verify-email', ['code' => '0000427']);
        self::assertSame(422, $invalid->getStatusCode());
        self::assertStringContainsString('Enter the six-digit code', (string) $invalid->getBody());
        $expired = $post('/verify-email', ['code' => '111111']);
        self::assertSame(422, $expired->getStatusCode());
        self::assertStringContainsString('expired', (string) $expired->getBody());
        self::assertStringNotContainsString('Upstream details', (string) $expired->getBody());

        // The email in the encrypted pending signup is authoritative.
        $verified = $post('/verify-email', ['code' => '000042', 'email' => 'other@example.test']);
        self::assertSame('/technician/profile', $verified->getHeaderLine('Location'));
        $saveCookies($verified);
        self::assertNotEmpty($cookies['__Host-swapfix_session']);
        self::assertSame('', $cookies['__Host-swapfix_pending']);
    }

    public function testPublicMarketplaceShowsRealEmptyStateWithoutSampleRecords(): void
    {
        $response = $this->app([new Upstream(200, [], '{"items":[],"total":0}')])->handle($this->request('GET', '/market'));
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('No listings match yet.', (string) $response->getBody());
        self::assertStringNotContainsString('Sample listings', (string) $response->getBody());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
    }

    public function testPublicListingRendersDatesPricesAndEscapesSellerContent(): void
    {
        $listing = [
            'id' => '00000000-0000-4000-8000-000000000002', 'owner_id' => '00000000-0000-4000-8000-000000000001',
            'title' => 'iPhone <script>bad</script>', 'brand' => 'Apple', 'model' => '13', 'category' => 'cellphone',
            'description' => 'Screen works', 'condition' => 'good', 'known_defects' => '', 'price' => '18500.50',
            'location' => 'Manila', 'photo_paths' => [], 'status' => 'available', 'created_at' => '2026-11-18T18:00:00Z',
            'owner' => ['display_name' => 'Seller', 'avatar_path' => null],
        ];
        $response = $this->app([new Upstream(200, [], json_encode([$listing]))])->handle($this->request('GET', '/market/' . $listing['id']));
        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        self::assertStringContainsString('Nov 19, 2026', $html);
        self::assertStringContainsString('18,500.50', $html);
        self::assertStringContainsString('&lt;script&gt;bad&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>bad</script>', $html);
        self::assertStringContainsString('Sign in to inquire', $html);
    }

    public function testCustomerCannotUseTechnicianDashboard(): void
    {
        $container = new Container($this->config(), $this->services());
        $cookie = $container->sessions()->issue(['access_token' => 'test-access', 'refresh_token' => 'test-refresh', 'expires_in' => 3600]);
        [$name, $value] = explode('=', explode(';', $cookie, 2)[0], 2);
        $session = ['user_id' => '00000000-0000-4000-8000-000000000001', 'profile' => ['id' => '00000000-0000-4000-8000-000000000001', 'display_name' => 'Customer', 'avatar_path' => null, 'account_type' => 'customer']];
        $response = $this->app([new Upstream(200, [], json_encode($session))])->handle($this->request('GET', '/technician/dashboard')->withCookieParams([$name => rawurldecode($value)]));
        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('technician accounts', (string) $response->getBody());
        self::assertStringNotContainsString('test-access', (string) $response->getBody());
    }
}
