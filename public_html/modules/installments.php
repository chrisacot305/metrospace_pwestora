<?php
/**
 * modules/installments.php  (Lessor → "Installments")
 * PHP port of the Installments component in pwestora-lessor-web.jsx.
 */
$lessorId = $user['id'];
$error = '';

$loggingReq = isset($_GET['log']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['request_id'])) {
    csrf_check();
    $reqId  = (int) $_POST['request_id'];
    $action = $_POST['action'];

    try {
        $stmt = $pdo->prepare('SELECT * FROM installment_requests WHERE id = ? AND lessor_id = ?');
        $stmt->execute([$reqId, $lessorId]);
        $reqRow = $stmt->fetch();

        if ($reqRow && $reqRow['status'] === 'pending') {
            $newStatus = $action === 'approve' ? 'approved' : 'rejected';
            $pdo->prepare('UPDATE installment_requests SET status = ? WHERE id = ?')->execute([$newStatus, $reqId]);
            audit_log($pdo, $lessorId, ucfirst($newStatus) . ' installment plan', $reqRow['plan_label']);

            try {
                $tenantPhoneStmt = $pdo->prepare(
                    'SELECT u.phone FROM tenants t JOIN users u ON u.id = t.lessee_id WHERE t.id = ?'
                );
                $tenantPhoneStmt->execute([$reqRow['tenant_id']]);
                $lesseePhone = $tenantPhoneStmt->fetchColumn();
                if (!empty($lesseePhone)) {
                    require_once __DIR__ . '/../includes/sms.php';
                    if ($newStatus === 'approved') {
                        $planName = $reqRow['plan_label'] ?? 'Restructuring';
                        $planAmt  = number_format((float) $reqRow['amount'], 2);
                        send_sms($lesseePhone, "[Pwestora] Good news! Your restructuring arrangement request ($planName for ₱$planAmt) has been APPROVED by your lessor. You can now view your schedule and submit installment payments via the Pwestora app.");
                    } else {
                        send_sms($lesseePhone, "[Pwestora] Notice: Your restructuring arrangement request was declined by your lessor. Please check the Pwestora app or contact your lessor for details.");
                    }
                }
            } catch (Throwable $smsEx) {
                error_log('Installment decision SMS error: ' . $smsEx->getMessage());
            }
        }
        // Seamless transition: Prevent blank screen and close the review drawer without reloading
        echo '<script>if(window.history.replaceState){window.history.replaceState(null,null,"/dashboard.php?page=installments");}</script>';
        unset($_GET['review']);
    } catch (Throwable $e) {
        error_log('Installment decision failed: ' . $e->getMessage());
        $error = 'Could not process this request — the error has been logged. Please try again.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_request'])) {
    csrf_check();
    $tenantId = (int) $_POST['tenant_id'];
    $reason   = trim($_POST['reason'] ?? '');
    $plan     = $_POST['plan_label'] ?? '';
    $amount   = (float) $_POST['amount'];

    $check = $pdo->prepare('SELECT id FROM tenants WHERE id = ? AND lessor_id = ?');
    $check->execute([$tenantId, $lessorId]);

    if (!$check->fetch() || $reason === '' || !in_array($plan, ['2 payments', '3 payments'], true) || $amount <= 0) {
        $error = 'Please fill in every field with a valid tenant, plan, and amount.';
    } else {
        try {
            $pdo->prepare(
                'INSERT INTO installment_requests (lessor_id, tenant_id, reason, plan_label, amount) VALUES (?, ?, ?, ?, ?)'
            )->execute([$lessorId, $tenantId, $reason, $plan, $amount]);
            
            // Seamless transition: Prevent blank screen and close the log drawer without reloading
            echo '<script>if(window.history.replaceState){window.history.replaceState(null,null,"/dashboard.php?page=installments");}</script>';
            $loggingReq = false;
            unset($_GET['log']);
        } catch (Throwable $e) {
            error_log('Log installment request failed: ' . $e->getMessage());
            $error = 'Could not log this request — the error has been logged. Please try again.';
        }
    }
}

// Updated query to join the properties table to get the property name
$requests = $pdo->prepare(
    'SELECT ir.*, t.tenant_name, p.name AS property_name 
     FROM installment_requests ir
     JOIN tenants t ON t.id = ir.tenant_id
     JOIN properties p ON p.id = t.property_id
     WHERE ir.lessor_id = ? ORDER BY ir.created_at DESC'
);
$requests->execute([$lessorId]);
$requests = $requests->fetchAll();

