<?php
declare(strict_types=1);

namespace SwapFix\Application;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use SwapFix\Http\Flash;
use SwapFix\Http\Input;
use SwapFix\Http\UserSession;
use SwapFix\Support\DomainError;
use SwapFix\Support\Uuid;

abstract class Controller
{
    public function __construct(protected readonly Container $app) {}

    protected function page(Request $request, Response $response, string $template, array $data = [], int $status = 200, string $layout = 'app'): Response
    {
        $response->getBody()->write($this->app->view()->render($request, $template, $data, $layout));
        return $response->withStatus($status)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store');
    }

    protected function redirect(Response $response, string $location): Response
    {
        return $response->withStatus(303)->withHeader('Location', $location);
    }

    protected function session(Request $request): ?UserSession
    {
        $session = $request->getAttribute('session');
        return $session instanceof UserSession ? $session : null;
    }

    /** Route guards guarantee a session on every route that calls this. */
    protected function user(Request $request): UserSession
    {
        return $this->session($request) ?? throw new DomainError('Sign in to continue.', 401);
    }

    protected function flash(Request $request): Flash
    {
        $flash = $request->getAttribute('flash');
        return $flash instanceof Flash ? $flash : new Flash();
    }

    protected function id(Request $request, array $args, string $key = 'id'): string
    {
        $id = $args[$key] ?? '';
        if (!Uuid::valid($id)) {
            throw new HttpNotFoundException($request);
        }
        return strtolower($id);
    }

    protected function found(Request $request, ?array $record): array
    {
        return $record ?? throw new HttpNotFoundException($request);
    }

    /**
     * Runs a state change, reports it with a toast and always redirects (POST, redirect, GET).
     * Business rejections go back to $failureTo so the user keeps their place.
     */
    protected function act(Request $request, Response $response, callable $change, string $success, string $successTo, ?string $failureTo = null): Response
    {
        try {
            $change(Input::body($request));
        } catch (DomainError $error) {
            if ($error->status === 401) {
                throw $error;
            }
            $this->flash($request)->error($error->getMessage());
            return $this->redirect($response, $failureTo ?? $successTo);
        }
        $this->flash($request)->success($success);
        return $this->redirect($response, $successTo);
    }
}
