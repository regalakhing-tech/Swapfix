<?php
declare(strict_types=1);

namespace SwapFix\Modules\Accounts;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SwapFix\Application\Controller;
use SwapFix\Http\Input;
use SwapFix\Support\DomainError;

final class AccountController extends Controller
{
    public function edit(Request $request, Response $response): Response
    {
        return $this->page($request, $response, 'account/profile', ['old' => $this->user($request)->profile, 'mailAvailable' => $this->app->mailDispatcher() !== null]);
    }

    public function update(Request $request, Response $response): Response
    {
        $user = $this->user($request);
        $input = Input::body($request);
        $uploads = $this->app->uploads($user);
        $old = ['display_name' => $input->text('display_name', 80), 'location' => $input->text('location', 120), 'email_notifications' => $input->flag('email_notifications')] + ($user->profile ?? []);
        try {
            $avatar = $uploads->take($request, 'avatar', 'avatars', 1)[0] ?? null;
            $result = $this->app->accounts()->updateProfile($user->accessToken, $old['display_name'], $old['location'], $avatar, $avatar === null && $input->flag('remove_avatar'), $input->flag('email_notifications'));
        } catch (DomainError $error) {
            $uploads->discard();
            return $this->page($request, $response, 'account/profile', ['old' => $old, 'error' => $error->getMessage(), 'mailAvailable' => $this->app->mailDispatcher() !== null], 422);
        } catch (\Throwable $exception) {
            $uploads->discard();
            throw $exception;
        }
        if (is_string($result['replaced_avatar_path'] ?? null)) {
            $uploads->remove('avatars', [$result['replaced_avatar_path']]);
        }
        $this->flash($request)->success('Profile saved.');
        return $this->redirect($response, '/account');
    }
}
