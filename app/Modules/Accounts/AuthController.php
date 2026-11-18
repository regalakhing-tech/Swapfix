<?php
declare(strict_types=1);

namespace SwapFix\Modules\Accounts;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SwapFix\Application\Controller;
use SwapFix\Http\Input;
use SwapFix\Support\DomainError;
use SwapFix\Support\IntegrationException;

final class AuthController extends Controller
{
    private const PENDING = 'pending';
    private const PENDING_MAX_AGE = 604800;
    private const VERIFY_PATH = '/verify-email';
    private const AUTH_ERRORS = [
        'invalid_credentials' => 'Email or password is incorrect.',
        'email_not_confirmed' => 'Confirm your email first, then sign in.',
        'user_already_exists' => 'That email already has an account. Sign in instead.',
        'email_exists' => 'That email already has an account. Sign in instead.',
        'weak_password' => 'Choose a stronger password.',
        'email_address_invalid' => 'Enter a valid email address.',
        'signup_disabled' => 'New accounts are not open right now.',
        'over_request_rate_limit' => 'Too many attempts. Wait a moment and try again.',
        'over_email_send_rate_limit' => 'Too many emails were sent. Wait a moment and try again.',
        'email_address_not_authorized' => 'Email delivery is unavailable for this address. Try again later.',
        'otp_expired' => 'That code is incorrect or has expired. Check the code or request a new one.',
    ];

    public function showLogin(Request $request, Response $response): Response
    {
        if ($this->session($request)) {
            return $this->redirect($response, $this->safeNext(Input::query($request)->text('next', 500)));
        }
        return $this->loginPage($request, $response, Input::query($request)->text('next', 500));
    }

    public function login(Request $request, Response $response): Response
    {
        $input = Input::body($request);
        $next = $input->text('next', 500);
        $email = $input->text('email', 254);
        try {
            $tokens = $this->app->auth()->signIn($email, (string) ($input->all()['password'] ?? ''));
        } catch (\InvalidArgumentException) {
            return $this->loginPage($request, $response, $next, 'Enter your email and password.', $email, 422);
        } catch (IntegrationException $exception) {
            if ($exception->errorCode === 'email_not_confirmed') {
                $pending = $this->pendingSignup($request);
                $normalized = mb_strtolower($email);
                $pending = ($pending['m'] ?? '') === $normalized ? $pending : ['m' => $normalized];
                $pending['next'] = $this->safeNext($next);
                return $this->beginVerification($response, $pending);
            }
            return $this->loginPage($request, $response, $next, $this->authMessage($exception), $email, 422);
        }
        return $this->startSession($request, $response, $tokens, $this->safeNext($next));
    }

    public function showRegister(Request $request, Response $response): Response
    {
        if ($this->session($request)) {
            return $this->redirect($response, '/market');
        }
        return $this->page($request, $response, 'auth/register', ['old' => ['account_type' => Input::query($request)->choice('type', ['customer', 'technician'], 'customer')]], layout: 'auth');
    }

    public function register(Request $request, Response $response): Response
    {
        $input = Input::body($request);
        $old = [
            'account_type' => $input->choice('account_type', ['customer', 'technician']),
            'display_name' => $input->text('display_name', 80),
            'location' => $input->text('location', 120),
            'email' => $input->text('email', 254),
        ];
        $password = (string) ($input->all()['password'] ?? '');
        $error = match (true) {
            $old['account_type'] === '' => 'Choose a customer or technician account.',
            mb_strlen($old['display_name']) < 2 => 'Enter your name.',
            !filter_var($old['email'], FILTER_VALIDATE_EMAIL) => 'Enter a valid email address.',
            strlen($password) < 8 => 'Use a password with at least 8 characters.',
            default => null,
        };
        if ($error !== null) {
            return $this->page($request, $response, 'auth/register', ['old' => $old, 'error' => $error], 422, 'auth');
        }
        try {
            $result = $this->app->auth()->signUp($old['email'], $password, $this->confirmationRedirect());
        } catch (IntegrationException $exception) {
            return $this->page($request, $response, 'auth/register', ['old' => $old, 'error' => $this->authMessage($exception)], 422, 'auth');
        }
        $pending = ['t' => $old['account_type'], 'n' => $old['display_name'], 'l' => $old['location'], 'm' => mb_strtolower($old['email'])];
        if (is_string($result['access_token'] ?? null)) {
            return $this->startSession($request, $response, $result, '/market', $pending);
        }
        return $this->beginVerification($response, $pending);
    }

    public function showVerification(Request $request, Response $response): Response
    {
        if ($this->session($request)) {
            return $this->redirect($response, '/market');
        }
        $pending = $this->pendingSignup($request);
        return $pending === null ? $this->redirect($response, '/login') : $this->verificationPage($request, $response, $pending);
    }

    public function verifyEmail(Request $request, Response $response): Response
    {
        if ($this->session($request)) {
            return $this->redirect($response, '/market');
        }
        $pending = $this->pendingSignup($request);
        if ($pending === null) {
            return $this->redirect($response, '/login');
        }
        $code = Input::body($request)->all()['code'] ?? '';
        $code = is_string($code) ? trim($code) : '';
        if (!preg_match('/\A(?:[0-9]{6}|[0-9]{8})\z/', $code)) {
            return $this->verificationPage($request, $response, $pending, 'Enter the verification code from your email.', 422);
        }
        try {
            $tokens = $this->app->auth()->verifySignup($pending['m'], $code);
        } catch (IntegrationException $exception) {
            return $this->verificationPage($request, $response, $pending, $this->authMessage($exception), 422);
        }
        return $this->startSession($request, $response, $tokens, $this->safeNext($pending['next'] ?? ''), $pending);
    }

