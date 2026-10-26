<?php
/**
 * views/lessor/overview.php
 * Presentation layer for the Lessor Overview.
 */
?>

<!-- HEADER & SEARCH BAR -->
<div class="rev-top-bar mb-24">
  <div class="rev-header-titles">
    <h2 class="page-title">Dashboard Overview</h2>
  </div>
  
  <div class="search-wrapper" style="width: 300px;">
    <!-- Pure SVG Search Icon -->
    <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="11" cy="11" r="8"></circle>
      <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
    </svg>
    <input id="overview-search" type="search" class="search-input" placeholder="Search tenants, tickets, or requests..." autocomplete="off">
  </div>
</div>

<!-- TOP ANALYTICS SECTION -->
<div class="analytics-grid">
  <!-- Rent Concessions Card -->
  <div class="analytics-card">
    <p class="analytics-card-title">Rent Concessions</p>
    <p class="analytics-card-subtitle"><?= $ir['total'] ?> installment request<?= $ir['total'] === 1 ? '' : 's' ?> on record all-time</p>
    
    <?php 
    $bars = [
        ['label' => 'Installments (30d)', 'val' => $ir['last_30d'], 'max' => 20, 'color' => 'var(--accent)', 'suf' => ''],
        ['label' => 'Approval rate', 'val' => $irApprovalRate, 'max' => 100, 'color' => 'var(--success)', 'suf' => '%'],
        ['label' => 'Pending requests', 'val' => $ir['pending'], 'max' => 10, 'color' => 'var(--primary)', 'suf' => ''],
        ['label' => 'Rejected requests', 'val' => $ir['rejected'], 'max' => 10, 'color' => 'var(--error)', 'suf' => ''],
    ];
    foreach ($bars as $b): $pctWidth = $b['max'] > 0 ? min(100, round(($b['val'] / $b['max']) * 100)) : 0; 
    ?>
      <div class="stat-bar-wrap">
        <div class="stat-bar-header">
          <span class="stat-bar-label"><?= $b['label'] ?></span>
          <span class="stat-bar-value"><?= $b['val'] ?><?= $b['suf'] ?></span>
        </div>
        <div class="stat-bar-track">
          <div class="stat-bar-fill" style="width: <?= $pctWidth ?>%; background: <?= $b['color'] ?>;"></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Facility Maintenance Card -->
  <div class="analytics-card">
    <p class="analytics-card-title">Facility Maintenance</p>
    <p class="analytics-card-subtitle"><?= $mt['total'] ?> ticket<?= $mt['total'] === 1 ? '' : 's' ?> on record all-time</p>
    
    <?php 
    $bars = [
        ['label' => 'Total tickets (30d)', 'val' => $mt['last_30d'], 'max' => 30, 'color' => 'var(--accent)', 'suf' => ''],
        ['label' => 'Completion rate', 'val' => $mtCompletionRate, 'max' => 100, 'color' => 'var(--success)', 'suf' => '%'],
        ['label' => 'Open tickets on track', 'val' => $mtOnTrackRate, 'max' => 100, 'color' => 'var(--primary)', 'suf' => '%'],
        ['label' => 'Currently overdue', 'val' => $mt['overdue'], 'max' => 10, 'color' => 'var(--error)', 'suf' => ''],
    ];
    foreach ($bars as $b): $pctWidth = $b['max'] > 0 ? min(100, round(($b['val'] / $b['max']) * 100)) : 0; 
    ?>
      <div class="stat-bar-wrap">
        <div class="stat-bar-header">
          <span class="stat-bar-label"><?= $b['label'] ?></span>
          <span class="stat-bar-value"><?= $b['val'] ?><?= $b['suf'] ?></span>
        </div>
        <div class="stat-bar-track">
          <div class="stat-bar-fill" style="width: <?= $pctWidth ?>%; background: <?= $b['color'] ?>;"></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Conflict Resolution Card -->
  <div class="analytics-card">
    <p class="analytics-card-title">Conflict Resolution</p>
    <p class="analytics-card-subtitle"><?= $v['total'] ?> violation<?= $v['total'] === 1 ? '' : 's' ?> on record all-time</p>
    
    <?php 
    $bars = [
        ['label' => 'Violations issued (30d)', 'val' => $v['last_30d'], 'max' => 20, 'color' => 'var(--warning)', 'suf' => ''],
        ['label' => 'Resolved at Strike 1', 'val' => $vResolvedRate, 'max' => 100, 'color' => 'var(--success)', 'suf' => '%'],
        ['label' => 'Tenants at Strike 2+', 'val' => $v['strike2plus'], 'max' => 10, 'color' => 'var(--primary)', 'suf' => ''],
        ['label' => 'Evictions generated', 'val' => $v['evictions'], 'max' => 10, 'color' => 'var(--error)', 'suf' => ''],
    ];
    foreach ($bars as $b): $pctWidth = $b['max'] > 0 ? min(100, round(($b['val'] / $b['max']) * 100)) : 0; 
    ?>
      <div class="stat-bar-wrap">
        <div class="stat-bar-header">
          <span class="stat-bar-label"><?= $b['label'] ?></span>
          <span class="stat-bar-value"><?= $b['val'] ?><?= $b['suf'] ?></span>
        </div>
        <div class="stat-bar-track">
          <div class="stat-bar-fill" style="width: <?= $pctWidth ?>%; background: <?= $b['color'] ?>;"></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<hr class="mb-24" style="border:none; border-top:1px solid var(--border);">

