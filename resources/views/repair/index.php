<?php
$total = (int) $results['total'];
$session = $ui->session;
$ui->title = 'Repair';
$specialties = ['Screen replacement' => 'phone', 'Battery replacement' => 'battery', 'Charging port repair' => 'plug', 'Software troubleshooting' => 'settings'];
$quickFilters = ['Brand' => array_map(
    static fn (string $brand): array => [$brand, 'phone', ['brand' => $brand], $filters['brand'] === $brand],
    ['Apple', 'Samsung', 'Xiaomi', 'Oppo', 'Vivo'],
)];
$guideCta = match (true) {
    $session === null => ['Are you a technician?', 'Offer your repair services.', '/register?type=technician', 'Join as technician', 'wrench'],
    $session->isTechnician() => ['Keep your services current', 'Update prices and specialties.', '/technician/services', 'Manage services', 'settings'],
    default => null,
};
?>
<section class="browse-page discover-repair" aria-labelledby="repair-directory-title">
    <header class="browse-intro repair-intro">
        <div class="browse-intro-copy">
            <p class="browse-section-name"><?= $ui->icon('wrench') ?>Repair</p>
            <h1 id="repair-directory-title">Find a technician.</h1>
            <p>Pick a specialty or area. Approve the quote before work starts.</p>
            <ul class="browse-facts">
                <li><?= $ui->icon('wrench') ?><?= $total ?> <?= $total === 1 ? 'technician' : 'technicians' ?></li>
                <li><?= $ui->icon('clipboard') ?>Quote first</li>
                <li><?= $ui->icon('star') ?>Real reviews</li>
            </ul>
            <?= $session ? $ui->link('/repairs', 'My repairs', 'clipboard', 'button button-outline') : $ui->link('/login?next=%2Frepairs', 'Track a repair', 'clipboard', 'button button-outline') ?>
        </div>
        <div class="browse-scene" aria-hidden="true">
            <?php $sceneKey = 'scene-repair'; $sceneLabel = ''; $sceneSizes = '(max-width: 720px) 45vw, 340px'; require __DIR__ . '/../partials/scene-art.php'; ?>
        </div>
    </header>
    <nav class="repair-specialties" aria-label="Popular repair specialties">
        <?php foreach ($specialties as $specialty => $glyph): ?>
            <?php $active = $filters['specialty'] === $specialty; ?>
            <a class="category-chip<?= $active ? ' active' : '' ?>"<?= $active ? ' aria-current="true"' : '' ?> href="<?= $e($ui->url(['specialty' => $active ? null : $specialty, 'page' => null])) ?>">
                <?= $ui->icon($glyph) ?><?= $e($specialty) ?>
            </a>
        <?php endforeach ?>
    </nav>
<?php require __DIR__ . '/../partials/quick-filters.php' ?>
<form class="filter-bar card" method="get" action="/repair" role="search">
    <label class="search-field filter-search"><?= $ui->icon('search') ?><span class="sr-only">Search technicians</span><input type="search" name="q" value="<?= $e($filters['q']) ?>" maxlength="100" placeholder="Name or specialty"></label>
    <div class="form-grid filter-grid filter-inline">
        <?= $ui->input('area', 'Area', $filters['area'], ['maxlength' => 120]) ?>
        <?= $ui->input('specialty', 'Specialty', $filters['specialty'], ['maxlength' => 60]) ?>
        <?= $ui->input('brand', 'Brand', $filters['brand'], ['maxlength' => 60]) ?>
    </div>
    <button class="button button-primary" type="submit"><?= $ui->icon('search') ?>Search</button>
</form>
<p class="browse-count"><?= $total ?> <?= $total === 1 ? 'technician' : 'technicians' ?></p>
<?php if ($results['items'] === []): ?>
    <?= $ui->emptyState('wrench', 'No technicians match yet.', $ui->link('/repair', 'Clear filters', 'close', 'button button-outline')) ?>
<?php else: ?>
    <div class="tech-grid">
        <?php foreach ($results['items'] as $tech): require __DIR__ . '/../partials/technician-card.php'; endforeach ?>
    </div>
    <?= $ui->pagination($total, $pageNumber, $pageSize) ?>
<?php endif ?>
<?php $guideKey = 'repair'; require __DIR__ . '/../partials/section-guide.php' ?>
</section>
