<?php
/**
 * views/superadmin/overview.php
 * Presentation layer for the Super Admin Overview.
 */

// Safely capture GET params for the UI (Controller handles actual filtering)
$searchQuery = $_GET['search'] ?? '';
$filterType = $_GET['type'] ?? '';
?>

<!-- HEADER & TOOLBAR IN ONE ROW -->
<link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/style.css') ?>">

<div class="rev-top-bar">
  <div class="rev-header-titles">
    <h2 class="page-title">Platform Overview</h2>
  </div>

  <form method="GET" action="/dashboard.php" class="rev-toolbar">
    <input type="hidden" name="page" value="overview">
    
    <div class="search-wrapper">
      <i class="bi bi-search search-icon"></i>
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
    <div class="kpi-icon-box blue"><i class="bi bi-people"></i></div>
    <div class="kpi-details">
      <span class="kpi-title">Total Lessors</span>
      <h3 class="kpi-amount"><?= $totalLessors ?></h3>
      <div class="kpi-subtext">Verified property owners</div>
    </div>
  </div>
  
  <div class="card kpi-enhanced">
    <div class="kpi-icon-box green"><i class="bi bi-person-check"></i></div>
    <div class="kpi-details">
      <span class="kpi-title">Total Lessees</span>
      <h3 class="kpi-amount"><?= $totalLessees ?></h3>
      <div class="kpi-subtext">Active tenants</div>
    </div>
  </div>
  
  <div class="card kpi-enhanced">
    <div class="kpi-icon-box orange"><i class="bi bi-hourglass-split"></i></div>
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