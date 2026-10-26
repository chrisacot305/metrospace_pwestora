<?php
/**
 * modules/payments.php  (Lessor → "Rent Payments")
 * Comprehensive payment ledger & verification queue.
 * Lessors can verify online tenant transfers and manually log offline payments.
 */
$lessorId = $user['id'];
$error = '';
$msg = $_GET['msg'] ?? '';

// Track if the log payment popup drawer should be open
$loggingPayment = isset($_GET['log']);

$METHODS = [
    'cash'          => 'Cash',
    'bank_transfer' => 'Bank Transfer / InstaPay',
    'gcash'         => 'GCash',
    'other'         => 'Other',
];

// Helper to parse payment targets & reference numbers from notes
function parse_payment_note(?string $note): array {
    $res = [
        'target' => 'Monthly Rent',
        'ref'    => '—',
        'raw'    => $note ?? '',
        'status' => 'recorded',
    ];
    if (!$note) return $res;

    if (str_starts_with($note, '[PENDING]')) {
        $res['status'] = 'pending';
    } elseif (str_starts_with($note, '[VERIFIED]')) {
        $res['status'] = 'verified';
    } elseif (str_starts_with($note, '[DECLINED')) {
        $res['status'] = 'declined';
    }

    if (preg_match('/Target:\s*([^|]+)/i', $note, $m)) {
        $res['target'] = trim($m[1]);
    }
    if (preg_match('/Ref:\s*([^|]+)/i', $note, $m)) {
        $res['ref'] = trim($m[1]);
    }

    return $res;
}

// 1. APPROVE / CONFIRM PAYMENT ACTION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve_payment') {
    csrf_check();
    $paymentId = (int) ($_POST['payment_id'] ?? 0);

    try {
        $check = $pdo->prepare(
            'SELECT pay.*, t.tenant_name, t.lessee_id, t.application_id, a.rent AS monthly_rent,
                    u.phone AS lessee_phone, u.full_name AS lessee_full_name
             FROM payments pay
             JOIN tenants t ON t.id = pay.tenant_id
             LEFT JOIN applications a ON a.id = t.application_id
             LEFT JOIN users u ON u.id = t.lessee_id
             WHERE pay.id = ? AND pay.lessor_id = ?'
        );
        $check->execute([$paymentId, $lessorId]);
        $pRow = $check->fetch();

        if ($pRow) {
            $currentNote = $pRow['note'] ?? '';
            $newNote = str_contains($currentNote, '[PENDING]')
                ? str_replace('[PENDING]', '[VERIFIED]', $currentNote)
                : ('[VERIFIED] ' . $currentNote);

            $update = $pdo->prepare('UPDATE payments SET logged_by = ?, note = ? WHERE id = ?');
            $update->execute([$lessorId, $newNote, $paymentId]);

            // If this payment was a Security Deposit Top-Up, credit the tenant's deposit buffer
            if (stripos($currentNote, 'Security Deposit Top-Up') !== false || stripos($currentNote, 'security_deposit') !== false) {
                $pdo->prepare('UPDATE tenants SET security_deposit = COALESCE(security_deposit, 0) + ? WHERE id = ?')
                    ->execute([$pRow['amount'], $pRow['tenant_id']]);

                $pdo->prepare(
                    'INSERT INTO deposits (lessor_id, tenant_id, amount, method, status, note, created_at)
                     VALUES (?, ?, ?, ?, "locked", ?, NOW())'
                )->execute([$lessorId, $pRow['tenant_id'], $pRow['amount'], $pRow['method'] ?? 'bank_transfer', 'Tenant Deposit Top-Up Verified']);
            }

            audit_log(
                $pdo,
                $lessorId,
                'Confirmed Tenant Payment',
                '₱' . number_format($pRow['amount'], 2) . ' from ' . $pRow['tenant_name']
            );

            require_once __DIR__ . '/../includes/payment_sms_helper.php';
            send_payment_receipt_sms($pdo, $paymentId);

            echo '<script>if(window.history.replaceState){window.history.replaceState(null,null,"/dashboard.php?page=payments&msg=approved");} else {window.location.href="/dashboard.php?page=payments&msg=approved";}</script>';
            exit;
        } else {
            $error = 'Payment record not found or unauthorized.';
        }
    } catch (Throwable $e) {
        error_log('Approve payment error: ' . $e->getMessage());
        $error = 'Could not approve this payment. Please try again.';
    }
}

