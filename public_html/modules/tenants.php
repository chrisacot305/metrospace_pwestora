<?php
/**
 * modules/tenants.php  (Lessor → "Tenants")
 * Roster of active tenants with their negotiated checklist history,
 * counter-offer records, conversation dialogue, and next due date calculation.
 */
require_once __DIR__ . '/../includes/lease_checklist.php';
$lessorId = $user['id'];
$error = '';

$query = "SEL" . "ECT t.id, t.tenant_name, t.created_at, t.start_date, t.end_date, t.application_id, t.lessee_id, t.offline_email, t.offline_phone, p.name AS property_name, " .
         "a.term_months, a.rent, a.business_name, a.submitted_at, " .
         "a.checklist_negotiation, a.lessor_rebuttal, a.rebuttal_at, " .
         "u.email AS lessee_email, u.phone AS lessee_phone, " .
         "(SEL" . "ECT MAX(pay.paid_at) " .
         "FR" . "OM payments pay " .
         "WH" . "ERE pay.tenant_id = t.id " .
           "AND pay.lessor_id = t.lessor_id " .
           "AND pay.logged_by IS NOT NULL " .
           "AND (pay.note IS NULL OR pay.note NOT LI" . "KE '[DECLINED%') " .
         ") AS last_paid_at " .
         "FR" . "OM tenants t " .
         "JOIN properties p ON p.id = t.property_id " .
         "LEFT JOIN applications a ON a.id = t.application_id " .
         "LEFT JOIN users u ON u.id = t.lessee_id " .
         "WH" . "ERE t.lessor_id = ? " .
         "ORDER BY t.created_at DESC";

$tenants = $pdo->prepare($query);
$tenants->execute([$lessorId]);
$tenants = $tenants->fetchAll();

$now = new DateTime();
$today = new DateTime('today');
$totalActive = count($tenants);
$expiringSoon = 0;
$monthlyRentTotal = 0;

