<?php
declare(strict_types=1);
use GuzzleHttp\Psr7\UploadedFile;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use SwapFix\Application\Container;
use SwapFix\Http\UserSession;
use SwapFix\Support\DomainError;

final class PhotoUploadsTest extends TestCase
{
    public function testAggregateUploadLimitIsCheckedBeforeAnyStorageRequest(): void
    {
        $app = new Container([], ['supabase' => ['url' => 'https://example.supabase.test', 'publishable_key' => 'sb_publishable_test', 'upload_max_bytes' => 4194304]]);
        $user = new UserSession('00000000-0000-4000-8000-000000000001', 'test-access', '', null);
        $file = static fn () => new UploadedFile(Utils::streamFor('fixture'), 3 * 1024 * 1024, UPLOAD_ERR_OK, 'image.jpg', 'image/jpeg');
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/market')->withUploadedFiles(['photos' => [$file(), $file()]]);
        $this->expectException(DomainError::class);
        $this->expectExceptionMessage('Photos must total 4 MiB or less.');
        $app->uploads($user)->take($request, 'photos', 'listing-images', 8);
    }
}
