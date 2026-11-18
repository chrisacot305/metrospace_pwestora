<?php /* app/Views/auth/reset_password.php — choose a new password (or "link expired") */
$invalid = $invalid ?? false;
$error   = $error ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<title>Reset password · <?= APP_NAME ?></title>
<link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(APP_ROOT . '/assets/css/style.css') ?>">
<link rel="icon" type="image/png" href="/assets/img/icon.png">
<meta name="theme-color" content="#102340">
<style>
  .pw-wrap { position: relative; }
  .pw-wrap input { padding-right: 64px; }
  .pw-toggle { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: 0;
               font-size: 12px; font-weight: 600; color: var(--ink-500); cursor: pointer; padding: 6px; }
  .pw-rules { list-style: none; padding: 0; margin: -4px 0 16px; font-size: 12px; color: var(--ink-500); }
  .pw-rules li { margin: 3px 0; }
  .pw-rules li::before { content: "○ "; }
  .pw-rules li.ok { color: var(--success); }
  .pw-rules li.ok::before { content: "✓ "; }
</style>
</head>
<body>
<div class="auth-shell">

  <div class="auth-visual">
    <div class="brand-mark"><div class="brand-badge"><img src="/assets/img/logo-web.png?v=2" alt="Pwestora" style="height:22px;"></div></div>
    <div class="tagline">
      <h1>Set a new password.</h1>
      <p>Choose something you don't use anywhere else. Once it's saved, you'll be signed out of other devices and the mobile app.</p>
    </div>
    <div></div>
  </div>

  <div class="auth-form-side">
    <div class="auth-card">
      <div class="card-brand"><img src="/assets/img/logo-web.png?v=2" alt="Pwestora"></div>

      <?php if ($invalid): ?>
        <h2 class="auth-title">Link expired or invalid</h2>
        <div class="error-msg"><?= e($error !== '' ? $error : 'This reset link is invalid, has already been used, or has expired. Please request a new one.') ?></div>
        <a class="btn btn-primary" href="/forgot_password.php" style="display:block; text-align:center; text-decoration:none;">Request a new link</a>
      <?php else: ?>
        <h2 class="auth-title">Choose a new password</h2>
        <p class="auth-sub">For <?= e($email) ?></p>

        <?php if ($error): ?>
          <div class="error-msg"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/reset_password.php" id="resetForm">
          <?= csrf_field() ?>
          <!-- Helps password managers save the new password against the right account -->
          <input type="email" name="username" value="<?= e($email) ?>" autocomplete="username" hidden readonly>
          <div class="field">
            <label for="pw1">New password</label>
            <div class="pw-wrap">
              <input id="pw1" type="password" name="password" required minlength="8" maxlength="72"
                     autocomplete="new-password" autofocus>
              <button type="button" class="pw-toggle" data-target="pw1">Show</button>
            </div>
          </div>
          <ul class="pw-rules" id="pwRules">
            <li data-rule="len">At least 8 characters</li>
            <li data-rule="mix">At least one letter and one number</li>
            <li data-rule="match">Both passwords match</li>
          </ul>
          <div class="field">
            <label for="pw2">Confirm new password</label>
            <div class="pw-wrap">
              <input id="pw2" type="password" name="password_confirm" required minlength="8" maxlength="72"
                     autocomplete="new-password">
              <button type="button" class="pw-toggle" data-target="pw2">Show</button>
            </div>
          </div>
          <button class="btn btn-primary" type="submit" id="resetBtn">Save new password</button>
        </form>
      <?php endif; ?>

      <div style="margin-top:18px; text-align:center; font-size:13px; color: var(--ink-500);">
        <a class="btn-link" href="/login.php">Back to login</a>
      </div>
    </div>
  </div>

</div>
<?php if (!$invalid): ?>
<script>
(function () {
  var p1 = document.getElementById('pw1'), p2 = document.getElementById('pw2');
  function rule(name, ok) { document.querySelector('#pwRules [data-rule="' + name + '"]').classList.toggle('ok', ok); }
  function check() {
    var v = p1.value;
    rule('len', v.length >= 8 && v.length <= 72);
    rule('mix', /[A-Za-z]/.test(v) && /\d/.test(v));
    rule('match', v.length > 0 && v === p2.value);
    p2.setCustomValidity(p2.value && v !== p2.value ? 'Passwords do not match' : '');
  }
  p1.addEventListener('input', check);
  p2.addEventListener('input', check);
  document.querySelectorAll('.pw-toggle').forEach(function (b) {
    b.addEventListener('click', function () {
      var i = document.getElementById(b.dataset.target), show = i.type === 'password';
      i.type = show ? 'text' : 'password';
      b.textContent = show ? 'Hide' : 'Show';
    });
  });
  document.getElementById('resetForm').addEventListener('submit', function () {
    var btn = document.getElementById('resetBtn');
    setTimeout(function () { btn.disabled = true; btn.textContent = 'Saving…'; }, 0);
  });
})();
</script>
<?php endif; ?>
</body>
</html>
