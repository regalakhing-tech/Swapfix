<?php
declare(strict_types=1);
/** @var SwapFix\Application\Ui $ui @var callable $e @var string $content */
$session = $ui->session;
$logo = $ui->logo();
$navigation = $ui->navigation();
$tabs = [['/market', 'Buy', 'bag', 0], ['/exchange', 'Swap', 'swap', 0], ['/repair', 'Repair', 'wrench', 0]];
$tabs[] = $session ? ['/messages', 'Chat', 'message', $session->unreadMessages] : ['/login', 'Sign in', 'user', 0];
$logout = $session ? $ui->postButton('/logout', 'Log out', 'logout', 'menu-action danger', [], ['Log out?', 'You will need to sign in again.', 'Log out']) : '';
$logoTag = '<img src="' . $e($logo['src']) . '" alt="SwapFix" width="' . (int) $logo['width'] . '" height="' . (int) $logo['height'] . '">';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1046d1">
    <title><?= $e($ui->title === 'SwapFix' ? 'SwapFix' : $ui->title . ' | SwapFix') ?></title>
    <link rel="preload" href="<?= $ui->asset('/assets/fonts/sora-latin.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= $ui->asset('/assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= $ui->asset('/assets/css/workspace.css') ?>">
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
    <a class="icon-button has-count" href="/notifications" aria-label="Notifications"><?= $ui->icon('bell') ?><?php if ($session->unreadNotifications > 0): ?><b class="count"><?= (int) $session->unreadNotifications ?></b><?php endif ?></a>
    <details class="avatar-menu">
        <summary aria-label="Account menu"><?= $ui->avatar($session->profile) ?></summary>
        <nav aria-label="Account">
            <?php foreach ($navigation as $group => $items): if ($group === 'Explore') { continue; } foreach ($items as [$href, $label, , $glyph]): if ($href === '/messages' || $href === '/notifications') { continue; } ?>
                <a href="<?= $e($href) ?>"><?= $ui->icon($glyph) ?><?= $e($label) ?></a>
            <?php endforeach; endforeach ?>
            <?= $logout ?>
        </nav>
    </details>
</header>
<?php else: ?>
<header class="site-header"><div class="container header-inner">
    <a class="brand" href="/" aria-label="SwapFix home"><?= $logoTag ?></a>
    <nav class="desktop-nav" aria-label="Main navigation">
        <?php foreach ($navigation['Explore'] as [$href, $label, , $glyph]): ?><a href="<?= $e($href) ?>" class="<?= $ui->active($href) ? 'home-link' : '' ?>"><?= $ui->icon($glyph) ?><?= $e($label) ?></a><?php endforeach ?>
    </nav>
    <div class="header-actions">
        <a class="button button-outline header-signin" href="/login">Sign in</a>
        <a class="button button-primary header-cta" href="/register">Join <?= $ui->icon('arrow') ?></a>
    </div>
</div></header>
<?php endif ?>
<main id="main" class="<?= $session ? 'app-main' : 'public-main container' ?>"><?= $content ?></main>
<?php if (!$session): ?>
<footer class="site-footer"><div class="container footer-bottom"><p>&copy; <?= date('Y') ?> SwapFix.</p><p>Buy smart. Swap better. Fix what matters.</p></div></footer>
<?php endif ?>
<nav class="tabbar" aria-label="Quick navigation">
    <?php foreach ($tabs as [$href, $label, $glyph, $count]): ?>
        <a href="<?= $e($href) ?>" class="<?= $ui->active($href) ? 'active' : '' ?>"><span class="tab-icon"><?= $ui->icon($glyph) ?><?php if ($count > 0): ?><b class="count"><?= (int) $count ?></b><?php endif ?></span><?= $e($label) ?></a>
    <?php endforeach ?>
</nav>
<?php require __DIR__ . '/../partials/toasts.php' ?>
</body>
</html>
