<?php
declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as Upstream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use SwapFix\Application\Application;
use SwapFix\Application\Container;
use SwapFix\Http\CsrfTokens;

/** Exercises registered routes and their requests to Supabase without writing hosted data. */
final class WorkflowHttpTest extends TestCase
{
    private const ORIGIN = 'https://swapfix.example.test';
    private const USER = '00000000-0000-4000-8000-000000000001';
    private const ITEM = '00000000-0000-4000-8000-000000000002';
    private const RELATED = '00000000-0000-4000-8000-000000000003';

    public function testEditingAProfileKeepsTheEmailPreferenceWhenTheControlIsUnavailable(): void
    {
        $response = $this->submit('customer', '/account', ['display_name' => 'Updated Name', 'location' => 'Manila'], 'sf_update_profile', [
            'p_display_name' => 'Updated Name',
            'p_location' => 'Manila',
            'p_avatar_path' => null,
            'p_email_notifications' => true,
        ]);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/account', $response->getHeaderLine('Location'));
    }

    public function testActivityLoadsOlderRepairsForBothRolesAndKeepsTheTabInPagination(): void
    {
        $rows = array_fill(0, 50, [
            'id' => self::ITEM, 'device_brand' => 'Apple', 'device_model' => 'iPhone',
            'status' => 'completed', 'updated_at' => '2026-11-18T18:00:00Z',
            'technician_id' => self::USER, 'customer' => ['display_name' => 'Customer'],
        ]);
        $rows[] = ['device_model' => 'Lookahead only'] + $rows[0];
        [$response, $history] = $this->dispatch('technician', 'GET', '/activity', $rows, ['tab' => 'repairs', 'page' => '2']);
        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        self::assertStringContainsString('href="/activity?tab=repairs"', $html);
        self::assertStringContainsString('href="/activity?tab=repairs&amp;page=3"', $html);
        self::assertStringNotContainsString('Lookahead only', $html);
        $records = $history[1]['request'];
        self::assertSame('/rest/v1/repair_requests', $records->getUri()->getPath());
        parse_str($records->getUri()->getQuery(), $query);
        self::assertSame('(customer_id.eq.' . self::USER . ',technician_id.eq.' . self::USER . ')', $query['or']);
        self::assertSame('50', $query['offset']);
        self::assertSame('51', $query['limit']);
    }

    #[DataProvider('workflowActions')]
    public function testFormActionReachesItsExpectedRpc(
        string $role, string $path, array $fields, string $rpc, array $expected, string $redirect, array $result = [],
    ): void {
        $response = $this->submit($role, $path, $fields, $rpc, $expected, $result);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame($redirect, $response->getHeaderLine('Location'));
    }

    public static function workflowActions(): iterable
    {
        $id = self::ITEM;
        $related = self::RELATED;
        $product = ['title' => 'Secondhand phone', 'category' => 'cellphone', 'brand' => 'Apple', 'model' => '13', 'condition' => 'good', 'location' => 'Manila'];
        yield 'publish gadget' => ['customer', '/market', $product + ['price' => '12345.50', 'known_defects' => 'Scratch'],
            'sf_save_listing', ['p_id' => null, 'p_price' => '12345.50', 'p_known_defects' => 'Scratch', 'p_photo_paths' => []], "/market/$id", ['id' => $id]];
        yield 'reserve gadget' => ['customer', "/market/$id/status", ['status' => 'reserved', 'expected_status' => 'available'],
            'sf_set_listing_status', ['p_id' => $id, 'p_status' => 'reserved', 'p_expected_status' => 'available'], "/market/$id"];
        yield 'contact seller' => ['customer', "/market/$id/inquire", ['message' => 'Is pickup available?'],
            'sf_start_listing_inquiry', ['p_listing_id' => $id, 'p_message' => 'Is pickup available?'], "/messages/$related", ['conversation_id' => $related]];
        yield 'list exchange' => ['customer', '/exchange', $product + ['wanted_item' => 'Tablet', 'wanted_details' => 'Wi-Fi model', 'cash_preference' => 'either'],
            'sf_save_exchange', ['p_id' => null, 'p_wanted_item' => 'Tablet', 'p_cash_preference' => 'either'], "/exchange/$id", ['id' => $id]];
        yield 'submit trade offer' => ['customer', "/exchange/$id/offers", [
            'device_title' => 'Phone 12', 'brand' => 'Apple', 'model' => '12', 'condition' => 'fair',
            'cash_amount' => '500.00', 'cash_payer' => 'offerer',
        ], 'sf_submit_offer', ['p_exchange_id' => $id, 'p_cash_amount' => '500.00', 'p_cash_payer' => 'offerer'], "/offers/$related", ['id' => $related]];
        yield 'accept current offer' => ['customer', "/offers/$id/accept", ['version' => '2'],
            'sf_accept_offer', ['p_offer_id' => $id, 'p_expected_version' => 2], "/offers/$id"];
        yield 'technician requests added-cost approval' => ['technician', "/repairs/$id/added-costs", [
            'reason' => 'Damaged connector', 'proposed_work' => 'Replace connector', 'amount' => '350.00',
        ], 'sf_request_added_cost', ['p_repair_id' => $id, 'p_amount' => '350.00', 'p_proposed_work' => 'Replace connector'], "/repairs/$id"];
        yield 'technician records progress' => ['technician', "/repairs/$id/status", ['expected_status' => 'in_repair', 'status' => 'ready_for_pickup'],
            'sf_update_repair_status', ['p_repair_id' => $id, 'p_expected_status' => 'in_repair', 'p_status' => 'ready_for_pickup'], "/repairs/$id"];
        yield 'review a completed repair' => ['customer', "/repairs/$id/review", ['rating' => '5', 'comment' => 'Screen works well.'],
            'sf_create_review', ['p_repair_id' => $id, 'p_rating' => 5, 'p_comment' => 'Screen works well.'], "/repairs/$id"];
        yield 'send a contextual message' => ['customer', "/messages/$id", ['body' => 'I can drop it off tomorrow.'],
            'sf_send_message', ['p_conversation_id' => $id, 'p_body' => 'I can drop it off tomorrow.'], "/messages/$id"];
        yield 'mark all notifications read' => ['customer', '/notifications/read-all', [],
            'sf_mark_all_notifications_read', [], '/notifications'];
    }

