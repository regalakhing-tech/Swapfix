<?php
declare(strict_types=1);

use SwapFix\Support\Assets;
use SwapFix\Support\Icon;

/** @var bool $publicSignedIn */
$footerLogo = Assets::image('logo');
$footerColumns = [
    'Explore' => [['/market', 'Buy & Sell', 'bag'], ['/exchange', 'Exchange', 'swap'], ['/repair', 'Repair', 'wrench']],
    'Get started' => [
        ['/market/new', 'Sell a gadget', 'plus'],
        ['/exchange/new', 'Post a trade', 'plus'],
        $publicSignedIn ? ['/activity', 'My activity', 'activity'] : ['/register', 'Create account', 'user'],
    ],
    'Help' => [['/#how-it-works', 'Repair guide', 'info'], ['/#safety', 'Safety tips', 'shield'], ['/#questions', 'FAQs', 'message']],
];
?>
<footer class="site-footer public-footer">
    <div class="container public-footer-grid">
        <div class="public-footer-brand">
            <a class="brand" href="/" aria-label="SwapFix footer home"><img src="<?= htmlspecialchars($footerLogo['src'], ENT_QUOTES, 'UTF-8') ?>" alt="SwapFix" width="<?= $footerLogo['width'] ?>" height="<?= $footerLogo['height'] ?>" loading="lazy" decoding="async"></a>
            <p>Buy smart. Swap better. Fix what matters.</p>
        </div>
        <?php foreach ($footerColumns as $heading => $links): ?>
            <nav aria-label="<?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?>">
                <h2><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></h2>
                <?php foreach ($links as [$href, $label, $glyph]): ?>
                    <a href="<?= $href ?>"><?= Icon::render($glyph) ?><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
                <?php endforeach ?>
            </nav>
        <?php endforeach ?>
    </div>
    <div class="container public-footer-bottom"><p>&copy; <?= date('Y') ?> SwapFix</p></div>
</footer>
