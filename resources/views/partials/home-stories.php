<?php
declare(strict_types=1);

use SwapFix\Support\Assets;

$deviceOptions = [
    ['discover-buy-sell', '/market/new', 'bag', 'Sell it as-is', 'Show the damage and set a price.'],
    ['discover-exchange', '/exchange/new', 'swap', 'Offer it for trade', 'Say what you want in return.'],
    ['discover-repair', '/repair?specialty=Screen+replacement', 'wrench', 'Get the screen fixed', 'Find a technician and get a quote.'],
];
$sellImage = Assets::image('home-sell');
$tradeImage = Assets::image('home-trade');
$repairImage = Assets::image('home-repair');
$safetyTips = [
    ['pin', 'Meet in public', 'Choose a busy, safe place.'],
    ['check-circle', 'Test first', 'Check the device before paying.'],
    ['message', 'Keep it in chat', 'Your agreement stays on record.'],
    ['alert', 'Trust your gut', 'Walk away if a deal feels off.'],
];
$questions = [
    ['bag', 'What can I list on SwapFix?', 'Phones, tablets, laptops, accessories, and spare parts. Add clear photos, the condition, and any defects.'],
    ['cash', 'How do payments and meetups work?', 'Buyers and sellers agree on payment and handover in chat. SwapFix does not process payments.'],
    ['swap', 'Can I add cash to an exchange?', 'Yes. Set the amount and who pays it in your offer. The other person can accept, decline, or counter.'],
    ['wrench', 'What if a repair needs extra work?', 'Your technician must request approval first. You review the new amount before it applies.'],
    ['activity', 'Where can I check my updates?', 'Sign in and open My Activity for listings, offers, and repairs. Messages holds your conversations.'],
];
?>
<section class="home-device" id="device-options" aria-labelledby="home-device-title">
    <div class="container home-device-grid">
        <figure class="home-device-art">
            <?php $sceneKey = 'home-device'; $sceneLabel = 'A smartphone with a cracked screen beside a mint case'; $sceneSizes = '(max-width: 720px) 62vw, 390px'; require __DIR__ . '/scene-art.php'; ?>
        </figure>
        <div class="home-device-copy">
            <h2 id="home-device-title">Cracked screen? You have options.</h2>
            <ul class="home-device-options">
                <?php foreach ($deviceOptions as [$theme, $href, $glyph, $label, $description]): ?>
                    <li>
                        <a class="home-device-option <?= $escape($theme) ?>" href="<?= $escape($href) ?>">
                            <span class="discover-symbol"><?= $icon($glyph) ?></span>
                            <span><strong><?= $escape($label) ?></strong><small><?= $escape($description) ?></small></span>
                            <?= $icon('arrow-up-right', 'directory-arrow') ?>
                        </a>
                    </li>
                <?php endforeach ?>
            </ul>
        </div>
    </div>
</section>

<section class="home-feature home-sell" id="sell" aria-labelledby="home-sell-title">
    <div class="container home-feature-grid">
        <figure class="home-feature-media home-sell-media">
            <img src="<?= $escape($sellImage['src']) ?>" srcset="<?= $escape($sellImage['srcset']) ?>" sizes="(max-width: 720px) 92vw, 48vw" width="<?= $sellImage['width'] ?>" height="<?= $sellImage['height'] ?>" alt="Hands taking a listing photo of a blue smartphone and its charging cable" loading="lazy" decoding="async">
        </figure>
        <div class="home-feature-copy">
            <p class="home-feature-label"><?= $icon('bag') ?> Selling on SwapFix</p>
            <h2 id="home-sell-title">Have a gadget you no longer use?</h2>
            <ul class="home-feature-points">
                <li><span class="feature-icon"><?= $icon('camera') ?></span><span><strong>Show it clearly</strong><small>Add photos and note any wear.</small></span></li>
                <li><span class="feature-icon"><?= $icon('pin') ?></span><span><strong>Set price and place</strong><small>Tell buyers where to meet.</small></span></li>
                <li><span class="feature-icon"><?= $icon('message') ?></span><span><strong>Reply in chat</strong><small>Answer buyers in one place.</small></span></li>
            </ul>
            <a class="button button-primary" href="/market/new">List a gadget <?= $icon('arrow-up-right') ?></a>
        </div>
    </div>
</section>

