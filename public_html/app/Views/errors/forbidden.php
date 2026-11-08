<?php /* app/Views/errors/forbidden.php — "no access" page. Expects: $message */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>No access · <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="/assets/css/style.css?v=12">
<link rel="icon" type="image/png" href="/assets/img/icon.png">
<meta name="theme-color" content="#102340">
</head>
<body>
<div class="auth-shell">
  <div class="auth-visual">
    <div class="brand-mark"><div class="brand-badge"><img src="/assets/img/logo-web.png?v=2" alt="Pwestora" style="height:22px;"></div></div>
    <div class="tagline">
      <h1>This page isn't available for your account.</h1>
    </div>
    <div></div>
  </div>
  <div class="auth-form-side">
    <div class="auth-card" style="text-align:center;">
      <div class="card-brand" style="justify-content:center;"><img src="/assets/img/logo-web.png?v=2" alt="Pwestora"></div>
      <div style="font-size:40px; margin-bottom:8px;">🔒</div>
      <h2 class="auth-title">No access</h2>
      <p class="auth-sub"><?= e($message ?? 'You do not have access to this page.') ?></p>
      <a href="/logout.php" class="btn btn-primary" style="display:inline-flex;">Log out</a>
    </div>
  </div>
</div>
</body>
</html>
