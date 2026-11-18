<?php
$total = (int) $results['total'];
$session = $ui->session;
echo $ui->heading('wrench', 'Find a technician', $total . ' ' . ($total === 1 ? 'technician' : 'technicians'), $session ? $ui->link('/repairs', 'My repairs', 'clipboard', 'button button-outline') : '');
?>
<form class="filter-bar card" method="get" action="/repair" role="search">
    <label class="search-field filter-search"><?= $ui->icon('search') ?><span class="sr-only">Search technicians</span><input type="search" name="q" value="<?= $e($filters['q']) ?>" maxlength="100" placeholder="Name or specialty"></label>
    <div class="form-grid filter-grid filter-inline">
        <?= $ui->input('area', 'Area', $filters['area'], ['maxlength' => 120]) ?>
        <?= $ui->input('specialty', 'Specialty', $filters['specialty'], ['maxlength' => 60]) ?>
        <?= $ui->input('brand', 'Brand', $filters['brand'], ['maxlength' => 60]) ?>
    </div>
    <button class="button button-primary" type="submit"><?= $ui->icon('search') ?>Search</button>
</form>
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
