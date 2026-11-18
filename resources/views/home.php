<?php
declare(strict_types=1);

$sections = [
    'buy-sell' => ['Buy & Sell', 'bag', '/market', 'Shop secondhand gadgets', 'Compare prices, condition, and location.', 'Browse listings'],
    'exchange' => ['Exchange', 'swap', '/exchange', 'Find your next trade', 'See which devices owners want in return.', 'Browse exchanges'],
    'repair' => ['Repair', 'wrench', '/repair', 'Get a repair quote', 'Find a technician for your device and location.', 'Find a technician'],
];
$categories = ['all' => ['All gadgets', 'grid'], 'phones' => ['Phones', 'phone'], 'laptops' => ['Laptops', 'laptop'], 'tablets' => ['Tablets', 'tablet'], 'accessories' => ['Accessories', 'headphones'], 'spare-parts' => ['Spare parts', 'chip']];
[$sectionLabel, $sectionIcon, $sectionUrl, $sectionTitle, $sectionDescription, $sectionAction] = $sections[$view];
$isRepair = $view === 'repair';
$sampleLabel = $isRepair ? 'Sample services' : 'Sample listings';
$accountUrl = $signedIn ? '/activity' : '/register';
$accountLabel = $signedIn ? 'My activity' : 'Create account';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b1930">
    <meta name="description" content="Browse secondhand gadgets, trade your old tech, and find repair technicians on SwapFix.">
    <title>SwapFix | Buy, swap, and repair gadgets</title>
    <link rel="preload" href="<?= $escape($asset('/assets/fonts/sora-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= $escape($asset('/assets/css/app.css')) ?>">
    <link rel="stylesheet" href="<?= $escape($asset('/assets/css/discover.css')) ?>">
    <script src="<?= $escape($asset('/assets/js/app.js')) ?>" defer></script>
</head>
<body class="discover-home">
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header" id="top">
    <div class="container header-inner">
        <a class="brand" href="/" aria-label="SwapFix home">
            <img src="<?= $escape($logo['src']) ?>" alt="SwapFix" width="<?= $logo['width'] ?>" height="<?= $logo['height'] ?>">
        </a>
        <nav class="desktop-nav" aria-label="Main navigation">
            <a href="/" class="home-link" aria-current="page">Home</a>
            <?php foreach ($sections as [$label, $glyph, $url]): ?>
                <a href="<?= $escape($url) ?>"><?= $escape($label) ?></a>
            <?php endforeach ?>
        </nav>
        <div class="header-actions">
            <?php if (!$signedIn): ?><a class="home-signin" href="/login">Sign in</a><?php endif ?>
            <a class="button button-primary header-cta" href="<?= $accountUrl ?>"><?= $accountLabel ?> <?= $icon('arrow-up-right') ?></a>
            <details class="mobile-menu">
                <summary class="icon-button" aria-label="Open navigation"><?= $icon('menu') ?></summary>
                <nav aria-label="Mobile navigation">
                    <a href="/" aria-current="page"><?= $icon('home') ?>Home</a>
                    <?php foreach ($sections as [$label, $glyph, $url]): ?>
                        <a href="<?= $escape($url) ?>"><?= $icon($glyph) ?><?= $escape($label) ?></a>
                    <?php endforeach ?>
                    <?php if (!$signedIn): ?><a href="/login"><?= $icon('user') ?>Sign in</a><?php endif ?>
                    <a href="<?= $accountUrl ?>"><?= $icon('arrow-up-right') ?><?= $accountLabel ?></a>
                </nav>
            </details>
        </div>
    </div>
