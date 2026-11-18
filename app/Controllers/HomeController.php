<?php
declare(strict_types=1);

namespace SwapFix\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SwapFix\Http\Flash;
use SwapFix\Http\UserSession;
use SwapFix\Preview\Catalog;
use SwapFix\Support\Assets;
use SwapFix\Support\Icon;

final class HomeController
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $query = $request->getQueryParams();
        $text = static fn (string $key, string $default = ''): string => is_string($query[$key] ?? null) ? mb_substr($query[$key], 0, 100) : $default;
        $view = in_array($text('view'), ['buy-sell', 'exchange', 'repair'], true) ? $text('view') : 'buy-sell';
        $category = $text('category', 'all');
        $search = $text('q');
        $sort = in_array($text('sort'), ['price-low', 'price-high'], true) ? $text('sort') : 'latest';
        $openItem = $text('item');
        $items = (new Catalog())->browse($view, $category, $search, $sort);
        $icon = static fn (string $name, string $class = ''): string => Icon::render($name, $class);
        $asset = static fn (string $path): string => Assets::url($path);
        $logo = Assets::image('logo');
        $hero = Assets::image('hero-phone');
        $catalogUrl = static function (array $changes = []) use ($view, $category, $search, $sort): string {
            $params = array_replace(['view'=>$view,'category'=>$category,'q'=>$search,'sort'=>$sort], $changes);
            return '/?' . http_build_query(array_filter($params, static fn ($value): bool => $value !== '' && $value !== null)) . '#explore';
        };
        $session = $request->getAttribute('session');
        $signedIn = $session instanceof UserSession;
        $flash = $request->getAttribute('flash');
        $flashes = $flash instanceof Flash ? $flash->incoming() : [];
        ob_start();
        require dirname(__DIR__, 2) . '/resources/views/home.php';
        $response->getBody()->write((string) ob_get_clean());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withHeader('Cache-Control', $signedIn ? 'no-store' : 'no-cache');
    }
}
