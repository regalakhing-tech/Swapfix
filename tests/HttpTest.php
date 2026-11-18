<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

final class HttpTest extends TestCase
{
    private function response(string $method, string $path): \Psr\Http\Message\ResponseInterface
    {
        $app = require __DIR__ . '/../bootstrap/app.php';
        return $app->handle((new ServerRequestFactory())->createServerRequest($method, $path));
    }

    public function testHomeLoadsWithoutExternalCredentials(): void
    {
        $response = $this->response('GET', '/');
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('SwapFix', (string) $response->getBody());
        self::assertStringContainsString('Sample listings', (string) $response->getBody());
        self::assertSame('no-cache', $response->getHeaderLine('Cache-Control'));
    }

    public function testHealthDistinguishesRuntimeFromIntegrationReadiness(): void
    {
        $response = $this->response('GET', '/api/health');
        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ok', $body['status']);
        self::assertSame('not_checked', $body['integrations']['supabase']['connection']);
        self::assertSame('not_checked', $body['integrations']['mail']['delivery']);
        self::assertStringNotContainsString('password', (string) $response->getBody());
    }

    public function testUnknownApiRouteIsJson404(): void
    {
        $response = $this->response('GET', '/api/missing');
        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('Not found.', json_decode((string) $response->getBody(), true)['error']);
    }

    public function testUnsupportedHealthMethodIs405(): void
    {
        self::assertSame(405, $this->response('POST', '/api/health')->getStatusCode());
    }

    public function testPrivateFilesDoNotResolveAsPublicRoutes(): void
    {
        foreach (['/.env', '/composer.json', '/app/Integrations/Mail/Mailer.php', '/supabase/config.toml'] as $path) {
            self::assertSame(404, $this->response('GET', $path)->getStatusCode(), $path);
        }
    }

    public function testSearchInputIsEscapedInTheRenderedPage(): void
    {
        $query = http_build_query(['q' => '"><script>alert(1)</script>']);
        $response = $this->response('GET', '/?' . $query);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString('<script>alert(1)</script>', (string) $response->getBody());
    }
}
