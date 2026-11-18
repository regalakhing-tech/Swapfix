<?php
declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as Upstream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use SwapFix\Application\Application;
use SwapFix\Application\Container;
use SwapFix\Http\CsrfTokens;

final class RepairFormsHttpTest extends TestCase
{
    private const URL = 'https://swapfix.example.test';
    private const TECHNICIAN = '00000000-0000-4000-8000-000000000001';
    private const CUSTOMER = '00000000-0000-4000-8000-000000000002';
    private const REPAIR = '00000000-0000-4000-8000-000000000003';
    private const SERVICE = '00000000-0000-4000-8000-000000000004';

    private function submit(string $path, array $fields, array $upstream, bool $technician = true): ResponseInterface
    {
        $config = ['environment' => 'production', 'url' => self::URL, 'timezone' => 'Asia/Manila', 'key' => str_repeat('x', 32)];
        $services = ['supabase' => ['url' => 'https://example.supabase.test', 'publishable_key' => 'sb_publishable_test', 'upload_max_bytes' => 5242880]];
        $id = $technician ? self::TECHNICIAN : self::CUSTOMER;
        $session = self::json(['user_id' => $id, 'profile' => self::person($id) + ['account_type' => $technician ? 'technician' : 'customer']]);
        $container = new Container($config, $services);
        $cookie = $container->sessions()->issue(['access_token' => 'test-access', 'refresh_token' => 'test-refresh', 'expires_in' => 3600]);
        [$name, $value] = explode('=', explode(';', $cookie, 2)[0], 2);
        $seed = str_repeat('a', 64);
        $csrf = new CsrfTokens($container->crypto(), $seed, $id);
        $request = (new ServerRequestFactory())->createServerRequest('POST', self::URL . $path)
            ->withCookieParams([$name => rawurldecode($value), $container->cookies()->name('csrf') => $seed])
            ->withHeader('Origin', self::URL)
            ->withParsedBody($fields + ['_csrf' => $csrf->token()]);
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([$session, ...$upstream]))]);
        return Application::create($config, $services, $client)->handle($request);
    }

    private static function person(string $id): array
    {
        return ['id' => $id, 'display_name' => $id === self::TECHNICIAN ? 'Test Tech' : 'Test Customer', 'avatar_path' => null];
    }

    private static function repair(string $status): array
    {
        return [
            'id' => self::REPAIR, 'customer_id' => self::CUSTOMER, 'technician_id' => self::TECHNICIAN,
            'customer' => self::person(self::CUSTOMER), 'technician' => ['profile' => self::person(self::TECHNICIAN)],
            'status' => $status, 'device_type' => 'cellphone', 'device_brand' => 'Apple', 'device_model' => 'iPhone',
            'problem' => 'Cracked screen', 'requested_service' => '', 'photo_paths' => [], 'approved_total' => '100.00',
            'estimated_completion' => null, 'completed_at' => null, 'created_at' => '2026-11-18T18:00:00Z',
            'repair_quotations' => [], 'repair_added_costs' => [], 'repair_events' => [], 'reviews' => [], 'conversations' => [],
        ];
    }

    private static function json(array $data, int $status = 200): Upstream
    {
        return new Upstream($status, [], json_encode($data, JSON_THROW_ON_ERROR));
    }

    private static function openDialog(ResponseInterface $response, string $id): string
    {
        self::assertSame(422, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        $html = (string) $response->getBody();
        self::assertMatchesRegularExpression('~<dialog\b(?=[^>]*id="' . $id . '")(?=[^>]*\bopen\b)[^>]*>~', $html);
        preg_match('~<dialog\b[^>]*id="' . $id . '"[^>]*>.*?</dialog>~s', $html, $matches);
        return $matches[0];
    }

    #[DataProvider('servicePaths')]
    public function testInvalidServiceSubmissionKeepsTheEditorOpen(string $path): void
    {
        $response = $this->submit($path, ['name' => 'Screen <check>', 'estimated_price' => 'invalid', 'supported_models' => 'Model A, Model B'], [self::json([])]);
        $dialog = self::openDialog($response, 'service-dialog');
        self::assertStringContainsString('Estimated price must be a valid amount', $dialog);
        self::assertStringContainsString('value="Screen &lt;check&gt;"', $dialog);
        self::assertStringContainsString('value="invalid"', $dialog);
        self::assertStringContainsString('value="Model A, Model B"', $dialog);
        self::assertStringContainsString('href="/technician/services"', $dialog);
    }

    public static function servicePaths(): iterable
    {
        yield 'new service' => ['/technician/services'];
        yield 'existing service' => ['/technician/services/' . self::SERVICE];
    }

    public function testEditingAServiceSubmitsItsIdThroughTheServicesPage(): void
    {
        $response = $this->submit('/technician/services', ['service_id' => self::SERVICE, 'name' => 'Screen repair', 'estimated_price' => '3500'], [
            static function ($request): Upstream {
                self::assertSame('/rest/v1/rpc/sf_save_repair_service', $request->getUri()->getPath());
                self::assertSame(self::SERVICE, json_decode((string) $request->getBody(), true)['p_id']);
                return self::json(['id' => self::SERVICE]);
            },
        ]);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/technician/services', $response->getHeaderLine('Location'));
    }

    #[DataProvider('repairActions')]
    public function testRejectedRepairSubmissionKeepsItsValuesAndFeedbackInTheDialog(string $action, string $dialogId, string $status, array $fields, array $heldValues, bool $technician): void
    {
        $error = 'Please correct the submitted repair details.';
        $path = '/repairs/' . self::REPAIR;
        $response = $this->submit($path . '/' . $action, $fields, [self::json(['code' => 'SF422', 'message' => $error], 400), self::json([self::repair($status)])], $technician);
        $dialog = self::openDialog($response, $dialogId);
        self::assertStringContainsString($error, $dialog);
        self::assertStringContainsString('href="' . $path . '"', $dialog);
        foreach ($heldValues as $value) {
            self::assertStringContainsString($value, $dialog);
        }
    }

    public static function repairActions(): iterable
    {
        yield 'quotation' => ['quotations', 'quote-dialog', 'request_sent', ['scope' => 'Replace <panel>', 'parts' => 'OLED display', 'cost' => '1250.00', 'estimated_completion' => '2026-11-21'], ['Replace &lt;panel&gt;', 'value="OLED display"', 'value="1250.00"', 'value="2026-11-21"'], true];
        yield 'added cost' => ['added-costs', 'added-dialog', 'accepted', ['reason' => 'Bent connector', 'proposed_work' => 'Replace cable', 'amount' => '199.50', 'revised_completion' => '2026-11-22'], ['Bent connector', 'Replace cable', 'value="199.50"', 'value="2026-11-22"'], true];
        yield 'cancellation' => ['cancel', 'cancel-dialog', 'request_sent', ['reason' => 'Parts unavailable'], ['Parts unavailable'], true];
        yield 'review' => ['review', 'review-dialog', 'completed', ['rating' => '4', 'comment' => 'Great repair'], ['value="4" required checked', 'Great repair'], false];
    }

    public function testInvalidReviewRatingIsRejectedWithoutChangingItToFiveStars(): void
    {
        $response = $this->submit('/repairs/' . self::REPAIR . '/review', ['rating' => '6', 'comment' => 'A review'], [
            static function ($request): Upstream {
                self::assertSame('/rest/v1/repair_requests', $request->getUri()->getPath());
                return self::json([self::repair('completed')]);
            },
        ], false);
        $dialog = self::openDialog($response, 'review-dialog');
        self::assertStringContainsString('Choose a rating from 1 to 5.', $dialog);
        self::assertStringContainsString('A review', $dialog);
    }

    public function testRepairStateChangeShowsTheErrorWhenTheDialogIsNoLongerAvailable(): void
    {
        $response = $this->submit('/repairs/' . self::REPAIR . '/quotations', [
            'scope' => 'Replace screen', 'cost' => '1500', 'estimated_completion' => '2026-11-21',
        ], [
            self::json(['code' => 'SF409', 'message' => 'You cannot quote at this stage.'], 400),
            self::json([self::repair('accepted')]),
        ]);
        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('You cannot quote at this stage.', (string) $response->getBody());
        self::assertStringNotContainsString('id="quote-dialog"', (string) $response->getBody());
    }
}
