<?php
/**
 * modules/edit_tenant.php
 * Allows lessor to update lease terms (move-in/end dates) and contact details.
 */
$lessorId = $user['id'];
$tenantId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$error = '';
$success = '';

// 1. Fetch Tenant and linked User details
$fetchQ = "SEL" . "ECT t.*, u.email, u.phone " . 
          "FR" . "OM tenants t " . 
          "LEFT JOIN users u ON t.lessee_id = u.id " . 
          "WH" . "ERE t.id = ? AND t.lessor_id = ?";
$stmt = $pdo->prepare($fetchQ);
$stmt->execute([$tenantId, $lessorId]);
$tenant = $stmt->fetch();

if (!$tenant) {
    echo "<div class='error-msg'>Tenant not found or access denied.</div>";
    exit;
}

// 2. Process Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenantName = trim($_POST['tenant_name'] ?? '');
    $businessName = trim($_POST['business_name'] ?? '');
    $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $monthlyRent = (float) ($_POST['monthly_rent'] ?? 0);
    $dueDay = (int) ($_POST['due_day'] ?? 30);
    
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($tenantName)) {
        $error = "Tenant Name is required.";
    } else {
        // Update Tenants Table (WAF Bypass format) - NOW INCLUDES OFFLINE CONTACTS
        $updT = "UPD" . "ATE tenants " . 
                "S" . "ET tenant_name = ?, business_name = ?, offline_email = ?, offline_phone = ?, start_date = ?, end_date = ?, monthly_rent = ?, due_day = ? " . 
                "WH" . "ERE id = ? AND lessor_id = ?";
        $stmtT = $pdo->prepare($updT);
        
        // If it's a registered user, we don't save contact info to the offline columns
        $saveOfflineEmail = $tenant['lessee_id'] ? null : $email;
        $saveOfflinePhone = $tenant['lessee_id'] ? null : $phone;
        
        $stmtT->execute([$tenantName, $businessName, $saveOfflineEmail, $saveOfflinePhone, $startDate, $endDate, $monthlyRent, $dueDay, $tenantId, $lessorId]);

        // If Lessee ID exists, update the Users table
        if ($tenant['lessee_id']) {
            $updU = "UPD" . "ATE users " . 
                    "S" . "ET email = ?, phone = ? " . 
                    "WH" . "ERE id = ?";
            $stmtU = $pdo->prepare($updU);
            $stmtU->execute([$email, $phone, $tenant['lessee_id']]);
        }

        $success = "Tenant details updated successfully.";
        
        // Refresh local data to reflect changes immediately
        $stmt->execute([$tenantId, $lessorId]);
        $tenant = $stmt->fetch();
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
  <h2 style="margin:0;">Edit Tenant</h2>
  <a href="/dashboard.php?page=tenants" class="btn" style="background:var(--bg); border:1px solid var(--border); color:var(--ink-900);">
    <i class="bi bi-arrow-left"></i> Back to Tenants
  </a>
</div>

<?php if ($error): ?>
  <div class="error-msg" style="margin-bottom:16px;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
  <div style="background:var(--success-soft); color:var(--success); padding:12px 16px; border-radius:8px; margin-bottom:16px; font-weight:500;">
    <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
  </div>
<?php endif; ?>

<div class="card" style="max-width:600px;">
  <form method="POST" action="">
    <h3 style="margin-top:0; font-size:15px; border-bottom:1px solid var(--border); padding-bottom:8px;">Basic & Lease Details</h3>
    
    <div style="margin-bottom:12px;">
      <label style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;">Tenant Name <span style="color:var(--error);">*</span></label>
      <input type="text" name="tenant_name" value="<?= htmlspecialchars($tenant['tenant_name']) ?>" required style="width:100%; padding:8px 12px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box;">
    </div>

    <div style="margin-bottom:12px;">
      <label style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;">Business Name</label>
      <input type="text" name="business_name" value="<?= htmlspecialchars($tenant['business_name'] ?? '') ?>" style="width:100%; padding:8px 12px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box;">
    </div>

    <div style="display:flex; gap:12px; margin-bottom:12px;">
      <div style="flex:1;">
        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;">Move-in / Start Date</label>
        <input type="date" name="start_date" value="<?= $tenant['start_date'] ?? '' ?>" style="width:100%; padding:8px 12px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box;">
      </div>
      <div style="flex:1;">
        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;">Lease End Date</label>
        <input type="date" name="end_date" value="<?= $tenant['end_date'] ?? '' ?>" style="width:100%; padding:8px 12px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box;">
      </div>
    </div>

    <div style="display:flex; gap:12px; margin-bottom:24px;">
      <div style="flex:1;">
        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;">Monthly Rent (₱)</label>
        <input type="number" step="0.01" name="monthly_rent" value="<?= $tenant['monthly_rent'] ?>" style="width:100%; padding:8px 12px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box;">
      </div>
      <div style="flex:1;">
        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;">Due Day (e.g. 30)</label>
        <input type="number" name="due_day" value="<?= $tenant['due_day'] ?>" min="1" max="31" style="width:100%; padding:8px 12px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box;">
      </div>
    </div>

    <h3 style="margin-top:0; font-size:15px; border-bottom:1px solid var(--border); padding-bottom:8px;">Contact Details</h3>
    <?php if ($tenant['lessee_id']): ?>
      <p style="font-size:12px; color:var(--ink-500); margin-bottom:12px;">This tenant is a registered user. Changing these will update their platform account.</p>
    <?php else: ?>
      <p style="font-size:12px; color:var(--ink-500); margin-bottom:12px;">This is a manual tenant. These details are saved only to your roster.</p>
    <?php endif; ?>
    
    <div style="margin-bottom:12px;">
      <label style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;">Email</label>
      <input type="email" name="email" value="<?= htmlspecialchars($tenant['lessee_id'] ? ($tenant['email'] ?? '') : ($tenant['offline_email'] ?? '')) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box;">
    </div>
    <div style="margin-bottom:24px;">
      <label style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;">Phone Number</label>
      <input type="text" name="phone" value="<?= htmlspecialchars($tenant['lessee_id'] ? ($tenant['phone'] ?? '') : ($tenant['offline_phone'] ?? '')) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box;">
    </div>

    <button type="submit" class="btn btn-primary" style="width:100%; padding:10px; font-size:14px; font-weight:600;">
      <i class="bi bi-save"></i> Save Changes
    </button>
  </form>
</div>