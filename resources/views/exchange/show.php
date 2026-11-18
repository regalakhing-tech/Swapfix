<?php
use SwapFix\Support\Labels;

$session = $ui->session;
$id = $exchange['id'];
$status = $exchange['status'];
$myActiveOffer = null;
foreach ($offers as $offer) {
    if (!$isOwner && in_array($offer['status'], ['pending', 'accepted'], true)) {
        $myActiveOffer = $offer;
        break;
    }
}
$actions = '';
if ($isOwner && $status === 'open') {
    $actions = $ui->link('/exchange/' . $id . '/edit', 'Edit', 'edit', 'button button-outline');
} elseif (!$isOwner && $status === 'open' && $session && $myActiveOffer === null) {
    $actions = $ui->dialogLink('offer-dialog', 'offer', 'Make an offer', 'swap');
} elseif (!$isOwner && $status === 'open' && !$session) {
    $actions = $ui->link('/login?next=' . rawurlencode('/exchange/' . $id . '?offer=1'), 'Sign in to offer', 'swap');
}
echo $ui->heading('swap', $exchange['title'], trim($exchange['brand'] . ' ' . $exchange['model']), $actions);
$photoSources = array_map(static fn (string $path): string => $ui->photoUrl('listing-images', $path), $exchange['photo_paths']);
$photoAlt = $exchange['title'];
$photoIcon = 'camera';
$acceptedOffer = null;
foreach ($offers as $offer) {
    if ($offer['id'] === $exchange['accepted_offer_id']) {
        $acceptedOffer = $offer;
    }
}
?>
<?= $ui->alert($offerError ?? null) ?>
<div class="detail-layout">
    <div class="detail-main">
        <?php require __DIR__ . '/../partials/gallery.php' ?>
        <?php if ($exchange['description'] !== ''): ?><section class="card"><h2 class="section-title"><?= $ui->icon('info') ?>Details</h2><p class="prose"><?= nl2br($e($exchange['description'])) ?></p></section><?php endif ?>
        <section class="card <?= $exchange['known_defects'] !== '' ? 'card-warning' : '' ?>"><h2 class="section-title"><?= $ui->icon('alert') ?>Known defects</h2><p class="prose"><?= $exchange['known_defects'] !== '' ? nl2br($e($exchange['known_defects'])) : 'None disclosed by the owner.' ?></p></section>
        <?php if ($session): ?>
            <section class="card">
                <h2 class="section-title"><?= $ui->icon('cash') ?><?= $isOwner ? 'Offers received' : 'Your offers' ?></h2>
                <?php if ($offers === []): ?><p class="muted-line">No offers yet.</p><?php endif ?>
                <div class="row-list">
                    <?php foreach ($offers as $offer): $terms = $offer['latest']; $myTurn = ($offer['awaiting_user_id'] ?? null) === $session->id && $status === 'open'; ?>
                        <a class="list-row" href="/offers/<?= $e($offer['id']) ?>">
                            <?= $ui->avatar($offer['offerer']) ?>
                            <span><strong><?= $e($terms['device_title'] ?? 'Offer') ?></strong><small><?= $e($offer['offerer']['display_name']) ?> &middot; <?= $e(Labels::cashTerms($terms['cash_amount'] ?? 0, $terms['cash_payer'] ?? 'none', $isOwner)) ?></small></span>
                            <?php if ($myTurn): ?><span class="status status-amber">Your turn</span><?php else: ?><?= $ui->status($offer['status']) ?><?php endif ?>
                        </a>
                    <?php endforeach ?>
                </div>
            </section>
        <?php endif ?>
    </div>
    <aside class="detail-side">
        <section class="card price-card">
            <?= $ui->status($status) ?>
            <h2 class="wanted-title"><small>Wants</small><?= $e($exchange['wanted_item']) ?></h2>
            <?php if ($exchange['wanted_details'] !== ''): ?><p class="prose"><?= nl2br($e($exchange['wanted_details'])) ?></p><?php endif ?>
            <dl class="facts">
                <div><dt>Cash</dt><dd><?= $e(Labels::CASH_PREFERENCES[$exchange['cash_preference']] ?? '') ?></dd></div>
                <div><dt>Condition</dt><dd><?= $e(Labels::CONDITIONS[$exchange['condition']] ?? '') ?></dd></div>
                <div><dt>Location</dt><dd><?= $e($exchange['location']) ?></dd></div>
                <?php if ($exchange['completed_at']): ?><div><dt>Completed</dt><dd><?= $ui->date($exchange['completed_at']) ?></dd></div><?php endif ?>
            </dl>
        </section>
        <section class="card person-card"><?= $ui->avatar($exchange['owner']) ?><div><small>Owner</small><strong><?= $e($exchange['owner']['display_name']) ?></strong></div></section>
        <?php if ($isOwner && $status === 'agreed'): ?>
            <section class="card">
                <h2 class="section-title"><?= $ui->icon('check-circle') ?>Agreement</h2>
                <p class="muted-line">Complete this after the physical handover.</p>
                <div class="action-stack">
                    <?php if ($acceptedOffer): ?><?= $ui->link('/offers/' . $acceptedOffer['id'], 'View agreed offer', 'eye', 'button button-outline') ?><?php endif ?>
                    <?= $ui->postButton('/exchange/' . $id . '/complete', 'Mark completed', 'check-circle', 'button button-primary', [], ['Complete exchange?', 'Confirm the handover happened. The listing will close.', 'Complete']) ?>
                    <?= $ui->dialogLink('cancel-dialog', 'cancel', 'Cancel agreement', 'close', 'button button-outline') ?>
                </div>
            </section>
        <?php elseif ($isOwner && $status === 'open'): ?>
            <section class="card"><?= $ui->postButton('/exchange/' . $id . '/remove', 'Remove listing', 'trash', 'button button-outline button-block', [], ['Remove listing?', 'Pending offers will be cancelled.', 'Remove']) ?></section>
        <?php endif ?>
    </aside>
</div>
<?php if (!$isOwner && $status === 'open' && $session && $myActiveOffer === null): ?>
    <?= $ui->dialogStart('offer-dialog', 'Make an offer', 'offer') ?>
    <form method="post" action="/exchange/<?= $e($id) ?>/offers" enctype="multipart/form-data" class="stack-form">
        <?= $ui->csrf() ?>
        <?php $allowPhotos = true; require __DIR__ . '/offer-fields.php' ?>
        <button class="button button-primary button-block" type="submit"><?= $ui->icon('send') ?>Send offer</button>
    </form>
    <?= $ui->dialogEnd() ?>
<?php endif ?>
<?php if ($isOwner && $status === 'agreed'): ?>
    <?= $ui->dialogStart('cancel-dialog', 'Cancel agreement', 'cancel') ?>
    <form method="post" action="/exchange/<?= $e($id) ?>/cancel-agreement" class="stack-form">
        <?= $ui->csrf() ?>
        <?= $ui->textarea('note', 'Reason', '', ['required' => true, 'minlength' => 2, 'maxlength' => 1000]) ?>
        <button class="button button-primary button-block" type="submit">Cancel agreement</button>
    </form>
    <?= $ui->dialogEnd() ?>
<?php endif ?>
