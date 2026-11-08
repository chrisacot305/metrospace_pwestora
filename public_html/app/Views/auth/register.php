<?php /* app/Views/auth/register.php — page HTML (was the bottom half of register.php) */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Lessor Registration · <?= APP_NAME ?></title>
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
      <h1>List your commercial space with confidence.</h1>
      <p>Every lessor and property is document-verified before going live,
         so tenants trust what they see, and you get serious applicants.</p>
      <div class="badges">
        <span>1–2 Business Day Review</span>
        <span>Secure Document Upload</span>
      </div>
    </div>
    <div></div>
  </div>

  <div class="auth-form-side">
    <div class="auth-card">
      <div class="card-brand"><img src="/assets/img/logo-web.png?v=2" alt="Pwestora"></div>

      <h2 class="auth-title">Create your lessor account</h2>
      <p class="auth-sub">Register your business as our team verifies your documents before you can list properties.</p>

      <?php if ($error): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="field">
          <label>Company / Business Name</label>
          <input type="text" name="company_name" required value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Full Name</label>
          <input type="text" name="full_name" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Email</label>
          <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Phone</label>
          <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Password (min. 8 characters)</label>
          <input type="password" name="password" required minlength="8">
        </div>

        <hr style="border:none; border-top:1px solid var(--border); margin:20px 0;">
        <p style="font-size:13px; font-weight:700; margin-bottom:4px;">Verification documents</p>
        <p style="font-size:12px; color:var(--ink-500); margin-bottom:14px;">
          PDF, JPG, or PNG · max 5MB each. Our team reviews these before your account is activated.
        </p>

        <?php foreach ($REQUIRED_DOCS as $field => [$column, $label]): ?>
          <div class="field">
            <label><?= htmlspecialchars($label) ?></label>
            <input type="file" name="<?= $field ?>" accept=".pdf,.jpg,.jpeg,.png" required>
          </div>
        <?php endforeach; ?>

        <button class="btn btn-primary" type="submit">Create Account</button>
      </form>

      <div style="margin-top:16px; text-align:center; font-size:13px; color: var(--ink-500);">
        Already have an account? <a class="btn-link" href="/login.php">Log in</a>
      </div>
    </div>
  </div>

</div>
<script src="/assets/js/pwa.js"></script>
</body>
</html>