</header>
<main id="main">
    <section class="home-hero" aria-labelledby="hero-title">
        <div class="container home-hero-grid">
            <div class="home-hero-copy">
                <p class="home-kicker">The gadget marketplace</p>
                <h1 id="hero-title">Give good tech<br><span>a second life.</span></h1>
                <p class="home-hero-description">Buy and sell secondhand gadgets, trade for an upgrade, or find someone to fix the device you already own.</p>
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
                    <span class="discover-symbol"><?= $icon($glyph) ?></span>
                    <span><strong><?= $escape($label) ?></strong><small><?= $escape($description) ?></small></span>
                    <?= $icon('arrow-up-right', 'directory-arrow') ?>
                </a>
            <?php endforeach ?>
        </div>
    </nav>

    <section class="home-showcase discover-<?= $escape($view) ?>" id="explore" aria-labelledby="catalog-title">
        <div class="container">
            <nav class="showcase-tabs" aria-label="Marketplace preview">
                <?php foreach ($sections as $key => [$label, $glyph]): ?>
                    <a href="<?= $escape($catalogUrl(['view' => $key, 'category' => 'all', 'q' => '', 'sort' => 'latest'])) ?>"<?= $view === $key ? ' aria-current="true"' : '' ?>><?= $icon($glyph) ?><?= $escape($label) ?></a>
                <?php endforeach ?>
            </nav>
            <header class="showcase-heading">
                <div>
                    <p class="showcase-label"><?= $sampleLabel ?></p>
                    <h2 id="catalog-title"><?= $escape($sectionTitle) ?></h2>
                    <p><?= $escape($sectionDescription) ?></p>
                </div>
                <a class="button button-outline" href="<?= $escape($sectionUrl) ?>"><?= $escape($sectionAction) ?> <?= $icon('arrow-up-right') ?></a>
            </header>
            <form class="search-form showcase-search" action="/#explore" method="get" role="search">
                <input type="hidden" name="view" value="<?= $escape($view) ?>">
                <input type="hidden" name="category" value="<?= $escape($category) ?>">
                <label class="search-field"><?= $icon('search') ?><span class="sr-only">Search <?= $isRepair ? 'sample services' : 'sample gadgets' ?></span><input type="search" name="q" value="<?= $escape($search) ?>" placeholder="<?= $isRepair ? 'Search sample services' : 'Search sample gadgets' ?>" maxlength="100"></label>
                <?php if ($view !== 'exchange'): ?>
                    <label class="sort-field"><span class="sr-only">Sort results</span>
                        <select name="sort">
                            <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>Featured first</option>
                            <option value="price-low" <?= $sort === 'price-low' ? 'selected' : '' ?>>Price: low to high</option>
                            <option value="price-high" <?= $sort === 'price-high' ? 'selected' : '' ?>>Price: high to low</option>
                        </select>
                    </label>
                <?php endif ?>
                <button class="button button-primary showcase-submit" type="submit"><?= $icon('search') ?>Search</button>
            </form>
            <nav class="category-filters" aria-label="Gadget categories">
                <?php foreach ($categories as $key => [$label, $glyph]): ?>
                    <a class="category-chip <?= $category === $key ? 'active' : '' ?>" <?= $category === $key ? 'aria-current="true"' : '' ?> href="<?= $escape($catalogUrl(['category' => $key])) ?>"><?= $icon($glyph) ?><?= $escape($label) ?></a>
                <?php endforeach ?>
            </nav>
            <?php if ($search !== '' || $category !== 'all'): ?>
                <div class="results-heading"><p><?= count($items) ?> results<?= $search !== '' ? ' for “' . $escape($search) . '”' : '' ?></p><a href="<?= $escape($catalogUrl(['category' => 'all', 'q' => ''])) ?>">Clear filters <?= $icon('close') ?></a></div>
            <?php endif ?>
            <?php if ($items === []): ?>
                <div class="empty-state"><span class="discover-symbol"><?= $icon('search') ?></span><h3>No matching samples</h3><p>Try another search or choose a different category.</p><a class="button button-outline" href="<?= $escape($catalogUrl(['category' => 'all', 'q' => ''])) ?>">Reset filters</a></div>
            <?php else: ?>
                <div class="product-grid showcase-results">
                    <?php foreach ($items as $item): require __DIR__ . '/partials/catalog-card.php'; endforeach ?>
                </div>
            <?php endif ?>
        </div>
    </section>

    <?php require __DIR__ . '/partials/home-stories.php' ?>
</main>
<footer class="site-footer home-footer">
    <div class="container footer-bottom">
        <a class="brand" href="/" aria-label="SwapFix home"><img src="<?= $escape($logo['src']) ?>" alt="SwapFix" width="<?= $logo['width'] ?>" height="<?= $logo['height'] ?>" loading="lazy" decoding="async"></a>
        <nav class="footer-links" aria-label="Footer navigation">
            <?php foreach ($sections as [$label, , $url]): ?><a href="<?= $escape($url) ?>"><?= $escape($label) ?></a><?php endforeach ?>
            <a href="#questions">FAQs</a>
            <a href="<?= $accountUrl ?>"><?= $accountLabel ?></a>
        </nav>
        <p>&copy; <?= date('Y') ?> SwapFix</p>
    </div>
</footer>
<nav class="bottom-tabs" aria-label="Quick navigation">
    <a href="/" class="active" aria-current="page"><?= $icon('home') ?><span>Home</span></a>
    <?php foreach ($sections as [$label, $glyph, $url]): ?>
        <a href="<?= $escape($url) ?>"><?= $icon($glyph) ?><span><?= $escape($label) ?></span></a>
    <?php endforeach ?>
</nav>
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
