<?php
declare(strict_types=1);

use SwapFix\Support\Assets;

$deviceOptions = [
    ['discover-buy-sell', '/market/new', 'bag', 'Sell it as-is', 'Show the damage in your photos and set a price.'],
    ['discover-exchange', '/exchange/new', 'swap', 'Offer it for trade', 'Describe its condition and say what you want in return.'],
    ['discover-repair', '/repair?specialty=Screen+replacement', 'wrench', 'Get the screen fixed', 'Find a technician and ask for a quote.'],
];
$sellImage = Assets::image('home-sell');
$tradeImage = Assets::image('home-trade');
$repairImage = Assets::image('home-repair');
$questions = [
    ['What can I list on SwapFix?', 'Post phones, tablets, laptops, accessories, and spare parts. Include clear photos, the condition, and any defects.'],
    ['How do payments and meetups work?', 'Buyers and sellers agree on payment and handover in their SwapFix conversation. Check the device and confirm the details before paying.'],
    ['Can I add cash to an exchange?', 'Yes. Include the cash amount and who will pay it when you make an offer. The other person can accept, decline, or counter.'],
    ['What if a repair needs extra work?', 'Your technician can request approval for the extra work and cost. Review the new amount before deciding whether to accept it.'],
    ['Where can I check my updates?', 'Sign in and open My Activity to see your listings, offers, and repairs. Open Messages to continue a conversation.'],
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
            <p>Make it easy for the next owner to decide. Show how it works, what comes with it, and what you’re asking.</p>
            <ul class="home-feature-points">
                <li><?= $icon('camera') ?><span><strong>Show the device clearly</strong><small>Add photos and point out any wear or damage.</small></span></li>
                <li><?= $icon('pin') ?><span><strong>Set a price and location</strong><small>Let interested buyers know where they can meet you.</small></span></li>
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
            <p>Post the gadget you have and name what you want in return. Compare offers, agree on the terms, then arrange the handover in chat.</p>
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
                <li><?= $icon('search') ?><span><strong>Choose a technician</strong><small>Check specialties, supported brands, and service areas.</small></span></li>
                <li><?= $icon('clipboard') ?><span><strong>Review the quotation</strong><small>See the work, price, and estimated completion date.</small></span></li>
                <li><?= $icon('check-circle') ?><span><strong>Follow the repair</strong><small>Check progress and see when it is ready for pickup.</small></span></li>
            </ol>
            <a class="button home-primary-action" href="/repair">Find a technician <?= $icon('arrow-up-right') ?></a>
        </div>
    </div>
</section>

<section class="home-questions" id="questions" aria-labelledby="questions-title">
    <div class="container home-questions-grid">
        <header>
            <h2 id="questions-title">Common questions</h2>
            <p>How listings, exchanges, and repairs work on SwapFix.</p>
        </header>
        <div class="home-question-list">
            <?php foreach ($questions as [$question, $answer]): ?>
                <details>
                    <summary><span><?= $escape($question) ?></span><?= $icon('plus') ?></summary>
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
