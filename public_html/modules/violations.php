<?php
/**
 * modules/violations.php  (Lessor → "Violations")
 * PHP port of the Violations component. Strike level auto-escalates per
 * tenant (their prior highest strike + 1) exactly like the original.
 */
$lessorId = $user['id'];
$error = '';
$msg = $_GET['msg'] ?? '';

$CATEGORIES = [
    'Unpaid Rent Default (Past Due)',
    'Noise Complaint',
    'Improper Waste Disposal',
    'Late Operating Hours',
    'Unauthorized Modifications',
    'Lease Violation',
    'Safety Violation',
];

// Track if the popup drawer should be open
$issuing = isset($_GET['issue']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_violation'])) {
    csrf_check();
    $tenantId = (int) $_POST['tenant_id'];
    $category = $_POST['category'] ?? '';

    $check = $pdo->prepare('SELECT id FROM tenants WHERE id = ? AND lessor_id = ?');
    $check->execute([$tenantId, $lessorId]);

    if (!$check->fetch() || !in_array($category, $CATEGORIES, true)) {
        $error = 'Please select a tenant and a valid category.';
    } else {
        try {
            $maxStrike = $pdo->prepare('SELECT COALESCE(MAX(strike), 0) FROM violations WHERE tenant_id = ?');
            $maxStrike->execute([$tenantId]);
            $newStrike = (int) $maxStrike->fetchColumn() + 1;

            try {
                $pdo->exec("ALTER TABLE violations ADD COLUMN is_paid TINYINT(1) NOT NULL DEFAULT 0");
            } catch (Throwable $ignored) {}

            $fineAmount = $newStrike == 2 ? 500.00 : ($newStrike >= 3 ? 1500.00 : 0.00);

            $pdo->prepare('INSERT INTO violations (lessor_id, tenant_id, category, strike, fine_amount) VALUES (?, ?, ?, ?, ?)')
                ->execute([$lessorId, $tenantId, $category, $newStrike, $fineAmount]);

            // Ensure message thread exists between lessor and tenant
            $threadStmt = $pdo->prepare('SELECT id FROM message_threads WHERE lessor_id = ? AND tenant_id = ?');
            $threadStmt->execute([$lessorId, $tenantId]);
            $threadId = $threadStmt->fetchColumn();
            if (!$threadId) {
                $pdo->prepare('INSERT INTO message_threads (lessor_id, tenant_id) VALUES (?, ?)')->execute([$lessorId, $tenantId]);
                $threadId = (int) $pdo->lastInsertId();
            }

            $strikeBadge = "STRIKE $newStrike OF 3";
            $penaltyLine = $newStrike == 2
                ? "\nPenalty Injected: ₱500.00 late/infraction penalty added to billing statement."
                : ($newStrike >= 3
                    ? "\nPenalty Injected: ₱1,500.00 penalty & Formal Lease Termination / Eviction proceedings initiated."
                    : "\nAction Required: Immediate compliance with commercial property guidelines.");

            $msgBody = "⚠️ OFFICIAL NOTICE OF INFRACTION ($strikeBadge)\nCategory: $category$penaltyLine\n\nA formal citation has been officially recorded against your lease agreement. Please review the citation details and disciplinary policy under your account.";

            $pdo->prepare('INSERT INTO messages (thread_id, sender, notice_type, body) VALUES (?, "lessor", "Infraction Notice", ?)')
                ->execute([$threadId, $msgBody]);

            audit_log($pdo, $lessorId, 'Issued violation', "$category — strike $newStrike");

            $msg = 'issued';
            $issuing = false;
            unset($_GET['issue']);

            // Seamless transition without blank screen
            echo '<script>if(window.history.replaceState){window.history.replaceState(null,null,"/dashboard.php?page=violations&msg=issued");}</script>';
        } catch (Throwable $e) {
            error_log('Issue violation failed: ' . $e->getMessage());
            $error = 'Could not issue this violation — the error has been logged. Please try again.';
        }
    }
}

// Fetch violations joined with properties and tenants
$violations = $pdo->prepare(
    'SELECT v.*, t.tenant_name, p.name AS property_name 
     FROM violations v
     JOIN tenants t ON t.id = v.tenant_id
     LEFT JOIN properties p ON p.id = t.property_id
     WHERE v.lessor_id = ? ORDER BY v.issued_at DESC'
);
$violations->execute([$lessorId]);
$violations = $violations->fetchAll();

// Fetch properties for the dropdown
$properties = $pdo->prepare('SELECT id, name FROM properties WHERE lessor_id = ? ORDER BY name');
$properties->execute([$lessorId]);
$properties = $properties->fetchAll();

