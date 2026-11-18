<?php
use SwapFix\Support\Labels;

$id = $repair['id'];
$status = $repair['status'];
$technicianPerson = $repair['technician']['profile'];
$customer = $repair['customer'];
$isTechnician = $actions['isTechnician'];
$other = $isTechnician ? $customer : $technicianPerson;
$base = '/repairs/' . $id;
$flow = Labels::REPAIR_FLOW;
$position = array_search($status, $flow, true);
$nextLabels = ['in_repair' => ['Start repair', 'wrench'], 'ready_for_pickup' => ['Mark ready for pickup', 'check'], 'completed' => ['Record handover', 'check-circle']];
$eventLabels = [
    'quotation_sent' => 'Quotation sent', 'quotation_accepted' => 'Quotation accepted', 'quotation_declined' => 'Quotation declined',
    'quotation_superseded' => 'Quotation replaced', 'added_cost_requested' => 'Added cost requested', 'added_cost_accepted' => 'Added cost approved',
    'added_cost_declined' => 'Added cost declined', 'added_cost_withdrawn' => 'Added cost withdrawn', 'review_submitted' => 'Review submitted',
];
$nameOf = static fn (?string $person): string => $person === null ? 'SwapFix' : ($person === $ui->session->id ? 'You' : ($person === $customer['id'] ? $customer['display_name'] : $technicianPerson['display_name']));
$decide = static fn (string $url, string $decision, string $label, string $icon, string $class, array $confirm = []) => $ui->postButton($url, $label, $icon, $class, ['decision' => $decision], $confirm);
$formDialog = $form['dialog'] ?? null;
$formValue = static fn (string $dialog, string $name): string => $formDialog === $dialog ? ($form['values'][$name] ?? '') : '';
$formAlert = static fn (string $dialog): string => $formDialog === $dialog ? $ui->alert($form['error']) : '';
$formAction = match ($formDialog) {
    'quote' => 'canQuote',
    'added' => 'canRequestAddedCost',
    'cancel' => 'canCancel',
    'review' => 'canReview',
    default => null,
};
echo $ui->heading('wrench', $repair['device_brand'] . ' ' . $repair['device_model'], Labels::DEVICE_TYPES[$repair['device_type']] ?? '', $ui->status($status));
if ($formAction !== null && !$actions[$formAction]) {
    echo $formAlert($formDialog);
}
?>
<?php if ($status === 'cancelled'): ?>
    <?= $ui->alert('Cancelled by ' . $nameOf($repair['cancelled_by']) . ': ' . $repair['cancel_reason']) ?>
<?php else: ?>
    <ol class="steps" aria-label="Repair progress">
        <?php foreach ($flow as $index => $step): ?>
            <li class="<?= $index < $position ? 'done' : ($index === $position ? 'current' : '') ?>" <?= $index === $position ? 'aria-current="step"' : '' ?>><span><?= $index < $position ? $ui->icon('check') : $index + 1 ?></span><?= $e(Labels::status($step)) ?></li>
        <?php endforeach ?>
    </ol>
