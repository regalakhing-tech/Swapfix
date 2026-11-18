<?php
$total = (int) $results['total'];
$session = $ui->session;
$ui->title = 'Repair';
$specialties = ['Screen replacement' => 'phone', 'Battery replacement' => 'battery', 'Charging port repair' => 'plug', 'Software troubleshooting' => 'settings'];
?>
<section class="browse-page discover-repair" aria-labelledby="repair-directory-title">
    <header class="browse-intro repair-intro">
        <div class="browse-intro-copy">
            <p class="browse-section-name"><?= $ui->icon('wrench') ?>Repair</p>
            <h1 id="repair-directory-title">Find a technician.</h1>
            <p>Choose a specialty or search by area. Review the technician’s quote before work starts.</p>
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
        <?php foreach ($results['items'] as $tech): ?>
            <a class="card tech-card" href="/technicians/<?= $e($tech['id']) ?>">
                <div class="tech-card-top"><?= $ui->avatar($tech, 'avatar-large') ?><div><h2><?= $e($tech['display_name']) ?></h2><?php if ($tech['headline'] !== ''): ?><p><?= $e($tech['headline']) ?></p><?php endif ?>
                    <span class="rating-line"><?= $ui->stars($tech['rating_average']) ?><small><?= $tech['rating_count'] > 0 ? $e(number_format((float) $tech['rating_average'], 1)) . ' (' . (int) $tech['rating_count'] . ')' : 'No reviews yet' ?></small></span></div></div>
                <div class="tag-row"><?php foreach (array_slice($tech['specialties'], 0, 4) as $specialty): ?><span class="tag"><?= $e($specialty) ?></span><?php endforeach ?></div>
                <p class="item-location"><?= $ui->icon('pin') ?><?= $e($tech['service_area'] ?: $tech['location']) ?></p>
                <p class="item-location"><?= $ui->icon('clock') ?><?= $e($tech['available_hours']) ?></p>
                <?php if ($tech['starting_price'] !== null): ?><strong class="from-price">From <?= $ui->money($tech['starting_price']) ?></strong><?php endif ?>
            </a>
        <?php endforeach ?>
    </div>
    <?= $ui->pagination($total, $pageNumber, $pageSize) ?>
<?php endif ?>
</section>
