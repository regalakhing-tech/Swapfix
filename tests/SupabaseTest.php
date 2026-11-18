<?php
declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use SwapFix\Integrations\Supabase\Auth;
use SwapFix\Integrations\Supabase\SupabaseClient;
use SwapFix\Integrations\Supabase\Storage;
use SwapFix\Support\IntegrationException;

final class SupabaseTest extends TestCase
{
    public function testUserTokenAndPublishableKeyAreForwardedSeparately(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], '[{"id":"one"}]')]));
        $stack->push(Middleware::history($history));
        $client = new SupabaseClient('https://example.supabase.co', 'sb_publishable_test', new Client(['handler' => $stack]));
        self::assertSame([['id' => 'one']], $client->request('GET', '/rest/v1/product_listings', [], 'user-token'));
        self::assertSame('sb_publishable_test', $history[0]['request']->getHeaderLine('apikey'));
        self::assertSame('Bearer user-token', $history[0]['request']->getHeaderLine('Authorization'));
        self::assertSame('https://example.supabase.co/rest/v1/product_listings', (string) $history[0]['request']->getUri());
    }

    public function testAnonymousRequestDoesNotUseApiKeyAsUserJwt(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], '[]')]));
        $stack->push(Middleware::history($history));
        (new SupabaseClient('https://example.supabase.co', 'sb_publishable_test', new Client(['handler' => $stack])))
            ->request('GET', '/rest/v1/product_listings');
        self::assertFalse($history[0]['request']->hasHeader('Authorization'));
    }

    public function testPrivilegedKeyCannotBeUsedForNormalUserRequests(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SupabaseClient('https://example.supabase.co', 'sb_secret_private');
    }

    public function testAuthenticationFailureDoesNotExposeUpstreamDetails(): void
    {
        $http = new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(401, [], '{"message":"private upstream details"}'),
        ]))]);
        $this->expectException(IntegrationException::class);
        $this->expectExceptionMessage('Supabase request failed.');
        (new Auth(new SupabaseClient('https://example.supabase.co', 'sb_publishable_test', $http)))->user('bad-token');
    }

    public function testAuthRequiresVerifiedUserIdentity(): void
    {
        $http = new Client(['handler' => HandlerStack::create(new MockHandler([new Response(200, [], '{}')]))]);
        $this->expectException(IntegrationException::class);
        (new Auth(new SupabaseClient('https://example.supabase.co', 'sb_publishable_test', $http)))->user('token');
    }

    public function testUploadRejectsNonImageBeforeSendingAnything(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'swapfix-test-');
        file_put_contents($path, '<?php echo "not an image";');
        try {
            $client = new SupabaseClient('https://example.supabase.co', 'sb_publishable_test', new Client([
                'handler' => HandlerStack::create(new MockHandler([])),
            ]));
            $this->expectException(InvalidArgumentException::class);
            (new Storage($client, new Auth($client)))->upload('token', $path, 'avatars');
        } finally {
            unlink($path);
        }
    }

    public function testUploadUsesVerifiedUserFolderAndRandomFilename(): void
    {
        $history = [];
        $userId = '11111111-1111-4111-8111-111111111111';
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode(['id' => $userId])),
            new Response(200, [], '{"Key":"uploaded"}'),
        ]));
        $stack->push(Middleware::history($history));
        $client = new SupabaseClient('https://example.supabase.co', 'sb_publishable_test', new Client(['handler' => $stack]));
        $path = tempnam(sys_get_temp_dir(), 'swapfix-test-');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jR1sAAAAASUVORK5CYII='));
        try {
            $result = (new Storage($client, new Auth($client)))->upload('token', $path, 'avatars');
            self::assertMatchesRegularExpression('~^' . $userId . '/[a-f0-9]{32}\.png$~', $result['path']);
            self::assertStringContainsString('/storage/v1/object/avatars/' . $userId . '/', (string) $history[1]['request']->getUri());
            self::assertSame('image/png', $history[1]['request']->getHeaderLine('Content-Type'));
        } finally {
            unlink($path);
        }
    }
}
