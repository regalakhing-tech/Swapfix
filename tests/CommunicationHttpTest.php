<?php
declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as Upstream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use SwapFix\Application\Application;
use SwapFix\Application\Container;
use SwapFix\Http\CsrfTokens;

final class CommunicationHttpTest extends TestCase
{
    private const USER = '00000000-0000-4000-8000-000000000001';
    private const OTHER = '00000000-0000-4000-8000-000000000002';
    private const THREAD = '00000000-0000-4000-8000-000000000003';
    private const RECORD = '00000000-0000-4000-8000-000000000004';

    public function testOlderUnreadNotificationsStillCountAndCanBeCleared(): void
    {
        $read = self::notification('A read notification', '2026-11-19T05:00:00Z');
        $response = $this->request('/notifications', [
            self::json(self::session(2, 0)), self::json(array_fill(0, 100, $read)),
        ]);

        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        self::assertStringContainsString('>2 unread<', $html);
        self::assertStringContainsString('action="/notifications/read-all"', $html);
        self::assertStringContainsString('<body class="portal" data-refresh>', $html);
    }

    public function testOlderNotificationsAreAvailableOnTheNextPage(): void
    {
        $read = self::notification('A read notification', '2026-11-19T05:00:00Z');
        $older = self::notification('An older unread notification', null);
        $first = $this->request('/notifications', [
            self::json(self::session(1, 0)), self::json([...array_fill(0, 100, $read), $older]),
        ]);

        self::assertSame(200, $first->getStatusCode());
        self::assertStringContainsString('href="/notifications?page=2"', (string) $first->getBody());
        self::assertStringNotContainsString($older['title'], (string) $first->getBody());

        $second = $this->request('/notifications?page=2', [
            self::json(self::session(1, 0)),
            static function ($request) use ($older): Upstream {
                parse_str($request->getUri()->getQuery(), $query);
                self::assertSame('100', $query['offset'] ?? null);
                return self::json([$older]);
            },
        ]);

        self::assertSame(200, $second->getStatusCode());
        self::assertStringContainsString('>Newer<', (string) $second->getBody());
        self::assertStringContainsString($older['title'], (string) $second->getBody());
    }

    public function testOpeningAnUnreadThreadRefreshesThePageAndNavigationCounts(): void
    {
        $conversation = [
            'id' => self::THREAD, 'context_type' => 'listing', 'listing_id' => self::THREAD,
            'context_title' => 'Test listing', 'last_message_preview' => 'Is this available?',
            'initiator_id' => self::USER, 'recipient_id' => self::OTHER, 'initiator_unread' => 3,
            'recipient_unread' => 0, 'recipient' => ['id' => self::OTHER, 'display_name' => 'Second user'],
        ];
        $readConversation = array_replace($conversation, ['initiator_unread' => 0]);
        $response = $this->request('/messages/' . self::THREAD, [
            self::json(self::session(1, 3)),
            self::json([$conversation]),
            self::json(['id' => self::THREAD]),
            static function ($request) use ($readConversation): Upstream {
                return match ($request->getUri()->getPath()) {
                    '/rest/v1/rpc/sf_session' => self::json(self::session(0, 0)),
                    '/rest/v1/conversations' => self::json([$readConversation]),
                };
            },
            static function ($request) use ($readConversation): Upstream {
                return match ($request->getUri()->getPath()) {
                    '/rest/v1/conversations' => self::json([$readConversation]),
                    '/rest/v1/messages' => self::json([]),
                };
            },
            self::json([]),
        ]);

        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        self::assertStringContainsString('>All caught up<', $html);
        self::assertStringNotContainsString('class="count"', $html);
    }