    private function submit(string $role, string $path, array $fields, string $rpc, array $expected, array $result = []): ResponseInterface
    {
        [$response, $history] = $this->dispatch($role, 'POST', $path, (object) $result, body: $fields);
        $mutation = $history[1]['request'];
        self::assertSame('/rest/v1/rpc/' . $rpc, $mutation->getUri()->getPath());
        $arguments = json_decode((string) $mutation->getBody(), true, flags: JSON_THROW_ON_ERROR);
        foreach ($expected as $name => $value) {
            self::assertArrayHasKey($name, $arguments);
            self::assertSame($value, $arguments[$name], $name);
        }
        return $response;
    }

    private function dispatch(string $role, string $method, string $path, array|object $result, array $query = [], array $body = []): array
    {
        $config = ['environment' => 'production', 'url' => self::ORIGIN, 'timezone' => 'Asia/Manila', 'key' => str_repeat('x', 32)];
        $services = ['supabase' => ['url' => 'https://example.supabase.test', 'publishable_key' => 'sb_publishable_test', 'upload_max_bytes' => 4_194_304], 'mail' => []];
        $profile = ['id' => self::USER, 'account_type' => $role, 'display_name' => 'Test Name', 'location' => 'Manila', 'email_notifications' => true];
        $history = [];
        $handler = HandlerStack::create(new MockHandler([
            new Upstream(200, [], json_encode(['user_id' => self::USER, 'profile' => $profile], JSON_THROW_ON_ERROR)),
            new Upstream(200, [], json_encode($result, JSON_THROW_ON_ERROR)),
        ]));
        $handler->push(Middleware::history($history));
        $app = Application::create($config, $services, new Client(['handler' => $handler]));
        $container = new Container($config, $services);
        $cookies = $container->cookies();
        $sessionCookie = $container->sessions()->issue(['access_token' => 'workflow-test-access', 'refresh_token' => 'workflow-test-refresh']);
        $session = explode('=', explode(';', $sessionCookie, 2)[0], 2)[1];
        $seed = str_repeat('b', 64);
        $token = (new CsrfTokens($container->crypto(), $seed, self::USER))->token();
        $request = (new ServerRequestFactory())->createServerRequest($method, self::ORIGIN . $path)
            ->withHeader('Origin', self::ORIGIN)
            ->withCookieParams([$cookies->name('session') => rawurldecode($session), $cookies->name('csrf') => $seed])
            ->withQueryParams($query)
            ->withParsedBody($body + ['_csrf' => $token]);
        $response = $app->handle($request);
        self::assertCount(2, $history, 'Both session loading and the intended mutation must be reached.');
        self::assertSame('/rest/v1/rpc/sf_session', $history[0]['request']->getUri()->getPath());
        self::assertSame('Bearer workflow-test-access', $history[1]['request']->getHeaderLine('Authorization'));
        return [$response, $history];
    }
}
