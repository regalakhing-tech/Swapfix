<?php
declare(strict_types=1);

namespace SwapFix\Application;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Psr7\Response;
use SwapFix\Http\Json;
use SwapFix\Support\DomainError;
use SwapFix\Support\IntegrationException;

/** Turns failures into safe HTML or JSON. Upstream details are never shown. */
final class ErrorRenderer
{
    private const MESSAGES = [
        400 => 'Invalid request.', 401 => 'Authentication required.', 403 => 'Access denied.', 404 => 'Not found.',
        405 => 'Method not allowed.', 409 => 'This record changed. Refresh and try again.', 413 => 'Upload too large.',
        422 => 'Invalid input.', 503 => 'Service unavailable.',
    ];
    private const TITLES = [
        401 => 'Please sign in', 403 => 'Access denied', 404 => 'Page not found', 405 => 'Not allowed',
        409 => 'This changed', 422 => 'Check your details', 503 => 'Temporarily unavailable',
    ];

    public function __construct(private readonly Container $app) {}

    public function render(ServerRequestInterface $request, \Throwable $exception): ResponseInterface
    {
        [$status, $message] = $this->classify($exception);
        if ($status >= 500) {
            // Location is useful in production without logging request data or credentials.
            error_log('SwapFix request failed: ' . $exception::class . ' in ' . basename($exception->getFile()) . ':' . $exception->getLine());
        }
        $isApi = str_starts_with($request->getUri()->getPath(), '/api/') || str_starts_with($request->getUri()->getPath(), '/internal/');
        if ($status === 401 && !$isApi) {
            $response = (new Response(303))->withHeader('Location', '/login');
            return $this->app->ready() ? $response->withAddedHeader('Set-Cookie', $this->app->sessions()->clear()) : $response;
        }
        if ($isApi) {
            $response = Json::respond(new Response(), ['error' => $message], $status);
        } else {
            $response = new Response($status);
            $response->getBody()->write($this->page($request, $exception, $status, $message));
            $response = $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withHeader('Cache-Control', 'no-store');
        }
        if ($exception instanceof HttpMethodNotAllowedException) {
            $response = $response->withHeader('Allow', implode(', ', $exception->getAllowedMethods()));
        }
        return $response;
    }

    private function classify(\Throwable $exception): array
    {
        if ($exception instanceof SetupRequired) {
            return [503, 'SwapFix is not connected to its database yet.'];
        }
        if ($exception instanceof DomainError) {
            return [$exception->status, $exception->getMessage()];
        }
        if ($exception instanceof HttpException) {
            $status = $exception->getCode();
            $custom = $status === 403 && !str_starts_with($exception->getMessage(), 'Forbidden') ? $exception->getMessage() : null;
            return [$status, $custom ?? (self::MESSAGES[$status] ?? 'An unexpected error occurred.')];
        }
        if ($exception instanceof IntegrationException) {
            return $exception->upstreamStatus === 401
                ? [401, self::MESSAGES[401]]
                : [503, 'SwapFix could not reach its database. Refresh to check the latest record before trying again.'];
        }
        return [500, 'An unexpected error occurred.'];
    }

    private function page(ServerRequestInterface $request, \Throwable $exception, int $status, string $message): string
    {
        $template = $exception instanceof SetupRequired ? 'system/setup' : 'system/error';
        return $this->app->view()->render($request, $template, [
            'title' => self::TITLES[$status] ?? 'Something went wrong',
            'status' => $status,
            'message' => $message,
            'settings' => $exception instanceof SetupRequired ? $exception->settings : [],
        ]);
    }
}