    public function testRepairDetailsClearTheEmbeddedThreadsUnreadBadges(): void
    {
        $repair = [
            'id' => self::RECORD, 'customer_id' => self::USER, 'technician_id' => self::OTHER,
            'customer' => ['id' => self::USER, 'display_name' => 'First user'],
            'technician' => ['profile' => ['id' => self::OTHER, 'display_name' => 'Second user']],
            'status' => 'request_sent', 'device_type' => 'cellphone', 'device_brand' => 'Apple', 'device_model' => 'iPhone',
            'problem' => 'Cracked screen', 'requested_service' => '', 'photo_paths' => [], 'approved_total' => null,
            'estimated_completion' => null, 'completed_at' => null, 'created_at' => '2026-11-19T05:00:00Z',
            'repair_quotations' => [], 'repair_added_costs' => [], 'repair_events' => [], 'reviews' => [],
            'conversations' => self::embeddedThread(),
        ];
        $response = $this->request('/repairs/' . self::RECORD, [
            self::json(self::session(1, 3)), self::json([$repair]), ...self::readEmbeddedThread(),
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('See you on Friday.', (string) $response->getBody());
        self::assertStringNotContainsString('class="count"', (string) $response->getBody());
    }

    public function testOfferDetailsClearTheEmbeddedThreadsUnreadBadges(): void
    {
        $response = $this->request('/offers/' . self::RECORD, [
            self::json(self::session(1, 3)), self::json([self::offer()]), self::json([self::embeddedThread()]),
            ...self::readEmbeddedThread(),
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('See you on Friday.', (string) $response->getBody());
        self::assertStringNotContainsString('class="count"', (string) $response->getBody());
    }

    public function testRejectedCounterofferKeepsUnreadBadges(): void
    {
        $response = $this->request('/offers/' . self::RECORD . '/counter', [
            self::json(self::session(1, 3)), self::json([self::offer()]), self::json([self::embeddedThread()]),
            static function ($request): Upstream {
                self::assertSame('/rest/v1/messages', $request->getUri()->getPath());
                return self::json(self::messages());
            },
        ], ['cash_amount' => 'invalid']);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('Cash amount must be a valid amount', (string) $response->getBody());
        self::assertStringContainsString('See you on Friday.', (string) $response->getBody());
        self::assertStringContainsString('class="count">3<', (string) $response->getBody());
        // A page rendered by a POST holds the rejected form, so it must not refresh itself.
        self::assertStringNotContainsString('data-refresh', (string) $response->getBody());
    }

    private function request(string $path, array $queue, ?array $form = null): ResponseInterface
    {
        $config = ['environment' => 'production', 'url' => 'https://swapfix.example.test', 'timezone' => 'Asia/Manila', 'key' => str_repeat('x', 32)];
        $services = ['supabase' => ['url' => 'https://example.supabase.test', 'publishable_key' => 'sb_publishable_test', 'upload_max_bytes' => 5242880]];
        $container = new Container($config, $services);
        $session = $container->crypto()->encrypt(['a' => 'test-access-token', 'r' => 'test-refresh-token', 'e' => time() + 3600], 'session');
        $app = Application::create($config, $services, new Client(['handler' => HandlerStack::create(new MockHandler($queue))]));
        $request = (new ServerRequestFactory())->createServerRequest($form === null ? 'GET' : 'POST', $config['url'] . $path);
        $cookies = [$container->cookies()->name('session') => $session];
        if ($form !== null) {
            $seed = str_repeat('a', 64);
            $cookies[$container->cookies()->name('csrf')] = $seed;
            $csrf = new CsrfTokens($container->crypto(), $seed, self::USER);
            $request = $request->withHeader('Origin', $config['url'])->withParsedBody($form + ['_csrf' => $csrf->token()]);
        }
        return $app->handle($request->withCookieParams($cookies));
    }

    private static function offer(): array
    {
        return [
            'id' => self::RECORD, 'owner_id' => self::USER, 'offerer_id' => self::OTHER, 'status' => 'pending',
            'awaiting_user_id' => self::USER, 'current_version' => 1,
            'offerer' => ['id' => self::OTHER, 'display_name' => 'Second user'],
            'exchange' => ['id' => self::RECORD, 'title' => 'Exchange listing', 'status' => 'open'],
            'trade_offer_terms' => [[
                'version' => 1, 'proposed_by' => self::OTHER, 'device_title' => 'A phone', 'brand' => 'Apple', 'model' => 'iPhone',
                'condition' => 'good', 'cash_amount' => '0', 'cash_payer' => 'none', 'photo_paths' => [], 'known_defects' => '', 'message' => 'Interested?',
            ]],
            'trade_offer_events' => [],
        ];
    }

    private static function embeddedThread(): array
    {
        return ['id' => self::THREAD, 'initiator_id' => self::OTHER, 'initiator_unread' => 0, 'recipient_unread' => 3];
    }

    private static function readEmbeddedThread(): array
    {
        return [
            static function ($request): Upstream {
                if ($request->getUri()->getPath() === '/rest/v1/messages') {
                    return self::json(self::messages());
                }
                self::assertSame('/rest/v1/rpc/sf_mark_conversation_read', $request->getUri()->getPath());
                self::assertSame(['p_conversation_id' => self::THREAD], json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR));
                return self::json(['id' => self::THREAD]);
            },
            self::json(self::session(0, 0)),
            self::json(self::messages()),
        ];
    }

    private static function messages(): array
    {
        return [['sender_id' => self::OTHER, 'body' => 'See you on Friday.', 'created_at' => '2026-11-19T05:00:00Z']];
    }

    private static function session(int $unreadNotifications, int $unreadMessages): array
    {
        return [
            'user_id' => self::USER,
            'profile' => ['display_name' => 'First user', 'account_type' => 'customer'],
            'unread_notifications' => $unreadNotifications,
            'unread_messages' => $unreadMessages,
        ];
    }

    private static function notification(string $title, ?string $readAt): array
    {
        return [
            'id' => self::THREAD, 'kind' => 'message', 'title' => $title, 'body' => '',
            'read_at' => $readAt, 'created_at' => '2026-11-19T05:00:00Z',
        ];
    }

    private static function json(array $data): Upstream
    {
        return new Upstream(200, ['Content-Type' => 'application/json'], json_encode($data, JSON_THROW_ON_ERROR));
    }
}
