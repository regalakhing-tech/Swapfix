<?php
declare(strict_types=1);

namespace SwapFix\Modules\Activity;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SwapFix\Application\Controller;
use SwapFix\Http\Input;

final class ActivityController extends Controller
{
    private const PAGE_SIZE = 50;

    public const TABS = [
        'listings' => ['Listings', 'bag'], 'exchanges' => ['Exchanges', 'swap'], 'offers' => ['Offers', 'cash'],
        'repairs' => ['Repairs', 'wrench'], 'conversations' => ['Chats', 'message'], 'reviews' => ['Reviews', 'star'],
    ];

    public function index(Request $request, Response $response): Response
    {
        $user = $this->user($request);
        $query = Input::query($request);
        $tab = $query->choice('tab', array_keys(self::TABS), 'listings');
        $page = $query->int('page', 1, 1, intdiv(PHP_INT_MAX, self::PAGE_SIZE));
        $limit = self::PAGE_SIZE + 1;
        $offset = ($page - 1) * self::PAGE_SIZE;
        $activity = $this->app->activity();
        $token = $user->accessToken;
        $items = match ($tab) {
            'listings' => $activity->listings($token, $user->id, $limit, $offset),
            'exchanges' => $activity->exchanges($token, $user->id, $limit, $offset),
            'offers' => $activity->offers($token, $limit, $offset),
            'repairs' => $this->app->repairs()->forParticipant($token, $user->id, $limit, $offset),
            'conversations' => $this->app->messages()->conversations($token, $limit, $offset),
            'reviews' => $activity->reviews($token, $user->id, $limit, $offset),
        };
        return $this->page($request, $response, 'activity/index', [
            'tab' => $tab, 'tabs' => self::TABS, 'items' => array_slice($items, 0, self::PAGE_SIZE),
            'pageNumber' => $page, 'hasOlder' => count($items) > self::PAGE_SIZE,
        ]);
    }
}
