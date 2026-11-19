<?php
declare(strict_types=1);

namespace SwapFix\Application;

use Psr\Http\Message\ServerRequestInterface;
use SwapFix\Http\CsrfTokens;
use SwapFix\Http\Flash;
use SwapFix\Http\UserSession;
use SwapFix\Support\Assets;
use SwapFix\Support\Icon;
use SwapFix\Support\Labels;
use SwapFix\Support\Money;

/** Template helpers. Everything returning HTML escapes its own values. */
final class Ui
{
    use UiComponents;

    public string $title = 'SwapFix';
    public readonly ?UserSession $session;
    public readonly string $path;
    public readonly array $query;

    public function __construct(private readonly Container $app, private readonly ServerRequestInterface $request)
    {
        $session = $request->getAttribute('session');
        $this->session = $session instanceof UserSession ? $session : null;
        $this->path = $request->getUri()->getPath();
        $this->query = $request->getQueryParams();
    }

    public function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function icon(string $name, string $class = ''): string
    {
        return Icon::render($name, $class);
    }

    public function asset(string $path): string
    {
        return $this->e(Assets::url($path));
    }

    public function logo(): array
    {
        return Assets::image('logo');
    }

    public function csrf(): string
    {
        $csrf = $this->request->getAttribute('csrf');
        return $csrf instanceof CsrfTokens ? '<input type="hidden" name="_csrf" value="' . $this->e($csrf->token()) . '">' : '';
    }

    public function flashes(): array
    {
        $flash = $this->request->getAttribute('flash');
        return $flash instanceof Flash ? array_filter($flash->incoming(), 'is_array') : [];
    }

    public function money(mixed $value): string
    {
        return $this->e(Money::format($value));
    }

    public function date(?string $value): string
    {
        return $value ? $this->e($this->time($value)->format('M j, Y')) : '';
    }

    public function dateTime(?string $value): string
    {
        return $value ? $this->e($this->time($value)->format('M j, Y g:i A')) : '';
    }

    private function time(string $value): \DateTimeImmutable
    {
        $zone = new \DateTimeZone($this->app->config['timezone'] ?? 'Asia/Manila');
        return (new \DateTimeImmutable($value, $zone))->setTimezone($zone);
    }

    public function status(string $status): string
    {
        return '<span class="status status-' . $this->e(Labels::tone($status)) . '">' . $this->e(Labels::status($status)) . '</span>';
    }

    public function photoUrl(string $bucket, string $path): string
    {
        return $this->app->storage()->publicUrl($bucket, $path);
    }

    public function avatar(array|null $person, string $class = ''): string
    {
        $name = (string) ($person['display_name'] ?? '?');
        $path = $person['avatar_path'] ?? null;
        if (is_string($path) && $path !== '') {
            return '<img class="avatar ' . $this->e($class) . '" src="' . $this->e($this->photoUrl('avatars', $path)) . '" width="96" height="96" alt="" loading="lazy" decoding="async">';
        }
        return '<span class="avatar avatar-initial ' . $this->e($class) . '" aria-hidden="true">' . $this->e(mb_strtoupper(mb_substr($name, 0, 1))) . '</span>';
    }

    public function stars(mixed $rating): string
    {
        $filled = (int) round((float) $rating);
        $html = '<span class="stars" role="img" aria-label="' . $this->e(number_format((float) $rating, 1)) . ' out of 5">';
        for ($star = 1; $star <= 5; $star++) {
            $html .= Icon::render('star', $star <= $filled ? 'star-filled' : 'star-empty');
        }
        return $html . '</span>';
    }

    /** The current URL with some query values changed; null removes a value. */
    public function url(array $changes = []): string
    {
        $query = array_filter(array_replace($this->query, $changes), static fn ($value): bool => $value !== null && $value !== '');
        return $this->path . ($query === [] ? '' : '?' . http_build_query($query));
    }

    public function active(string $prefix): bool
    {
        return $this->path === $prefix || str_starts_with($this->path, rtrim($prefix, '/') . '/');
    }

    /** The active navigation entry as [group, label, icon], for the top bar. */
    public function currentPage(): array
    {
        foreach ($this->navigation() as $group => $items) {
            foreach ($items as [$href, $label, , $glyph]) {
                if ($this->active($href)) {
                    return [$group, $label, $glyph];
                }
            }
        }
        return ['SwapFix', $this->title, 'grid'];
    }

    /** Sidebar groups: group => list of [path, label, short label, icon, badge count]. */
    public function navigation(): array
    {
        $session = $this->session;
        $groups = ['Explore' => [
            ['/market', 'Buy & Sell', 'Buy', 'bag', 0],
            ['/exchange', 'Exchange', 'Swap', 'swap', 0],
            ['/repair', 'Repair', 'Repair', 'wrench', 0],
        ]];
        if ($session === null) {
            return $groups;
        }
        $groups['Inbox'] = [
            ['/messages', 'Messages', 'Chat', 'message', $session->unreadMessages],
            ['/notifications', 'Notifications', 'Alerts', 'bell', $session->unreadNotifications],
        ];
        $groups['My SwapFix'] = [
            ['/activity', 'My Activity', 'Activity', 'activity', 0],
            ['/repairs', 'My Repairs', 'Repairs', 'clipboard', 0],
        ];
        if ($session->isTechnician()) {
            $groups['Technician'] = [
                ['/technician/dashboard', 'Dashboard', 'Jobs', 'grid', 0],
                ['/technician/services', 'Services', 'Services', 'settings', 0],
                ['/technician/profile', 'Public Profile', 'Profile', 'shield', 0],
            ];
        }
        $groups['Account'] = [['/account', 'Profile', 'Profile', 'user', 0]];
        return $groups;
    }
}
