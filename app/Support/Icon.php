<?php
declare(strict_types=1);

namespace SwapFix\Support;

final class Icon
{
    private const PATHS = [
        'arrow' => '<path d="M4 12h16m-6-6 6 6-6 6"/>',
        'arrow-up' => '<path d="m6 14 6-6 6 6M12 8v12"/>',
        'arrow-up-right' => '<path d="M6 18 18 6M6 6h12v12"/>',
        'chevron' => '<path d="m8 4 8 8-8 8"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
        'phone' => '<rect x="6" y="2" width="12" height="20" rx="3"/><path d="M10 5h4m-3 14h2"/>',
        'laptop' => '<rect x="4" y="3" width="16" height="13" rx="2"/><path d="m4 16-2 4h20l-2-4M9 20h6"/>',
        'tablet' => '<rect x="4" y="2" width="16" height="20" rx="3"/><path d="M11 18h2"/>',
        'headphones' => '<path d="M3 14v-3a9 9 0 0 1 18 0v3"/><rect x="2" y="12" width="5" height="9" rx="2"/><rect x="17" y="12" width="5" height="9" rx="2"/>',
        'chip' => '<rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v4m6-4v4M9 18v4m6-4v4M2 9h4m-4 6h4m12-6h4m-4 6h4"/><rect x="9" y="9" width="6" height="6" rx="1"/>',
        'bag' => '<path d="M4 7h16l1 14H3L4 7Z"/><path d="M8 8V6a4 4 0 0 1 8 0v2"/>',
        'swap' => '<path d="M4 7h16m-4-4 4 4-4 4M20 17H4m4-4-4 4 4 4"/>',
        'wrench' => '<path d="M14 6a6 6 0 0 0-7.5 7.5L2 18a2.8 2.8 0 0 0 4 4l4.5-4.5A6 6 0 0 0 18 10l-4 4-4-4 4-4Z"/>',
        'battery' => '<rect x="2" y="6" width="18" height="12" rx="2"/><path d="M22 10v4M7 9v6m-3-3h6m3 0h4"/>',
        'plug' => '<path d="M8 3v5m8-5v5M6 8h12v3a6 6 0 0 1-12 0V8Zm6 9v5"/>',
        'settings' => '<path d="M4 7h16M4 17h16"/><circle cx="8" cy="7" r="3"/><circle cx="16" cy="17" r="3"/>',
        'shield' => '<path d="m12 2 8 4v6c0 5-8 10-8 10S4 17 4 12V6l8-4Z"/><path d="m8 12 3 3 5-6"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
        'heart' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/>',
        'pin' => '<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'close' => '<path d="m6 6 12 12M6 18 18 6"/>',
        'home' => '<path d="m2 10 10-8 10 8M5 9v12h14V9M9 21v-8h6v8"/>',
        'leaf' => '<path d="M20 3C8 1 2 9 6 16s16 3 14-13ZM4 22l12-13"/>',
        'message' => '<path d="M21 11a9 9 0 0 1-9 9H3l1-5a9 9 0 1 1 17-4Z"/><path d="M8 10h8m-8 4h5"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10h.01"/>',
    ];

    public static function render(string $name, string $class = ''): string
    {
        $paths = self::PATHS[$name] ?? self::PATHS['grid'];
        $class = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
        return '<svg class="icon ' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
    }
}
