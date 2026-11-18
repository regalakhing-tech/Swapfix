<?php $ui->title = 'Sign in'; ?>
<h1>Sign in to SwapFix</h1>
<p class="auth-lead">Enter your email and password to continue.</p>
<div data-email-confirmed <?= $confirmed ? '' : 'hidden' ?>><?= $ui->alert('Email confirmed. Sign in to continue.', 'info') ?></div>
<?= $ui->alert($error ?? null) ?>
<form method="post" action="/login" class="stack-form">
    <?= $ui->csrf() ?>
    <input type="hidden" name="next" value="<?= $e($next) ?>">
    <?= $ui->input('email', 'Email', $email, ['type' => 'email', 'required' => true, 'autocomplete' => 'email', 'placeholder' => 'you@example.com', 'maxlength' => 254]) ?>
    <?php $placeholder = 'Enter your password'; $autocomplete = 'current-password'; require __DIR__ . '/../partials/password.php' ?>
    <button class="button button-primary button-block" type="submit">Sign in <?= $ui->icon('arrow') ?></button>
</form>
<p class="auth-switch">No account? <a href="/register">Create one</a></p>