// Fetch properties for the dropdown
$properties = $pdo->prepare('SELECT id, name FROM properties WHERE lessor_id = ? ORDER BY name');
$properties->execute([$lessorId]);
$properties = $properties->fetchAll();

// Include property_id to allow JS filtering in the popup
$tenants = $pdo->prepare('SELECT id, tenant_name, property_id FROM tenants WHERE lessor_id = ? ORDER BY tenant_name');
$tenants->execute([$lessorId]);
$tenants = $tenants->fetchAll();

$reviewId = isset($_GET['review']) ? (int) $_GET['review'] : null;
$reviewing = null;
if ($reviewId) {
    foreach ($requests as $r) {
        if ((int) $r['id'] === $reviewId) { $reviewing = $r; break; }
    }
}
?>

<div style="margin-bottom:20px;">
  <h2 style="margin:0 0 4px;">Installment Requests</h2>
  <p style="color:var(--ink-500); margin:0;">Structured restructuring only, no informal rent-reduction negotiation.</p>
</div>

<?php if ($error && !$loggingReq && !$reviewing): ?>
  <div class="error-msg"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card" style="padding:0; overflow:hidden; margin-bottom:28px;">
  
  <!-- FILTER BAR & LOG BUTTON -->
  <div style="display:flex; gap:10px; flex-wrap:nowrap; align-items:center; padding:14px 16px; border-bottom:1px solid var(--border); overflow-x:auto;">
    
    <div style="flex:1; min-width:220px; position:relative;">
      <i class="bi bi-search" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--ink-400); font-size:13px;"></i>
      <input id="req-search" type="search" placeholder="Search property, tenant, reason, or plan..."
             autocomplete="off"
             style="width:100%; box-sizing:border-box; padding:8px 12px 8px 34px; border:1px solid var(--border); border-radius:8px; font:inherit; font-size:13px; background:var(--bg); color:var(--ink-900);">
    </div>

    <!-- LOG REQUEST BUTTON -->
    <a href="/dashboard.php?page=installments&log=1" class="btn btn-primary" style="width:auto; display:inline-flex; align-items:center; gap:6px; padding:6px 14px; font-size:12px; flex-shrink:0;">
      <i class="bi bi-plus-lg"></i> Log Request
    </a>

    <select id="req-status-filter"
            style="padding:8px 12px; border:1px solid var(--border); border-radius:8px; font:inherit; font-size:13px; background:#fff; color:var(--ink-900); min-width:140px; flex-shrink:0;">
      <option value="">All statuses</option>
      <option value="pending">Pending</option>
      <option value="approved">Approved</option>
      <option value="rejected">Rejected</option>
    </select>
    <span id="req-filter-count" style="font-size:12.5px; color:var(--ink-500); white-space:nowrap; flex-shrink:0;"></span>
    
  </div>
  <!-- END FILTER BAR -->

  <div class="table-scroll">
  <table>
    <thead><tr><th>Property</th><th>Tenant</th><th>Reason</th><th>Plan</th><th>Amount</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php if (!$requests): ?>
        <tr><td colspan="7" style="text-align:center; color:var(--ink-500);">No installment requests yet.</td></tr>
      <?php else: foreach ($requests as $r): 
          // Generate hidden search text for this row
          $searchBlob = strtolower(trim(implode(' ', array_filter([
              $r['property_name'] ?? '',
              $r['tenant_name'],
              $r['reason'],
              $r['plan_label'],
              $r['status']
          ]))));
      ?>
        <tr class="req-row" data-status="<?= htmlspecialchars($r['status']) ?>" data-search="<?= htmlspecialchars($searchBlob) ?>">
          <td style="color:var(--ink-500);"><?= htmlspecialchars($r['property_name'] ?? 'Unknown Property') ?></td>
          <td style="font-weight:600;"><?= htmlspecialchars($r['tenant_name']) ?></td>
          <td style="color:var(--ink-500);"><?= htmlspecialchars($r['reason']) ?></td>
          <td style="color:var(--ink-500);"><?= htmlspecialchars($r['plan_label']) ?></td>
          <td style="color:var(--ink-500);">₱<?= number_format($r['amount'], 0) ?></td>
          <td>
            <?php if ($r['status'] === 'pending'): ?>
              <span class="badge badge-pending">Pending</span>
            <?php elseif ($r['status'] === 'approved'): ?>
              <span class="badge badge-approved">Approved</span>
            <?php else: ?>
              <span class="badge badge-rejected">Rejected</span>
            <?php endif; ?>
          </td>
          <td style="text-align:right;">
            <?php if ($r['status'] === 'pending'): ?>
              <a class="btn btn-primary" style="padding:6px 14px; display:inline-flex;"
                 href="/dashboard.php?page=installments&review=<?= $r['id'] ?>">Review</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
        
        <tr id="req-empty-filter" style="display:none;">
          <td colspan="7" style="text-align:center; color:var(--ink-500);">No requests match your search or filter.</td>
        </tr>
    </tbody>
  </table>
  </div>
  
  <!-- Pagination UI -->
  <?php if ($requests): ?>
  <div id="req-pagination-bar" style="display:flex; justify-content:center; align-items:center; padding:12px 16px; border-top:1px solid var(--border); background:var(--surface);">
    <div id="req-page-controls" style="display:flex; gap:6px; align-items:center; justify-content:center;"></div>
  </div>
  <?php endif; ?>