<!-- PENDING PAYMENTS ALERT -->
<?php if ($pendingPaymentsCount > 0): ?>
  <div class="alert-banner">
    <div class="alert-content">
      <div class="alert-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 7V5a2 2 0 0 1 2-2h2"></path>
          <path d="M17 3h2a2 2 0 0 1 2 2v2"></path>
          <path d="M21 17v2a2 2 0 0 1-2 2h-2"></path>
          <path d="M7 21H5a2 2 0 0 1-2-2v-2"></path>
          <rect x="7" y="7" width="4" height="4" rx="1"></rect>
          <rect x="13" y="13" width="4" height="4" rx="1"></rect>
        </svg>
      </div>
      <div class="alert-text">
        <p class="alert-title">
          <?= $pendingPaymentsCount ?> Tenant Payment<?= $pendingPaymentsCount > 1 ? 's' : '' ?> Awaiting Verification (₱<?= number_format($pendingPaymentsTotal, 2) ?>)
        </p>
        <p class="alert-subtitle">Transfers submitted via InstaPay QR code require your receipt confirmation to update the tenant ledger.</p>
      </div>
    </div>
  
    <a href="/dashboard.php?page=payments" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
      Review Payments 
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="5" y1="12" x2="19" y2="12"></line>
        <polyline points="12 5 19 12 12 19"></polyline>
      </svg>
    </a>
  </div>
<?php endif; ?>

<!-- KPI GRID -->
<div class="kpi-grid">
  <div class="card">
    <div class="kpi-label">Active Leases</div>
    <div class="kpi-value"><?= $kpis['active_leases'] ?></div>
  </div>
  <div class="card">
    <div class="kpi-label">Pending Payments</div>
    <div class="kpi-value <?= $pendingPaymentsCount > 0 ? 'text-primary' : '' ?>">
      <?= $pendingPaymentsCount ?>
    </div>
  </div>
  <div class="card">
    <div class="kpi-label">Pending Applications</div>
    <div class="kpi-value"><?= $kpis['pending_apps'] ?></div>
  </div>
  <div class="card">
    <div class="kpi-label">Open Maintenance</div>
    <div class="kpi-value"><?= $kpis['open_tickets'] ?></div>
  </div>
</div>

