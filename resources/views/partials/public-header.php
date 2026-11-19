<?php
declare(strict_types=1);

use SwapFix\Support\Assets;
use SwapFix\Support\Icon;

$publicLogo = Assets::image('logo');
$publicNav = [
    ['/', 'Home', 'home'],
    ['/market', 'Buy & Sell', 'bag'],
    ['/exchange', 'Exchange', 'swap'],
    ['/repair', 'Repair', 'wrench'],
];
$publicAccount = $publicSignedIn ? '/activity' : '/register';
$publicAccountLabel = $publicSignedIn ? 'My activity' : 'Create account';
$publicCurrent = static function (string $href) use ($publicPath): bool {
    if ($href === '/') {
        return $publicPath === '/';
    }
    return $href === $publicPath
        || str_starts_with($publicPath, $href . '/')
        || ($href === '/repair' && str_starts_with($publicPath, '/technicians/'));
};
?>
<header class="site-header public-header" id="top">
    <div class="container public-header-inner">
        <a class="public-brand" href="/" aria-label="SwapFix home">
            <img src="<?= htmlspecialchars($publicLogo['src'], ENT_QUOTES, 'UTF-8') ?>" alt="SwapFix" width="<?= $publicLogo['width'] ?>" height="<?= $publicLogo['height'] ?>">
        </a>
        <nav class="public-header-nav" aria-label="Main navigation">
            <?php foreach ($publicNav as [$href, $label, $glyph]): ?>
                <a href="<?= $href ?>"<?= $publicCurrent($href) ? ' aria-current="page"' : '' ?>><?= Icon::render($glyph) ?><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach ?>
        </nav>
        <div class="public-header-actions">
            <?php if (!$publicSignedIn): ?>
                <a class="public-signin" href="/login"<?= $publicPath === '/login' ? ' aria-current="page"' : '' ?>>Sign in</a>
            <?php endif ?>
            <a class="button button-primary public-account-action" href="<?= $publicAccount ?>"<?= $publicPath === $publicAccount ? ' aria-current="page"' : '' ?>><?= $publicAccountLabel ?> <?= Icon::render('arrow-up-right') ?></a>
            <details class="mobile-menu public-mobile-menu">
                <summary class="icon-button" aria-label="Open navigation"><?= Icon::render('menu') ?></summary>
                <nav aria-label="Mobile navigation">
                    <?php foreach ($publicNav as [$href, $label, $glyph]): ?>
                        <a href="<?= $href ?>"<?= $publicCurrent($href) ? ' aria-current="page"' : '' ?>><?= Icon::render($glyph) ?><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
                    <?php endforeach ?>
                    <a href="<?= $publicAccount ?>"<?= $publicPath === $publicAccount ? ' aria-current="page"' : '' ?>><?= Icon::render('arrow-up-right') ?><?= $publicAccountLabel ?></a>
                </nav>
            </details>
        </div>
    </div>
</header>
<nav class="bottom-tabs public-bottom-nav" aria-label="Quick navigation">
    <?php foreach ($publicNav as [$href, $label, $glyph]): ?>
        <a href="<?= $href ?>"<?= $publicCurrent($href) ? ' class="active" aria-current="page"' : '' ?>><?= Icon::render($glyph) ?><span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span></a>
    <?php endforeach ?>
</nav>