<?php endif ?>
<div class="detail-layout">
    <div class="detail-main">
        <section class="card">
            <h2 class="section-title"><?= $ui->icon('clipboard') ?>Request</h2>
            <dl class="facts facts-grid">
                <div><dt>Customer</dt><dd><?= $e($customer['display_name']) ?></dd></div>
                <div><dt>Technician</dt><dd><a class="text-link" href="/technicians/<?= $e($repair['technician_id']) ?>"><?= $e($technicianPerson['display_name']) ?></a></dd></div>
                <div><dt>Service</dt><dd><?= $e($repair['requested_service'] ?: 'Not specified') ?></dd></div>
                <div><dt>Requested</dt><dd><?= $ui->date($repair['created_at']) ?></dd></div>
                <?php if ($repair['approved_total'] !== null): ?><div><dt>Agreed total</dt><dd><strong><?= $ui->money($repair['approved_total']) ?></strong></dd></div><?php endif ?>
                <?php if ($repair['estimated_completion']): ?><div><dt>Estimated completion</dt><dd><?= $ui->date($repair['estimated_completion']) ?></dd></div><?php endif ?>
                <?php if ($repair['completed_at']): ?><div><dt>Completed</dt><dd><?= $ui->date($repair['completed_at']) ?></dd></div><?php endif ?>
            </dl>
            <p class="prose"><?= nl2br($e($repair['problem'])) ?></p>
            <?php if ($photoUrls !== []): ?><div class="photo-strip"><?php foreach ($photoUrls as $url): ?><a href="<?= $e($url) ?>" target="_blank" rel="noopener"><img src="<?= $e($url) ?>" width="160" height="120" alt="Reported device problem" loading="lazy"></a><?php endforeach ?></div><?php endif ?>
        </section>

        <section class="card">
            <h2 class="section-title"><?= $ui->icon('cash') ?>Quotations</h2>
            <?php if ($repair['repair_quotations'] === []): ?><p class="muted-line">No quotation yet.</p><?php endif ?>
            <?php foreach ($repair['repair_quotations'] as $quotation): ?>
                <article class="record <?= $quotation['status'] === 'pending' ? 'record-active' : '' ?>">
                    <div class="record-head"><strong>Version <?= (int) $quotation['version'] ?> &middot; <?= $ui->money($quotation['cost']) ?></strong><?= $ui->status($quotation['status']) ?></div>
                    <p class="prose"><?= nl2br($e($quotation['scope'])) ?></p>
                    <?php if ($quotation['parts'] !== ''): ?><p class="record-meta">Parts: <?= $e($quotation['parts']) ?></p><?php endif ?>
                    <p class="record-meta">Estimated completion <?= $ui->date($quotation['estimated_completion']) ?></p>
                    <?php if ($quotation['decision_note'] !== ''): ?><p class="record-meta">Customer note: <?= $e($quotation['decision_note']) ?></p><?php endif ?>
                    <?php if ($actions['canDecideQuotation'] && $quotation['status'] === 'pending'): $url = $base . '/quotations/' . $quotation['id']; ?>
                        <div class="record-actions">
                            <?= $decide($url, 'accepted', 'Accept quotation', 'check', 'button button-primary', ['Accept this quotation?', 'You approve ' . SwapFix\Support\Money::format($quotation['cost']) . ' for this work.', 'Accept']) ?>
                            <?= $decide($url, 'declined', 'Decline', 'close', 'button button-outline', ['Decline this quotation?', 'No work will start. The technician may send a new quotation.', 'Decline']) ?>
                        </div>
                    <?php endif ?>
                </article>
            <?php endforeach ?>
        </section>

        <?php if ($repair['repair_added_costs'] !== []): ?>
            <section class="card">
                <h2 class="section-title"><?= $ui->icon('plus') ?>Added costs</h2>
                <?php foreach ($repair['repair_added_costs'] as $cost): ?>
                    <article class="record <?= $cost['status'] === 'pending' ? 'record-active' : '' ?>">
                        <div class="record-head"><strong>+<?= $ui->money($cost['amount']) ?></strong><?= $ui->status($cost['status']) ?></div>
                        <p class="prose"><?= nl2br($e($cost['reason'])) ?></p>
                        <p class="record-meta">Proposed work: <?= $e($cost['proposed_work']) ?></p>
                        <p class="record-meta">Total <?= $ui->money($cost['previous_total']) ?> to <?= $ui->money($cost['revised_total']) ?><?php if ($cost['revised_completion']): ?> &middot; New date <?= $ui->date($cost['revised_completion']) ?><?php endif ?></p>
                        <?php if ($cost['status'] === 'pending' && $actions['canDecideAddedCost']): $url = $base . '/added-costs/' . $cost['id']; ?>
                            <div class="record-actions">
                                <?= $decide($url, 'accepted', 'Approve added cost', 'check', 'button button-primary', ['Approve added cost?', 'Your agreed total becomes ' . SwapFix\Support\Money::format($cost['revised_total']) . '.', 'Approve']) ?>
                                <?= $decide($url, 'declined', 'Decline', 'close', 'button button-outline', ['Decline added cost?', 'The agreed total stays the same and this extra work will not proceed.', 'Decline']) ?>
                            </div>
                        <?php elseif ($cost['status'] === 'pending' && $actions['canWithdrawAddedCost']): ?>
                            <div class="record-actions"><?= $ui->postButton($base . '/added-costs/' . $cost['id'] . '/withdraw', 'Withdraw', 'close', 'button button-outline', [], ['Withdraw added cost?', 'The customer will be told it no longer applies.', 'Withdraw']) ?></div>
                        <?php endif ?>
                    </article>
                <?php endforeach ?>
            </section>
        <?php endif ?>

        <?php if ($conversationId): ?>
            <section class="card"><h2 class="section-title"><?= $ui->icon('message') ?>Drop-off and pickup chat</h2><?php $threadBack = $base; require __DIR__ . '/../partials/thread.php' ?></section>
        <?php endif ?>
    </div>
    <aside class="detail-side">
        <section class="card person-card"><?= $ui->avatar($other) ?><div><small><?= $isTechnician ? 'Customer' : 'Technician' ?></small><strong><?= $e($other['display_name']) ?></strong></div></section>
        <?php $hasActions = $actions['canQuote'] || $actions['canRequestAddedCost'] || $actions['nextStatus'] || $actions['canCancel'] || $actions['canReview']; ?>
        <section class="card">
            <h2 class="section-title"><?= $ui->icon('check-circle') ?>What's next?</h2>
            <?php if (!$hasActions && !$actions['canDecideQuotation'] && !$actions['canDecideAddedCost']): ?><p class="muted-line"><?= $status === 'completed' || $status === 'cancelled' ? 'This record is closed.' : 'Waiting for the other party.' ?></p><?php endif ?>
            <?php if ($actions['canDecideQuotation'] || $actions['canDecideAddedCost']): ?><p class="muted-line">Your decision is needed on the left.</p><?php endif ?>
            <div class="action-stack">
                <?php if ($actions['canQuote']): ?><?= $ui->dialogLink('quote-dialog', 'quote', $actions['quotation'] ? 'Replace quotation' : 'Send quotation', 'cash') ?><?php endif ?>
                <?php if ($actions['nextStatus']): [$label, $glyph] = $nextLabels[$actions['nextStatus']]; ?>
                    <?php if ($actions['nextBlocked']): ?><p class="muted-line">Resolve the pending added cost first.</p><?php else: ?>
                        <?= $ui->postButton($base . '/status', $label, $glyph, 'button button-primary', ['status' => $actions['nextStatus'], 'expected_status' => $status], $actions['nextStatus'] === 'completed' ? ['Record handover?', 'This completes the repair and lets the customer review it.', 'Complete'] : []) ?>
                    <?php endif ?>
                <?php endif ?>
                <?php if ($actions['canRequestAddedCost']): ?><?= $ui->dialogLink('added-dialog', 'added', 'Request added cost', 'plus', 'button button-outline') ?><?php endif ?>
                <?php if ($actions['canReview']): ?><?= $ui->dialogLink('review-dialog', 'review', 'Rate this repair', 'star') ?><?php endif ?>
                <?php if ($actions['canCancel']): ?><?= $ui->dialogLink('cancel-dialog', 'cancel', 'Cancel repair', 'close', 'button button-outline') ?><?php endif ?>
            </div>
        </section>
        <?php if ($repair['review']): ?>
            <section class="card"><h2 class="section-title"><?= $ui->icon('star') ?>Review</h2><?= $ui->stars($repair['review']['rating']) ?><?php if ($repair['review']['comment'] !== ''): ?><p class="prose"><?= nl2br($e($repair['review']['comment'])) ?></p><?php endif ?></section>
        <?php endif ?>
        <section class="card">
            <h2 class="section-title"><?= $ui->icon('activity') ?>History</h2>
            <ol class="timeline"><?php foreach ($repair['repair_events'] as $event): ?>
                <li><strong><?= $e($event['kind'] === 'status' ? Labels::status((string) $event['to_status']) : ($eventLabels[$event['kind']] ?? $event['kind'])) ?><?php if ($event['amount'] !== null): ?> &middot; <?= $ui->money($event['amount']) ?><?php endif ?></strong><small><?= $e($nameOf($event['actor_id'])) ?> &middot; <?= $ui->dateTime($event['created_at']) ?></small><?php if ($event['note'] !== ''): ?><p><?= $e($event['note']) ?></p><?php endif ?></li>
            <?php endforeach ?></ol>
        </section>
    </aside>