// 2. DECLINE PAYMENT ACTION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'decline_payment') {
    csrf_check();
    $paymentId = (int) ($_POST['payment_id'] ?? 0);
    $reason = trim($_POST['decline_reason'] ?? 'Declined by lessor');

    try {
        $check = $pdo->prepare(
            'SELECT pay.*, t.tenant_name FROM payments pay
             JOIN tenants t ON t.id = pay.tenant_id
             WHERE pay.id = ? AND pay.lessor_id = ?'
        );
        $check->execute([$paymentId, $lessorId]);
        $pRow = $check->fetch();

        if ($pRow) {
            $currentNote = $pRow['note'] ?? '';
            $tag = '[DECLINED: ' . htmlspecialchars($reason) . ']';
            $newNote = str_contains($currentNote, '[PENDING]')
                ? str_replace('[PENDING]', $tag, $currentNote)
                : ($tag . ' ' . $currentNote);

            $update = $pdo->prepare('UPDATE payments SET logged_by = NULL, note = ? WHERE id = ?');
            $update->execute([$newNote, $paymentId]);

            audit_log(
                $pdo,
                $lessorId,
                'Declined Tenant Payment',
                '₱' . number_format($pRow['amount'], 2) . ' from ' . $pRow['tenant_name'] . ' (' . $reason . ')'
            );

            echo '<script>if(window.history.replaceState){window.history.replaceState(null,null,"/dashboard.php?page=payments&msg=declined");} else {window.location.href="/dashboard.php?page=payments&msg=declined";}</script>';
            exit;
        } else {
            $error = 'Payment record not found or unauthorized.';
        }
    } catch (Throwable $e) {
        error_log('Decline payment error: ' . $e->getMessage());
        $error = 'Could not decline this payment. Please try again.';
    }
}

// 3. LOG MANUAL PAYMENT (Offline) - Defaulting to PENDING
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_payment'])) {
    csrf_check();
    $tenantId = (int) $_POST['tenant_id'];
    
    // Remove commas from the amount before parsing
    $rawAmount = $_POST['amount'] ?? '0';
    $amount    = (float) str_replace(',', '', $rawAmount);
    
    // Handle the "Other" specification
    $rawMethod = $_POST['method'] ?? 'cash';
    $finalMethod = $rawMethod;
    if ($rawMethod === 'other') {
        $specified = trim($_POST['method_other'] ?? '');
        $finalMethod = $specified !== '' ? $specified : 'Other';
    }
    
    $paidAt   = $_POST['paid_at'] ?? '';
    
    // Auto-prepend [PENDING] to the note to flag it for verification later
    $rawNote = trim($_POST['note'] ?? '');
    $note = '[PENDING] ' . ($rawNote ? $rawNote : 'Offline manual entry');

    $check = $pdo->prepare('SELECT id, tenant_name FROM tenants WHERE id = ? AND lessor_id = ?');
    $check->execute([$tenantId, $lessorId]);
    $tRow = $check->fetch();

    $validDate = DateTime::createFromFormat('Y-m-d', $paidAt) !== false;

    if (!$tRow || $amount <= 0 || !isset($METHODS[$rawMethod]) || !$validDate) {
        $error = 'Please fill in every field with a valid tenant, amount, method, and date.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO payments (tenant_id, lessor_id, amount, method, note, paid_at, logged_by)
                 VALUES (?, ?, ?, ?, ?, ?, NULL)'
            );
            $stmt->execute([$tenantId, $lessorId, $amount, $finalMethod, $note, $paidAt]);

            $newPayId = (int) $pdo->lastInsertId();

            audit_log($pdo, $lessorId, 'Logged pending rent payment', "₱$amount via {$finalMethod} for {$tRow['tenant_name']}");

            $loggingPayment = false;
            echo '<script>if(window.history.replaceState){window.history.replaceState(null,null,"/dashboard.php?page=payments&msg=logged");} else {window.location.href="/dashboard.php?page=payments&msg=logged";}</script>';
        } catch (Throwable $e) {
            error_log('Log payment failed: ' . $e->getMessage());
            $error = 'Could not log this payment — the error has been logged. Please try again.';
        }
    }
}

