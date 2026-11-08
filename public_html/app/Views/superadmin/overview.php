<?php
/**
 * app/Views/superadmin/overview.php
 * Presentation layer for the Super Admin Overview.
 */

// Safely capture GET params for the UI (Controller handles actual filtering)
$searchQuery = $_GET['search'] ?? '';
$filterType = $_GET['type'] ?? '';
?>

<!-- HEADER & TOOLBAR IN ONE ROW -->
<link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(APP_ROOT . '/assets/css/style.css') ?>">

<div class="rev-top-bar">
  <div class="rev-header-titles">
    <h2 class="page-title">Platform Overview</h2>
  </div>

  <form method="GET" action="/dashboard.php" class="rev-toolbar">
    <input type="hidden" name="page" value="overview">
    
    <div class="search-wrapper">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="search-icon"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
      <input type="search" name="search" class="search-input" placeholder="Search platform..." value="<?= htmlspecialchars($searchQuery) ?>">
    </div>

    <select name="type" class="filter-select" onchange="this.form.submit()">
      <option value="">All Accounts</option>
      <option value="lessor" <?= $filterType === 'lessor' ? 'selected' : '' ?>>Lessors</option>
      <option value="lessee" <?= $filterType === 'lessee' ? 'selected' : '' ?>>Lessees</option>
    </select>

    <?php if ($filterType || $searchQuery): ?>
      <a href="/dashboard.php?page=overview" class="btn btn-outline btn-sm">Clear</a>
    <?php endif; ?>
    
    <button type="submit" class="hidden-row"></button>
  </form>
</div>

<!-- ENHANCED KPI GRID -->
<div class="kpi-grid">
  <div class="card kpi-enhanced">
    <div class="kpi-icon-box blue">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
    </div>
    <div class="kpi-details">
      <span class="kpi-title">Total Lessors</span>
      <h3 class="kpi-amount"><?= $totalLessors ?></h3>
      <div class="kpi-subtext">Verified property owners</div>
    </div>
  </div>
  
  <div class="card kpi-enhanced">
    <div class="kpi-icon-box green">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><polyline points="17 11 19 13 23 9"></polyline></svg>
    </div>
    <div class="kpi-details">
      <span class="kpi-title">Total Lessees</span>
      <h3 class="kpi-amount"><?= $totalLessees ?></h3>
      <div class="kpi-subtext">Active tenants</div>
    </div>
  </div>
  
  <div class="card kpi-enhanced">
    <div class="kpi-icon-box orange">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 22h14"></path><path d="M5 2h14"></path><path d="M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22"></path><path d="M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2"></path></svg>
    </div>
    <div class="kpi-details">
      <span class="kpi-title">Pending Verifications</span>
      <h3 class="kpi-amount"><?= $totalPending ?></h3>
      <div class="kpi-subtext"><?= $pendingLessors ?> lessors · <?= $pendingProps ?> properties</div>
    </div>
  </div>
</div>

<!-- PENDING VERIFICATIONS LIST -->
<div class="card">
  <p class="section-heading">Awaiting verification</p>
  
  <?php foreach ($pendingLessorRows as $l): ?>
    <div class="pending-list-row">
      <p class="pending-list-text"><?= htmlspecialchars($l['company_name']) ?></p>
      <span class="badge badge-lessor">Lessor</span>
    </div>
  <?php endforeach; ?>
  
  <?php foreach ($pendingPropRows as $p): ?>
    <div class="pending-list-row">
      <p class="pending-list-text"><?= htmlspecialchars($p['name']) ?></p>
      <span class="badge badge-accent">Property</span>
    </div>
  <?php endforeach; ?>
  
  <?php if (!$pendingLessorRows && !$pendingPropRows): ?>
    <p class="empty-state-text text-center p-24">Nothing awaiting verification.</p>
  <?php endif; ?>
</div>