    public function resendVerification(Request $request, Response $response): Response
    {
        if ($this->session($request)) {
            return $this->redirect($response, '/market');
        }
        $pending = $this->pendingSignup($request);
        if ($pending === null) {
            return $this->redirect($response, '/login');
        }
        try {
            $this->app->auth()->resendSignup($pending['m'], $this->confirmationRedirect());
        } catch (IntegrationException $exception) {
            return $this->verificationPage($request, $response, $pending, $this->authMessage($exception), 422);
        }
        $this->flash($request)->success('If verification is still pending, a new email is on its way.');
        return $this->redirect($response, self::VERIFY_PATH);
    }

    public function showOnboarding(Request $request, Response $response): Response
    {
        if ($this->user($request)->profile !== null) {
            return $this->redirect($response, '/market');
        }
        return $this->page($request, $response, 'auth/onboarding', ['old' => ['account_type' => 'customer']], layout: 'auth');
    }

    public function onboard(Request $request, Response $response): Response
    {
        $user = $this->user($request);
        $input = Input::body($request);
        $old = ['account_type' => $input->choice('account_type', ['customer', 'technician']), 'display_name' => $input->text('display_name', 80), 'location' => $input->text('location', 120)];
        try {
            $this->app->accounts()->createProfile($user->accessToken, $old['account_type'], $old['display_name'], $old['location']);
        } catch (DomainError $error) {
            if ($error->status === 409) {
                return $this->redirect($response, '/market');
            }
            return $this->page($request, $response, 'auth/onboarding', ['old' => $old, 'error' => $error->getMessage()], 422, 'auth');
        }
        $this->flash($request)->success('Welcome to SwapFix.');
        return $this->redirect($response, $old['account_type'] === 'technician' ? '/technician/profile' : '/market');
    }

    public function logout(Request $request, Response $response): Response
    {
        $session = $this->session($request);
        if ($session) {
            try {
                $this->app->auth()->signOut($session->accessToken);
            } catch (IntegrationException) {
                // The local session still ends; the upstream token expires on its own.
            }
        }
        $this->flash($request)->success('You are signed out.');
        return $this->redirect($response->withAddedHeader('Set-Cookie', $this->app->sessions()->clear()), '/');
    }

    private function loginPage(Request $request, Response $response, string $next, ?string $error = null, string $email = '', int $status = 200): Response
    {
        return $this->page($request, $response, 'auth/login', [
            'next' => $next, 'error' => $error, 'email' => $email,
            'confirmed' => Input::query($request)->flag('confirmed'),
        ], $status, 'auth');
    }

    private function pendingSignup(Request $request): ?array
    {
        $cookie = $request->getCookieParams()[$this->app->cookies()->name(self::PENDING)] ?? null;
        $pending = is_string($cookie) ? $this->app->crypto()->decrypt($cookie, self::PENDING) : null;
        return is_string($pending['m'] ?? null) && filter_var($pending['m'], FILTER_VALIDATE_EMAIL) ? $pending : null;
    }

    private function beginVerification(Response $response, array $pending): Response
    {
        $cookie = $this->app->cookies()->set(self::PENDING, $this->app->crypto()->encrypt($pending, self::PENDING), self::PENDING_MAX_AGE);
        return $this->redirect($response->withAddedHeader('Set-Cookie', $cookie), self::VERIFY_PATH);
    }

    private function verificationPage(Request $request, Response $response, array $pending, ?string $error = null, int $status = 200): Response
    {
        return $this->page($request, $response, 'auth/check-email', ['email' => $pending['m'], 'error' => $error], $status, 'auth');
    }

    private function confirmationRedirect(): string
    {
        return $this->app->config['url'] . '/login?confirmed=1';
    }

    private function startSession(Request $request, Response $response, array $tokens, string $next, ?array $pending = null): Response
    {
        $session = $this->app->sessions()->sessionFor($tokens);
        $response = $response->withAddedHeader('Set-Cookie', $this->app->sessions()->issue($tokens));
        $response = $response->withAddedHeader('Set-Cookie', $this->app->cookies()->clear(self::PENDING));
        if ($session->profile !== null) {
            $this->flash($request)->success('Welcome back, ' . $session->name() . '.');
            return $this->redirect($response, $next);
        }
        $pending ??= $this->pendingSignup($request);
        if (!isset($pending['t'], $pending['n'], $pending['l'], $pending['m'])
            || $session->email === '' || !hash_equals(mb_strtolower($session->email), $pending['m'])) {
            return $this->redirect($response, '/onboarding');
        }
        try {
            $this->app->accounts()->createProfile($session->accessToken, (string) ($pending['t'] ?? ''), (string) ($pending['n'] ?? ''), (string) ($pending['l'] ?? ''));
        } catch (DomainError) {
            return $this->redirect($response, '/onboarding');
        }
        $this->flash($request)->success('Welcome to SwapFix.');
        return $this->redirect($response, $pending['t'] === 'technician' ? '/technician/profile' : $next);
    }

    private function authMessage(IntegrationException $exception): string
    {
        return self::AUTH_ERRORS[$exception->errorCode ?? ''] ?? match (true) {
            $exception->upstreamStatus === 429 => self::AUTH_ERRORS['over_request_rate_limit'],
            $exception->upstreamStatus >= 500 => 'The account service is temporarily unavailable. Try again shortly.',
            default => 'Those details were not accepted.',
        };
    }

    /** Only same-site paths are allowed, so sign-in cannot redirect elsewhere. */
    private function safeNext(string $next): string
    {
        return preg_match('~^/(?![/\\\\])[^\\\\\s]*$~', $next) === 1 ? $next : '/market';
    }
}
