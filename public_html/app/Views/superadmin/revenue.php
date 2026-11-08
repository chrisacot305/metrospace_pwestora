<?php
/**
 * app/Views/superadmin/revenue.php
 * Presentation layer for Revenue & Commission.
 */
?>

<!-- HEADER & TOOLBAR IN ONE ROW -->
<link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(APP_ROOT . '/assets/css/style.css') ?>">

<div class="rev-top-bar">
  <div class="rev-header-titles">
    <h2 class="page-title">Revenue &amp; Commission</h2>
  </div>

  <form method="GET" action="/dashboard.php" class="rev-toolbar">
    <input type="hidden" name="page" value="revenue">
    
    <div class="search-wrapper">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="search-icon"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
      <input type="search" name="search" class="search-input" placeholder="Search lessor or property..." value="<?= htmlspecialchars($searchQuery ?? '') ?>">
    </div>

    <select name="type" class="filter-select" onchange="this.form.submit()">
      <option value="">All Property Types</option>
      <?php foreach ($availableTypes as $t): ?>
        <option value="<?= htmlspecialchars($t) ?>" <?= $filterType === $t ? 'selected' : '' ?>>
          <?= htmlspecialchars($t) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <?php if ($filterType || $searchQuery): ?>
      <a href="/dashboard.php?page=revenue" class="btn btn-outline btn-sm">Clear</a>
    <?php endif; ?>
    
    <button type="submit" class="hidden-row"></button>
  </form>
</div>

<!-- ENHANCED KPI GRID -->
<div class="kpi-grid">
  <div class="card kpi-enhanced">
    <div class="kpi-icon-box blue">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"></rect><circle cx="12" cy="12" r="2"></circle><path d="M6 12h.01M18 12h.01"></path></svg>
    </div>
    <div class="kpi-details">
      <span class="kpi-title">Monthly Recurring Rent</span>
      <h3 class="kpi-amount">₱<?= number_format($mrr, 0) ?></h3>
      <div class="kpi-subtext">
        <?= ($filterType || $searchQuery) ? 'Based on filtered leases' : 'Across all active leases' ?>
      </div>
    </div>
  </div>
  
  <div class="card kpi-enhanced">
    <div class="kpi-icon-box green">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4"></path><path d="M4 6v12c0 1.1.9 2 2 2h14v-4"></path><path d="M18 12a2 2 0 0 0-2 2c0 1.1.9 2 2 2h4v-4h-4z"></path></svg>
    </div>
    <div class="kpi-details">
      <span class="kpi-title">Est. Monthly Commission</span>
      <h3 class="kpi-amount">₱<?= number_format($estCommission, 0) ?></h3>
      <div class="kpi-subtext"><?= rtrim(rtrim(number_format($feePercent, 1), '0'), '.') ?>% of MRR</div>
    </div>
  </div>
  
  <div class="card kpi-enhanced">
    <div class="kpi-icon-box purple">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect><path d="M9 22v-4h6v4"></path><path d="M8 6h.01"></path><path d="M16 6h.01"></path><path d="M12 6h.01"></path><path d="M12 10h.01"></path><path d="M12 14h.01"></path><path d="M16 10h.01"></path><path d="M16 14h.01"></path><path d="M8 10h.01"></path><path d="M8 14h.01"></path></svg>
    </div>
    <div class="kpi-details">
      <span class="kpi-title">Active Leases</span>
      <h3 class="kpi-amount"><?= $activeLeases ?></h3>
    </div>
  </div>
  
  <div class="card kpi-enhanced">
    <div class="kpi-icon-box orange">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
    </div>
    <div class="kpi-details">
      <span class="kpi-title">Avg. Lease Value</span>
      <h3 class="kpi-amount">₱<?= number_format($avgRent, 0) ?></h3>
    </div>
  </div>
</div>

<div class="two-col">
  <!-- REVENUE BY TYPE -->
  <div class="card">
    <h3 class="section-heading">Monthly rent by property type</h3>
    <?php if (!$byType): ?>
      <p class="empty-state-text">No active leases match this filter.</p>
    <?php else: foreach ($byType as $i => $row): 
        $pctWidth = $typeMax > 0 ? min(100, round(($row['total'] / $typeMax) * 100)) : 0;
        $color = $barColors[$i % count($barColors)];
    ?>
      <div class="rev-bar-wrap">
        <div class="rev-bar-header">
          <span class="rev-bar-label"><?= htmlspecialchars($row['type']) ?></span>
          <span class="rev-bar-value">₱<?= number_format($row['total'], 0) ?></span>
        </div>
        <div class="rev-bar-track">
          <div class="rev-bar-fill" style="width: <?= $pctWidth ?>%; background: <?= $color ?>;"></div>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- REVENUE BY LESSOR -->
  <div class="card">
    <h3 class="section-heading">Top lessors by monthly rent</h3>
    <?php if (!$byLessor): ?>
      <p class="empty-state-text">No active leases match this filter.</p>
    <?php else: foreach ($byLessor as $i => $row): 
        $pctWidth = $lessorMax > 0 ? min(100, round(($row['total'] / $lessorMax) * 100)) : 0;
        $color = $barColors[$i % count($barColors)];
    ?>
      <div class="rev-bar-wrap">
        <div class="rev-bar-header">
          <span class="rev-bar-label"><?= htmlspecialchars($row['company_name']) ?></span>
          <span class="rev-bar-value">₱<?= number_format($row['total'], 0) ?></span>
        </div>
        <div class="rev-bar-track">
          <div class="rev-bar-fill" style="width: <?= $pctWidth ?>%; background: <?= $color ?>;"></div>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>