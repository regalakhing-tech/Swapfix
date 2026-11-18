<?php
use SwapFix\Support\Labels;

$me = $ui->session->id;
$isOwner = $offer['owner_id'] === $me;
$other = $isOwner ? $offer['offerer'] : $offer['owner'];
$latest = $offer['latest'];
$version = (int) $offer['current_version'];
$eventLabels = ['offered' => 'Offer sent', 'countered' => 'Counteroffer sent', 'accepted' => 'Accepted', 'declined' => 'Declined', 'withdrawn' => 'Withdrawn', 'cancelled' => 'Cancelled', 'completed' => 'Exchange completed'];
$nameOf = static fn (string $id): string => $id === $me ? 'You' : $other['display_name'];
echo $ui->heading('cash', 'Trade offer', $exchange['title'], $ui->link('/exchange/' . $exchange['id'], 'View listing', 'swap', 'button button-outline'));
?>
<?= $ui->alert($counterError ?? null) ?>
<div class="detail-layout">
    <div class="detail-main">
        <section class="card">
            <div class="card-heading"><h2 class="section-title"><?= $ui->icon('swap') ?>Current terms <small>Version <?= $version ?></small></h2><?= $ui->status($offer['status']) ?></div>
            <?php if ($actions['respond']): ?><?= $ui->alert('Your response is needed.', 'info') ?><?php elseif ($actions['waitingForOther']): ?><?= $ui->alert('Waiting for ' . $other['display_name'] . ' to respond.', 'info') ?><?php elseif ($actions['onHold']): ?><?= $ui->alert('On hold while another offer is agreed.', 'info') ?><?php endif ?>
            <h3 class="terms-title"><?= $e($latest['device_title']) ?></h3>
            <dl class="facts facts-grid">
                <div><dt>Brand</dt><dd><?= $e(trim($latest['brand'] . ' ' . $latest['model'])) ?></dd></div>
                <div><dt>Condition</dt><dd><?= $e(Labels::CONDITIONS[$latest['condition']] ?? '') ?></dd></div>
                <div><dt>Cash</dt><dd><?= $e(Labels::cashTerms($latest['cash_amount'], $latest['cash_payer'], $isOwner)) ?></dd></div>
                <div><dt>Proposed by</dt><dd><?= $e($nameOf($latest['proposed_by'])) ?></dd></div>
            </dl>
            <p class="prose"><strong>Known defects:</strong> <?= $latest['known_defects'] !== '' ? nl2br($e($latest['known_defects'])) : 'None disclosed.' ?></p>
            <p class="prose"><?= nl2br($e($latest['message'])) ?></p>
            <?php if ($latest['photo_paths'] !== []): ?>
                <div class="photo-strip"><?php foreach ($latest['photo_paths'] as $path): if (isset($photoUrls[$path])): ?><a href="<?= $e($photoUrls[$path]) ?>" target="_blank" rel="noopener"><img src="<?= $e($photoUrls[$path]) ?>" width="160" height="120" alt="Offered gadget" loading="lazy"></a><?php endif; endforeach ?></div>
            <?php endif ?>
        </section>
        <?php if (count($offer['terms']) > 1): ?>
            <section class="card">
                <h2 class="section-title"><?= $ui->icon('clock') ?>Earlier versions</h2>
                <div class="row-list"><?php foreach (array_slice($offer['terms'], 1) as $terms): ?>
                    <div class="list-row static"><span class="version-tag">v<?= (int) $terms['version'] ?></span><span><strong><?= $e($terms['device_title']) ?></strong><small><?= $e($nameOf($terms['proposed_by'])) ?> &middot; <?= $e(Labels::cashTerms($terms['cash_amount'], $terms['cash_payer'], $isOwner)) ?></small></span></div>
                <?php endforeach ?></div>
            </section>
        <?php endif ?>
        <?php if ($conversationId): ?>
            <section class="card"><h2 class="section-title"><?= $ui->icon('message') ?>Discussion</h2><?php $threadBack = '/offers/' . $offer['id']; require __DIR__ . '/../partials/thread.php' ?></section>
        <?php endif ?>
    </div>
    <aside class="detail-side">
        <section class="card person-card"><?= $ui->avatar($other) ?><div><small><?= $isOwner ? 'Offer from' : 'Listing owner' ?></small><strong><?= $e($other['display_name']) ?></strong></div></section>
        <?php if ($actions['respond'] || $actions['withdraw'] || $actions['complete'] || $actions['cancelAgreement']): ?>
            <section class="card">
                <h2 class="section-title"><?= $ui->icon('check-circle') ?>What's next?</h2>
                <div class="action-stack">
                    <?php if ($actions['respond']): ?>
                        <?= $ui->postButton('/offers/' . $offer['id'] . '/accept', 'Accept terms', 'check', 'button button-primary', ['version' => $version], ['Accept these terms?', 'This records your agreement. The handover is completed separately.', 'Accept']) ?>
                        <?= $ui->dialogLink('counter-dialog', 'counter', 'Counteroffer', 'swap', 'button button-outline') ?>
                        <?= $ui->dialogLink('decline-dialog', 'decline', 'Decline', 'close', 'button button-outline') ?>
                    <?php endif ?>
                    <?php if ($actions['complete']): ?><?= $ui->postButton('/exchange/' . $exchange['id'] . '/complete', 'Mark completed', 'check-circle', 'button button-primary', [], ['Complete exchange?', 'Confirm the handover happened.', 'Complete']) ?><?php endif ?>
                    <?php if ($actions['cancelAgreement']): ?><?= $ui->dialogLink('cancel-dialog', 'cancel', 'Cancel agreement', 'close', 'button button-outline') ?><?php endif ?>
                    <?php if ($actions['withdraw']): ?><?= $ui->postButton('/offers/' . $offer['id'] . '/withdraw', 'Withdraw offer', 'trash', 'button button-outline', [], ['Withdraw offer?', 'This cannot be undone.', 'Withdraw']) ?><?php endif ?>
                </div>
            </section>
        <?php endif ?>
        <section class="card">
            <h2 class="section-title"><?= $ui->icon('activity') ?>History</h2>
            <ol class="timeline"><?php foreach (array_reverse($offer['trade_offer_events']) as $event): ?>
                <li><strong><?= $e($eventLabels[$event['event']] ?? $event['event']) ?></strong><small><?= $e($nameOf($event['actor_id'])) ?> &middot; <?= $ui->dateTime($event['created_at']) ?></small><?php if ($event['note'] !== ''): ?><p><?= $e($event['note']) ?></p><?php endif ?></li>
            <?php endforeach ?></ol>
        </section>
    </aside>
