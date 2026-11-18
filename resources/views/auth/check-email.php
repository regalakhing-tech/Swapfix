<?php $ui->title = 'Check your email'; ?>
<span class="icon-tile"><?= $ui->icon('message') ?></span>
<h1>Check your email</h1>
<p class="auth-lead">If <?= $e($email) ?> can be registered, a confirmation link is on its way. Open it, then sign in.</p>
<a class="button button-primary button-block" href="/login">Go to sign in</a>
