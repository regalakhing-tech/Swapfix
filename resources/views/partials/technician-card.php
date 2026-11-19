<a class="card tech-card" href="/technicians/<?= $e($tech['id']) ?>">
    <div class="tech-card-top"><?= $ui->avatar($tech, 'avatar-large') ?><div><h2><?= $e($tech['display_name']) ?></h2><?php if ($tech['headline'] !== ''): ?><p><?= $e($tech['headline']) ?></p><?php endif ?>
        <span class="rating-line"><?= $ui->stars($tech['rating_average']) ?><small><?= $tech['rating_count'] > 0 ? $e(number_format((float) $tech['rating_average'], 1)) . ' (' . (int) $tech['rating_count'] . ')' : 'No reviews yet' ?></small></span></div></div>
    <div class="tag-row"><?php foreach (array_slice($tech['specialties'], 0, 4) as $specialty): ?><span class="tag"><?= $e($specialty) ?></span><?php endforeach ?></div>
    <p class="item-location"><?= $ui->icon('pin') ?><?= $e($tech['service_area'] ?: $tech['location']) ?></p>
    <p class="item-location"><?= $ui->icon('clock') ?><?= $e($tech['available_hours']) ?></p>
    <?php if ($tech['starting_price'] !== null): ?><strong class="from-price">From <?= $ui->money($tech['starting_price']) ?></strong><?php endif ?>
</a>
