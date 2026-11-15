<?php /* app/Views/auth/verify_otp.php — page HTML (was the bottom half of verify_otp.php) */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Verify Code · <?= APP_NAME ?></title>
<link rel="stylesheet" href="/assets/css/style.css?v=12">
<link rel="icon" type="image/png" href="/assets/img/icon.png">
<meta name="theme-color" content="#102340">
</head>
<body>
<div class="auth-shell">

  <div class="auth-visual">
    <div class="brand-mark"><div class="brand-badge"><img src="/assets/img/logo-web.png?v=2" alt="Pwestora" style="height:22px;"></div></div>
    <div class="tagline">
      <h1>One more step.</h1>
      <p>We emailed a 6-digit code to keep your account secure. Enter it to finish logging in.</p>
    </div>
    <div></div>
  </div>

  <div class="auth-form-side">
    <div class="auth-card">
      <div class="card-brand"><img src="/assets/img/logo-web.png?v=2" alt="Pwestora"></div>

      <h2 class="auth-title">Enter your code</h2>
      <p class="auth-sub">Sent to <?= htmlspecialchars($user['email']) ?>. It expires in 10 minutes.</p>

      <?php if ($error): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($resent): ?>
        <div class="success-msg">A new code has been sent.</div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="field">
          <label>6-digit code</label>
          <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                 required autofocus placeholder="000000"
                 style="letter-spacing:6px; font-size:20px; text-align:center; font-weight:700;">
        </div>
        <button class="btn btn-primary" type="submit">Verify &amp; Log In</button>
      </form>

      <form method="POST" style="margin-top:14px;">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="resend" value="1">
        <button type="submit" class="btn-link" style="background:none; border:none; width:100%; text-align:center; font-size:13px; cursor:pointer;">
          Didn't get it? Resend code
        </button>
      </form>

      <div style="margin-top:14px; text-align:center; font-size:13px; color: var(--ink-500);">
        <a class="btn-link" href="/login.php">Back to login</a>
      </div>
    </div>
  </div>

</div>
</body>
</html>