<section class="home-feature home-trade" id="trade" aria-labelledby="home-trade-title">
    <div class="container home-feature-grid home-trade-grid">
        <div class="home-feature-copy">
            <p class="home-feature-label"><?= $icon('swap') ?> Gadget exchange</p>
            <h2 id="home-trade-title">Trade for the device you need.</h2>
            <ul class="home-feature-points">
                <li><span class="feature-icon"><?= $icon('plus') ?></span><span><strong>Post your gadget</strong><small>Name what you want back.</small></span></li>
                <li><span class="feature-icon"><?= $icon('cash') ?></span><span><strong>Compare offers</strong><small>Accept, decline, or counter.</small></span></li>
                <li><span class="feature-icon"><?= $icon('check-circle') ?></span><span><strong>Complete the swap</strong><small>Arrange the handover in chat.</small></span></li>
            </ul>
            <div class="home-feature-actions">
                <a class="button home-trade-action" href="/exchange">Browse exchanges <?= $icon('arrow-up-right') ?></a>
                <a class="button button-outline" href="/exchange/new">Post a trade</a>
            </div>
        </div>
        <figure class="home-feature-media home-trade-media">
            <img src="<?= $escape($tradeImage['src']) ?>" srcset="<?= $escape($tradeImage['srcset']) ?>" sizes="(max-width: 720px) 92vw, 48vw" width="<?= $tradeImage['width'] ?>" height="<?= $tradeImage['height'] ?>" alt="3D illustration of a blue phone and tablet with raised green exchange arrows" loading="lazy" decoding="async">
        </figure>
    </div>
</section>

<section class="home-feature home-repair-guide" id="how-it-works" aria-labelledby="guide-title">
    <div class="container home-feature-grid home-repair-grid">
        <figure class="home-feature-media home-repair-media">
            <img src="<?= $escape($repairImage['src']) ?>" srcset="<?= $escape($repairImage['srcset']) ?>" sizes="(max-width: 720px) 92vw, 48vw" width="<?= $repairImage['width'] ?>" height="<?= $repairImage['height'] ?>" alt="Technician using a precision screwdriver on an opened smartphone" loading="lazy" decoding="async">
        </figure>
        <div class="home-feature-copy">
            <p class="home-feature-label"><?= $icon('wrench') ?> Repair services</p>
            <h2 id="guide-title">Know the cost before the repair.</h2>
            <ol class="home-feature-points">
                <li><span class="feature-icon"><?= $icon('search') ?></span><span><strong>Choose a technician</strong><small>Check skills, brands, and area.</small></span></li>
                <li><span class="feature-icon"><?= $icon('clipboard') ?></span><span><strong>Review the quote</strong><small>See the work, price, and date.</small></span></li>
                <li><span class="feature-icon"><?= $icon('check-circle') ?></span><span><strong>Follow the repair</strong><small>Know when it is ready.</small></span></li>
            </ol>
            <a class="button home-primary-action" href="/repair">Find a technician <?= $icon('arrow-up-right') ?></a>
        </div>
    </div>
</section>

<section class="home-safety" id="safety" aria-labelledby="safety-title">
    <div class="container">
        <header class="home-section-head">
            <h2 id="safety-title">Deal with confidence</h2>
        </header>
        <ul class="home-safety-grid">
            <?php foreach ($safetyTips as [$glyph, $title, $text]): ?>
                <li><span class="discover-symbol"><?= $icon($glyph) ?></span><strong><?= $escape($title) ?></strong><small><?= $escape($text) ?></small></li>
            <?php endforeach ?>
        </ul>
    </div>
</section>

<section class="home-questions" id="questions" aria-labelledby="questions-title">
    <div class="container home-questions-grid">
        <header>
            <h2 id="questions-title">Common questions</h2>
            <p>Listings, exchanges, and repairs.</p>
        </header>
        <div class="home-question-list">
            <?php foreach ($questions as [$glyph, $question, $answer]): ?>
                <details>
                    <summary><span class="discover-symbol"><?= $icon($glyph) ?></span><span><?= $escape($question) ?></span><?= $icon('plus', 'question-toggle') ?></summary>
                    <p><?= $escape($answer) ?></p>
                </details>
            <?php endforeach ?>
        </div>
    </div>
</section>

<section class="home-next" aria-labelledby="home-next-title">
    <div class="container home-next-inner">
        <h2 id="home-next-title"><?= $signedIn ? 'Pick up where you left off.' : 'Start buying, selling, or swapping.' ?></h2>
        <div class="home-feature-actions">
            <a class="button home-primary-action" href="<?= $accountUrl ?>"><?= $accountLabel ?> <?= $icon('arrow-up-right') ?></a>
            <a class="button home-secondary-action" href="/market">Browse gadgets</a>
        </div>
    </div>
</section>
