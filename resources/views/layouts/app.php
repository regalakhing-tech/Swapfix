<?php
declare(strict_types=1);
/** @var SwapFix\Application\Ui $ui @var callable $e @var string $content */
$session = $ui->session;
$logo = $ui->logo();
$navigation = $ui->navigation();
$discover = in_array($ui->path, ['/market', '/exchange', '/repair'], true);
$tabs = [['/market', 'Buy', 'bag', 0], ['/exchange', 'Swap', 'swap', 0], ['/repair', 'Repair', 'wrench', 0]];
$tabs[] = ['/messages', 'Chat', 'message', $session?->unreadMessages ?? 0];
$logout = $session ? $ui->postButton('/logout', 'Log out', 'logout', 'menu-action danger', [], ['Log out?', 'You will need to sign in again.', 'Log out']) : '';
$currentPage = $session ? $ui->currentPage() : null;
$logoTag = '<img src="' . $e($logo['src']) . '" alt="SwapFix" width="' . (int) $logo['width'] . '" height="' . (int) $logo['height'] . '">';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1046d1">
    <title><?= $e($ui->title === 'SwapFix' ? 'SwapFix' : $ui->title . ' | SwapFix') ?></title>
    <?php require __DIR__ . '/../partials/head-icons.php' ?>
    <link rel="preload" href="<?= $ui->asset('/assets/fonts/sora-latin.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= $ui->asset('/assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= $ui->asset('/assets/css/workspace.css') ?>">
    <?php if (!$session): ?><link rel="stylesheet" href="<?= $ui->asset('/assets/css/public-header.css') ?>"><?php endif ?>
    <?php if ($discover): ?><link rel="stylesheet" href="<?= $ui->asset('/assets/css/discover.css') ?>"><?php endif ?>
    <script src="<?= $ui->asset('/assets/js/app.js') ?>" defer></script>
</head>
<body class="<?= $session ? 'portal' : 'public-page' ?>">
<a class="skip-link" href="#main">Skip to content</a>
<?php if ($session): ?>
<aside class="sidebar" aria-label="Main navigation">
    <a class="sidebar-brand" href="/market" aria-label="SwapFix home"><?= $logoTag ?></a>
    <nav class="sidebar-nav">
        <?php foreach ($navigation as $group => $items): ?>
            <p class="sidebar-label"><?= $e($group) ?></p>
            <?php foreach ($items as [$href, $label, , $glyph, $count]): ?>
                <a href="<?= $e($href) ?>" class="<?= $ui->active($href) ? 'active' : '' ?>" <?= $ui->active($href) ? 'aria-current="page"' : '' ?>><?= $ui->icon($glyph) ?><span><?= $e($label) ?></span><?php if ($count > 0): ?><b class="count"><?= (int) $count ?></b><?php endif ?></a>
            <?php endforeach ?>
        <?php endforeach ?>
    </nav>
    <div class="sidebar-account">
        <?= $ui->avatar($session->profile) ?>
        <span><strong><?= $e($session->name()) ?></strong><small><?= $session->isTechnician() ? 'Technician' : 'Customer' ?></small></span>
        <?= $logout ?>
    </div>
</aside>
<header class="topbar">
    <a class="topbar-brand" href="/market" aria-label="SwapFix home"><?= $logoTag ?></a>
    <div class="topbar-page"><span class="topbar-badge"><?= $ui->icon($currentPage[2]) ?></span><span><small><?= $e($currentPage[0]) ?></small><strong><?= $e($currentPage[1]) ?></strong></span></div>
    <a class="icon-button has-count" href="/notifications" aria-label="Notifications"><?= $ui->icon('bell') ?><?php if ($session->unreadNotifications > 0): ?><b class="count"><?= (int) $session->unreadNotifications ?></b><?php endif ?></a>
    <details class="avatar-menu">
        <summary aria-label="Account menu"><?= $ui->avatar($session->profile) ?><span class="topbar-name"><?= $e($session->name()) ?></span><?= $ui->icon('chevron-down') ?></summary>
        <nav aria-label="Account">
            <?php foreach ($navigation as $group => $items): if ($group === 'Explore') { continue; } foreach ($items as [$href, $label, , $glyph]): if ($href === '/messages' || $href === '/notifications') { continue; } ?>
                <a href="<?= $e($href) ?>"><?= $ui->icon($glyph) ?><?= $e($label) ?></a>
            <?php endforeach; endforeach ?>
            <?= $logout ?>
        </nav>
    </details>
</header>
<?php else: $publicPath = $ui->path; $publicSignedIn = false; require __DIR__ . '/../partials/public-header.php'; ?>
<?php endif ?>
<main id="main" class="<?= $session ? 'app-main' : 'public-main container' ?>"><?= $content ?></main>
<?php if (!$session): $publicSignedIn = false; require __DIR__ . '/../partials/public-footer.php'; endif ?>
<?php if ($session): ?>
    <nav class="tabbar" aria-label="Quick navigation">
        <?php foreach ($tabs as [$href, $label, $glyph, $count]): ?>
            <a href="<?= $e($href) ?>" <?= $ui->active($href) ? 'class="active" aria-current="page"' : '' ?>><span class="tab-icon"><?= $ui->icon($glyph) ?><?php if ($count > 0): ?><b class="count"><?= (int) $count ?></b><?php endif ?></span><?= $e($label) ?></a>
        <?php endforeach ?>
    </nav>
<?php endif ?>
<a class="back-to-top" href="#main" aria-label="Back to top" hidden><?= $ui->icon('arrow-up') ?></a>
<?php require __DIR__ . '/../partials/toasts.php' ?>
</body>
</html>
