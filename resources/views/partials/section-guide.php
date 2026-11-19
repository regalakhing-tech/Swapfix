<?php
declare(strict_types=1);

use SwapFix\Support\Icon;

/** @var string $guideKey @var array|null $guideCta [title, text, href, label, icon] */
$guide = (require __DIR__ . '/../../data/guides.php')[$guideKey];
$guideText = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<div class="section-guide">
    <section class="guide-panel" aria-labelledby="guide-steps-title">
        <h2 class="guide-title" id="guide-steps-title"><?= Icon::render('info') ?><?= $guideText($guide['title']) ?></h2>
        <?php require __DIR__ . '/guide-steps.php' ?>
    </section>
    <section class="guide-panel" aria-labelledby="guide-tips-title">
        <h2 class="guide-title" id="guide-tips-title"><?= Icon::render('shield') ?>Good to know</h2>
        <ul class="guide-tips">
            <?php foreach ($guide['tips'] as [$glyph, $title, $text]): ?>
                <li><span class="discover-symbol"><?= Icon::render($glyph) ?></span><span><strong><?= $guideText($title) ?></strong><small><?= $guideText($text) ?></small></span></li>
            <?php endforeach ?>
        </ul>
    </section>
    <?php if ($guideCta !== null): [$ctaTitle, $ctaText, $ctaHref, $ctaLabel, $ctaIcon] = $guideCta; ?>
        <aside class="guide-cta" aria-label="<?= $guideText($ctaTitle) ?>">
            <span class="guide-cta-icon"><?= Icon::render($ctaIcon) ?></span>
            <div><strong><?= $guideText($ctaTitle) ?></strong><p><?= $guideText($ctaText) ?></p></div>
            <a class="button guide-cta-action" href="<?= $guideText($ctaHref) ?>"><?= $guideText($ctaLabel) ?><?= Icon::render('arrow') ?></a>
        </aside>
    <?php endif ?>
</div>