<!-- DATA COLUMNS -->
<div class="two-col">
  
  <!-- INSTALLMENTS -->
  <div class="card">
    <p class="section-heading mb-12">Pending installment requests</p>
    <?php if (!$pendingInstallments): ?>
      <p class="empty-state-text">Nothing pending right now.</p>
    <?php else: foreach ($pendingInstallments as $r): 
        $searchBlob = strtolower(trim($r['tenant_name'] . ' ' . $r['reason'] . ' ' . $r['plan_label']));
    ?>
      <div class="list-row inst-row" data-search="<?= htmlspecialchars($searchBlob) ?>">
        <div>
          <p class="list-title"><?= htmlspecialchars($r['tenant_name']) ?></p>
          <p class="list-subtitle"><?= htmlspecialchars($r['reason']) ?></p>
        </div>
        <span class="badge badge-accent"><?= htmlspecialchars($r['plan_label']) ?></span>
      </div>
    <?php endforeach; endif; ?>
    
    <div id="inst-empty" class="hidden-row empty-state-text text-center p-24">No requests match your search.</div>
    <?php if (count($pendingInstallments) > 5): ?>
      <div id="inst-pagination" class="pagination-bar mt-16" style="border-top: none; padding-top: 0;">
        <div id="inst-controls" class="page-controls"></div>
      </div>
    <?php endif; ?>
  </div>

  <!-- MAINTENANCE -->
  <div class="card">
    <p class="section-heading mb-12">Maintenance SLA watch</p>
    <?php if (!$openTicketRows): ?>
      <p class="empty-state-text">No open tickets.</p>
    <?php else: foreach ($openTicketRows as $t): 
        $searchBlob = strtolower(trim($t['title'] . ' ' . $t['tenant_name'] . ' ' . $ticketStages[$t['stage']]));
    ?>
      <div class="list-row maint-row" data-search="<?= htmlspecialchars($searchBlob) ?>">
        <div>
          <p class="list-title"><?= htmlspecialchars($t['title']) ?></p>
          <p class="list-subtitle"><?= htmlspecialchars($t['tenant_name']) ?> · <?= $ticketStages[$t['stage']] ?></p>
        </div>
      </div>
    <?php endforeach; endif; ?>
    
    <div id="maint-empty" class="hidden-row empty-state-text text-center p-24">No tickets match your search.</div>
    <?php if (count($openTicketRows) > 5): ?>
      <div id="maint-pagination" class="pagination-bar mt-16" style="border-top: none; padding-top: 0;">
        <div id="maint-controls" class="page-controls"></div>
      </div>
    <?php endif; ?>
  </div>
  
</div>

<!-- PAGINATION & SEARCH LOGIC -->
<script>
(function() {
  var searchInput = document.getElementById('overview-search');
  
  function setupList(rowClass, emptyId, paginationId, controlsId) {
    var rows = Array.prototype.slice.call(document.querySelectorAll('.' + rowClass));
    var emptyMsg = document.getElementById(emptyId);
    var paginationBar = document.getElementById(paginationId);
    var controls = document.getElementById(controlsId);
    var perPage = 5;
    var currentPage = 1;
    
    if (rows.length === 0) return;

    function filterAndPaginate() {
      var q = searchInput ? searchInput.value.trim().toLowerCase() : '';
      
      var matchingRows = rows.filter(function(row) {
        var text = (row.getAttribute('data-search') || '').toLowerCase();
        return !q || text.indexOf(q) !== -1;
      });

      var totalPages = Math.max(1, Math.ceil(matchingRows.length / perPage));
      if (currentPage > totalPages) currentPage = totalPages;

      rows.forEach(function(row) { row.classList.add('hidden-row'); });

      var start = (currentPage - 1) * perPage;
      matchingRows.slice(start, start + perPage).forEach(function(row) {
        row.classList.remove('hidden-row');
      });

      if (emptyMsg) {
        if (matchingRows.length === 0 && rows.length > 0) {
          emptyMsg.classList.remove('hidden-row');
        } else {
          emptyMsg.classList.add('hidden-row');
        }
      }

      if (paginationBar && controls) {
        if (matchingRows.length <= perPage) {
          paginationBar.classList.add('hidden-row');
        } else {
          paginationBar.classList.remove('hidden-row');
          renderControls(matchingRows.length, totalPages);
        }
      }
    }

    function renderControls(total, totalPages) {
      controls.innerHTML = '';
      
      var prev = document.createElement('button');
      prev.type = 'button';
      prev.className = 'page-btn';
      prev.innerHTML = '&laquo;';
      prev.disabled = currentPage === 1;
      prev.onclick = function() { if (currentPage > 1) { currentPage--; filterAndPaginate(); } };
      controls.appendChild(prev);

      for (var i = 1; i <= totalPages; i++) {
        (function(p) {
          var btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'page-btn';
          if (p === currentPage) btn.classList.add('active');
          btn.textContent = p;
          btn.onclick = function() { currentPage = p; filterAndPaginate(); };
          controls.appendChild(btn);
        })(i);
      }

      var next = document.createElement('button');
      next.type = 'button';
      next.className = 'page-btn';
      next.innerHTML = '&raquo;';
      next.disabled = currentPage === totalPages;
      next.onclick = function() { if (currentPage < totalPages) { currentPage++; filterAndPaginate(); } };
      controls.appendChild(next);
    }

    if (searchInput) {
      searchInput.addEventListener('input', function() {
        currentPage = 1;
        filterAndPaginate();
      });
    }
    
    filterAndPaginate();
  }

  // Initialize both lists independently
  setupList('inst-row', 'inst-empty', 'inst-pagination', 'inst-controls');
  setupList('maint-row', 'maint-empty', 'maint-pagination', 'maint-controls');
})();
</script>