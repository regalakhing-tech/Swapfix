<?php
$unread = $ui->session->unreadNotifications;
$icons = ['message' => 'message', 'repair_request' => 'wrench', 'repair_status' => 'wrench', 'review' => 'star'];
$iconFor = static fn (string $kind): string => $icons[$kind] ?? (str_starts_with($kind, 'trade') ? 'swap' : 'cash');
echo $ui->heading('bell', 'Notifications', $unread . ' unread', $unread > 0 ? $ui->postButton('/notifications/read-all', 'Mark all read', 'check') : '');
?>
<?php if ($notifications === []): ?>
    <?= $ui->emptyState('bell', 'Nothing new yet.') ?>
<?php else: ?>
    <div class="card row-list">
        <?php foreach ($notifications as $item): $isUnread = $item['read_at'] === null; ?>
            <form method="post" action="/notifications/<?= $e($item['id']) ?>/open" class="notification-form">
                <?= $ui->csrf() ?>
                <button type="submit" class="notification-row<?= $isUnread ? ' is-unread' : '' ?>">
                    <span class="icon-tile small"><?= $ui->icon($iconFor($item['kind'])) ?></span>
                    <span class="notification-copy">
                        <strong><?= $e($item['title']) ?></strong>
                        <?php if ($item['body'] !== ''): ?><span><?= $e($item['body']) ?></span><?php endif ?>
                        <time datetime="<?= $e($item['created_at']) ?>"><?= $ui->dateTime($item['created_at']) ?></time>
                    </span>
                    <?php if ($isUnread): ?><span class="sr-only">Unread</span><?php endif ?>
                    <span class="notification-end" aria-hidden="true">
                        <?php if ($isUnread): ?><span class="unread-dot"></span><?php endif ?>
                        <?= $ui->icon('chevron') ?>
                    </span>
                </button>
            </form>
        <?php endforeach ?>
    </div>
<?php endif ?>
<?php if ($pageNumber > 1 || $hasOlder): ?>
    <nav class="pagination" aria-label="Notification pages">
        <?php if ($pageNumber > 1): ?><?= $ui->link($ui->url(['page' => $pageNumber > 2 ? $pageNumber - 1 : null]), 'Newer', 'arrow-left', 'button button-outline') ?><?php endif ?>
        <?php if ($hasOlder): ?><?= $ui->link($ui->url(['page' => $pageNumber + 1]), 'Older', 'arrow', 'button button-outline') ?><?php endif ?>
    </nav>
<?php endif ?>
