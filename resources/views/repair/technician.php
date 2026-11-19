<?php
$person = $technician['profile'];
$id = $technician['user_id'];
$requestUrl = '/repair/request/' . $id;
$cta = $isSelf ? $ui->link('/technician/profile', 'Edit profile', 'edit', 'button button-outline') : $ui->link($ui->session ? $requestUrl : '/login?next=' . rawurlencode($requestUrl), 'Request repair', 'wrench');
echo $ui->heading('wrench', $person['display_name'], $technician['headline'] ?: 'Repair technician', $ui->link('/repair', 'Back', 'arrow-left', 'button button-outline') . $cta);
$chips = static function (array $items) use ($e): string {
    return implode('', array_map(static fn (string $item): string => '<span class="tag">' . $e($item) . '</span>', $items));
};
?>
<div class="detail-layout">
    <div class="detail-main">
        <?php if ($technician['bio'] !== ''): ?><section class="card"><h2 class="section-title"><?= $ui->icon('info') ?>About</h2><p class="prose"><?= nl2br($e($technician['bio'])) ?></p></section><?php endif ?>
        <section class="card">
            <h2 class="section-title"><?= $ui->icon('settings') ?>Services</h2>
            <?php if ($services === []): ?><p class="muted-line">No published services yet.</p><?php endif ?>
            <div class="row-list"><?php foreach ($services as $service): ?>
                <div class="list-row static service-row">
                    <span><strong><?= $e($service['name']) ?></strong><?php if ($service['description'] !== ''): ?><small><?= $e($service['description']) ?></small><?php endif ?><?php if ($service['supported_brands'] !== [] || $service['supported_models'] !== []): ?><small><?= $e(implode(', ', array_merge($service['supported_brands'], $service['supported_models']))) ?></small><?php endif ?></span>
                    <span class="service-price"><small>Estimate</small><strong><?= $ui->money($service['estimated_price']) ?></strong><?php if (!$isSelf): ?><a class="text-link" href="<?= $e($ui->session ? $requestUrl . '?service=' . $service['id'] : '/login?next=' . rawurlencode($requestUrl . '?service=' . $service['id'])) ?>">Request</a><?php endif ?></span>
                </div>
            <?php endforeach ?></div>
        </section>
        <section class="card">
            <h2 class="section-title"><?= $ui->icon('star') ?>Reviews</h2>
            <?php if ($reviews === []): ?><p class="muted-line">No reviews yet.</p><?php endif ?>
            <div class="review-list"><?php foreach ($reviews as $review): ?>
                <article class="review"><div class="review-head"><?= $ui->stars($review['rating']) ?><strong><?= $e($review['reviewer_name']) ?></strong><time><?= $ui->date($review['created_at']) ?></time></div><?php if ($review['comment'] !== ''): ?><p><?= nl2br($e($review['comment'])) ?></p><?php endif ?></article>
            <?php endforeach ?></div>
        </section>
    </div>
    <aside class="detail-side">
        <section class="card person-card person-card-large"><?= $ui->avatar($person, 'avatar-large') ?><div><strong><?= $e($person['display_name']) ?></strong><span class="rating-line"><?= $ui->stars($technician['rating_average']) ?><small><?= $technician['rating_count'] > 0 ? $e(number_format((float) $technician['rating_average'], 1)) . ' (' . (int) $technician['rating_count'] . ')' : 'No reviews yet' ?></small></span></div></section>
        <section class="card">
            <dl class="facts">
                <div><dt>Location</dt><dd><?= $e($technician['location']) ?></dd></div>
                <div><dt>Service area</dt><dd><?= $e($technician['service_area']) ?></dd></div>
                <div><dt>Hours</dt><dd><?= $e($technician['available_hours']) ?></dd></div>
            </dl>
        </section>
        <section class="card"><h2 class="section-title"><?= $ui->icon('wrench') ?>Specialties</h2><div class="tag-row"><?= $chips($technician['specialties']) ?></div></section>
        <?php if ($technician['supported_brands'] !== [] || $technician['supported_models'] !== []): ?><section class="card"><h2 class="section-title"><?= $ui->icon('phone') ?>Supported devices</h2><div class="tag-row"><?= $chips(array_merge($technician['supported_brands'], $technician['supported_models'])) ?></div></section><?php endif ?>
    </aside>
</div>
