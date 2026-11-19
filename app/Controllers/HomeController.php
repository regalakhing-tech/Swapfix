<?php
declare(strict_types=1);

namespace SwapFix\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SwapFix\Application\Container;
use SwapFix\Application\Ui;
use SwapFix\Http\Flash;
use SwapFix\Http\UserSession;
use SwapFix\Support\Assets;
use SwapFix\Support\DomainError;
use SwapFix\Support\Icon;
use SwapFix\Support\IntegrationException;

final class HomeController
{
    private const SHOWCASE_SIZE = 8;

    public function __construct(private readonly Container $app) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $e = static fn (mixed $value): string => $escape((string) $value);
        $query = $request->getQueryParams();
        $view = in_array($query['view'] ?? null, ['buy-sell', 'exchange', 'repair'], true) ? $query['view'] : 'buy-sell';
        $icon = static fn (string $name, string $class = ''): string => Icon::render($name, $class);
        $asset = static fn (string $path): string => Assets::url($path);
        $logo = Assets::image('logo');
        $hero = Assets::image('hero-discover');
        $session = $request->getAttribute('session');
        $signedIn = $session instanceof UserSession;
        $items = $this->latest($view, $signedIn ? $session->accessToken : null);
        $ui = new Ui($this->app, $request);
        $flash = $request->getAttribute('flash');
        $flashes = $flash instanceof Flash ? $flash->incoming() : [];
        ob_start();
        require dirname(__DIR__, 2) . '/resources/views/home.php';
        $response->getBody()->write((string) ob_get_clean());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withHeader('Cache-Control', $signedIn ? 'no-store' : 'no-cache');
    }

    /** Newest live records for the tab, or null when they cannot load. Never falls back to made-up items. */
    private function latest(string $view, ?string $token): ?array
    {
        if (!$this->app->ready()) {
            return null;
        }
        try {
            $results = match ($view) {
                'exchange' => $this->app->exchanges()->search(['q' => '', 'category' => '', 'brand' => '', 'condition' => '', 'location' => ''], 1, $token),
                'repair' => $this->app->technicians()->search(['q' => '', 'area' => '', 'specialty' => '', 'brand' => ''], 1, $token),
                default => $this->app->listings()->search(['q' => '', 'category' => '', 'brand' => '', 'condition' => '', 'min' => null, 'max' => null, 'location' => '', 'sort' => 'newest'], 1, $token),
            };
        } catch (IntegrationException | DomainError) {
            return null;
        }
        return array_slice($results['items'] ?? [], 0, self::SHOWCASE_SIZE);
    }
}