// ============================================================================
// DATA FETCHING: UNIFIED TABLE (Pending on top, then verified/declined)
// ============================================================================
$q = trim($_GET['q'] ?? '');
$searchSql = '';
$queryParams = [$lessorId];

if ($q !== '') {
    $searchSql = " AND (t.tenant_name LIKE ? OR p.name LIKE ? OR pay.note LIKE ?) ";
    $wildcard = '%' . $q . '%';
    array_push($queryParams, $wildcard, $wildcard, $wildcard);
}

// Fetch all payments for this lessor. 
// Custom SORT order: Unverified/Pending items rank 0 (top), others rank 1. Then by date descending.
$allQuery = $pdo->prepare(
    "SELECT pay.*, t.tenant_name, p.name AS property_name
     FROM payments pay
     JOIN tenants t ON t.id = pay.tenant_id
     LEFT JOIN properties p ON p.id = t.property_id
     WHERE pay.lessor_id = ? $searchSql
     ORDER BY 
       CASE WHEN (pay.logged_by IS NULL AND pay.note NOT LIKE '[DECLINED%') THEN 0 ELSE 1 END ASC,
       pay.paid_at DESC, 
       pay.id DESC 
     LIMIT 500" // Increased limit since we now have pagination
);
$allQuery->execute($queryParams);
$allPayments = $allQuery->fetchAll();

// Calculate Stats from the fetched array
$pendingCount = 0;
$pendingTotal = 0;
$historyCount = 0;

foreach ($allPayments as $p) {
    $isPending = ($p['logged_by'] === null && !str_starts_with($p['note'] ?? '', '[DECLINED'));
    if ($isPending) {
        $pendingCount++;
        $pendingTotal += $p['amount'];
    } else {
        $historyCount++;
    }
}

// Month Total (Requires independent SQL to catch all records outside the limit)
$monthTotalStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(amount), 0) FROM payments
     WHERE lessor_id = ? AND logged_by IS NOT NULL
     AND paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
);
$monthTotalStmt->execute([$lessorId]);
$monthTotal = (float) $monthTotalStmt->fetchColumn();

// Fetch Options for the Logging Form
$properties = $pdo->prepare('SELECT id, name FROM properties WHERE lessor_id = ? ORDER BY name');
$properties->execute([$lessorId]);
$properties = $properties->fetchAll();

$tenants = $pdo->prepare('SELECT id, tenant_name, property_id FROM tenants WHERE lessor_id = ? ORDER BY tenant_name');
$tenants->execute([$lessorId]);
$tenants = $tenants->fetchAll();
?>

<!-- FLOATING TOAST NOTIFICATIONS -->
<?php if ($msg || ($error && !$loggingPayment)): ?>
  <div id="toast-notification" style="position:fixed; top:24px; right:24px; z-index:9999; transition: opacity 0.5s ease, transform 0.5s ease; transform: translateY(0); opacity: 1;">
    <?php if ($msg === 'approved'): ?>
      <div class="success-msg" style="margin:0; box-shadow:0 4px 12px rgba(0,0,0,0.1); border-radius:8px; display:flex; align-items:center; gap:8px;">
        <i class="bi bi-check-circle-fill"></i>
        <span><strong>Payment Confirmed!</strong> The payment has been verified and recorded.</span>
      </div>
    <?php elseif ($msg === 'declined'): ?>
      <div class="error-msg" style="margin:0; box-shadow:0 4px 12px rgba(0,0,0,0.1); border-radius:8px; display:flex; align-items:center; gap:8px;">
        <i class="bi bi-x-circle-fill"></i>
        <span><strong>Payment Declined.</strong> The submission was declined and marked accordingly.</span>
      </div>
    <?php elseif ($msg === 'logged'): ?>
      <div class="success-msg" style="margin:0; box-shadow:0 4px 12px rgba(0,0,0,0.1); border-radius:8px; display:flex; align-items:center; gap:8px;">
        <i class="bi bi-info-circle-fill"></i>
        <span>Payment logged and is now <strong>Pending Review</strong>.</span>
      </div>
    <?php endif; ?>

    <?php if ($error && !$loggingPayment): ?>
      <div class="error-msg" style="margin:0; box-shadow:0 4px 12px rgba(0,0,0,0.1); border-radius:8px; display:flex; align-items:center; gap:8px;">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
    <?php endif; ?>
  </div>

  <script>
    setTimeout(function() {
      var toast = document.getElementById('toast-notification');
      if (toast) {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-20px)';
        setTimeout(function() { toast.remove(); }, 500);
      }
    }, 3500);
  </script>
