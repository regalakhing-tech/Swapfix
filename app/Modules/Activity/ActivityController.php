<?php
declare(strict_types=1);

namespace SwapFix\Modules\Activity;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SwapFix\Application\Controller;
use SwapFix\Http\Input;

final class ActivityController extends Controller
{
    public const TABS = [
        'listings' => ['Listings', 'bag'], 'exchanges' => ['Exchanges', 'swap'], 'offers' => ['Offers', 'cash'],
        'repairs' => ['Repairs', 'wrench'], 'conversations' => ['Chats', 'message'], 'reviews' => ['Reviews', 'star'],
    ];

    public function index(Request $request, Response $response): Response
    {
        $user = $this->user($request);
        $tab = Input::query($request)->choice('tab', array_keys(self::TABS), 'listings');
        $activity = $this->app->activity();
        $token = $user->accessToken;
        $items = match ($tab) {
            'listings' => $activity->listings($token, $user->id),
            'exchanges' => $activity->exchanges($token, $user->id),
            'offers' => $activity->offers($token),
            'repairs' => $this->repairs($token, $user->id),
            'conversations' => $this->app->messages()->conversations($token),
            'reviews' => $activity->reviews($token, $user->id),
        };
        return $this->page($request, $response, 'activity/index', ['tab' => $tab, 'tabs' => self::TABS, 'items' => $items]);
    }

    private function repairs(string $token, string $userId): array
    {
        $repairs = $this->app->repairs();
        $all = array_merge($repairs->forCustomer($token, $userId), $repairs->forTechnician($token, $userId));
        usort($all, static fn (array $a, array $b): int => strcmp($b['updated_at'], $a['updated_at']));
        return $all;
    }
}
