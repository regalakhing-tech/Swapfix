<?php
declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as Upstream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use SwapFix\Application\Application;
use SwapFix\Application\Container;
use SwapFix\Support\Labels;

/** The landing page, the browse tabs and the dashboard shell. */
final class PublicPagesTest extends TestCase
{
    private const USER = '00000000-0000-4000-8000-000000000001';
    private const EMPTY_RESULTS = '{"items":[],"total":0}';

    private function config(): array
    {
        return ['environment' => 'production', 'url' => 'https://swapfix.example.test', 'timezone' => 'Asia/Manila', 'key' => str_repeat('x', 32)];
    }

    private function services(): array
    {
        return ['supabase' => ['url' => 'https://example.supabase.test', 'publishable_key' => 'sb_publishable_test', 'upload_max_bytes' => 5242880], 'mail' => []];
    }

    /** The queue is exact: a page that makes an extra upstream request fails the test. */
    private function html(ServerRequestInterface $request, array $upstream = []): string
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler($upstream))]);
        $response = Application::create($this->config(), $this->services(), $client)->handle($request);
        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        self::assertStringNotContainsString(' style="', $html, 'The content security policy blocks inline styles.');
        self::assertMatchesRegularExpression('~<link rel="icon" type="image/png" sizes="64x64" href="/assets/images/favicon\.[a-f0-9]{10}\.png">~', $html);
        self::assertStringContainsString('<a class="back-to-top" href="#main" aria-label="Back to top" hidden>', $html);
        self::assertStringContainsString('id="main"', $html);
        return $html;
    }

    private function request(string $path, array $query = []): ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest('GET', 'https://swapfix.example.test' . $path)->withQueryParams($query);
    }

    private function member(string $path, string $accountType): array
    {
        $container = new Container($this->config(), $this->services());
        $cookie = $container->sessions()->issue(['access_token' => 'test-access', 'refresh_token' => 'test-refresh', 'expires_in' => 3600]);
        [$name, $value] = explode('=', explode(';', $cookie, 2)[0], 2);
        $session = new Upstream(200, [], json_encode([
            'user_id' => self::USER,
            'profile' => ['id' => self::USER, 'display_name' => 'Rae', 'avatar_path' => null, 'account_type' => $accountType],
        ]));
        return [$this->request($path)->withCookieParams([$name => rawurldecode($value)]), $session];
    }

    public function testLandingPageReachesEveryCategoryAndItsOwnSections(): void
    {
        $html = $this->html($this->request('/'), [new Upstream(200, [], self::EMPTY_RESULTS)]);
        self::assertStringContainsString('<form class="home-search" action="/market" method="get" role="search">', $html);
        foreach (array_keys(Labels::CATEGORIES) as $category) {
            self::assertStringContainsString('href="/market?category=' . $category . '"', $html);
        }
        preg_match_all('~href="/#([a-z-]+)"~', $html, $anchors);
        self::assertNotEmpty($anchors[1]);
        foreach (array_unique($anchors[1]) as $anchor) {
            self::assertStringContainsString('id="' . $anchor . '"', $html, 'Footer link has no target: ' . $anchor);
        }
    }

    /** @return array<string, array{string, string, string, string, array<string, mixed>}> */
    public static function landingTabs(): array
    {
        $listing = ['id' => self::USER, 'title' => 'Galaxy A54 128GB', 'brand' => 'Samsung', 'model' => 'Galaxy A54', 'category' => 'cellphone',
            'condition' => 'good', 'price' => '13500.00', 'location' => 'San Jose, Antique', 'photo_paths' => [], 'status' => 'available', 'known_defects' => ''];
        $trade = ['wanted_item' => 'Galaxy S23', 'title' => 'iPhone 13 128GB'] + $listing;
        $technician = ['id' => self::USER, 'display_name' => 'Jerome Phone Repair', 'avatar_path' => null, 'headline' => 'Phone screens', 'rating_average' => null,
            'rating_count' => 0, 'specialties' => ['Screen replacement'], 'service_area' => 'San Jose', 'location' => 'San Jose, Antique', 'available_hours' => 'Mon to Sat', 'starting_price' => '600.00'];
        return [
            'buy' => ['buy-sell', 'Find it', 'Latest listings', 'href="/market/' . self::USER . '"', $listing],
            'trade' => ['exchange', 'Send an offer', 'Latest trades', 'href="/exchange/' . self::USER . '"', $trade],
            'repair' => ['repair', 'Approve the quote', 'Technicians', 'href="/technicians/' . self::USER . '"', $technician],
        ];
    }

    #[DataProvider('landingTabs')]
    public function testEveryLandingTabShowsItsStepsAndNewestLiveRecords(string $view, string $step, string $label, string $link, array $record): void
    {
        $html = $this->html($this->request('/', ['view' => $view]), [new Upstream(200, [], json_encode(['items' => [$record], 'total' => 1]))]);
        self::assertStringContainsString('<strong>' . $step . '</strong>', $html);
        self::assertStringContainsString($label, $html);
        self::assertStringContainsString($link, $html);
        self::assertStringContainsString($record['title'] ?? $record['display_name'], $html);
        self::assertStringNotContainsStringIgnoringCase('sample', $html);
    }

    public function testLandingPageSaysSoWhenLiveRecordsCannotLoad(): void
    {
        $html = $this->html($this->request('/'), [new Upstream(503, [], '{"message":"unavailable"}')]);
        self::assertStringContainsString('Could not load the latest posts.', $html);
        self::assertStringNotContainsString('class="product-card', $html);
    }

    /** @return array<string, array{string, string, string, string}> */
    public static function browseTabs(): array
    {
        return [
            'market' => ['/market', 'How buying works', 'Start selling', 'href="/market/new"'],
            'exchange' => ['/exchange', 'How trading works', 'Start a trade', 'href="/exchange/new"'],
            'repair' => ['/repair', 'How repairs work', 'Join as technician', 'href="/register?type=technician"'],
        ];
    }

    #[DataProvider('browseTabs')]
    public function testEveryBrowseTabAddsGuidanceAndQuickFilters(string $path, string $guide, string $action, string $link): void
    {
        $html = $this->html($this->request($path), [new Upstream(200, [], self::EMPTY_RESULTS)]);
        self::assertSame(1, substr_count($html, '<h1'));
        self::assertStringContainsString($guide, $html);
        self::assertStringContainsString('Good to know', $html);
        self::assertStringContainsString('class="quick-filters"', $html);
        self::assertStringContainsString($action, $html);
        self::assertStringContainsString($link, $html);
        self::assertStringContainsString('class="site-footer public-footer"', $html);
    }

    public function testRepairTabHidesTheTechnicianInviteFromCustomers(): void
    {
        [$request, $session] = $this->member('/repair', 'customer');
        $html = $this->html($request, [$session, new Upstream(200, [], self::EMPTY_RESULTS)]);
        self::assertStringContainsString('How repairs work', $html);
        self::assertStringNotContainsString('guide-cta', $html);
    }

    public function testQuickFilterAppliesItsValuesAndTogglesThemOff(): void
    {
        $html = $this->html($this->request('/market', ['max' => '5000', 'sort' => 'price_asc', 'page' => '2']), [new Upstream(200, [], self::EMPTY_RESULTS)]);
        self::assertMatchesRegularExpression('~class="category-chip active" aria-current="true" href="/market\?sort=price_asc">.*?Under ₱5,000</a>~s', $html);
        self::assertStringContainsString('href="/market?max=5000&amp;sort=price_asc&amp;condition=good"', $html);
        self::assertStringContainsString('href="/market?max=20000&amp;sort=price_asc&amp;min=5000"', $html);
        self::assertSame(1, substr_count($html, 'class="category-chip active" aria-current="true" href="/market?sort'));
    }

    public function testActivityDashboardUsesOnlyTheDataTheTabAlreadyLoads(): void
    {
        [$request, $session] = $this->member('/activity', 'customer');
        $html = $this->html($request, [$session, new Upstream(200, [], '[]')]);
        self::assertStringContainsString('<title>My Activity | SwapFix</title>', $html);
        self::assertStringContainsString('<h1>Welcome back, Rae</h1>', $html);
        self::assertSame(3, substr_count($html, 'class="kpi kpi-'));
        self::assertStringContainsString('<small>My SwapFix</small><strong>My Activity</strong>', $html);
    }

    public function testProfilePageShowsTheAccountCardBesideGroupedSettings(): void
    {
        [$request, $session] = $this->member('/account', 'technician');
        $html = $this->html($request, [$session]);
        self::assertStringContainsString('<aside class="card profile-card">', $html);
        self::assertStringContainsString('No location added', $html);
        self::assertStringContainsString('href="/technicians/' . self::USER . '"', $html, 'Technicians reach their public page.');
        foreach (['Personal details', 'Profile photo', 'Save profile'] as $text) {
            self::assertStringContainsString($text, $html);
        }
        self::assertStringContainsString('name="_csrf"', $html);
    }

    public function testDetailPagesOfferAWayBackToTheirSection(): void
    {
        $listing = ['id' => self::USER, 'owner_id' => '00000000-0000-4000-8000-000000000009', 'title' => 'Galaxy A54', 'description' => '', 'category' => 'cellphone',
            'brand' => 'Samsung', 'model' => 'A54', 'condition' => 'good', 'known_defects' => '', 'price' => '13500.00', 'location' => 'San Jose', 'photo_paths' => [],
            'status' => 'available', 'created_at' => '2026-11-18T18:00:00Z', 'updated_at' => '2026-11-18T18:00:00Z',
            'owner' => ['id' => '00000000-0000-4000-8000-000000000009', 'display_name' => 'Mika', 'avatar_path' => null]];
        $html = $this->html($this->request('/market/' . self::USER), [new Upstream(200, [], json_encode([$listing]))]);
        self::assertMatchesRegularExpression('~<div class="page-actions"><a class="button button-outline" href="/market">.*?Back</a>~s', $html);
    }

    public function testTechnicianDashboardShowsEachGroupsShareOfJobs(): void
    {
        $job = ['device_brand' => 'Apple', 'device_model' => 'iPhone', 'updated_at' => '2026-11-18T18:00:00Z', 'repair_added_costs' => [], 'customer' => ['display_name' => 'Client', 'avatar_path' => null]];
        $jobs = [
            ['id' => '00000000-0000-4000-8000-000000000002', 'status' => 'request_sent'] + $job,
            ['id' => '00000000-0000-4000-8000-000000000003', 'status' => 'completed'] + $job,
        ];
        [$request, $session] = $this->member('/technician/dashboard', 'technician');
        $html = $this->html($request, [$session, new Upstream(200, [], json_encode($jobs))]);
        self::assertSame(4, substr_count($html, 'class="kpi kpi-'));
        self::assertSame(2, substr_count($html, '<progress value="1" max="2"'));
        self::assertSame(2, substr_count($html, '<progress value="0" max="2"'));
        self::assertStringContainsString('<small>Technician</small><strong>Dashboard</strong>', $html);
    }
}