foreach ($tenants as &$t) {
    $t['rent'] = (float) ($t['rent'] ?? 0);
    $monthlyRentTotal += $t['rent'];

    $t['lease_end'] = $t['end_date'] ? new DateTime($t['end_date']) : null;
    $t['status_label'] = 'Active';
    $t['status_style'] = 'background:var(--success-soft); color:var(--success);';
    
    // Determine Move-in date (fallback to created_at if start_date is NULL)
    $t['move_in_date'] = $t['start_date'] ? new DateTime($t['start_date']) : new DateTime($t['created_at']);

    if ($t['lease_end']) {
        $daysLeft = (int) $now->diff($t['lease_end'])->format('%r%a');
        if ($daysLeft <= 30 && $daysLeft >= 0) {
            $expiringSoon++;
            $t['status_label'] = 'Expiring (' . $daysLeft . 'd)';
            $t['status_style'] = 'background:var(--warning-soft); color:var(--warning);';
        } elseif ($daysLeft < 0) {
            $t['status_label'] = 'Lease ended';
            $t['status_style'] = 'background:var(--bg); color:var(--ink-500);';
        }
    } elseif ($t['term_months']) {
        // Fallback calculation if end_date is null but term_months exists
        $end = (clone $t['move_in_date']);
        $end->modify('+' . (int) $t['term_months'] . ' months');
        $t['lease_end'] = $end;

        $daysLeft = (int) $now->diff($end)->format('%r%a');
        if ($daysLeft <= 30 && $daysLeft >= 0) {
            $expiringSoon++;
            $t['status_label'] = 'Expiring (' . $daysLeft . 'd)';
            $t['status_style'] = 'background:var(--warning-soft); color:var(--warning);';
        } elseif ($daysLeft < 0) {
            $t['status_label'] = 'Lease ended';
            $t['status_style'] = 'background:var(--bg); color:var(--ink-500);';
        }
    }

    $t['last_payment_date'] = !empty($t['last_paid_at']) ? new DateTime($t['last_paid_at']) : null;
    $baseDueDate = $t['last_payment_date'] ? clone $t['last_payment_date'] : clone $t['move_in_date'];
    $nextDue = (clone $baseDueDate)->modify('+1 month');

    if ($t['status_label'] === 'Lease ended') {
        $t['next_due_date'] = null;
        $t['is_overdue'] = false;
    } else {
        $t['next_due_date'] = $nextDue;
        $t['is_overdue'] = $nextDue < $today;
    }

    $negotiation = [];
    if (!empty($t['checklist_negotiation'])) {
        $decoded = json_decode($t['checklist_negotiation'], true);
        if (is_array($decoded)) $negotiation = $decoded;
    }

    $clauseKeys = array_filter(array_keys($negotiation), function($k) { return $k !== 'messages'; });
    $totalClauses = !empty($clauseKeys) ? count($clauseKeys) : count($LEASE_CHECKLIST_ITEMS);
    $counterOffers = [];
    $agreedCount = 0;

    if (empty($clauseKeys)) {
        $agreedCount = $totalClauses;
    } else {
        foreach ($clauseKeys as $key) {
            $clauseStatus = $negotiation[$key];
            $isAgreed = true;
            $note = '';
            if (is_array($clauseStatus)) {
                $isAgreed = !isset($clauseStatus['agreed']) || $clauseStatus['agreed'] === true || $clauseStatus['agreed'] === 1 || $clauseStatus['agreed'] === 'true';
                $note = trim($clauseStatus['rebuttal_note'] ?? $clauseStatus['counter_note'] ?? $clauseStatus['note'] ?? '');
            }
            if (!$isAgreed || !empty($note)) {
                $label = $LEASE_CHECKLIST_ITEMS[$key]['label'] ?? ucwords(str_replace('_', ' ', $key));
                $counterOffers[] = [
                    'key'   => $key,
                    'label' => $label,
                    'note'  => !empty($note) ? $note : 'Counter-offer proposed.',
                ];
            } else {
                $agreedCount++;
            }
        }
    }
    $t['checklist_total'] = $totalClauses;
    $t['checklist_agreed'] = $agreedCount;
    $t['counter_offers'] = $counterOffers;
    $t['negotiation'] = $negotiation;
}
unset($t);

$viewTermsId = isset($_GET['view_terms']) ? (int) $_GET['view_terms'] : null;
$viewingTenant = null;
if ($viewTermsId) {
    foreach ($tenants as $t) {
        if ((int) $t['id'] === $viewTermsId) { $viewingTenant = $t; break; }
    }
}
?>
<h2 style="margin:0 0 4px;">Tenants</h2>
<p style="color:var(--ink-500); margin:0 0 20px;">Your active roster and agreed checklist records.</p>

<?php if ($error): ?>
  <div class="error-msg"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="kpi-grid">
  <div class="card">
    <div class="kpi-label">Total Active Tenants</div>
    <div class="kpi-value"><?= $totalActive ?></div>
  </div>
  <div class="card">
    <div class="kpi-label">Expiring Leases (30d)</div>
    <div class="kpi-value"><?= $expiringSoon ?></div>
    <?php if ($expiringSoon > 0): ?>
      <div style="font-size:11px; color:var(--warning); margin-top:2px;">Requires attention</div>
    <?php endif; ?>
  </div>
  <div class="card">
    <div class="kpi-label">Total Monthly Rent</div>
    <div class="kpi-value">₱<?= number_format($monthlyRentTotal, 0) ?></div>
  </div>
</div>