// Include property_id to allow cascading JS filtering
$tenants = $pdo->prepare('SELECT id, tenant_name, property_id FROM tenants WHERE lessor_id = ? ORDER BY tenant_name');
$tenants->execute([$lessorId]);
$tenants = $tenants->fetchAll();
?>

<!-- FLOATING TOAST NOTIFICATIONS -->
<?php if ($msg || ($error && !$issuing)): ?>
  <div id="toast-notification" style="position:fixed; top:24px; right:24px; z-index:9999; transition: opacity 0.5s ease, transform 0.5s ease; transform: translateY(0); opacity: 1;">
    <?php if ($msg === 'issued'): ?>
      <div class="success-msg" style="margin:0; box-shadow:0 4px 12px rgba(0,0,0,0.1); border-radius:8px; display:flex; align-items:center; gap:8px;">
        <i class="bi bi-check-circle-fill"></i>
        <span><strong>Violation Issued!</strong> The tenant has been notified and the strike recorded.</span>
      </div>
    <?php endif; ?>

    <?php if ($error && !$issuing): ?>
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

<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
  <div>
    <h2 style="margin:0 0 4px; font-weight:800; color:var(--ink-900);">Violations &amp; Escalation</h2>
    <p style="color:var(--ink-500); margin:0;">Every warning is documented, timestamped, and impossible to deny receipt of.</p>
  </div>
  <a href="/dashboard.php?page=violations&issue=1" class="btn" style="background:var(--error); color:#fff; display:inline-flex; align-items:center; gap:6px; padding:10px 18px; font-size:13px; font-weight:600; white-space:nowrap; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.1);">
    <i class="bi bi-exclamation-triangle"></i> Issue Violation
  </a>
</div>

<div class="card" style="padding:0; overflow:hidden; border-radius:16px; border:none; box-shadow:0 2px 12px rgba(0,0,0,0.03);">
  
  <!-- REAL-TIME FILTER BAR -->
  <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; padding:16px 20px; border-bottom:1px solid var(--border); background:var(--surface);">
    <div style="flex:1; min-width:220px; position:relative;">
      <i class="bi bi-search" style="position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--ink-400); font-size:13px;"></i>
      <input id="viol-search" type="search" placeholder="Search property, tenant, or category..."
             autocomplete="off"
             style="width:100%; box-sizing:border-box; padding:10px 12px 10px 38px; border:1px solid var(--border); border-radius:10px; font:inherit; font-size:13px; background:#fff; color:var(--ink-900);">
    </div>
    
    <select id="viol-strike-filter"
            style="padding:10px 14px; border:1px solid var(--border); border-radius:10px; font:inherit; font-size:13px; background:#fff; color:var(--ink-900); min-width:140px; cursor:pointer;">
      <option value="">All Strikes</option>
      <option value="1">Strike 1</option>
      <option value="2">Strike 2</option>
      <option value="3+">Strike 3+ (Eviction)</option>
    </select>
  </div>

  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>Property & Tenant</th>
          <th>Category</th>
          <th>Strike Level</th>
          <th>Date Issued</th>
          <th>Status</th>
          <th style="text-align:right;">Action</th>
        </tr>
      </thead>
      <tbody id="violations-table-body">
        <?php if (!$violations): ?>
          <tr>
            <td colspan="6" style="text-align:center; padding:40px 16px; color:var(--ink-500);">
              <i class="bi bi-shield-check" style="font-size:32px; color:var(--ink-300); display:block; margin-bottom:12px;"></i>
              No violations on record. Your tenants are behaving!
            </td>
          </tr>
        <?php else: foreach ($violations as $v): 
          $strikeLevel = $v['strike'] >= 3 ? '3+' : (string)$v['strike'];
          
          $searchBlob = strtolower(trim(implode(' ', array_filter([
              $v['property_name'] ?? '',
              $v['tenant_name'],
              $v['category'],
              'strike ' . $v['strike'],
              (new DateTime($v['issued_at']))->format('F j Y M d')
          ]))));
        ?>
          <tr class="viol-row" data-strike="<?= $strikeLevel ?>" data-search="<?= htmlspecialchars($searchBlob) ?>">
            
            <td>
              <div style="font-weight:700; color:var(--ink-900); font-size:14px;"><?= htmlspecialchars($v['tenant_name']) ?></div>
              <div style="color:var(--ink-500); font-size:12px; margin-top:2px;">
                <i class="bi bi-building" style="margin-right:4px;"></i><?= htmlspecialchars($v['property_name'] ?? 'Unknown Property') ?>
              </div>
            </td>
            
            <td style="color:var(--ink-900); font-weight:500; font-size:13.5px;">
              <?= htmlspecialchars($v['category']) ?>
            </td>
            
            <td>
              <?php
                $strikeClass = $v['strike'] >= 3 ? 'badge-rejected' : ($v['strike'] == 2 ? 'badge-pending' : '');
                $strikeStyle = $v['strike'] == 1 ? 'background:var(--accent-soft); color:var(--primary);' : '';
              ?>
              <span class="badge <?= $strikeClass ?>" style="<?= $strikeStyle ?>">Strike <?= $v['strike'] ?></span>
            </td>
            
            <td style="color:var(--ink-500); font-size:13px;">
              <?= (new DateTime($v['issued_at']))->format('M j, Y g:i A') ?>
            </td>
            
            <td>
              <?php if ($v['acknowledged']): ?>
                <span class="badge badge-approved" style="font-size:11px;">
                  <i class="bi bi-check-all" style="margin-right:2px; font-size:13px;"></i> Read
                </span>
              <?php else: ?>
                <span class="badge" style="background:#f1f3f4; color:var(--ink-500); font-size:11px;">Pending Read</span>
              <?php endif; ?>
            </td>
            
            <td style="text-align:right;">
              <?php if ($v['strike'] >= 3): ?>
                <a href="/eviction_notice.php?id=<?= $v['id'] ?>" target="_blank"
                   class="btn" style="background:var(--error); color:#fff; padding:6px 12px; font-size:12px; display:inline-flex; border-radius:6px; font-weight:600;">
                  <i class="bi bi-download" style="margin-right:4px;"></i> Eviction Notice
                </a>
              <?php else: ?>
                <span style="font-size:12px; color:var(--ink-300);">—</span>
              <?php endif; ?>
            </td>
            
          </tr>
        <?php endforeach; endif; ?>
          <tr id="viol-empty-filter" style="display:none;">
            <td colspan="6" style="text-align:center; padding:40px 16px; color:var(--ink-500);">No violations match your search.</td>
          </tr>
      </tbody>
    </table>
  </div>
  
  <!-- Pagination UI -->
  <?php if ($violations): ?>
  <div id="viol-pagination-bar" style="display:flex; justify-content:center; align-items:center; padding:16px; background:#fff; border-top:1px solid var(--border);">
    <div id="viol-page-controls" style="display:flex; gap:6px; align-items:center; justify-content:center;"></div>
  </div>
  <?php endif; ?>
