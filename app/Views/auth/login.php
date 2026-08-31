<?php

use App\Core\Csrf;
?>
<section class="login-card">
    <div class="login-brand"><span class="brand-mark">V</span><span><strong>Veelox</strong><small>Digital</small></span></div>
    <div class="eyebrow">Agency management</div>
    <h1>Welcome back.</h1>
    <p>Sign in to manage customers, orders, billing and support.</p>

    <?php if (!empty($error)): ?>
        <div class="alert error"><?= htmlspecialchars((string) $error) ?></div>
    <?php endif; ?>

    <form action="/login" method="post" class="form-stack">
        <input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
        <label>Email address<input type="email" name="email" autocomplete="email" required autofocus></label>
        <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
        <button class="primary-button" type="submit">Sign in</button>
    </form>
</section>