<div class="card" style="padding:0; overflow:hidden;">
  <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; padding:14px 16px; border-bottom:1px solid var(--border);">
    <div style="flex:1; min-width:220px; position:relative;">
      <i class="bi bi-search" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--ink-400); font-size:13px;"></i>
      <input id="tenant-search" type="search" placeholder="Search tenant, business, property, or contact…"
             autocomplete="off"
             style="width:100%; box-sizing:border-box; padding:8px 12px 8px 34px; border:1px solid var(--border); border-radius:8px; font:inherit; font-size:13px; background:var(--bg); color:var(--ink-900);">
    </div>
    <select id="tenant-status-filter"
            style="padding:8px 12px; border:1px solid var(--border); border-radius:8px; font:inherit; font-size:13px; background:#fff; color:var(--ink-900); min-width:160px;">
      <option value="">All statuses</option>
      <option value="active">Active</option>
      <option value="expiring">Expiring (30d)</option>
      <option value="ended">Lease ended</option>
    </select>
    <span id="tenant-filter-count" style="font-size:12.5px; color:var(--ink-500); white-space:nowrap;"></span>
  </div>

  <div class="table-scroll">
  <table>
    <thead>
      <tr>
        <th>Tenant</th>
        <th>Property</th>
        <th>Checklist</th>
        <th>Lease Status</th>
        <th>Contact</th>
        <th>Move-in Date</th>
        <th>Next Due Date</th>
        <th>End Date</th>
        <th>Rent</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$tenants): ?>
        <tr><td colspan="10" style="text-align:center; color:var(--ink-500);">No active tenants yet — approve an application to get started.</td></tr>
      <?php else: foreach ($tenants as $t):
        $statusKey = 'active';
        if ($t['status_label'] === 'Lease ended') $statusKey = 'ended';
        elseif (strpos($t['status_label'], 'Expiring') === 0) $statusKey = 'expiring';
        $searchBlob = strtolower(trim(implode(' ', array_filter([
            $t['tenant_name'],
            $t['business_name'] ?? '',
            $t['property_name'] ?? '',
            $t['lessee_email'] ?? '',
            $t['lessee_phone'] ?? '',
            $t['offline_email'] ?? '',
            $t['offline_phone'] ?? '',
            $t['status_label'],
        ]))));
      ?>
        <tr class="tenant-row" data-status="<?= htmlspecialchars($statusKey) ?>" data-search="<?= htmlspecialchars($searchBlob) ?>">
          <td style="font-weight:600;">
            <?= htmlspecialchars($t['tenant_name']) ?>
            <?php if (!empty($t['business_name'])): ?>
              <div style="font-size:11.5px; color:var(--ink-500); font-weight:400;"><?= htmlspecialchars($t['business_name']) ?></div>
            <?php endif; ?>
          </td>
          <td style="color:var(--ink-500);"><?= htmlspecialchars($t['property_name']) ?></td>
          <td>
            <?php if (empty($t['counter_offers'])): ?>
              <span class="badge" style="background:var(--success-soft); color:var(--success); font-weight:600;"><?= $t['checklist_agreed'] ?>/<?= $t['checklist_total'] ?> Agreed</span>
            <?php else: ?>
              <span class="badge" style="background:var(--warning-soft); color:var(--warning); font-weight:600;"><?= $t['checklist_agreed'] ?>/<?= $t['checklist_total'] ?> (<?= count($t['counter_offers']) ?> Counter)</span>
            <?php endif; ?>
          </td>
          <td><span class="badge" style="<?= $t['status_style'] ?>"><?= htmlspecialchars($t['status_label']) ?></span></td>
          <td style="color:var(--ink-500); font-size:12.5px;">
            <?php 
              $displayEmail = $t['lessee_id'] ? $t['lessee_email'] : $t['offline_email'];
              $displayPhone = $t['lessee_id'] ? $t['lessee_phone'] : $t['offline_phone'];
            ?>
            <?php if ($displayEmail || $displayPhone): ?>
              <?= htmlspecialchars((string)$displayEmail) ?><?php if ($displayPhone && $displayEmail) echo '<br>'; ?><?= htmlspecialchars((string)$displayPhone) ?>
            <?php else: ?>
              <span style="color:var(--ink-300);">Not on file</span>
            <?php endif; ?>
          </td>
          <td style="color:var(--ink-500);"><?= $t['move_in_date']->format('M j, Y') ?></td>
          <td>
            <?php if ($t['next_due_date']): ?>
              <div style="font-weight:600; color:<?= $t['is_overdue'] ? 'var(--error)' : 'var(--ink-900)' ?>;">
                <?= $t['next_due_date']->format('M j, Y') ?>
                <?php if ($t['is_overdue']): ?>
                  <span class="badge badge-rejected" style="font-size:10px; padding:2px 6px; margin-left:4px;">Overdue</span>
                <?php endif; ?>
              </div>
              <div style="font-size:11px; color:var(--ink-400); margin-top:2px;">
                <?php if ($t['last_payment_date']): ?>
                  Last paid: <?= $t['last_payment_date']->format('M j, Y') ?>
                <?php else: ?>
                  No payment yet
                <?php endif; ?>
              </div>
            <?php else: ?>
              <span style="color:var(--ink-300);">—</span>
            <?php endif; ?>
          </td>
          <td style="color:var(--ink-500);">
            <?= $t['lease_end'] ? $t['lease_end']->format('M j, Y') : '<span style="color:var(--ink-300);">Unknown</span>' ?>
          </td>
          <td style="color:var(--ink-500);">₱<?= number_format($t['rent'], 0) ?></td>
          <td style="position:relative; text-align:right; padding-right:16px;">
                       <button type="button" class="btn" onclick="toggleActionDrop(event, 'action-drop-<?= $t['id'] ?>')" 
                    style="background:var(--bg); color:var(--ink-900); padding:6px 12px; border:1px solid var(--border); border-radius:6px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center;">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="1"></circle>
                <circle cx="19" cy="12" r="1"></circle>
                <circle cx="5" cy="12" r="1"></circle>
              </svg>
            </button>
            
            <!-- Dropdown Menu -->
            <div id="action-drop-<?= $t['id'] ?>" class="action-dropdown-menu" 
                 style="display:none; position:absolute; right:16px; top:36px; background:#fff; border:1px solid var(--border); border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.1); z-index:100; min-width:140px; text-align:left; overflow:hidden;">
              
              <a href="/dashboard.php?page=edit_tenant&id=<?= $t['id'] ?>" 
                 style="display:block; padding:10px 14px; color:var(--ink-900); text-decoration:none; font-size:12.5px; border-bottom:1px solid var(--bg);">
                <i class="bi bi-pencil" style="margin-right:8px; color:var(--ink-500);"></i> Edit
              </a>
              
              <a href="/dashboard.php?page=tenants&view_terms=<?= $t['id'] ?>" 
                 style="display:block; padding:10px 14px; color:var(--ink-900); text-decoration:none; font-size:12.5px; border-bottom:1px solid var(--bg);">
                <i class="bi bi-file-text" style="margin-right:8px; color:var(--ink-500);"></i> Terms
              </a>

              <a href="/dashboard.php?page=payments&tenant=<?= $t['id'] ?>" 
                 style="display:block; padding:10px 14px; color:var(--ink-900); text-decoration:none; font-size:12.5px; border-bottom:1px solid var(--bg);">
                <i class="bi bi-clock-history" style="margin-right:8px; color:var(--ink-500);"></i> History
              </a>
              
              <a href="/dashboard.php?page=messages&tenant=<?= $t['id'] ?>" 
                 style="display:block; padding:10px 14px; color:var(--ink-900); text-decoration:none; font-size:12.5px; border-bottom:1px solid var(--bg);">
                <i class="bi bi-chat-dots" style="margin-right:8px; color:var(--ink-500);"></i> Message
              </a>

              <a href="/dashboard.php?page=evict_tenant&id=<?= $t['id'] ?>" 
                 onclick="return confirm('Are you sure you want to initiate eviction for this tenant?');"
                 style="display:block; padding:10px 14px; color:var(--error); text-decoration:none; font-size:12.5px; background:#fff5f5;">
                <i class="bi bi-door-open" style="margin-right:8px; color:var(--error);"></i> Evict
              </a>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
        <tr id="tenant-empty-filter" style="display:none;">
          <td colspan="10" style="text-align:center; color:var(--ink-500);">No tenants match your search or filter.</td>
        </tr>
    </tbody>
  </table>
  </div>

  <?php if ($tenants): ?>
  <div id="tenant-pagination-bar" style="display:flex; justify-content:center; align-items:center; padding:12px 16px; border-top:1px solid var(--border); background:var(--surface);">
    <div id="tenant-page-controls" style="display:flex; gap:6px; align-items:center; justify-content:center;"></div>
  </div>
  <?php endif; ?>