</div>
<?php if ($actions['canQuote']): ?>
    <?= $ui->dialogStart('quote-dialog', 'Send quotation', 'quote') ?>
    <form method="post" action="<?= $e($base) ?>/quotations" class="stack-form">
        <?= $ui->csrf() ?>
        <?= $formAlert('quote') ?>
        <?= $ui->textarea('scope', 'Repair details', $formValue('quote', 'scope'), ['required' => true, 'minlength' => 5, 'maxlength' => 4000], 'The proposed work.') ?>
        <?= $ui->input('parts', 'Parts', $formValue('quote', 'parts'), ['maxlength' => 2000]) ?>
        <?= $ui->input('cost', 'Cost (PHP)', $formValue('quote', 'cost'), ['required' => true, 'inputmode' => 'decimal']) ?>
        <?= $ui->input('estimated_completion', 'Expected completion', $formValue('quote', 'estimated_completion'), ['required' => true, 'type' => 'date', 'min' => date('Y-m-d')]) ?>
        <button class="button button-primary button-block" type="submit"><?= $ui->icon('send') ?>Send quotation</button>
    </form>
    <?= $ui->dialogEnd() ?>
<?php endif ?>
<?php if ($actions['canRequestAddedCost']): ?>
    <?= $ui->dialogStart('added-dialog', 'Request added cost', 'added') ?>
    <form method="post" action="<?= $e($base) ?>/added-costs" class="stack-form">
        <?= $ui->csrf() ?>
        <?= $formAlert('added') ?>
        <p class="muted-line">Current agreed total: <?= $ui->money($repair['approved_total']) ?></p>
        <?= $ui->textarea('reason', 'Issue found', $formValue('added', 'reason'), ['required' => true, 'minlength' => 5, 'maxlength' => 2000, 'rows' => 3]) ?>
        <?= $ui->textarea('proposed_work', 'Proposed work', $formValue('added', 'proposed_work'), ['required' => true, 'minlength' => 5, 'maxlength' => 2000, 'rows' => 3]) ?>
        <?= $ui->input('amount', 'Added amount (PHP)', $formValue('added', 'amount'), ['required' => true, 'inputmode' => 'decimal']) ?>
        <?= $ui->input('revised_completion', 'New completion date', $formValue('added', 'revised_completion'), ['type' => 'date', 'min' => date('Y-m-d')]) ?>
        <button class="button button-primary button-block" type="submit"><?= $ui->icon('send') ?>Send for approval</button>
    </form>
    <?= $ui->dialogEnd() ?>
