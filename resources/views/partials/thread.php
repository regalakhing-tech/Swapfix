<?php
/** @var array $messages @var string $conversationId @var string $threadBack */
$me = $ui->session->id;
?>
<div class="thread" aria-label="Messages">
    <?php if ($messages === []): ?><p class="muted-line">No messages yet.</p><?php endif ?>
    <?php foreach ($messages as $message): $mine = $message['sender_id'] === $me; ?>
        <div class="bubble <?= $mine ? 'bubble-mine' : '' ?>"><p><?= nl2br($e($message['body'])) ?></p><time datetime="<?= $e($message['created_at']) ?>"><?= $ui->dateTime($message['created_at']) ?></time></div>
    <?php endforeach ?>
</div>
<form method="post" action="/messages/<?= $e($conversationId) ?>" class="composer">
    <?= $ui->csrf() ?>
    <?php if ($threadBack !== ''): ?><input type="hidden" name="back" value="<?= $e($threadBack) ?>"><?php endif ?>
    <label class="sr-only" for="message-body">Message</label>
    <textarea id="message-body" name="body" rows="2" maxlength="4000" required placeholder="Write a message"></textarea>
    <button class="button button-primary" type="submit" aria-label="Send message"><?= $ui->icon('send') ?><span>Send</span></button>
</form>
