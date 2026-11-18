<?php
declare(strict_types=1);

namespace SwapFix\Modules\Notifications;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SwapFix\Application\Controller;
use SwapFix\Support\DomainError;

final class NotificationController extends Controller
{
    public function index(Request $request, Response $response): Response
    {
        return $this->page($request, $response, 'notifications/index', ['notifications' => $this->app->notifications()->recent($this->user($request)->accessToken)]);
    }

    public function open(Request $request, Response $response, array $args): Response
    {
        try {
            $result = $this->app->notifications()->markRead($this->user($request)->accessToken, $this->id($request, $args));
        } catch (DomainError $error) {
            $this->flash($request)->error($error->getMessage());
            return $this->redirect($response, '/notifications');
        }
        return $this->redirect($response, $result['link_path']);
    }

    public function readAll(Request $request, Response $response): Response
    {
        $token = $this->user($request)->accessToken;
        return $this->act($request, $response, fn () => $this->app->notifications()->markAllRead($token), 'All notifications marked read.', '/notifications');
    }
}