</div>

<script>
(function () {
  var search = document.getElementById('req-search');
  var status = document.getElementById('req-status-filter');
  var count = document.getElementById('req-filter-count');
  var empty = document.getElementById('req-empty-filter');
  var rows = Array.prototype.slice.call(document.querySelectorAll('.req-row'));
  var total = rows.length;

  var paginationBar = document.getElementById('req-pagination-bar');
  var pageControls = document.getElementById('req-page-controls');
  var currentPage = 1;
  var perPage = 10;

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

    // Filter all rows first
    var matchingRows = rows.filter(function (row) {
      var matchQ = !q || (row.getAttribute('data-search') || '').indexOf(q) !== -1;
      var matchS = !st || row.getAttribute('data-status') === st;
      return matchQ && matchS;
    });

    var totalMatching = matchingRows.length;
    var totalPages = Math.max(1, Math.ceil(totalMatching / perPage));
    if (currentPage > totalPages) currentPage = totalPages;

    // Hide everything
    rows.forEach(function (row) { row.style.display = 'none'; });

    // Show only the slice for the current page
    var startIndex = (currentPage - 1) * perPage;
    var endIndex = startIndex + perPage;
    matchingRows.slice(startIndex, endIndex).forEach(function (row) {
      row.style.display = '';
    });

    if (empty && total > 0) empty.style.display = totalMatching ? 'none' : '';
    if (count) {
      count.textContent = (q || st) ? (totalMatching + ' of ' + total) : (total + ' request' + (total === 1 ? '' : 's'));
    }

    renderPagination(totalMatching, totalPages);
  }

  if (search && status) {
    search.addEventListener('input', function() { currentPage = 1; applyFilter(); });
    status.addEventListener('change', function() { currentPage = 1; applyFilter(); });
    applyFilter(); // Run on load
  }
})();
</script>

