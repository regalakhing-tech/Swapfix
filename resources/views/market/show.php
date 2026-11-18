<?php
use SwapFix\Support\Labels;

$session = $ui->session;
$id = $listing['id'];
$status = $listing['status'];
$live = in_array($status, ['available', 'reserved'], true);
$actions = '';
if ($isOwner && $live) {
    $actions = $ui->link('/market/' . $id . '/edit', 'Edit', 'edit', 'button button-outline');
} elseif (!$isOwner && $live && $session) {
    $actions = $ui->dialogLink('inquire-dialog', 'inquire', 'Send inquiry', 'message');
} elseif (!$isOwner && $live) {
    $actions = $ui->link('/login?next=' . rawurlencode('/market/' . $id . '?inquire=1'), 'Sign in to inquire', 'message');
}
echo $ui->heading(Labels::CATEGORY_ICONS[$listing['category']] ?? 'bag', $listing['title'], trim($listing['brand'] . ' ' . $listing['model']), $actions);
$photoSources = array_map(static fn (string $path): string => $ui->photoUrl('listing-images', $path), $listing['photo_paths']);
$photoAlt = $listing['title'];
$photoIcon = 'camera';
$setStatus = static fn (string $to, string $label, string $icon, array $confirm = []) => $ui->postButton('/market/' . $id . '/status', $label, $icon, 'button button-outline', ['status' => $to, 'expected_status' => $status], $confirm);
?>
<div class="detail-layout">
    <div class="detail-main">
        <?php require __DIR__ . '/../partials/gallery.php' ?>
        <section class="card"><h2 class="section-title"><?= $ui->icon('info') ?>Details</h2><p class="prose"><?= nl2br($e($listing['description'])) ?></p></section>
        <section class="card <?= $listing['known_defects'] !== '' ? 'card-warning' : '' ?>"><h2 class="section-title"><?= $ui->icon('alert') ?>Known defects</h2><p class="prose"><?= $listing['known_defects'] !== '' ? nl2br($e($listing['known_defects'])) : 'None disclosed by the seller.' ?></p></section>
    </div>
    <aside class="detail-side">
        <section class="card price-card">
            <?= $ui->status($status) ?>
            <strong class="price-large"><?= $ui->money($listing['price']) ?></strong>
            <dl class="facts">
                <div><dt>Condition</dt><dd><?= $e(Labels::CONDITIONS[$listing['condition']] ?? '') ?></dd></div>
                <div><dt>Category</dt><dd><?= $e(Labels::CATEGORIES[$listing['category']] ?? '') ?></dd></div>
                <div><dt>Location</dt><dd><?= $e($listing['location']) ?></dd></div>
                <div><dt>Listed</dt><dd><?= $ui->date($listing['created_at']) ?></dd></div>
            </dl>
        </section>
        <section class="card person-card"><?= $ui->avatar($listing['owner']) ?><div><small>Seller</small><strong><?= $e($listing['owner']['display_name']) ?></strong></div></section>
        <?php if ($isOwner && $live): ?>
            <section class="card">
                <h2 class="section-title"><?= $ui->icon('settings') ?>Availability</h2>
                <div class="action-stack">
                    <?= $status === 'available' ? $setStatus('reserved', 'Mark reserved', 'clock') : $setStatus('available', 'Mark available', 'check') ?>
                    <?= $setStatus('sold', 'Mark sold', 'check-circle', ['Mark as sold?', 'This removes the item from browsing and cannot be undone.', 'Mark sold']) ?>
                    <?= $setStatus('removed', 'Remove listing', 'trash', ['Remove listing?', 'It will no longer appear anywhere. This cannot be undone.', 'Remove']) ?>
                </div>
            </section>
        <?php endif ?>
        <?php if ($isOwner): ?>
            <section class="card">
                <h2 class="section-title"><?= $ui->icon('message') ?>Inquiries</h2>
                <?php if ($inquiries === []): ?><p class="muted-line">No inquiries yet.</p><?php endif ?>
                <div class="row-list"><?php foreach ($inquiries as $inquiry): ?>
                    <a class="list-row" href="/messages/<?= $e($inquiry['id']) ?>"><?= $ui->avatar($inquiry['initiator']) ?><span><strong><?= $e($inquiry['initiator']['display_name']) ?></strong><small><?= $e($inquiry['last_message_preview']) ?></small></span></a>
                <?php endforeach ?></div>
            </section>
        <?php endif ?>
    </aside>
</div>
<?php if (!$isOwner && $live && $session): ?>
    <?= $ui->dialogStart('inquire-dialog', 'Send inquiry', 'inquire') ?>
    <form method="post" action="/market/<?= $e($id) ?>/inquire" class="stack-form">
        <?= $ui->csrf() ?>
        <?= $ui->textarea('message', 'Message', 'Hi, is this still available?', ['required' => true, 'maxlength' => 4000]) ?>
        <button class="button button-primary button-block" type="submit"><?= $ui->icon('send') ?>Send</button>
    </form>
    <?= $ui->dialogEnd() ?>
<?php endif ?>