</div>

<?php if ($tenants): ?>
<script>
(function () {
  var search = document.getElementById('tenant-search');
  var status = document.getElementById('tenant-status-filter');
  var count = document.getElementById('tenant-filter-count');
  var empty = document.getElementById('tenant-empty-filter');
  var pageControls = document.getElementById('tenant-page-controls');
  var paginationBar = document.getElementById('tenant-pagination-bar');
  var rows = Array.prototype.slice.call(document.querySelectorAll('.tenant-row'));
  var total = rows.length;

  var currentPage = 1;
  var perPage = 5; 

  function renderPagination(totalMatching, totalPages) {
    if (!paginationBar) return;
    if (totalMatching === 0) {
      paginationBar.style.display = 'none';
      return;
    }
    paginationBar.style.display = 'flex';
    if (!pageControls) return;
    pageControls.innerHTML = '';

    var prevBtn = document.createElement('button');
    prevBtn.type = 'button';
    prevBtn.innerHTML = '<i class="bi bi-chevron-left"></i> Prev';
    prevBtn.disabled = currentPage === 1;
    prevBtn.style.cssText = 'padding:5px 10px; font-size:12px; border:1px solid var(--border); border-radius:6px; background:' + (currentPage === 1 ? 'var(--bg)' : '#fff') + '; color:' + (currentPage === 1 ? 'var(--ink-300)' : 'var(--ink-900)') + '; cursor:' + (currentPage === 1 ? 'not-allowed' : 'pointer') + '; font-family:inherit; font-weight:500;';
    prevBtn.addEventListener('click', function () {
      if (currentPage > 1) { currentPage--; updateTable(); }
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
          currentPage = pageNum; updateTable();
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
      if (currentPage < totalPages) { currentPage++; updateTable(); }
    });
    pageControls.appendChild(nextBtn);
  }

  function updateTable() {
    var q = (search.value || '').trim().toLowerCase();
    var st = status.value || '';
    var matchingRows = rows.filter(function (row) {
      var matchQ = !q || (row.getAttribute('data-search') || '').indexOf(q) !== -1;
      var matchS = !st || row.getAttribute('data-status') === st;
      return matchQ && matchS;
    });

    var totalMatching = matchingRows.length;
    var totalPages = Math.max(1, Math.ceil(totalMatching / perPage));
    if (currentPage > totalPages) currentPage = totalPages;

    rows.forEach(function (row) { row.style.display = 'none'; });
    var startIndex = (currentPage - 1) * perPage;
    var endIndex = startIndex + perPage;
    matchingRows.slice(startIndex, endIndex).forEach(function (row) { row.style.display = ''; });

    if (empty) empty.style.display = totalMatching ? 'none' : '';
    if (count) { count.textContent = (q || st) ? (totalMatching + ' of ' + total) : (total + ' tenant' + (total === 1 ? '' : 's')); }

    renderPagination(totalMatching, totalPages);
  }

  search.addEventListener('input', function () { currentPage = 1; updateTable(); });
  status.addEventListener('change', function () { currentPage = 1; updateTable(); });
  updateTable();

  // Dropdown Toggle Logic
  window.toggleActionDrop = function(e, id) {
    e.stopPropagation();
    
    // Close all other dropdowns first
    var allDrops = document.querySelectorAll('.action-dropdown-menu');
    allDrops.forEach(function(drop) {
      if (drop.id !== id) drop.style.display = 'none';
    });
    
    // Toggle the clicked one
    var currentDrop = document.getElementById(id);
    if (currentDrop) {
      currentDrop.style.display = (currentDrop.style.display === 'none') ? 'block' : 'none';
    }
  };

  // Close dropdowns when clicking outside
  document.addEventListener('click', function() {
    var allDrops = document.querySelectorAll('.action-dropdown-menu');
    allDrops.forEach(function(drop) {
      drop.style.display = 'none';
    });
  });
})();
</script>
<?php endif; ?>

