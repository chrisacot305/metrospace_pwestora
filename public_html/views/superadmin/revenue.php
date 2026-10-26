<?php
/**
 * views/superadmin/revenue.php
 * Presentation layer for Revenue & Commission.
 */
?>

<!-- HEADER & TOOLBAR IN ONE ROW -->
<link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/style.css') ?>">

<div class="rev-top-bar">
  <div class="rev-header-titles">
    <h2 class="page-title">Revenue &amp; Commission</h2>
  </div>

  <form method="GET" action="/dashboard.php" class="rev-toolbar">
    <input type="hidden" name="page" value="revenue">
    
    <div class="search-wrapper">
      <i class="bi bi-search search-icon"></i>
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
    <div class="kpi-icon-box blue"><i class="bi bi-cash-stack"></i></div>
    <div class="kpi-details">
      <span class="kpi-title">Monthly Recurring Rent</span>
      <h3 class="kpi-amount">₱<?= number_format($mrr, 0) ?></h3>
      <div class="kpi-subtext">
        <?= ($filterType || $searchQuery) ? 'Based on filtered leases' : 'Across all active leases' ?>
      </div>
    </div>
  </div>
  
  <div class="card kpi-enhanced">
    <div class="kpi-icon-box green"><i class="bi bi-wallet2"></i></div>
    <div class="kpi-details">
      <span class="kpi-title">Est. Monthly Commission</span>
      <h3 class="kpi-amount">₱<?= number_format($estCommission, 0) ?></h3>
      <div class="kpi-subtext"><?= rtrim(rtrim(number_format($feePercent, 1), '0'), '.') ?>% of MRR</div>
    </div>
  </div>
  
  <div class="card kpi-enhanced">
    <div class="kpi-icon-box purple"><i class="bi bi-building"></i></div>
    <div class="kpi-details">
      <span class="kpi-title">Active Leases</span>
      <h3 class="kpi-amount"><?= $activeLeases ?></h3>
    </div>
  </div>
  
  <div class="card kpi-enhanced">
    <div class="kpi-icon-box orange"><i class="bi bi-tags"></i></div>
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