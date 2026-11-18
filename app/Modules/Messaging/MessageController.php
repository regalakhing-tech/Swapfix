<?php
declare(strict_types=1);

namespace SwapFix\Modules\Messaging;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SwapFix\Application\Controller;
use SwapFix\Http\Input;

final class MessageController extends Controller
{
    public function index(Request $request, Response $response): Response
    {
        $user = $this->user($request);
        return $this->page($request, $response, 'messages/index', ['conversations' => $this->app->messages()->conversations($user->accessToken), 'active' => null, 'messages' => []]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $user = $this->user($request);
        $id = $this->id($request, $args);
        $messages = $this->app->messages();
        $conversation = $this->found($request, $messages->conversation($user->accessToken, $id));
        $unread = $conversation['initiator_id'] === $user->id ? $conversation['initiator_unread'] : $conversation['recipient_unread'];
        if ($unread > 0) {
            $messages->markRead($user->accessToken, $id);
        }
        return $this->page($request, $response, 'messages/index', [
            'conversations' => $messages->conversations($user->accessToken), 'active' => $conversation,
            'messages' => $messages->recent($user->accessToken, $id),
        ]);
    }

    public function send(Request $request, Response $response, array $args): Response
    {
        $user = $this->user($request);
        $id = $this->id($request, $args);
        $input = Input::body($request);
        $back = $input->text('back', 100);
        // Threads embedded in a repair or offer page return there; only those paths are accepted.
        $target = preg_match('~^/(repairs|offers)/[a-f0-9-]{36}$~', $back) === 1 ? $back : '/messages/' . $id;
        return $this->act($request, $response, fn () => $this->app->messages()->send($user->accessToken, $id, $input->text('body')), 'Message sent.', $target);
    }
}
