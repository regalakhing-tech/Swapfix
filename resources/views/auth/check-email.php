<?php $ui->title = 'Verify your email'; ?>
<span class="icon-tile"><?= $ui->icon('message') ?></span>
<h1>Verify your email</h1>
<p class="auth-lead">Enter the code from the SwapFix email sent to <strong><?= $e($email) ?></strong>.</p>
<?= $ui->alert($error ?? null) ?>
<form method="post" action="/verify-email" class="stack-form auth-code-form">
    <?= $ui->csrf() ?>
    <?= $ui->input('code', 'Verification code', '', [
        'type' => 'text', 'inputmode' => 'numeric', 'autocomplete' => 'one-time-code',
        'pattern' => '[0-9]{6}|[0-9]{8}', 'maxlength' => 8, 'required' => true,
    ]) ?>
    <button class="button button-primary button-block" type="submit">Verify email <?= $ui->icon('arrow') ?></button>
</form>
<div class="auth-code-actions">
    <form method="post" action="/verify-email/resend">
        <?= $ui->csrf() ?>
        <button class="button button-outline" type="submit">Resend email</button>
    </form>
    <a href="/register">Use a different email</a>
</div>
<p class="auth-code-help">Check spam if the email hasn’t arrived. If you received a confirmation link, you can open it and then <a href="/login">sign in</a>.</p>