<?php endif; ?>

<!-- HEADER SECTION -->
<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
  <div>
    <h2 style="margin:0 0 4px; font-weight:800; color:var(--ink-900);">Rent Payments</h2>
    <p style="color:var(--ink-500); margin:0;">
      Verify incoming tenant transfers and manage your complete rent ledger.
    </p>
  </div>
  <a href="/dashboard.php?page=payments&log=1" class="btn btn-primary" style="width:auto; height:fit-content; display:inline-flex; align-items:center; gap:6px; padding:10px 18px; font-size:13px; font-weight:600; white-space:nowrap; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.1);">
    <i class="bi bi-plus-lg"></i> Log Payment
  </a>
</div>

<!-- Summary KPI Grid -->
<div class="kpi-grid" style="margin-bottom:28px;">
  <div class="card" style="border-left: 3px solid <?= $pendingCount > 0 ? 'var(--warning)' : 'var(--border)' ?>;">
    <div class="kpi-label">Pending Verification</div>
    <div class="kpi-value" style="color:<?= $pendingCount > 0 ? 'var(--primary)' : 'inherit' ?>;">
      <?= $pendingCount ?> <span style="font-size:14px; font-weight:600; color:var(--ink-500);">(₱<?= number_format($pendingTotal, 2) ?>)</span>
    </div>
  </div>
  <div class="card">
    <div class="kpi-label">Verified This Month</div>
    <div class="kpi-value">₱<?= number_format($monthTotal, 2) ?></div>
  </div>
  <div class="card">
    <div class="kpi-label">Total Verified Records</div>
    <div class="kpi-value"><?= $historyCount ?></div>
  </div>
</div>

<!-- =============================================================
     UNIFIED PAYMENT LEDGER TABLE
     ============================================================= -->