<?php endif ?>
<?php if ($actions['canCancel']): ?>
    <?= $ui->dialogStart('cancel-dialog', 'Cancel repair', 'cancel') ?>
    <form method="post" action="<?= $e($base) ?>/cancel" class="stack-form">
        <?= $ui->csrf() ?>
        <?= $formAlert('cancel') ?>
        <?= $ui->textarea('reason', 'Reason', $formValue('cancel', 'reason'), ['required' => true, 'minlength' => 2, 'maxlength' => 1000, 'rows' => 3]) ?>
        <button class="button button-primary button-block" type="submit">Cancel repair</button>
    </form>
    <?= $ui->dialogEnd() ?>
<?php endif ?>
<?php if ($actions['canReview']): ?>
    <?= $ui->dialogStart('review-dialog', 'Rate this repair', 'review') ?>
    <form method="post" action="<?= $e($base) ?>/review" class="stack-form">
        <?= $ui->csrf() ?>
        <?= $formAlert('review') ?>
        <fieldset class="rating-input"><legend>Rating</legend>
            <?php for ($rating = 5; $rating >= 1; $rating--): ?><input class="sr-only" type="radio" id="rating-<?= $rating ?>" name="rating" value="<?= $rating ?>" required<?= $formValue('review', 'rating') === (string) $rating ? ' checked' : '' ?>><label for="rating-<?= $rating ?>" aria-label="<?= $rating ?> out of 5"><?= $ui->icon('star') ?></label><?php endfor ?>
        </fieldset>
        <?= $ui->textarea('comment', 'Review', $formValue('review', 'comment'), ['maxlength' => 2000, 'rows' => 3]) ?>
        <button class="button button-primary button-block" type="submit"><?= $ui->icon('send') ?>Submit review</button>
    </form>
    <?= $ui->dialogEnd() ?>
<?php endif ?>
