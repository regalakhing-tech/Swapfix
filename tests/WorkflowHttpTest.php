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
        string $role, string $path, array $fields, string $rpc, array $expected, string $redirect, array $result = [], array $before = [],
    ): void {
        $response = $this->submit($role, $path, $fields, $rpc, $expected, $result, $before);
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
        yield 'complete the displayed agreement' => ['customer', "/exchange/$id/complete", ['expected_offer_id' => $related],
            'sf_complete_exchange', ['p_exchange_id' => $id, 'p_expected_offer_id' => $related], "/exchange/$id"];
        yield 'cancel the displayed agreement' => ['customer', "/exchange/$id/cancel-agreement", ['expected_offer_id' => $related, 'note' => 'Meetup cancelled.'],
            'sf_cancel_agreement', ['p_exchange_id' => $id, 'p_expected_offer_id' => $related, 'p_note' => 'Meetup cancelled.'], "/exchange/$id"];
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

        $owned = ['id' => $id, 'owner_id' => self::USER, 'photo_paths' => [self::USER . '/kept.jpg']];
        yield 'edit own gadget and keep its photo' => ['customer', "/market/$id", $product + ['price' => '9999.00', 'description' => 'Works well.', 'keep_photos' => [self::USER . '/kept.jpg']],
            'sf_save_listing', ['p_id' => $id, 'p_price' => '9999.00', 'p_photo_paths' => [self::USER . '/kept.jpg']], "/market/$id", ['id' => $id], [[$owned]]];
        yield 'mark gadget sold' => ['customer', "/market/$id/status", ['status' => 'sold', 'expected_status' => 'reserved'],
            'sf_set_listing_status', ['p_id' => $id, 'p_status' => 'sold', 'p_expected_status' => 'reserved'], "/market/$id"];
        yield 'remove gadget' => ['customer', "/market/$id/status", ['status' => 'removed', 'expected_status' => 'available'],
            'sf_set_listing_status', ['p_status' => 'removed'], '/activity'];
        yield 'edit own exchange' => ['customer', "/exchange/$id", $product + ['wanted_item' => 'Laptop', 'cash_preference' => 'none'],
            'sf_save_exchange', ['p_id' => $id, 'p_wanted_item' => 'Laptop', 'p_photo_paths' => []], "/exchange/$id", ['id' => $id], [[$owned]]];
        yield 'remove exchange' => ['customer', "/exchange/$id/remove", [], 'sf_remove_exchange', ['p_id' => $id], '/activity?tab=exchanges'];
        yield 'decline current offer' => ['customer', "/offers/$id/decline", ['version' => '3', 'note' => 'Looking for a newer model.'],
            'sf_decline_offer', ['p_offer_id' => $id, 'p_expected_version' => 3, 'p_note' => 'Looking for a newer model.'], "/offers/$id"];
        yield 'counter an offer with new cash terms' => ['customer', "/offers/$id/counter", [
            'version' => '1', 'device_title' => 'Phone 12', 'brand' => 'Apple', 'condition' => 'good', 'message' => 'Add 1,000 and it is a deal.',
            'cash_amount' => '1000', 'cash_payer' => 'offerer',
        ], 'sf_counter_offer', ['p_offer_id' => $id, 'p_expected_version' => 1, 'p_cash_amount' => '1000', 'p_cash_payer' => 'offerer', 'p_photo_paths' => null], "/offers/$id"];
        yield 'withdraw own offer' => ['customer', "/offers/$id/withdraw", [], 'sf_withdraw_offer', ['p_offer_id' => $id], "/offers/$id"];
        yield 'request a repair' => ['customer', "/repair/request/$related", [
            'device_type' => 'cellphone', 'device_brand' => 'Samsung', 'device_model' => 'A54', 'problem' => 'Screen cracked after a fall.', 'service_id' => $id,
        ], 'sf_create_repair_request', ['p_technician_id' => $related, 'p_service_id' => $id, 'p_device_brand' => 'Samsung', 'p_photo_paths' => []], "/repairs/$id", ['id' => $id]];
        yield 'technician sends a quotation' => ['technician', "/repairs/$id/quotations", [
            'scope' => 'Replace screen', 'parts' => 'OLED panel', 'cost' => '1500', 'estimated_completion' => '2030-01-15',
        ], 'sf_send_quotation', ['p_repair_id' => $id, 'p_scope' => 'Replace screen', 'p_cost' => '1500', 'p_estimated_completion' => '2030-01-15'], "/repairs/$id"];
        yield 'customer accepts the quotation' => ['customer', "/repairs/$id/quotations/$related", ['decision' => 'accepted'],
            'sf_decide_quotation', ['p_quotation_id' => $related, 'p_decision' => 'accepted'], "/repairs/$id"];
        yield 'customer declines the quotation' => ['customer', "/repairs/$id/quotations/$related", ['decision' => 'declined', 'note' => 'Too expensive.'],
            'sf_decide_quotation', ['p_quotation_id' => $related, 'p_decision' => 'declined', 'p_note' => 'Too expensive.'], "/repairs/$id"];
        yield 'customer approves an added cost' => ['customer', "/repairs/$id/added-costs/$related", ['decision' => 'accepted'],
            'sf_decide_added_cost', ['p_id' => $related, 'p_decision' => 'accepted'], "/repairs/$id"];
        yield 'technician withdraws an added cost' => ['technician', "/repairs/$id/added-costs/$related/withdraw", [],
            'sf_withdraw_added_cost', ['p_id' => $related], "/repairs/$id"];
        yield 'cancel a repair' => ['customer', "/repairs/$id/cancel", ['reason' => 'Fixed it myself.'],
            'sf_cancel_repair', ['p_repair_id' => $id, 'p_reason' => 'Fixed it myself.'], "/repairs/$id"];
        yield 'technician saves the public profile' => ['technician', '/technician/profile', [
            'headline' => 'Phone repair', 'location' => 'San Jose', 'service_area' => 'Antique', 'available_hours' => 'Mon to Sat', 'specialties' => 'Screens, Batteries',
        ], 'sf_save_technician_profile', ['p_headline' => 'Phone repair', 'p_specialties' => ['Screens', 'Batteries']], '/technician/profile'];
        yield 'technician publishes a service' => ['technician', '/technician/services', [
            'name' => 'Battery replacement', 'description' => 'New battery.', 'supported_brands' => 'Apple, Samsung', 'estimated_price' => '800',
        ], 'sf_save_repair_service', ['p_id' => null, 'p_name' => 'Battery replacement', 'p_estimated_price' => '800'], '/technician/services'];
        yield 'technician hides a service' => ['technician', "/technician/services/$id/active", ['active' => '0'],
            'sf_set_service_active', ['p_id' => $id, 'p_active' => false], '/technician/services'];
        yield 'open a notification' => ['customer', "/notifications/$id/open", [],
            'sf_mark_notification_read', ['p_id' => $id], "/repairs/$related", ['link_path' => "/repairs/$related"]];
    }

    public function testCustomerCannotPublishTechnicianServices(): void
    {
        [$response, $history] = $this->dispatch('customer', 'POST', '/technician/services', [], body: ['name' => 'Battery', 'estimated_price' => '800'], expectMutation: false);
        self::assertSame(403, $response->getStatusCode());
        self::assertCount(1, $history, 'The technician guard must stop the request before Supabase is called.');
    }

    public function testRejectedStatusChangeReturnsWithTheDatabaseMessage(): void
    {
        [$response] = $this->dispatch('customer', 'POST', '/market/' . self::ITEM . '/status', [], body: ['status' => 'sold', 'expected_status' => 'available'],
            failure: new Upstream(400, [], json_encode(['code' => 'SF409', 'message' => 'This listing changed. Refresh and try again.'], JSON_THROW_ON_ERROR)));
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/market/' . self::ITEM, $response->getHeaderLine('Location'));
    }

    public function testInvalidPriceIsRejectedBeforeAnythingIsSaved(): void
    {
        [$response, $history] = $this->dispatch('customer', 'POST', '/market', [], body: [
            'title' => 'Phone', 'category' => 'cellphone', 'brand' => 'Apple', 'condition' => 'good', 'location' => 'Manila', 'description' => 'Works.', 'price' => '12.345',
        ], expectMutation: false);
        self::assertSame(422, $response->getStatusCode());
        self::assertCount(1, $history);
        self::assertStringContainsString('value="Phone"', (string) $response->getBody(), 'The form keeps what the seller typed.');
    }

    private function submit(string $role, string $path, array $fields, string $rpc, array $expected, array $result = [], array $before = []): ResponseInterface
    {
        [$response, $history] = $this->dispatch($role, 'POST', $path, (object) $result, body: $fields, before: $before);
        $mutation = end($history)['request'];
        self::assertSame('/rest/v1/rpc/' . $rpc, $mutation->getUri()->getPath());
        $arguments = json_decode((string) $mutation->getBody(), true, flags: JSON_THROW_ON_ERROR);
        foreach ($expected as $name => $value) {
            self::assertArrayHasKey($name, $arguments);
            self::assertSame($value, $arguments[$name], $name);
        }
        return $response;
    }

    /** $before are upstream response bodies that precede the result, such as the ownership lookup before an edit. */
    private function dispatch(string $role, string $method, string $path, array|object $result, array $query = [], array $body = [], array $before = [], bool $expectMutation = true, ?Upstream $failure = null): array
    {
        $config = ['environment' => 'production', 'url' => self::ORIGIN, 'timezone' => 'Asia/Manila', 'key' => str_repeat('x', 32)];
        $services = ['supabase' => ['url' => 'https://example.supabase.test', 'publishable_key' => 'sb_publishable_test', 'upload_max_bytes' => 4_194_304], 'mail' => []];
        $profile = ['id' => self::USER, 'account_type' => $role, 'display_name' => 'Test Name', 'location' => 'Manila', 'email_notifications' => true];
        $history = [];
        $handler = HandlerStack::create(new MockHandler([
            new Upstream(200, [], json_encode(['user_id' => self::USER, 'profile' => $profile], JSON_THROW_ON_ERROR)),
            ...array_map(static fn (array $row): Upstream => new Upstream(200, [], json_encode($row, JSON_THROW_ON_ERROR)), $before),
            $failure ?? new Upstream(200, [], json_encode($result, JSON_THROW_ON_ERROR)),
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
        self::assertSame('/rest/v1/rpc/sf_session', $history[0]['request']->getUri()->getPath());
        if ($expectMutation) {
            self::assertCount(2 + count($before), $history, 'Session loading, any lookups and the intended mutation must all be reached.');
            self::assertSame('Bearer workflow-test-access', end($history)['request']->getHeaderLine('Authorization'));
        }
        return [$response, $history];
    }
}
