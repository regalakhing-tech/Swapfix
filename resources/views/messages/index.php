<?php
use SwapFix\Modules\Messaging\MessageRepository;

$me = $ui->session->id;
$unreadTotal = $ui->session->unreadMessages;
echo $ui->heading('message', 'Messages', $unreadTotal > 0 ? $unreadTotal . ' unread' : 'All caught up');
$contextIcons = ['listing' => 'bag', 'offer' => 'swap', 'repair' => 'wrench'];
$otherOf = static fn (array $conversation): array => $conversation['initiator_id'] === $me ? $conversation['recipient'] : $conversation['initiator'];
?>
<?php if ($conversations === []): ?>
    <?= $ui->emptyState('message', 'Conversations start from a listing, offer or repair.', $ui->link('/market', 'Browse listings', 'bag')) ?>
<?php else: ?>
    <div class="chat-layout <?= $active ? 'has-active' : '' ?>">
        <nav class="card chat-list" aria-label="Conversations">
            <?php foreach ($conversations as $conversation): $person = $otherOf($conversation); $unread = $conversation['initiator_id'] === $me ? $conversation['initiator_unread'] : $conversation['recipient_unread']; ?>
                <a class="list-row <?= ($active['id'] ?? null) === $conversation['id'] ? 'selected' : '' ?>" href="/messages/<?= $e($conversation['id']) ?>">
                    <?= $ui->avatar($person) ?>
                    <span><strong><?= $e($person['display_name']) ?></strong><small class="context-line"><?= $ui->icon($contextIcons[$conversation['context_type']]) ?><?= $e($conversation['context_title']) ?></small><small><?= $e($conversation['last_message_preview']) ?></small></span>
                    <?php if ($unread > 0): ?><b class="count"><?= (int) $unread ?></b><?php endif ?>
                </a>
            <?php endforeach ?>
        </nav>
        <?php if ($active): $person = $otherOf($active); ?>
            <section class="card chat-panel">
                <header class="chat-head">
                    <a class="icon-button chat-back" href="/messages" aria-label="Back to conversations"><?= $ui->icon('arrow-left') ?></a>
                    <?= $ui->avatar($person) ?>
                    <div><strong><?= $e($person['display_name']) ?></strong><a class="text-link context-line" href="<?= $e(MessageRepository::contextUrl($active)) ?>"><?= $ui->icon($contextIcons[$active['context_type']]) ?><?= $e($active['context_title']) ?></a></div>
                </header>
                <?php $conversationId = $active['id']; $threadBack = ''; require __DIR__ . '/../partials/thread.php' ?>
            </section>
        <?php else: ?>
            <section class="card chat-panel chat-placeholder"><?= $ui->icon('message') ?><p>Select a conversation.</p></section>
        <?php endif ?>
    </div>
<?php endif ?>
