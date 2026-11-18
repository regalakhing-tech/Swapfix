<?php
$unread = count(array_filter($notifications, static fn (array $item): bool => $item['read_at'] === null));
$icons = ['message' => 'message', 'repair_request' => 'wrench', 'repair_status' => 'wrench', 'review' => 'star'];
$iconFor = static fn (string $kind): string => $icons[$kind] ?? (str_starts_with($kind, 'trade') ? 'swap' : 'cash');
echo $ui->heading('bell', 'Notifications', $unread . ' unread', $unread > 0 ? $ui->postButton('/notifications/read-all', 'Mark all read', 'check') : '');
?>
<?php if ($notifications === []): ?>
    <?= $ui->emptyState('bell', 'Nothing new yet.') ?>
<?php else: ?>
    <div class="card row-list">
        <?php foreach ($notifications as $item): ?>
            <form method="post" action="/notifications/<?= $e($item['id']) ?>/open" class="notification-form">
                <?= $ui->csrf() ?>
                <button type="submit" class="list-row <?= $item['read_at'] === null ? 'unread' : '' ?>">
                    <span class="icon-tile small"><?= $ui->icon($iconFor($item['kind'])) ?></span>
                    <span><strong><?= $e($item['title']) ?></strong><?php if ($item['body'] !== ''): ?><small><?= $e($item['body']) ?></small><?php endif ?><small><?= $ui->dateTime($item['created_at']) ?></small></span>
                    <?php if ($item['read_at'] === null): ?><span class="unread-dot" aria-label="Unread"></span><?php endif ?>
                </button>
            </form>
        <?php endforeach ?>
    </div>
<?php endif ?>