<div class="card" style="padding:0; overflow:hidden;">
  <!-- REAL-TIME FILTER BAR -->
  <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; padding:16px 20px; border-bottom:1px solid var(--border); background:var(--surface);">
    <div style="flex:1; min-width:220px; position:relative;">
      <i class="bi bi-search" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--ink-400); font-size:13px;"></i>
      <input id="pay-search" type="search" placeholder="Search property, tenant, target, or reference..." autocomplete="off" style="width:100%; box-sizing:border-box; padding:8px 12px 8px 34px; border:1px solid var(--border); border-radius:8px; font:inherit; font-size:13px; background:#fff; color:var(--ink-900);">
    </div>
    <select id="pay-status-filter" style="padding:8px 12px; border:1px solid var(--border); border-radius:8px; font:inherit; font-size:13px; background:#fff; color:var(--ink-900); min-width:140px;">
      <option value="">All statuses</option>
      <option value="pending">Pending Review</option>
      <option value="verified">Verified</option>
      <option value="declined">Declined</option>
    </select>
  </div>

  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>Property & Tenant</th>
          <th>Amount</th>
          <th>Target & Method</th>
          <th>Date</th>
          <th>Status</th>
          <th style="text-align:right;">Action</th>
        </tr>
      </thead>
      <tbody id="payments-table-body">
        <?php if (!$allPayments): ?>
          <tr>
            <td colspan="6" style="text-align:center; padding:40px 16px; color:var(--ink-500);">
              <i class="bi bi-receipt" style="font-size:32px; color:var(--ink-300); display:block; margin-bottom:12px;"></i>
              No payment records found.
            </td>
          </tr>
        <?php else: foreach ($allPayments as $p):
          $parsed = parse_payment_note($p['note']);
          $isDeclined = $parsed['status'] === 'declined';
          $isPending = ($p['logged_by'] === null && !$isDeclined);
          $statusVal = $isDeclined ? 'declined' : ($isPending ? 'pending' : 'verified');
          
          $searchBlob = strtolower(trim(implode(' ', array_filter([
              $p['property_name'] ?? '',
              $p['tenant_name'],
              $parsed['target'],
              $parsed['ref'],
              $p['method'],
              $p['note']
          ]))));
        ?>
          <tr class="pay-row" data-status="<?= $statusVal ?>" data-search="<?= htmlspecialchars($searchBlob) ?>" style="background: <?= $isPending ? 'rgba(11, 87, 208, 0.03)' : 'transparent' ?>;">
            
            <!-- Property & Tenant -->
            <td>
              <div style="font-weight:700; color:var(--ink-900); font-size:14px;"><?= htmlspecialchars($p['tenant_name']) ?></div>
              <div style="color:var(--ink-500); font-size:12px; margin-top:2px;">
                <i class="bi bi-building" style="margin-right:4px;"></i><?= htmlspecialchars($p['property_name'] ?? 'Unknown Property') ?>
              </div>
            </td>
            
            <!-- Amount -->
            <td>
              <span style="font-size:15px; font-weight:800; color:<?= $isDeclined ? 'var(--ink-400)' : 'var(--primary)' ?>;">
                ₱<?= number_format($p['amount'], 2) ?>
              </span>
            </td>
            
            <!-- Target & Method -->
            <td>
              <div style="font-size:12.5px;">
                <span style="font-weight:600; color:var(--ink-900);"><?= htmlspecialchars($parsed['target']) ?></span>
                <div style="color:var(--ink-500); margin-top:2px;">
                  <?= htmlspecialchars($METHODS[$p['method']] ?? $p['method']) ?>
                  <?php if ($parsed['ref'] !== '—'): ?>
                     · <span style="font-family:monospace; color:var(--ink-600);">Ref: <?= htmlspecialchars($parsed['ref']) ?></span>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            
            <!-- Date -->
            <td style="color:var(--ink-500); font-size:13px;">
              <?= (new DateTime($p['paid_at']))->format('M j, Y') ?>
            </td>
            
            <!-- Status -->
            <td>
              <?php if ($isDeclined): ?>
                <span class="badge badge-rejected" style="font-size:11px;">Declined</span>
              <?php elseif (!$isPending): ?>
                <span class="badge badge-approved" style="font-size:11px;">
                  <i class="bi bi-shield-check" style="margin-right:2px;"></i> Verified
                </span>
              <?php else: ?>
                <span class="badge badge-pending" style="font-size:11px;">Pending Review</span>
              <?php endif; ?>
            </td>
            
            <!-- Action -->
            <td style="text-align:right;">
              <?php if ($isPending): ?>
                <div style="display:inline-flex; gap:8px; justify-content:flex-end;">
                  <form method="POST" style="margin:0;" onsubmit="var btn = this.querySelector('button[type=submit]'); btn.disabled = true; btn.innerHTML = '<i class=\'bi bi-hourglass\'></i>...';">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="approve_payment">
                    <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                    <button type="submit" class="btn" style="padding:6px 12px; font-size:12px; background:var(--success); color:#fff; border-radius:6px;" title="Confirm Received">
                      <i class="bi bi-check-lg"></i> Confirm
                    </button>
                  </form>
                  <form method="POST" style="margin:0;" onsubmit="var reason = prompt('Reason for declining this payment?'); if(reason === null || reason.trim() === '') return false; this.querySelector('.decline-reason').value = reason; var btn = this.querySelector('button[type=submit]'); btn.disabled = true; btn.innerHTML = '<i class=\'bi bi-hourglass\'></i>';">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="decline_payment">
                    <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                    <input type="hidden" name="decline_reason" class="decline-reason" value="">
                    <button type="submit" class="btn" style="padding:6px 10px; font-size:12px; background:var(--error-soft); color:var(--error); border-radius:6px;" title="Decline">
                      <i class="bi bi-x-lg"></i>
                    </button>
                  </form>
                </div>
              <?php else: ?>
                <span style="font-size:12.5px; color:var(--ink-300); font-weight:500;">—</span>
              <?php endif; ?>
            </td>

          </tr>
        <?php endforeach; endif; ?>
          <tr id="payments-empty-filter" style="display:none;">
            <td colspan="6" style="text-align:center; padding:32px 16px; color:var(--ink-500);">No payments match your search.</td>
          </tr>
      </tbody>
    </table>
  </div>
  
  <!-- Pagination UI -->
  <?php if ($allPayments): ?>
  <div id="payment-pagination-bar" style="display:flex; justify-content:center; align-items:center; padding:12px 16px; border-top:1px solid var(--border); background:var(--surface);">
    <div id="payment-page-controls" style="display:flex; gap:6px; align-items:center; justify-content:center;"></div>
  </div>
  <?php endif; ?>