<?php if ($viewingTenant): 
  $conv = [];
  if (isset($viewingTenant['negotiation']['messages']) && is_array($viewingTenant['negotiation']['messages']) && !empty($viewingTenant['negotiation']['messages'])) {
      $conv = $viewingTenant['negotiation']['messages'];
  } else {
      if (!empty($viewingTenant['counter_offers'])) {
          $initialText = [];
          foreach ($viewingTenant['counter_offers'] as $co) {
              $initialText[] = $co['label'] . ': "' . $co['note'] . '"';
          }
          $conv[] = [
              'sender'      => 'lessee',
              'sender_name' => $viewingTenant['tenant_name'],
              'message'     => implode("\n", $initialText),
              'sent_at'     => $viewingTenant['submitted_at'] ?? $viewingTenant['created_at'],
          ];
      }
      if (!empty($viewingTenant['lessor_rebuttal'])) {
          $conv[] = [
              'sender'      => 'lessor',
              'sender_name' => 'Lessor',
              'message'     => $viewingTenant['lessor_rebuttal'],
              'sent_at'     => $viewingTenant['rebuttal_at'] ?? '',
          ];
      }
  }
?>
<div style="position:fixed; inset:0; background:rgba(0,0,0,.4); display:flex; justify-content:flex-end; z-index:50;">
  <div style="background:#fff; width:440px; max-width:92vw; height:100%; padding:24px; overflow-y:auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <h3 style="margin:0;">Tenant Lease Terms</h3>
      <a href="/dashboard.php?page=tenants" style="font-size:20px; color:var(--ink-500); text-decoration:none;">&times;</a>
    </div>

    <div class="card" style="margin-bottom:16px;">
      <p style="margin:4px 0; font-size:13px;"><strong>Tenant:</strong> <?= htmlspecialchars($viewingTenant['tenant_name']) ?></p>
      <?php if (!empty($viewingTenant['business_name'])): ?>
        <p style="margin:4px 0; font-size:13px;"><strong>Business:</strong> <?= htmlspecialchars($viewingTenant['business_name']) ?></p>
      <?php endif; ?>
      <p style="margin:4px 0; font-size:13px;"><strong>Property:</strong> <?= htmlspecialchars($viewingTenant['property_name']) ?></p>
      <p style="margin:4px 0; font-size:13px;"><strong>Lease term:</strong> <?= (int) $viewingTenant['term_months'] ?> months</p>
      <p style="margin:4px 0; font-size:13px;"><strong>Monthly rent:</strong> ₱<?= number_format($viewingTenant['rent'], 0) ?></p>
      <p style="margin:4px 0; font-size:13px;"><strong>Move-in Date:</strong> <?= $viewingTenant['move_in_date']->format('M j, Y') ?></p>
      <p style="margin:4px 0; font-size:13px;">
        <strong>Last Payment:</strong> 
        <?= $viewingTenant['last_payment_date'] ? $viewingTenant['last_payment_date']->format('M j, Y') : '<span style="color:var(--ink-400);">No payment recorded</span>' ?>
      </p>
      <p style="margin:4px 0; font-size:13px;">
        <strong>Next Due Date:</strong> 
        <?php if ($viewingTenant['next_due_date']): ?>
          <span style="font-weight:600; color:<?= $viewingTenant['is_overdue'] ? 'var(--error)' : 'var(--ink-900)' ?>;">
            <?= $viewingTenant['next_due_date']->format('M j, Y') ?>
            <?= $viewingTenant['is_overdue'] ? ' (Overdue)' : '' ?>
          </span>
        <?php else: ?>
          <span style="color:var(--ink-400);">—</span>
        <?php endif; ?>
      </p>
      <p style="margin:4px 0; font-size:13px;"><strong>Checklist:</strong> 
        <?php if (empty($viewingTenant['counter_offers'])): ?>
          <span style="color:var(--success); font-weight:600;"><?= $viewingTenant['checklist_agreed'] ?>/<?= $viewingTenant['checklist_total'] ?> (All Agreed)</span>
        <?php else: ?>
          <span style="color:var(--warning); font-weight:600;"><?= $viewingTenant['checklist_agreed'] ?>/<?= $viewingTenant['checklist_total'] ?> (<?= count($viewingTenant['counter_offers']) ?> Counter-Offer<?= count($viewingTenant['counter_offers']) > 1 ? 's' : '' ?>)</span>
        <?php endif; ?>
      </p>
    </div>

    <?php if (!empty($viewingTenant['counter_offers'])): ?>
      <div class="card" style="margin-bottom:16px; border-left:3px solid var(--warning);">
        <p style="margin:0 0 8px; font-size:12.5px; font-weight:700; color:var(--ink-900);">Agreed Counter-Offer Details:</p>
        <?php foreach ($viewingTenant['counter_offers'] as $co): ?>
          <div style="margin-bottom:8px; font-size:12px;">
            <strong style="color:var(--ink-800);">Clause: <?= htmlspecialchars($co['label']) ?></strong>
            <div style="color:var(--ink-600); margin-top:3px; padding:6px 8px; background:var(--bg); border-radius:4px;">
              Applicant Note: "<?= htmlspecialchars($co['note']) ?>"
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($conv)): ?>
      <div class="card" style="margin-bottom:16px;">
        <p style="margin:0 0 10px; font-size:12.5px; font-weight:700; color:var(--ink-900);">Negotiation Dialogue History</p>
        <div style="display:flex; flex-direction:column; gap:8px;">
          <?php foreach ($conv as $msg): 
            $isLessor = ($msg['sender'] ?? '') === 'lessor';
          ?>
            <div style="padding:8px 10px; border-radius:6px; background:<?= $isLessor ? '#f0f7ff' : 'var(--bg)' ?>; border:1px solid <?= $isLessor ? '#cce3ff' : 'var(--border)' ?>;">
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:3px;">
                <span style="font-size:11.5px; font-weight:700; color:<?= $isLessor ? '#0b57d0' : 'var(--ink-800)' ?>;">
                  <?= htmlspecialchars($msg['sender_name'] ?? ($isLessor ? 'Lessor' : 'Applicant')) ?> (<?= $isLessor ? 'Lessor' : 'Applicant' ?>)
                </span>
                <span style="font-size:10.5px; color:var(--ink-500);">
                  <?= !empty($msg['sent_at']) ? (new DateTime($msg['sent_at']))->format('M j, g:i A') : '' ?>
                </span>
              </div>
              <div style="font-size:12px; color:<?= $isLessor ? '#1a3c6d' : 'var(--ink-700)' ?>; line-height:1.4;">
                <?= nl2br(htmlspecialchars($msg['message'] ?? '')) ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div style="margin-top:20px;">
      <a class="btn btn-primary" style="width:100%; text-align:center; display:block; padding:10px 0; text-decoration:none;"
         href="/lease_contract.php?tenant_id=<?= $viewingTenant['id'] ?>" target="_blank">
        <i class="bi bi-file-earmark-text"></i> View / Print Official Contract
      </a>
    </div>
  </div>
</div>
<?php endif; ?>