<?php /* app/Views/auth/forgot_password.php — "Forgot password?" request page */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<title>Forgot password · <?= APP_NAME ?></title>
<link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(APP_ROOT . '/assets/css/style.css') ?>">
<link rel="icon" type="image/png" href="/assets/img/icon.png">
<meta name="theme-color" content="#102340">
</head>
<body>
<div class="auth-shell">

  <div class="auth-visual">
    <div class="brand-mark"><div class="brand-badge"><img src="/assets/img/logo-web.png?v=2" alt="Pwestora" style="height:22px;"></div></div>
    <div class="tagline">
      <h1>Locked out?<br>Let's get you back in.</h1>
      <p>Enter the email on your Pwestora account and we'll send you a secure, one-time link to set a new password.</p>
    </div>
    <div></div>
  </div>

  <div class="auth-form-side">
    <div class="auth-card">
      <div class="card-brand"><img src="/assets/img/logo-web.png?v=2" alt="Pwestora"></div>

      <?php if ($sent): ?>
        <h2 class="auth-title">Check your email</h2>
        <div class="success-msg">
          If an account exists for <strong><?= e($email) ?></strong>, we've sent a link to reset your password.
          It expires in 30 minutes and can be used once.
        </div>
        <p class="auth-sub">Didn't get it? Check your spam folder, or wait a minute and try again.</p>
        <a class="btn btn-primary" href="/forgot_password.php" style="display:block; text-align:center; text-decoration:none;">Send another link</a>
      <?php else: ?>
        <h2 class="auth-title">Forgot your password?</h2>
        <p class="auth-sub">We'll email you a link to reset it.</p>

        <?php if ($error): ?>
          <div class="error-msg"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/forgot_password.php" autocomplete="on">
          <?= csrf_field() ?>
          <div class="field">
            <label for="fpEmail">Email</label>
            <input id="fpEmail" type="email" name="email" required autofocus maxlength="150"
                   autocomplete="email" placeholder="you@company.com" value="<?= e($email) ?>">
          </div>
          <button class="btn btn-primary" type="submit">Send reset link</button>
        </form>
      <?php endif; ?>

      <div style="margin-top:18px; text-align:center; font-size:13px; color: var(--ink-500);">
        <a class="btn-link" href="/login.php">Back to login</a>
      </div>
    </div>
  </div>

</div>
</body>
</html>
