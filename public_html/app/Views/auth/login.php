<?php /* app/Views/auth/login.php — page HTML (was the bottom half of login.php) */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Log in · <?= APP_NAME ?></title>
<link rel="stylesheet" href="/assets/css/style.css?v=12">
<link rel="icon" type="image/png" href="/assets/img/icon.png">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png?v=2">
<link rel="manifest" href="/manifest.json">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Pwestora">
<meta name="theme-color" content="#102340">
</head>
<body>
<div class="auth-shell">

  <div class="auth-visual">
    <div class="brand-mark"><div class="brand-badge"><img src="/assets/img/logo-web.png?v=2" alt="Pwestora" style="height:22px;"></div></div>
    <div class="tagline">
      <h1>Commercial leasing,<br>run from one place.</h1>
      <p>Manage properties, tenants, installments, and compliance across every branch —
         built for landlords and property administrators who lease commercial space.</p>
      <div class="badges">
        <span>Verified Lessors</span>
        <span>Document-checked Listings</span>
        <span>Multi-branch Ready</span>
      </div>
    </div>
    <div></div>
  </div>

  <div class="auth-form-side">
    <div class="auth-card">
      <div class="card-brand"><img src="/assets/img/logo-web.png?v=2" alt="Pwestora"></div>

      <h2 class="auth-title">Welcome back</h2>
      <p class="auth-sub">Log in as Admin (Lessor) or Super Admin.</p>

      <?php if ($error): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="field">
          <label>Email</label>
          <input type="email" name="email" required autofocus placeholder="you@company.com">
        </div>
        <div class="field">
          <label>Password</label>
          <input type="password" name="password" required placeholder="••••••••">
        </div>
        <button class="btn btn-primary" type="submit">Log in</button>
      </form>

      <div style="margin-top:18px; text-align:center; font-size:13px; color: var(--ink-500);">
        New lessor? <a class="btn-link" href="/register.php">Create an account</a>
      </div>
    </div>
  </div>

</div>
<script src="/assets/js/pwa.js"></script>
</body>
</html>