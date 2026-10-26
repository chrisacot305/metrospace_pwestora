<?php
/**
 * views/superadmin/settings.php
 * Presentation layer for Platform Settings.
 */
?>

<!-- HEADER -->
<div class="rev-header-titles mb-24">
  <h2 class="page-title">Platform Settings</h2>
</div>

<?php if ($error): ?>
  <div class="error-msg"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($successMsg): ?>
  <div id="temp-success-msg" class="success-msg">
    <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($successMsg) ?>
  </div>
  <script>
    // Clean up the URL so refreshing doesn't resubmit the form
    if (window.history.replaceState) {
        window.history.replaceState(null, null, "/dashboard.php?page=settings");
    }
    
    // Smoothly fade out and remove the message after 3.5 seconds
    setTimeout(function() {
        var msgDiv = document.getElementById('temp-success-msg');
        if (msgDiv) {
            msgDiv.style.transition = 'opacity 0.5s ease';
            msgDiv.style.opacity = '0';
            setTimeout(function() { msgDiv.style.display = 'none'; }, 500);
        }
    }, 3500);
  </script>
<?php endif; ?>

<form method="POST">
  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

  <!-- TOGGLES CARD -->
  <div class="card card-md">
    <?php foreach ($TOGGLES as $key => [$label, $note]): $on = ($rows[$key] ?? '0') === '1'; ?>
      <div class="settings-row">
        <div>
          <p class="settings-label-text"><?= htmlspecialchars($label) ?></p>
          <p class="settings-note"><?= htmlspecialchars($note) ?></p>
        </div>
        <label class="toggle-switch">
          <input type="checkbox" name="<?= $key ?>" <?= $on ? 'checked' : '' ?>>
          <span class="toggle-slider"></span>
        </label>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- COMMISSION CARD -->
  <div class="card card-md mt-16">
    <h3 class="section-heading">Platform commission rate</h3>
    <p class="page-subtitle mb-12">Applied to gross monthly rent across all leases — shown in Revenue &amp; Commission.</p>
    
    <div class="commission-input-group">
      <input type="number" name="platform_fee_percent" min="0" max="100" step="0.1"
             class="commission-input"
             value="<?= htmlspecialchars($rows['platform_fee_percent'] ?? '5') ?>">
      <span class="commission-symbol">%</span>
    </div>
  </div>

  <!-- ACTIONS -->
  <div class="mt-16">
    <button type="submit" class="btn btn-accent">
      <i class="bi bi-save"></i> Save Changes
    </button>
  </div>
</form>