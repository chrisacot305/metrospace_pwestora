<?php
/**
 * app/Views/lessor/tenants.php — HTML for the "tenants" screen (was the bottom half of modules/tenants.php).
 * Data comes from app/Controllers/Lessor/TenantsController.php
 */
?>
<h2 style="margin:0 0 4px;">Tenants</h2>

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
      <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--ink-400);">
        <circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>
      </svg>
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
              <?php $b = $t['billing'] ?? []; ?>
              <div style="font-weight:600; color:<?= $t['is_overdue'] ? 'var(--error)' : ($t['is_grace_period'] ? 'var(--warning)' : 'var(--ink-900)') ?>;">
                <?= $t['next_due_date']->format('M j, Y') ?>
                <?php if ($t['is_overdue']): ?>
                  <span class="badge badge-rejected" style="font-size:10px; padding:2px 6px; margin-left:4px;">Overdue<?= !empty($b['strike_level']) ? ' · S' . (int)$b['strike_level'] : '' ?></span>
                <?php elseif (!empty($t['is_grace_period'])): ?>
                  <span class="badge" style="background:var(--warning-soft); color:var(--warning); font-size:10px; padding:2px 6px; margin-left:4px;"><?= (int)$t['grace_days_remaining'] ?>d Grace</span>
                <?php elseif (($b['rent_status'] ?? '') === 'settled'): ?>
                  <span class="badge" style="background:var(--success-soft); color:var(--success); font-size:10px; padding:2px 6px; margin-left:4px;">Paid</span>
                <?php endif; ?>
              </div>
              <?php if (($b['total_due'] ?? 0) > 0 && ($t['is_overdue'] || !empty($b['penalty_amount']))): ?>
                <div style="font-size:11px; color:var(--error); font-weight:600; margin-top:2px;">
                  Due: ₱<?= number_format((float)$b['total_due'], 0) ?><?= !empty($b['outstanding_months_label']) ? ' (' . htmlspecialchars($b['outstanding_months_label']) . ')' : '' ?>
                </div>
              <?php endif; ?>
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
                 style="display:flex; align-items:center; padding:10px 14px; color:var(--ink-900); text-decoration:none; font-size:12.5px; border-bottom:1px solid var(--bg);">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px; color:var(--ink-500);"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg> 
                Edit
              </a>
              
              <a href="/dashboard.php?page=tenants&view_terms=<?= $t['id'] ?>" 
                 style="display:flex; align-items:center; padding:10px 14px; color:var(--ink-900); text-decoration:none; font-size:12.5px; border-bottom:1px solid var(--bg);">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px; color:var(--ink-500);"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg> 
                Terms
              </a>

              <a href="/dashboard.php?page=contract&tenant_id=<?= $t['id'] ?>" 
                 style="display:flex; align-items:center; padding:10px 14px; color:var(--ink-900); text-decoration:none; font-size:12.5px; border-bottom:1px solid var(--bg);">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px; color:var(--ink-500);"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                Contract
                <?php $cs = $contractStatuses[(int) $t['id']]['status'] ?? null; ?>
                <?php if ($cs === 'draft'): ?><span style="margin-left:auto; font-size:10px; color:var(--ink-500);">Draft</span>
                <?php elseif ($cs === 'finalized'): ?><span style="margin-left:auto; font-size:10px; color:#9a6412;">Sent</span>
                <?php elseif ($cs === 'accepted'): ?><span style="margin-left:auto; font-size:10px; color:var(--success);">Signed</span>
                <?php elseif ($cs === 'rejected'): ?><span style="margin-left:auto; font-size:10px; color:#dc2626; font-weight:700;">Declined</span><?php endif; ?>
              </a>

              <a href="/dashboard.php?page=payments&tenant=<?= $t['id'] ?>" 
                 style="display:flex; align-items:center; padding:10px 14px; color:var(--ink-900); text-decoration:none; font-size:12.5px; border-bottom:1px solid var(--bg);">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px; color:var(--ink-500);"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> 
                History
              </a>
              
              <a href="/dashboard.php?page=messages&tenant=<?= $t['id'] ?>" 
                 style="display:flex; align-items:center; padding:10px 14px; color:var(--ink-900); text-decoration:none; font-size:12.5px; border-bottom:1px solid var(--bg);">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px; color:var(--ink-500);"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg> 
                Message
              </a>

              <!-- EVICT ACTION (Now a secure POST Form pointing to the correct page!) -->
              <form method="POST" action="/dashboard.php?page=tenants" style="margin:0; padding:0;" onsubmit="return confirm('Are you sure you want to terminate this lease and evict the tenant?');">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="evict">
                <input type="hidden" name="tenant_id" value="<?= $t['id'] ?>">
                <button type="submit" style="display:flex; align-items:center; width:100%; padding:10px 14px; color:var(--error); text-decoration:none; font-size:12.5px; background:#fff5f5; border:none; cursor:pointer; text-align:left; font-family:inherit;">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px;">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                  </svg>
                  Evict
                </button>
              </form>
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
    prevBtn.innerHTML = 'Prev';
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
    nextBtn.innerHTML = 'Next';
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
          <span style="font-weight:600; color:<?= $viewingTenant['is_overdue'] ? 'var(--error)' : ($viewingTenant['is_grace_period'] ? 'var(--warning)' : 'var(--ink-900)') ?>;">
            <?= $viewingTenant['next_due_date']->format('M j, Y') ?>
            <?= $viewingTenant['is_overdue'] ? ' (Overdue)' : ($viewingTenant['is_grace_period'] ? ' (' . (int)$viewingTenant['grace_days_remaining'] . 'd Grace)' : '') ?>
          </span>
        <?php else: ?>
          <span style="color:var(--ink-400);">—</span>
        <?php endif; ?>
      </p>
      <?php if (!empty($viewingTenant['billing']) && (float)$viewingTenant['billing']['total_due'] > 0): ?>
        <p style="margin:4px 0; font-size:13px;">
          <strong>Live Balance Due:</strong>
          <span style="font-weight:600; color:<?= $viewingTenant['is_overdue'] ? 'var(--error)' : 'var(--ink-900)' ?>;">
            ₱<?= number_format((float)$viewingTenant['billing']['total_due'], 2) ?>
            <?php if (!empty($viewingTenant['billing']['outstanding_months_label'])): ?>
              (<?= htmlspecialchars($viewingTenant['billing']['outstanding_months_label']) ?>)
            <?php endif; ?>
          </span>
        </p>
      <?php endif; ?>
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

    <?php $vcs = $contractStatuses[(int) $viewingTenant['id']] ?? null; ?>
    <div style="margin-top:20px; display:flex; flex-direction:column; gap:8px;">
      <a class="btn btn-primary" style="width:100%; text-align:center; display:block; padding:10px 0; text-decoration:none;"
         href="/dashboard.php?page=contract&tenant_id=<?= $viewingTenant['id'] ?>">
        <?= !$vcs ? 'Customize Contract' : ($vcs['status'] === 'draft' ? 'Continue Contract Draft' : 'Open Contract (v' . $vcs['version'] . ')') ?>
      </a>
      <a class="btn" style="width:100%; text-align:center; display:block; padding:10px 0; text-decoration:none; background:var(--bg); border:1px solid var(--border); color:var(--ink-900);"
         href="/lease_contract.php?tenant_id=<?= $viewingTenant['id'] ?>" target="_blank">
        View / Print Contract
      </a>
    </div>
  </div>
</div>
<?php endif; ?>