</div>
<?php if ($actions['respond']): ?>
    <?= $ui->dialogStart('counter-dialog', 'Counteroffer', 'counter') ?>
    <form method="post" action="/offers/<?= $e($offer['id']) ?>/counter" enctype="multipart/form-data" class="stack-form">
        <?= $ui->csrf() ?>
        <input type="hidden" name="version" value="<?= $version ?>">
        <?php $allowPhotos = !$isOwner; $old['message'] = isset($counterError) ? $old['message'] : ''; require __DIR__ . '/offer-fields.php' ?>
        <button class="button button-primary button-block" type="submit"><?= $ui->icon('send') ?>Send counteroffer</button>
    </form>
    <?= $ui->dialogEnd() ?>
    <?= $ui->dialogStart('decline-dialog', 'Decline offer', 'decline') ?>
    <form method="post" action="/offers/<?= $e($offer['id']) ?>/decline" class="stack-form">
        <?= $ui->csrf() ?>
        <input type="hidden" name="version" value="<?= $version ?>">
        <?= $ui->textarea('note', 'Note', '', ['maxlength' => 1000]) ?>
        <button class="button button-primary button-block" type="submit">Decline offer</button>
    </form>
    <?= $ui->dialogEnd() ?>
<?php endif ?>
<?php if ($actions['cancelAgreement']): ?>
    <?= $ui->dialogStart('cancel-dialog', 'Cancel agreement', 'cancel') ?>
    <form method="post" action="/exchange/<?= $e($exchange['id']) ?>/cancel-agreement" class="stack-form">
        <?= $ui->csrf() ?>
        <?= $ui->textarea('note', 'Reason', '', ['required' => true, 'minlength' => 2, 'maxlength' => 1000]) ?>
        <button class="button button-primary button-block" type="submit">Cancel agreement</button>
    </form>
    <?= $ui->dialogEnd() ?>
<?php endif ?>
