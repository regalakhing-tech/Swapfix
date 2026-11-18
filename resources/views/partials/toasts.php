<div class="toast-stack" id="toast-stack" aria-live="polite" aria-relevant="additions">
    <?php foreach ($ui->flashes() as [$kind, $message]): $isError = $kind === 'error'; ?>
        <div class="toast <?= $isError ? 'toast-error' : '' ?>" role="<?= $isError ? 'alert' : 'status' ?>" data-toast="<?= $isError ? 6000 : 2000 ?>"><span><?= $e($message) ?></span><button type="button" data-toast-close aria-label="Dismiss notification">Dismiss</button></div>
    <?php endforeach ?>
</div>
