<?php
use SwapFix\Modules\Exchange\OfferActions;
use SwapFix\Modules\Messaging\MessageRepository;

$me = $ui->session->id;
echo $ui->heading('activity', 'My Activity', $tabs[$tab][0]);
$empties = [
    'listings' => ['You have no listings yet.', $ui->link('/market/new', 'New listing', 'plus')],
    'exchanges' => ['You have no exchange listings yet.', $ui->link('/exchange/new', 'New exchange', 'plus')],
    'offers' => ['No trade offers yet.', $ui->link('/exchange', 'Browse exchanges', 'swap')],
    'repairs' => ['No repairs yet.', $ui->link('/repair', 'Find a technician', 'wrench')],
    'conversations' => ['No conversations yet.', ''],
    'reviews' => ['You have not reviewed a repair yet.', ''],
];
$thumb = static function (array $item, string $glyph) use ($ui, $e): string {
    $photo = $item['photo_paths'][0] ?? null;
    return $photo ? '<img class="row-thumb" src="' . $e($ui->photoUrl('listing-images', $photo)) . '" width="96" height="96" alt="" loading="lazy">' : '<span class="icon-tile small">' . $ui->icon($glyph) . '</span>';
};
?>
<nav class="tabs" aria-label="Activity sections">
    <?php foreach ($tabs as $key => [$label, $glyph]): ?><a class="<?= $tab === $key ? 'active' : '' ?>" <?= $tab === $key ? 'aria-current="page"' : '' ?> href="/activity?tab=<?= $e($key) ?>"><?= $ui->icon($glyph) ?><?= $e($label) ?></a><?php endforeach ?>
</nav>
<?php if ($items === []): ?>
    <?= $ui->emptyState($tabs[$tab][1], $empties[$tab][0], $empties[$tab][1]) ?>
<?php else: ?>
    <div class="card row-list">
        <?php foreach ($items as $item): ?>
            <?php if ($tab === 'listings'): ?>
                <a class="list-row" href="/market/<?= $e($item['id']) ?>"><?= $thumb($item, 'bag') ?><span><strong><?= $e($item['title']) ?></strong><small><?= $ui->money($item['price']) ?> &middot; <?= $e($item['location']) ?></small></span><?= $ui->status($item['status']) ?></a>
            <?php elseif ($tab === 'exchanges'): ?>
                <a class="list-row" href="/exchange/<?= $e($item['id']) ?>"><?= $thumb($item, 'swap') ?><span><strong><?= $e($item['title']) ?></strong><small>Wants <?= $e($item['wanted_item']) ?></small></span><?= $ui->status($item['status']) ?></a>
            <?php elseif ($tab === 'offers'):
                $sent = $item['offerer_id'] === $me;
                $actions = OfferActions::for($item, $item['exchange'] ?? ['status' => null], $me);
                $status = $actions['onHold'] ? 'on_hold' : $item['status'];
                ?>
                <a class="list-row" href="/offers/<?= $e($item['id']) ?>"><span class="icon-tile small"><?= $ui->icon('cash') ?></span><span><strong><?= $e($item['exchange']['title'] ?? 'Exchange') ?></strong><small><?= $sent ? 'Sent' : 'Received' ?> &middot; Version <?= (int) $item['current_version'] ?> &middot; <?= $ui->date($item['updated_at']) ?></small></span><?= $actions['respond'] ? '<span class="status status-amber">Your turn</span>' : $ui->status($status) ?></a>
            <?php elseif ($tab === 'repairs'): $showCustomer = $item['technician_id'] === $me; $job = $item; require __DIR__ . '/../repair/job-row.php'; ?>
            <?php elseif ($tab === 'conversations'): $person = $item['initiator_id'] === $me ? $item['recipient'] : $item['initiator']; ?>
                <a class="list-row" href="/messages/<?= $e($item['id']) ?>"><?= $ui->avatar($person) ?><span><strong><?= $e($item['context_title']) ?></strong><small><?= $e($person['display_name']) ?> &middot; <?= $e($item['last_message_preview']) ?></small></span></a>
            <?php else: ?>
                <a class="list-row" href="/repairs/<?= $e($item['repair_id']) ?>"><span class="icon-tile small"><?= $ui->icon('star') ?></span><span><strong><?= $e($item['technician']['profile']['display_name'] ?? 'Technician') ?></strong><small><?= $e($item['comment'] ?: 'No written review') ?></small></span><?= $ui->stars($item['rating']) ?></a>
            <?php endif ?>
        <?php endforeach ?>
    </div>
<?php endif ?>
<?php if ($pageNumber > 1 || $hasOlder): ?>
    <nav class="pagination" aria-label="Activity pages">
        <?php if ($pageNumber > 1): ?><?= $ui->link($ui->url(['page' => $pageNumber > 2 ? $pageNumber - 1 : null]), 'Newer', 'arrow-left', 'button button-outline') ?><?php endif ?>
        <?php if ($hasOlder): ?><?= $ui->link($ui->url(['page' => $pageNumber + 1]), 'Older', 'arrow', 'button button-outline') ?><?php endif ?>
    </nav>
<?php endif ?>
