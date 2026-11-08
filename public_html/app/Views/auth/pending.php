<?php /* app/Views/auth/pending.php — page HTML (was the bottom half of pending.php) */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= $isSuspended ? 'Account Suspended' : 'Pending Verification' ?> · <?= APP_NAME ?></title>
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
      <?php if ($isSuspended): ?>
        <h1>Access temporarily suspended.</h1>
        <p>Your account has been suspended by a Pwestora administrator. This is
           usually related to a platform policy issue, reach out to our team to resolve it.</p>
      <?php else: ?>
        <h1>You're almost in.</h1>
        <p>Our team double-checks every business document by hand, it keeps
           the whole Pwestora marketplace trustworthy for tenants and lessors alike.</p>
      <?php endif; ?>
    </div>
    <div></div>
  </div>

  <div class="auth-form-side">
    <div class="auth-card" style="text-align:center;">
      <div class="card-brand" style="justify-content:center;"><img src="/assets/img/logo-web.png?v=2" alt="Pwestora"></div>
      <?php if ($isSuspended): ?>
        <div style="font-size:40px; margin-bottom:8px;">🚫</div>
        <h2 class="auth-title">Account Suspended</h2>
        <p class="auth-sub">
          Hi <?= htmlspecialchars($user['name']) ?>, your account access has been suspended.
          If you believe this is a mistake, please contact Pwestora support at
          <?= htmlspecialchars($user['email']) ?> for assistance.
        </p>
      <?php else: ?>
        <div style="font-size:40px; margin-bottom:8px;">⏳</div>
        <h2 class="auth-title">Account Under Review</h2>
        <p class="auth-sub">
          Thanks for registering, <?= htmlspecialchars($user['name']) ?>. Our team is
          verifying your business documents. This usually takes 1–2 business days,
          we'll email you at <?= htmlspecialchars($user['email']) ?> once approved.
        </p>
      <?php endif; ?>
      <a href="/logout.php" class="btn btn-primary" style="display:inline-flex;">Log out</a>
    </div>
  </div>

</div>
<script src="/assets/js/pwa.js"></script>
</body>
</html>