<?php
declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as Upstream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use SwapFix\Application\Application;
use SwapFix\Application\Container;
use SwapFix\Http\CsrfTokens;

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

    public function testVerificationAcceptsLoopbackOriginOnTheActualDevelopmentPort(): void
    {
        $config = ['environment' => 'local', 'url' => 'http://127.0.0.1:8000'] + $this->config();
        $container = new Container($config, $this->services());
        $seed = str_repeat('a', 64);
        $token = (new CsrfTokens($container->crypto(), $seed, null))->token();
        $app = Application::create($config, $this->services());
        $request = (new ServerRequestFactory())->createServerRequest('POST', 'http://127.0.0.1:8012/verify-email')
            ->withCookieParams([$container->cookies()->name('csrf') => $seed])
            ->withHeader('Origin', 'http://127.0.0.1:8012')
            ->withParsedBody(['_csrf' => $token, 'code' => '000042']);
        self::assertSame('/login', $app->handle($request)->getHeaderLine('Location'));
        self::assertSame(403, $app->handle($request->withHeader('Origin', 'https://127.0.0.1:8012'))->getStatusCode());
    }

    public function testExpiredVerificationFormsOfferAFreshFormWithoutSendingOrVerifying(): void
    {
        $container = new Container($this->config(), $this->services());
        $pending = $container->crypto()->encrypt(['m' => 'person@example.test'], 'pending');
        $cookies = [$container->cookies()->name('pending') => $pending];
        $app = $this->app();
        foreach (['/verify-email', '/verify-email/resend'] as $path) {
            $request = $this->request('POST', $path)->withCookieParams($cookies)
                ->withHeader('Origin', 'https://swapfix.example.test')
                ->withParsedBody(['_csrf' => 'expired-token', 'code' => '000042']);
            $response = $app->handle($request);
            self::assertSame(303, $response->getStatusCode());
            self::assertSame('/verify-email', $response->getHeaderLine('Location'));
            self::assertStringNotContainsString('swapfix_session', $response->getHeaderLine('Set-Cookie'));
            self::assertSame(403, $app->handle($request->withHeader('Origin', 'https://other.example.test'))->getStatusCode());

            [$name, $value] = explode('=', explode(';', $response->getHeaderLine('Set-Cookie'), 2)[0], 2);
            $page = $app->handle($this->request('GET', '/verify-email')->withCookieParams($cookies + [$name => rawurldecode($value)]));
            self::assertSame(200, $page->getStatusCode());
            self::assertStringContainsString('Your verification form expired', (string) $page->getBody());
            self::assertStringContainsString('name="code"', (string) $page->getBody());
            self::assertStringNotContainsString('000042', (string) $page->getBody());
            self::assertStringContainsString('__Host-swapfix_csrf=', $page->getHeaderLine('Set-Cookie'));
        }
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

    public function testExpiredRegistrationAndSignInFormsReopenWithoutSubmitting(): void
    {
        $app = $this->app();
        foreach (['/login', '/register'] as $path) {
            $request = $this->request('POST', $path)->withHeader('Origin', 'https://swapfix.example.test')
                ->withParsedBody(['_csrf' => 'expired-token']);
            $result = $app->handle($request);
            self::assertSame(303, $result->getStatusCode());
            self::assertSame($path, $result->getHeaderLine('Location'));
        }
    }

    public function testUnauthenticatedPublishingReturnsToSignIn(): void
    {
        $response = $this->app()->handle($this->request('GET', '/market/new'));
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/login?next=%2Fmarket%2Fnew', $response->getHeaderLine('Location'));
    }

    public static function signupCodes(): iterable
    {
        yield 'six digits' => ['000042'];
        yield 'eight digits' => ['00000042'];
    }

    #[DataProvider('signupCodes')]
    public function testSignupCodeResendsAndVerifiesBeforeCreatingProfile(string $code): void
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
            static function ($request) use ($email, $code): Upstream {
                self::assertSame('/auth/v1/verify', $request->getUri()->getPath());
                self::assertSame(['email' => $email, 'token' => $code, 'type' => 'signup'], json_decode((string) $request->getBody(), true));
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
        self::assertStringContainsString('Enter the verification code', (string) $invalid->getBody());
        $expired = $post('/verify-email', ['code' => '111111']);
        self::assertSame(422, $expired->getStatusCode());
        self::assertStringContainsString('expired', (string) $expired->getBody());
        self::assertStringNotContainsString('Upstream details', (string) $expired->getBody());

        // The email in the encrypted pending signup is authoritative.
        $verified = $post('/verify-email', ['code' => $code, 'email' => 'other@example.test']);
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

    public function testActivityShowsAnOfferOnHoldWhileAnotherOfferIsAgreed(): void
    {
        $owner = '00000000-0000-4000-8000-000000000001';
        $pending = [
            'owner_id' => $owner, 'offerer_id' => '00000000-0000-4000-8000-000000000002',
            'status' => 'pending', 'awaiting_user_id' => $owner, 'current_version' => 1,
            'updated_at' => '2026-11-18T18:00:00Z',
        ];
        $offers = [
            ['id' => '00000000-0000-4000-8000-000000000003', 'exchange' => ['title' => 'Open trade', 'status' => 'open']] + $pending,
            ['id' => '00000000-0000-4000-8000-000000000004', 'exchange' => ['title' => 'Agreed trade', 'status' => 'agreed']] + $pending,
        ];
        $response = $this->app([$this->memberSession($owner), new Upstream(200, [], json_encode($offers))])
            ->handle($this->memberRequest('/activity?tab=offers')->withQueryParams(['tab' => 'offers']));

        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        self::assertMatchesRegularExpression('~<strong>Open trade</strong>[^\n]*>Your turn</span>~', $html);
        self::assertMatchesRegularExpression('~<strong>Agreed trade</strong>[^\n]*>On hold</span>~', $html);
    }

    public function testListingEditorsAllowPhotoReplacementAtCapacity(): void
    {
        $owner = '00000000-0000-4000-8000-000000000001';
        $id = '00000000-0000-4000-8000-000000000002';
        $listing = [
            'id' => $id, 'owner_id' => $owner, 'title' => 'iPhone 13', 'description' => 'In good condition',
            'category' => 'cellphone', 'brand' => 'Apple', 'model' => '13', 'condition' => 'good',
            'known_defects' => '', 'location' => 'Manila', 'price' => '18500.00', 'wanted_item' => 'Pixel',
            'wanted_details' => '', 'cash_preference' => 'none',
            'photo_paths' => array_map(static fn (int $photo): string => $owner . '/' . sprintf('%032x', $photo) . '.jpg', range(1, 8)),
        ];
        foreach (['market', 'exchange'] as $section) {
            $response = $this->app([$this->memberSession($owner), new Upstream(200, [], json_encode([$listing]))])
                ->handle($this->memberRequest('/' . $section . '/' . $id . '/edit'));

            self::assertSame(200, $response->getStatusCode(), $section);
            $html = (string) $response->getBody();
            self::assertSame(8, substr_count($html, 'name="keep_photos[]"'), $section);
            self::assertStringContainsString('name="photos[]"', $html, $section);
        }
    }

    private function memberSession(string $userId): Upstream
    {
        return new Upstream(200, [], json_encode([
            'user_id' => $userId,
            'profile' => ['id' => $userId, 'display_name' => 'Customer', 'avatar_path' => null, 'account_type' => 'customer'],
        ]));
    }

    private function memberRequest(string $path): \Psr\Http\Message\ServerRequestInterface
    {
        $container = new Container($this->config(), $this->services());
        $cookie = $container->sessions()->issue(['access_token' => 'test-access', 'refresh_token' => 'test-refresh', 'expires_in' => 3600]);
        [$name, $value] = explode('=', explode(';', $cookie, 2)[0], 2);
        return $this->request('GET', $path)->withCookieParams([$name => rawurldecode($value)]);
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
