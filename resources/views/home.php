<?php
declare(strict_types=1);

use SwapFix\Support\Labels;

$sections = [
    'buy-sell' => ['Buy & Sell', 'bag', '/market', 'Shop secondhand gadgets', 'Compare price, condition, and location.', 'Browse listings'],
    'exchange' => ['Exchange', 'swap', '/exchange', 'Find your next trade', 'See what owners want in return.', 'Browse exchanges'],
    'repair' => ['Repair', 'wrench', '/repair', 'Get a repair quote', 'Find a technician near you.', 'Find a technician'],
];
[$sectionLabel, $sectionIcon, $sectionUrl, $sectionTitle, $sectionDescription, $sectionAction] = $sections[$view];
$isRepair = $view === 'repair';
[$latestLabel, $searchLabel, $emptyLabel] = match ($view) {
    'exchange' => ['Latest trades', 'Search trades', 'No trades posted yet.'],
    'repair' => ['Technicians', 'Search technicians', 'No technicians listed yet.'],
    default => ['Latest listings', 'Search listings', 'No gadgets listed yet.'],
};
$accountUrl = $signedIn ? '/activity' : '/register';
$accountLabel = $signedIn ? 'My activity' : 'Create account';
$guide = (require __DIR__ . '/../data/guides.php')[$view];
$directoryScenes = [
    'buy-sell' => ['scene-market', 'Laptop, phone, and earbuds on a blue stand'],
    'exchange' => ['home-trade', 'Two devices with raised green exchange arrows'],
    'repair' => ['scene-repair', 'Opened phone and precision screwdriver on a copper stand'],
];
$directoryPerks = [
    'buy-sell' => [['camera', 'Photos'], ['check-circle', 'Condition'], ['pin', 'Location']],
    'exchange' => [['swap', 'Offers'], ['cash', 'Cash option'], ['message', 'Chat']],
    'repair' => [['clipboard', 'Quotes'], ['activity', 'Tracking'], ['star', 'Reviews']],
];
$trustItems = [
    ['shield', 'Verified email', 'Code check at sign-up'],
    ['clipboard', 'Quote first', 'You approve every repair'],
    ['message', 'Private chat', 'Only the two of you'],
    ['star', 'Real reviews', 'After finished repairs'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b1930">
    <meta name="description" content="Browse secondhand gadgets, trade your old tech, and find repair technicians on SwapFix.">
    <title>SwapFix | Buy, swap, and repair gadgets</title>
    <?php require __DIR__ . '/partials/head-icons.php' ?>
    <link rel="preload" href="<?= $escape($asset('/assets/fonts/sora-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= $escape($asset('/assets/css/app.css')) ?>">
    <?php if ($isRepair): ?><link rel="stylesheet" href="<?= $escape($asset('/assets/css/workspace.css')) ?>"><?php endif ?>
    <link rel="stylesheet" href="<?= $escape($asset('/assets/css/public-header.css')) ?>">
    <link rel="stylesheet" href="<?= $escape($asset('/assets/css/discover.css')) ?>">
    <script src="<?= $escape($asset('/assets/js/app.js')) ?>" defer></script>
</head>
<body class="discover-home">
<a class="skip-link" href="#main">Skip to content</a>
<?php $publicPath = '/'; $publicSignedIn = $signedIn; require __DIR__ . '/partials/public-header.php'; ?>
<main id="main">
    <section class="home-hero" aria-labelledby="hero-title">
        <div class="container home-hero-grid">
            <div class="home-hero-copy">
                <p class="home-kicker"><?= $icon('leaf') ?>The gadget marketplace</p>
                <h1 id="hero-title">Give good tech<br><span>a second life.</span></h1>
                <p class="home-hero-description">Buy, sell, trade, or repair the devices you already love.</p>
                <form class="home-search" action="/market" method="get" role="search">
                    <label class="sr-only" for="home-search-input">Search gadgets</label>
                    <?= $icon('search') ?>
                    <input id="home-search-input" type="search" name="q" maxlength="100" placeholder="Search phones, laptops, tablets">
                    <button class="button button-primary" type="submit">Search</button>
                </form>
                <div class="home-hero-actions">
                    <a class="button home-primary-action" href="/market">Browse gadgets <?= $icon('arrow-up-right') ?></a>
                    <a class="button home-secondary-action" href="/repair"><?= $icon('wrench') ?> Find a repair</a>
                </div>
            </div>
            <div class="home-hero-media">
                <img src="<?= $escape($hero['src']) ?>" srcset="<?= $escape($hero['srcset']) ?>" sizes="(max-width: 720px) 90vw, 48vw" width="<?= $hero['width'] ?>" height="<?= $hero['height'] ?>" alt="A cobalt smartphone, graphite tablet, and white wireless earbuds" fetchpriority="high" decoding="async">
            </div>
        </div>
    </section>

    <nav class="home-directory" aria-label="Explore SwapFix">
        <div class="container home-directory-grid">
            <?php foreach ($sections as $key => [$label, $glyph, $url, , $description]): ?>
                <a class="home-directory-link discover-<?= $escape($key) ?>" href="<?= $escape($url) ?>">
                    <span class="home-directory-art" aria-hidden="true">
                        <?php [$sceneKey, $sceneLabel] = $directoryScenes[$key]; $sceneSizes = '(max-width: 720px) 112px, 240px'; require __DIR__ . '/partials/scene-art.php'; ?>
                    </span>
                    <span class="home-directory-copy">
                        <span class="home-directory-name"><span class="discover-symbol"><?= $icon($glyph) ?></span><strong><?= $escape($label) ?></strong><?= $icon('arrow-up-right', 'directory-arrow') ?></span>
                        <small><?= $escape($description) ?></small>
                        <span class="home-directory-perks">
                            <?php foreach ($directoryPerks[$key] as [$perkIcon, $perkLabel]): ?><span><?= $icon($perkIcon) ?><?= $escape($perkLabel) ?></span><?php endforeach ?>
                        </span>
                    </span>
                </a>
            <?php endforeach ?>
        </div>
    </nav>

    <section class="home-trust" aria-label="Why SwapFix">
        <ul class="container home-trust-grid">
            <?php foreach ($trustItems as [$glyph, $title, $text]): ?>
                <li><span class="discover-symbol"><?= $icon($glyph) ?></span><span><strong><?= $escape($title) ?></strong><small><?= $escape($text) ?></small></span></li>
            <?php endforeach ?>
        </ul>
    </section>

    <section class="home-categories" aria-labelledby="categories-title">
        <div class="container">
            <header class="home-section-head">
                <h2 id="categories-title">Shop by category</h2>
                <a class="home-section-link" href="/market">View all <?= $icon('arrow') ?></a>
            </header>
            <div class="home-category-grid">
                <?php foreach (Labels::CATEGORIES as $key => $label): ?>
                    <a href="/market?category=<?= $escape($key) ?>"><span class="discover-symbol"><?= $icon(Labels::CATEGORY_ICONS[$key]) ?></span><strong><?= $escape($label) ?></strong></a>
                <?php endforeach ?>
            </div>
        </div>
    </section>

    <section class="home-showcase discover-<?= $escape($view) ?>" id="explore" aria-labelledby="catalog-title">
        <div class="container">
            <nav class="showcase-tabs" aria-label="Latest on SwapFix">
                <?php foreach ($sections as $key => [$label, $glyph]): ?>
                    <a href="/?view=<?= $escape($key) ?>#explore"<?= $view === $key ? ' aria-current="true"' : '' ?>><?= $icon($glyph) ?><?= $escape($label) ?></a>
                <?php endforeach ?>
            </nav>
            <header class="showcase-heading">
                <div>
                    <p class="showcase-label"><?= $escape($latestLabel) ?></p>
                    <h2 id="catalog-title"><?= $escape($sectionTitle) ?></h2>
                    <p><?= $escape($sectionDescription) ?></p>
                </div>
                <a class="button button-outline" href="<?= $escape($sectionUrl) ?>"><?= $escape($sectionAction) ?> <?= $icon('arrow-up-right') ?></a>
            </header>
            <?php require __DIR__ . '/partials/guide-steps.php' ?>
            <form class="search-form showcase-search" action="<?= $escape($sectionUrl) ?>" method="get" role="search">
                <label class="search-field"><?= $icon('search') ?><span class="sr-only"><?= $escape($searchLabel) ?></span><input type="search" name="q" placeholder="<?= $escape($searchLabel) ?>" maxlength="100"></label>
                <button class="button button-primary showcase-submit" type="submit"><?= $icon('search') ?>Search</button>
            </form>
            <?php if (!$isRepair): ?>
                <nav class="category-filters" aria-label="Gadget categories">
                    <?php foreach (Labels::CATEGORIES as $key => $label): ?>
                        <a class="category-chip" href="<?= $escape($sectionUrl . '?category=' . $key) ?>"><?= $icon(Labels::CATEGORY_ICONS[$key]) ?><?= $escape($label) ?></a>
                    <?php endforeach ?>
                </nav>
            <?php endif ?>
            <?php if ($items === null || $items === []): ?>
                <div class="empty-state"><span class="discover-symbol"><?= $icon($sectionIcon) ?></span><h3><?= $items === null ? 'Could not load the latest posts.' : $escape($emptyLabel) ?></h3><p><?= $items === null ? 'Please try again in a moment.' : 'Check back soon.' ?></p><a class="button button-outline" href="<?= $escape($sectionUrl) ?>"><?= $escape($sectionAction) ?></a></div>
            <?php elseif ($isRepair): ?>
                <div class="tech-grid showcase-results">
                    <?php foreach ($items as $tech): require __DIR__ . '/partials/technician-card.php'; endforeach ?>
                </div>
            <?php else: ?>
                <div class="product-grid showcase-results">
                    <?php foreach ($items as $item): require __DIR__ . '/partials/' . ($view === 'exchange' ? 'exchange' : 'listing') . '-card.php'; endforeach ?>
                </div>
            <?php endif ?>
        </div>
    </section>

    <?php require __DIR__ . '/partials/home-stories.php' ?>
</main>
<?php require __DIR__ . '/partials/public-footer.php' ?>
<a class="back-to-top" href="#main" aria-label="Back to top" hidden><?= $icon('arrow-up') ?></a>
<dialog class="detail-dialog start-dialog" id="start-dialog" aria-labelledby="start-title" <?= isset($query['start']) ? 'open' : '' ?>>
    <a class="icon-button dialog-close" href="/#explore" data-close-dialog aria-label="Close getting started"><?= $icon('close') ?></a>
    <div class="start-content">
        <h2 id="start-title">Explore SwapFix</h2>
        <div class="start-options">
            <?php foreach ($sections as [$label, $glyph, $url]): ?>
                <a href="<?= $escape($url) ?>"><?= $icon($glyph) ?><span><?= $escape($label) ?></span><?= $icon('arrow') ?></a>
            <?php endforeach ?>
        </div>
    </div>
</dialog>
<div class="toast-stack" id="toast-stack" aria-live="polite" aria-relevant="additions">
    <?php foreach (array_filter($flashes, 'is_array') as [$kind, $message]): ?>
        <div class="toast <?= $kind === 'error' ? 'toast-error' : '' ?>" role="<?= $kind === 'error' ? 'alert' : 'status' ?>" data-toast="<?= $kind === 'error' ? '6000' : '2000' ?>">
            <span><?= $escape($message) ?></span>
            <button type="button" class="toast-dismiss" data-toast-close aria-label="Dismiss notification"><?= $icon('close') ?></button>
        </div>
    <?php endforeach ?>
</div>
</body>
</html>