</div>

<script>
(function () {
  var search = document.getElementById('viol-search');
  var strikeFilter = document.getElementById('viol-strike-filter');
  var rows = Array.prototype.slice.call(document.querySelectorAll('#violations-table-body .viol-row'));
  var emptyFilter = document.getElementById('viol-empty-filter');
  var total = rows.length;

  var paginationBar = document.getElementById('viol-pagination-bar');
  var pageControls = document.getElementById('viol-page-controls');
  var currentPage = 1;
  var perPage = 10;

  if (!search || !strikeFilter) return;

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
    prevBtn.style.cssText = 'padding:6px 12px; font-size:13px; border:1px solid var(--border); border-radius:8px; background:' + (currentPage === 1 ? 'var(--bg)' : '#fff') + '; color:' + (currentPage === 1 ? 'var(--ink-300)' : 'var(--ink-900)') + '; cursor:' + (currentPage === 1 ? 'not-allowed' : 'pointer') + '; font-weight:600; transition:all 0.2s;';
    prevBtn.addEventListener('click', function () {
      if (currentPage > 1) { currentPage--; filterFeed(); }
    });
    pageControls.appendChild(prevBtn);

    for (var p = 1; p <= totalPages; p++) {
      (function (pageNum) {
        var pageBtn = document.createElement('button');
        pageBtn.type = 'button';
        pageBtn.textContent = pageNum;
        var isActive = pageNum === currentPage;
        pageBtn.style.cssText = 'padding:6px 12px; font-size:13px; border:1px solid ' + (isActive ? 'var(--primary)' : 'var(--border)') + '; border-radius:8px; background:' + (isActive ? 'var(--primary)' : '#fff') + '; color:' + (isActive ? '#fff' : 'var(--ink-900)') + '; cursor:pointer; font-weight:' + (isActive ? '700' : '600') + '; min-width:36px; transition:all 0.2s;';
        pageBtn.addEventListener('click', function () {
          currentPage = pageNum; filterFeed();
        });
        pageControls.appendChild(pageBtn);
      })(p);
    }

    var nextBtn = document.createElement('button');
    nextBtn.type = 'button';
    nextBtn.innerHTML = 'Next <i class="bi bi-chevron-right"></i>';
    nextBtn.disabled = currentPage === totalPages;
    nextBtn.style.cssText = 'padding:6px 12px; font-size:13px; border:1px solid var(--border); border-radius:8px; background:' + (currentPage === totalPages ? 'var(--bg)' : '#fff') + '; color:' + (currentPage === totalPages ? 'var(--ink-300)' : 'var(--ink-900)') + '; cursor:' + (currentPage === totalPages ? 'not-allowed' : 'pointer') + '; font-weight:600; transition:all 0.2s;';
    nextBtn.addEventListener('click', function () {
      if (currentPage < totalPages) { currentPage++; filterFeed(); }
    });
    pageControls.appendChild(nextBtn);
  }

  function filterFeed() {
    var q = (search.value || '').trim().toLowerCase();
    var st = strikeFilter.value || '';
    
    // Filter matching rows
    var matchingRows = rows.filter(function (row) {
      var matchQ = !q || (row.getAttribute('data-search') || '').indexOf(q) !== -1;
      var matchS = !st || row.getAttribute('data-strike') === st;
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

    if (emptyFilter) {
      emptyFilter.style.display = (totalMatching === 0 && total > 0) ? '' : 'none';
    }

    renderPagination(totalMatching, totalPages);
  }

  search.addEventListener('input', function() { currentPage = 1; filterFeed(); });
  strikeFilter.addEventListener('change', function() { currentPage = 1; filterFeed(); });
  filterFeed();
})();
</script>

<?php if ($issuing || ($error && isset($_POST['issue_violation']))): ?>
<div style="position:fixed; inset:0; background:rgba(0,0,0,.4); display:flex; justify-content:flex-end; z-index:50;">
  <div style="background:#fff; width:440px; max-width:92vw; height:100%; padding:24px; overflow-y:auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <h3 style="margin:0;">Issue Violation</h3>
      <a href="/dashboard.php?page=violations" style="font-size:20px; color:var(--ink-500); text-decoration:none;">&times;</a>
    </div>

    <?php if ($error): ?>
      <div class="error-msg" style="margin-bottom:16px; border-radius:8px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$tenants): ?>
      <p style="font-size:13px; color:var(--ink-500);">You don't have any active tenants yet.</p>
    <?php else: ?>
      <form method="POST" action="/dashboard.php?page=violations" onsubmit="var btn = this.querySelector('button[type=submit]'); btn.disabled = true; btn.innerHTML = '<i class=\'bi bi-hourglass-split\'></i> Issuing...';">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="issue_violation" value="1">

        <div class="field">
          <label style="font-weight:600;">Property</label>
          <select id="viol_property_select" name="property_id" required style="width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; font-family:inherit;">
            <option value="">Select property…</option>
            <?php foreach ($properties as $p): ?>
              <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field" style="margin-top:16px;">
          <label style="font-weight:600;">Tenant</label>
          <select id="viol_tenant_select" name="tenant_id" required disabled style="width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; font-family:inherit;">
            <option value="">Select a property first…</option>
            <?php foreach ($tenants as $t): ?>
              <option value="<?= $t['id'] ?>" data-property="<?= $t['property_id'] ?>"><?= htmlspecialchars($t['tenant_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <p style="font-size:13px; font-weight:700; margin:20px 0 10px;">Select Violation Category</p>
        <div style="display:grid; grid-template-columns:1fr; gap:8px; margin-bottom:20px;">
          <?php foreach ($CATEGORIES as $c): ?>
            <label style="display:flex; align-items:center; padding:12px 14px; border-radius:8px; font-size:13px; font-weight:500; border:1px solid var(--border); cursor:pointer; transition:background 0.2s;">
              <input type="radio" name="category" value="<?= $c ?>" required style="margin-right:10px; transform:scale(1.2);"> <?= $c ?>
            </label>
          <?php endforeach; ?>
        </div>

        <p style="font-size:12.5px; color:var(--ink-500); margin-bottom:24px; line-height:1.5; background:var(--bg); padding:12px; border-radius:8px;">
          <i class="bi bi-info-circle" style="margin-right:4px;"></i> This creates a permanent digital citation. The tenant receives a notification with a read receipt — escalation strikes accumulate automatically per tenant.
        </p>

        <button class="btn btn-primary" style="width:100%; display:flex; justify-content:center; padding:12px; border-radius:8px; font-weight:600; background:var(--error); border:none;" type="submit">
          Issue &amp; Notify Tenant <i class="bi bi-arrow-right" style="margin-left:6px;"></i>
        </button>
      </form>

      <!-- JAVASCRIPT FOR CASCADING DROPDOWN -->
      <script>
        document.addEventListener('DOMContentLoaded', function() {
           var propSel = document.getElementById('viol_property_select');
           var tenSel = document.getElementById('viol_tenant_select');
           
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