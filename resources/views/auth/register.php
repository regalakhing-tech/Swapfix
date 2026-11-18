<?php $ui->title = 'Create account'; ?>
<h1>Create your account</h1>
<p class="auth-lead">Choose how you’ll use SwapFix and enter your details.</p>
<?= $ui->alert($error ?? null) ?>
<form method="post" action="/register" class="stack-form">
    <?= $ui->csrf() ?>
    <?php require __DIR__ . '/../partials/account-type.php' ?>
    <div class="auth-register-fields">
        <?= $ui->input('display_name', 'Name', $old['display_name'] ?? '', ['required' => true, 'autocomplete' => 'name', 'minlength' => 2, 'maxlength' => 80, 'placeholder' => 'Your name']) ?>
        <?= $ui->input('location', 'Location', $old['location'] ?? '', ['autocomplete' => 'address-level2', 'maxlength' => 120, 'placeholder' => 'City']) ?>
        <?= $ui->input('email', 'Email', $old['email'] ?? '', ['type' => 'email', 'required' => true, 'autocomplete' => 'email', 'maxlength' => 254, 'placeholder' => 'you@example.com']) ?>
        <?php $placeholder = 'At least 8 characters'; $autocomplete = 'new-password'; require __DIR__ . '/../partials/password.php' ?>
    </div>
    <div class="auth-register-actions">
        <button class="button button-primary" type="submit">Create account <?= $ui->icon('arrow') ?></button>
        <p class="auth-switch">Have an account? <a href="/login">Sign in</a></p>
    </div>
</form>