<!-- LOG REQUEST POPUP DRAWER -->
<?php if ($loggingReq || ($error && !$reviewing)): ?>
<div style="position:fixed; inset:0; background:rgba(0,0,0,.4); display:flex; justify-content:flex-end; z-index:50;">
  <div style="background:#fff; width:440px; max-width:92vw; height:100%; padding:24px; overflow-y:auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <h3 style="margin:0;">Log a request</h3>
      <a href="/dashboard.php?page=installments" style="font-size:20px; color:var(--ink-500); text-decoration:none;">&times;</a>
    </div>
    
    <p style="font-size:12.5px; color:var(--ink-500); margin-bottom:16px;">
      Received an installment request offline? Log it here so it goes through the same review flow.
    </p>

    <?php if ($error): ?>
      <div class="error-msg" style="margin-bottom:16px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$tenants): ?>
      <p style="font-size:13px; color:var(--ink-500);">You don't have any active tenants yet — approve an application first.</p>
    <?php else: ?>
      <form method="POST" action="/dashboard.php?page=installments">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="log_request" value="1">
        
        <div class="field">
          <label>Property</label>
          <select id="inst_property_select" name="property_id" required>
            <option value="">Select property…</option>
            <?php foreach ($properties as $p): ?>
              <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label>Tenant</label>
          <select id="inst_tenant_select" name="tenant_id" required disabled>
            <option value="">Select a property first…</option>
            <!-- These options are hidden and managed by JS based on property_id -->
            <?php foreach ($tenants as $t): ?>
              <option value="<?= $t['id'] ?>" data-property="<?= $t['property_id'] ?>"><?= htmlspecialchars($t['tenant_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label>Reason</label>
          <input type="text" name="reason" placeholder="e.g. Seasonal slowdown in business" required>
        </div>
        <div class="field">
          <label>Plan</label>
          <select name="plan_label" required>
            <option value="2 payments">2 payments</option>
            <option value="3 payments">3 payments</option>
          </select>
        </div>
        <div class="field">
          <label>Total Amount (₱)</label>
          <input type="number" name="amount" min="1" step="0.01" required>
        </div>
        <button class="btn btn-primary" type="submit" style="width:100%; margin-top:8px;">Log Request</button>
      </form>

      <!-- JAVASCRIPT FOR CASCADING DROPDOWN -->
      <script>
        document.addEventListener('DOMContentLoaded', function() {
           var propSel = document.getElementById('inst_property_select');
           var tenSel = document.getElementById('inst_tenant_select');
           
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

<!-- REVIEW POPUP DRAWER -->
<?php if ($reviewing):
    $splitCount = $reviewing['plan_label'] === '3 payments' ? 3 : 2;
    $perPayment = round($reviewing['amount'] / $splitCount);
?>
<div style="position:fixed; inset:0; background:rgba(0,0,0,.4); display:flex; justify-content:flex-end; z-index:50;">
  <div style="background:#fff; width:420px; max-width:92vw; height:100%; padding:24px; overflow-y:auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <h3 style="margin:0;">IR-<?= str_pad($reviewing['id'], 3, '0', STR_PAD_LEFT) ?></h3>
      <a href="/dashboard.php?page=installments" style="font-size:20px; color:var(--ink-500); text-decoration:none;">&times;</a>
    </div>

    <div class="card" style="margin-bottom:16px;">
      <p style="margin:4px 0; font-size:13px;"><strong>Property:</strong> <?= htmlspecialchars($reviewing['property_name'] ?? 'Unknown') ?></p>
      <p style="margin:4px 0; font-size:13px;"><strong>Tenant:</strong> <?= htmlspecialchars($reviewing['tenant_name']) ?></p>
      <p style="margin:4px 0; font-size:13px;"><strong>Reason:</strong> <?= htmlspecialchars($reviewing['reason']) ?></p>
      <p style="margin:4px 0; font-size:13px;"><strong>Requested plan:</strong> <?= htmlspecialchars($reviewing['plan_label']) ?></p>
      <p style="margin:4px 0; font-size:13px;"><strong>Total amount:</strong> ₱<?= number_format($reviewing['amount'], 0) ?></p>
    </div>

    <p style="font-size:12px; font-weight:700; margin-bottom:8px;">System-generated schedule</p>
    <div class="card" style="margin-bottom:16px;">
      <?php for ($n = 1; $n <= $splitCount; $n++): ?>
        <p style="margin:6px 0; font-size:13px; display:flex; justify-content:space-between;">
          <span style="color:var(--ink-500);">Payment <?= $n ?></span>
          <span style="font-weight:600;">₱<?= number_format($perPayment, 0) ?></span>
        </p>
      <?php endfor; ?>
    </div>

    <p style="font-size:12px; color:var(--ink-500); margin-bottom:20px;">
      Approving updates the tenant ledger, recalculates due dates, and notifies both parties automatically.
    </p>

    <div style="display:flex; gap:12px;">
      <form method="POST" action="/dashboard.php?page=installments" style="flex:1;">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="request_id" value="<?= $reviewing['id'] ?>">
        <input type="hidden" name="action" value="approve">
        <button class="btn" style="width:100%; background:var(--success); color:#fff;" type="submit">
          <i class="bi bi-check-lg"></i> Approve Plan
        </button>
      </form>
      <form method="POST" action="/dashboard.php?page=installments" style="flex:1;">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="request_id" value="<?= $reviewing['id'] ?>">
        <input type="hidden" name="action" value="reject">
        <button class="btn" style="width:100%; background:var(--error); color:#fff;" type="submit">
          <i class="bi bi-x-lg"></i> Reject
        </button>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>