</div>

<!-- =============================================================
     POPUP DRAWER: LOG OFFLINE PAYMENT
     ============================================================= -->
<?php if ($loggingPayment || ($error && isset($_POST['log_payment']))): ?>
<div style="position:fixed; inset:0; background:rgba(0,0,0,.4); display:flex; justify-content:flex-end; z-index:50;">
  <div style="background:#fff; width:440px; max-width:92vw; height:100%; padding:24px; overflow-y:auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <h3 style="margin:0;">Log Offline Payment</h3>
      <a href="/dashboard.php?page=payments" style="font-size:20px; color:var(--ink-500); text-decoration:none;">&times;</a>
    </div>

    <p style="font-size:13px; color:var(--ink-500); margin-bottom:20px;">
      Directly record payments received. They will be added to the queue for pending verification.
    </p>

    <?php if ($error): ?>
      <div class="error-msg" style="margin-bottom:16px; border-radius:8px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$tenants): ?>
      <p style="font-size:13px; color:var(--ink-500);">You don't have any active tenants yet.</p>
    <?php else: ?>
      <form method="POST" action="/dashboard.php?page=payments" onsubmit="var btn = this.querySelector('button[type=submit]'); btn.disabled = true; btn.innerHTML = '<i class=\'bi bi-hourglass-split\'></i> Logging...';">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="log_payment" value="1">
        
        <div class="field">
          <label style="font-weight:600;">Property</label>
          <select id="payment_property_select" name="property_id" required style="width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; font-family:inherit;">
            <option value="">Select property…</option>
            <?php foreach ($properties as $p): ?>
              <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field" style="margin-top:16px;">
          <label style="font-weight:600;">Tenant</label>
          <select id="payment_tenant_select" name="tenant_id" required disabled style="width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; font-family:inherit;">
            <option value="">Select a property first…</option>
            <?php foreach ($tenants as $t): ?>
              <option value="<?= $t['id'] ?>" data-property="<?= $t['property_id'] ?>"><?= htmlspecialchars($t['tenant_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div style="display:flex; gap:16px; margin-top:16px;">
          <div class="field" style="flex:1;">
            <label style="font-weight:600;">Amount (₱)</label>
            <input type="text" id="manual_payment_amount" name="amount" placeholder="e.g. 2,500" required style="width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; box-sizing:border-box; font-family:inherit;" inputmode="numeric">
          </div>
          
          <div class="field" style="flex:1;">
            <label style="font-weight:600;">Payment Method</label>
            <select name="method" id="payment_method_select" required style="width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; font-family:inherit;">
              <?php foreach ($METHODS as $key => $label): ?>
                <option value="<?= $key ?>"><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="field" id="method_other_container" style="display:none; margin-top:16px;">
          <label style="font-weight:600;">Specify Method</label>
          <input type="text" name="method_other" id="method_other_input" placeholder="e.g. Western Union, Remitly" style="width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; box-sizing:border-box; font-family:inherit;">
        </div>
        
        <div class="field" style="margin-top:16px;">
          <label style="font-weight:600;">Date Received</label>
          <input type="date" name="paid_at" value="<?= date('Y-m-d') ?>" required style="width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; box-sizing:border-box; font-family:inherit;">
        </div>
        
        <div class="field" style="margin-top:16px; margin-bottom:24px;">
          <label style="font-weight:600;">Note / Remarks (optional)</label>
          <input type="text" name="note" placeholder="e.g. Official Receipt #10492" style="width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; box-sizing:border-box; font-family:inherit;">
        </div>
        
        <button class="btn btn-primary" type="submit" style="width:100%; padding:12px; font-weight:600; border-radius:8px;">
          <i class="bi bi-save2" style="margin-right:6px;"></i> Log Payment
        </button>
      </form>

      <script>
        document.addEventListener('DOMContentLoaded', function() {
           var propSel = document.getElementById('payment_property_select');
           var tenSel = document.getElementById('payment_tenant_select');
           var amtInput = document.getElementById('manual_payment_amount');
           var methodSel = document.getElementById('payment_method_select');
           var methodOtherContainer = document.getElementById('method_other_container');
           var methodOtherInput = document.getElementById('method_other_input');
           
           if (methodSel) {
               methodSel.addEventListener('change', function() {
                   if (this.value === 'other') {
                       methodOtherContainer.style.display = 'block';
                       methodOtherInput.required = true;
                   } else {
                       methodOtherContainer.style.display = 'none';
                       methodOtherInput.required = false;
                       methodOtherInput.value = '';
                   }
               });
           }

           function formatNumber(val) {
               var parts = val.replace(/,/g, '').split('.');
               parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
               return parts.join('.');
           }

           if (amtInput) {
               amtInput.addEventListener('input', function(e) {
                   var value = this.value.replace(/[^0-9.]/g, ''); 
                   var parts = value.split('.');
                   if (parts.length > 2) {
                       value = parts[0] + '.' + parts.slice(1).join(''); 
                   }
                   this.value = formatNumber(value);
               });
           }

           if (propSel && tenSel) {
               var allOptions = Array.from(tenSel.querySelectorAll('option[data-property]'));
               
               propSel.addEventListener('change', function() {
                   var pid = this.value;
                   tenSel.innerHTML = '<option value="">Select tenant...</option>';
                   var hasTenants = false;
                   
                   allOptions.forEach(function(opt) {
                       if(opt.getAttribute('data-property') === pid) {
                           tenSel.appendChild(opt.cloneNode(true));
                           hasTenants = true;
                       }
                   });
                   
                   if (!pid) {
                      tenSel.innerHTML = '<option value="">Select a property first...</option>';
                      tenSel.disabled = true;
                   } else if (!hasTenants) {
                      tenSel.innerHTML = '<option value="">No tenants found here</option>';
                      tenSel.disabled = true;
                   } else {
                      tenSel.disabled = false;
                   }
               });
               
               tenSel.innerHTML = '<option value="">Select a property first...</option>';
               tenSel.disabled = true;
           }
        });
      </script>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- Unified Real-Time Javascript Filtering & Pagination Script -->
<script>
(function () {
  var search = document.getElementById('pay-search');
  var status = document.getElementById('pay-status-filter');
  var rows = Array.prototype.slice.call(document.querySelectorAll('#payments-table-body .pay-row'));
  var emptyFilter = document.getElementById('payments-empty-filter');
  
  // Pagination vars
  var paginationBar = document.getElementById('payment-pagination-bar');
  var pageControls = document.getElementById('payment-page-controls');
  var currentPage = 1;
  var perPage = 10;
  var total = rows.length;

  function renderPagination(totalMatching, totalPages) {
    if (!paginationBar || !pageControls) return;
    if (totalMatching === 0) {
      paginationBar.style.display = 'none';
      return;
    }
    
    paginationBar.style.display = 'flex';
    pageControls.innerHTML = '';

    var prevBtn = document.createElement('button');
    prevBtn.type = 'button';
    prevBtn.innerHTML = '<i class="bi bi-chevron-left"></i> Prev';
    prevBtn.disabled = currentPage === 1;
    prevBtn.style.cssText = 'padding:5px 10px; font-size:12px; border:1px solid var(--border); border-radius:6px; background:' + (currentPage === 1 ? 'var(--bg)' : '#fff') + '; color:' + (currentPage === 1 ? 'var(--ink-300)' : 'var(--ink-900)') + '; cursor:' + (currentPage === 1 ? 'not-allowed' : 'pointer') + '; font-family:inherit; font-weight:500;';
    prevBtn.addEventListener('click', function () {
      if (currentPage > 1) { currentPage--; applyFilter(); }
    });
    pageControls.appendChild(prevBtn);

    for (var p = 1; p <= totalPages; p++) {
      (function (pageNum) {
        var pageBtn = document.createElement('button');
        pageBtn.type = 'button';
        pageBtn.textContent = pageNum;
        var isActive = pageNum === currentPage;
        pageBtn.style.cssText = 'padding:5px 10px; font-size:12px; border:1px solid ' + (isActive ? 'var(--primary)' : 'var(--border)') + '; border-radius:6px; background:' + (isActive ? 'var(--primary)' : '#fff') + '; color:' + (isActive ? '#fff' : 'var(--ink-900)') + '; cursor:pointer; font-weight:' + (isActive ? '700' : '500') + '; font-family:inherit; min-width:30px;';
        pageBtn.addEventListener('click', function () {
          currentPage = pageNum; applyFilter();
        });
        pageControls.appendChild(pageBtn);
      })(p);
    }

    var nextBtn = document.createElement('button');
    nextBtn.type = 'button';
    nextBtn.innerHTML = 'Next <i class="bi bi-chevron-right"></i>';
    nextBtn.disabled = currentPage === totalPages;
    nextBtn.style.cssText = 'padding:5px 10px; font-size:12px; border:1px solid var(--border); border-radius:6px; background:' + (currentPage === totalPages ? 'var(--bg)' : '#fff') + '; color:' + (currentPage === totalPages ? 'var(--ink-300)' : 'var(--ink-900)') + '; cursor:' + (currentPage === totalPages ? 'not-allowed' : 'pointer') + '; font-family:inherit; font-weight:500;';
    nextBtn.addEventListener('click', function () {
      if (currentPage < totalPages) { currentPage++; applyFilter(); }
    });
    pageControls.appendChild(nextBtn);
  }

  function applyFilter() {
    var q = (search.value || '').trim().toLowerCase();
    var st = status.value || '';
    
    // First, find all matching rows based on search & filter
    var matchingRows = rows.filter(function (row) {
      var matchQ = !q || (row.getAttribute('data-search') || '').indexOf(q) !== -1;
      var matchS = !st || row.getAttribute('data-status') === st;
      return matchQ && matchS;
    });

    var totalMatching = matchingRows.length;
    var totalPages = Math.max(1, Math.ceil(totalMatching / perPage));
    if (currentPage > totalPages) currentPage = totalPages;

    // Hide everything initially
    rows.forEach(function (row) { row.style.display = 'none'; });

    // Show only the slice for the current page
    var startIndex = (currentPage - 1) * perPage;
    var endIndex = startIndex + perPage;
    matchingRows.slice(startIndex, endIndex).forEach(function (row) { 
        row.style.display = ''; 
    });

    if (emptyFilter) {
        emptyFilter.style.display = (totalMatching === 0 && total > 0) ? '' : 'none';
    }

    // Re-render pagination buttons
    renderPagination(totalMatching, totalPages);
  }

  if (search && status) {
    search.addEventListener('input', function() { currentPage = 1; applyFilter(); });
    status.addEventListener('change', function() { currentPage = 1; applyFilter(); });
    applyFilter(); // Run immediately on load
  }
})();
</script>