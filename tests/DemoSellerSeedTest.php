<?php
declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as Upstream;
use PHPUnit\Framework\TestCase;
use SwapFix\Application\Container;
use SwapFix\Support\Labels;

require_once __DIR__ . '/../scripts/seed-demo-sellers.php';

final class DemoSellerSeedTest extends TestCase
{
    private const USER = '00000000-0000-4000-8000-000000000001';
    private array $requests = [];
    private string $images;

    protected function setUp(): void
    {
        $this->images = sys_get_temp_dir() . '/swapfix-seed-' . bin2hex(random_bytes(4));
        mkdir($this->images);
        file_put_contents($this->images . '/new.jpg', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
    }

    protected function tearDown(): void
    {
        unlink($this->images . '/new.jpg');
        rmdir($this->images);
    }

    private function app(array $queue): Container
    {
        $stack = HandlerStack::create(new MockHandler($queue));
        $stack->push(Middleware::history($this->requests));
        $services = ['supabase' => ['url' => 'https://example.supabase.test', 'publishable_key' => 'sb_publishable_test', 'upload_max_bytes' => 4194304]];
        return new Container(['environment' => 'production', 'url' => 'https://swapfix.example.test', 'timezone' => 'Asia/Manila', 'key' => str_repeat('x', 32)], $services, new Client(['handler' => $stack]));
    }

    private function paths(): array
    {
        return array_map(static fn (array $entry): string => $entry['request']->getUri()->getPath(), $this->requests);
    }

    private static function json(array $data, int $status = 200): Upstream
    {
        return new Upstream($status, [], json_encode($data));
    }

    private static function seller(): array
    {
        $listing = ['category' => 'cellphone', 'brand' => 'Brand', 'model' => 'Model', 'condition' => 'good', 'price' => '100.00', 'description' => 'Demo', 'known_defects' => '', 'image' => 'new.jpg'];
        return ['email' => 'seller@example.test', 'name' => 'Seller', 'location' => 'Cebu City', 'listings' => [
            ['title' => 'Already listed'] + $listing,
            ['title' => 'New item'] + $listing,
        ]];
    }

    public function testConfirmationStateComesFromTheAuthSettings(): void
    {
        self::assertFalse(demoEmailConfirmationIsOff($this->app([self::json(['mailer_autoconfirm' => false])])));
        self::assertTrue(demoEmailConfirmationIsOff($this->app([self::json(['mailer_autoconfirm' => true])])));
    }

    public function testNewSellerIsCreatedWithEmailOffAndExistingTitlesAreSkipped(): void
    {
        $login = ['password' => 'Password123!', 'created' => false];
        $saves = 0;
        $app = $this->app([
            self::json(['access_token' => 'token', 'user' => ['id' => self::USER]]),
            self::json(['code' => 'SF409', 'message' => 'Profile exists'], 400),
            self::json(['id' => self::USER]),
            self::json([['id' => 'old', 'title' => 'Already listed', 'location' => 'Cebu City', 'photo_paths' => []]]),
            self::json(['Key' => 'stored']),
            self::json(['id' => 'listing']),
        ]);
        self::assertSame([1, 0], demoSeedSeller($app, self::seller(), $login, $this->images, static function () use (&$saves): void { $saves++; }));
        self::assertTrue($login['created']);
        self::assertSame(1, $saves);
        self::assertSame('/auth/v1/signup', $this->paths()[0]);
        self::assertStringEndsWith('/rpc/sf_save_listing', $this->paths()[5]);
        $profile = json_decode((string) $this->requests[2]['request']->getBody(), true);
        self::assertFalse($profile['p_email_notifications']);
        $saved = json_decode((string) $this->requests[5]['request']->getBody(), true);
        self::assertSame('New item', $saved['p_title']);
        self::assertSame('Cebu City', $saved['p_location']);
        self::assertStringStartsWith(self::USER . '/', $saved['p_photo_paths'][0]);
    }

    public function testSignUpWithoutASessionStopsBeforeAnythingIsWritten(): void
    {
        $login = ['password' => 'Password123!', 'created' => false];
        try {
            demoSeedSeller($this->app([self::json(['id' => self::USER])]), self::seller(), $login, $this->images, static fn () => null);
            self::fail('The seed must stop while Confirm email is on.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('Confirm email', $error->getMessage());
        }
        self::assertFalse($login['created']);
        self::assertCount(1, $this->requests);
    }

    public function testExistingListingMovesToTheSellersNewLocationAndKeepsItsPhoto(): void
    {
        $login = ['password' => 'Password123!', 'created' => true];
        $seller = ['location' => 'Hamtic, Antique', 'listings' => [self::seller()['listings'][0]]] + self::seller();
        $app = $this->app([
            self::json(['access_token' => 'token', 'user' => ['id' => self::USER]]),
            self::json(['id' => self::USER]),
            self::json(['id' => self::USER]),
            self::json([['id' => 'old', 'title' => 'Already listed', 'location' => 'Cebu City', 'photo_paths' => [self::USER . '/photo.jpg']]]),
            self::json(['id' => 'old']),
        ]);
        self::assertSame([0, 1], demoSeedSeller($app, $seller, $login, $this->images, static fn () => null));
        self::assertSame('/auth/v1/token', $this->paths()[0]);
        $saved = json_decode((string) $this->requests[4]['request']->getBody(), true);
        self::assertSame('old', $saved['p_id']);
        self::assertSame('Hamtic, Antique', $saved['p_location']);
        self::assertSame([self::USER . '/photo.jpg'], $saved['p_photo_paths']);
    }

    public function testTechnicianGetsProfileAndOnlyMissingServices(): void
    {
        $login = ['password' => 'Password123!', 'created' => true];
        $technician = ['type' => 'technician', 'email' => 'tech@example.test', 'name' => 'Tech', 'location' => 'Hamtic, Antique',
            'profile' => ['headline' => 'Phones', 'bio' => '', 'service_area' => 'Hamtic', 'available_hours' => 'Daily', 'specialties' => ['Screen replacement'], 'supported_brands' => [], 'supported_models' => []],
            'services' => [
                ['name' => 'Screen replacement', 'description' => '', 'supported_brands' => [], 'supported_models' => [], 'estimated_price' => '1500.00'],
                ['name' => 'Battery replacement', 'description' => '', 'supported_brands' => [], 'supported_models' => [], 'estimated_price' => '800.00'],
            ]];
        $app = $this->app([
            self::json(['access_token' => 'token', 'user' => ['id' => self::USER]]),
            self::json(['id' => self::USER]),
            self::json(['id' => self::USER]),
            self::json(['user_id' => self::USER]),
            self::json([['name' => 'Screen replacement']]),
            self::json(['id' => 'service']),
        ]);
        self::assertSame([1, 0], demoSeedSeller($app, $technician, $login, $this->images, static fn () => null));
        $profile = json_decode((string) $this->requests[3]['request']->getBody(), true);
        self::assertSame('Hamtic, Antique', $profile['p_location']);
        self::assertStringEndsWith('/rpc/sf_save_repair_service', $this->paths()[5]);
        self::assertSame('Battery replacement', json_decode((string) $this->requests[5]['request']->getBody(), true)['p_name']);
        self::assertCount(6, $this->requests);
    }

    public function testFailedListingRemovesItsUploadedPhoto(): void
    {
        $login = ['password' => 'Password123!', 'created' => true];
        $app = $this->app([
            self::json(['access_token' => 'token', 'user' => ['id' => self::USER]]),
            self::json(['id' => self::USER]),
            self::json(['id' => self::USER]),
            self::json([['id' => 'old', 'title' => 'Already listed', 'location' => 'Cebu City', 'photo_paths' => []]]),
            self::json(['Key' => 'stored']),
            self::json(['code' => 'SF422', 'message' => 'Invalid listing'], 400),
            self::json([]),
        ]);
        try {
            demoSeedSeller($app, self::seller(), $login, $this->images, static fn () => null);
            self::fail('The listing error must reach the caller.');
        } catch (SwapFix\Support\DomainError) {
            self::assertSame('DELETE', $this->requests[6]['request']->getMethod());
        }
    }

    public function testDemoDataIsValid(): void
    {
        $sellers = require __DIR__ . '/../scripts/demo-sellers.php';
        self::assertCount(9, $sellers);
        self::assertCount(9, array_unique(array_column($sellers, 'email')));
        foreach ($sellers as $seller) {
            self::assertNotFalse(filter_var($seller['email'], FILTER_VALIDATE_EMAIL));
            self::assertGreaterThanOrEqual(2, mb_strlen($seller['name']));
            self::assertStringEndsWith(', Antique', $seller['location']);
            if (($seller['type'] ?? 'customer') === 'technician') {
                self::assertNotEmpty($seller['profile']['specialties']);
                self::assertGreaterThanOrEqual(3, count($seller['services']));
                self::assertSame(count($seller['services']), count(array_unique(array_column($seller['services'], 'name'))));
                continue;
            }
            self::assertGreaterThanOrEqual(9, count($seller['listings']));
            self::assertCount(2, $seller['exchanges']);
            self::assertCount(count($seller['listings']) + 2, array_unique(array_column([...$seller['listings'], ...$seller['exchanges']], 'image')));
            self::assertCount(count($seller['listings']), array_unique(array_column($seller['listings'], 'title')));
            foreach ($seller['exchanges'] as $exchange) {
                self::assertArrayHasKey($exchange['cash_preference'], Labels::CASH_PREFERENCES);
                self::assertGreaterThanOrEqual(2, mb_strlen($exchange['wanted_item']));
            }
            foreach ($seller['listings'] as $listing) {
                self::assertGreaterThanOrEqual(3, mb_strlen($listing['title']));
                self::assertNotSame('', $listing['description']);
                self::assertArrayHasKey($listing['category'], Labels::CATEGORIES);
                self::assertArrayHasKey($listing['condition'], Labels::CONDITIONS);
                self::assertLessThanOrEqual(150, mb_strlen($listing['title']));
            }
        }
    }
}
