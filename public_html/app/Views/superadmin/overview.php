<?php
/**
 * app/Views/superadmin/overview.php
 * Presentation layer for the Super Admin Overview.
 * KPI cards open drill-down lists (rendered here, toggled with JS — no page reload).
 * Data comes from app/Controllers/SuperAdmin/OverviewController.php
 */
$initials = static function (?string $name): string {
    $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) { $out .= mb_strtoupper(mb_substr($p, 0, 1)); }
    return $out !== '' ? $out : '?';
};
$fmtDate = static fn(?string $d): string => $d ? date('M j, Y', strtotime($d)) : '—';
$tenantTag = static function (array $t): array {
    $st = strtolower((string) ($t['status'] ?? ''));
    if ($st === 'active') return ['Active', 'ok'];
    if ($st === '' ) return ['—', 'info'];
    if (in_array($st, ['ended', 'terminated', 'evicted', 'inactive', 'moved_out'], true)) return [str_replace('_', ' ', $st), 'bad'];
    return [str_replace('_', ' ', $st), 'warn'];
};
$chev = '<svg class="sa-row-chevron" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>';
$arrow = '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
?>

<div class="rev-top-bar">
  <div class="rev-header-titles">
    <h2 class="page-title">Platform Overview</h2>
  </div>

  <form method="GET" action="/dashboard.php" class="rev-toolbar">
    <input type="hidden" name="page" value="overview">
    <div class="search-wrapper">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="search-icon"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
      <input type="search" name="search" class="search-input" placeholder="Search lessors, lessees, properties…" value="<?= e($searchQuery) ?>">
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

<?php if ($searchResults !== null):
    $hits = count($searchResults['lessors']) + count($searchResults['lessees']) + count($searchResults['properties']);
?>
<!-- SEARCH RESULTS -->
<div class="card sa-results">
  <div class="sa-panel-head">
    <div>
      <h3>Search results for “<?= e($searchQuery) ?>”</h3>
      <p><?= $hits ?> match<?= $hits === 1 ? '' : 'es' ?><?= $filterType ? ' in ' . e($filterType) . 's' : '' ?></p>
    </div>
  </div>
  <div class="sa-list">
    <?php foreach ($searchResults['lessors'] as $l): $st = $l['status']; ?>
      <a class="sa-row" href="/dashboard.php?page=lessors&amp;review=<?= (int) $l['id'] ?>">
        <span class="sa-avatar"><?= e($initials($l['company_name'])) ?></span>
        <div class="sa-row-main">
          <div class="sa-row-title"><?= e($l['company_name']) ?></div>
          <div class="sa-row-sub">Lessor · <?= e($l['full_name']) ?> · <?= e($l['email']) ?></div>
        </div>
        <div class="sa-row-meta">
          <span><?= (int) $l['property_count'] ?> propert<?= (int) $l['property_count'] === 1 ? 'y' : 'ies' ?></span>
          <span class="sa-tag <?= $st === 'active' ? 'ok' : ($st === 'pending' ? 'warn' : 'bad') ?>"><?= e($st) ?></span>
          <?= $chev ?>
        </div>
      </a>
    <?php endforeach; ?>
    <?php foreach ($searchResults['lessees'] as $t): [$tl, $tc] = $tenantTag($t); ?>
      <div class="sa-row">
        <span class="sa-avatar green"><?= e($initials($t['tenant_name'])) ?></span>
        <div class="sa-row-main">
          <div class="sa-row-title"><?= e($t['tenant_name']) ?><?= $t['business_name'] ? ' · ' . e($t['business_name']) : '' ?></div>
          <div class="sa-row-sub">Lessee · <?= e($t['property_name'] ?? '—') ?> · <?= e($t['lessor_name'] ?? '—') ?></div>
        </div>
        <div class="sa-row-meta"><span class="sa-tag <?= $tc ?>"><?= e($tl) ?></span></div>
      </div>
    <?php endforeach; ?>
    <?php foreach ($searchResults['properties'] as $p): $st = $p['status']; ?>
      <a class="sa-row" href="/dashboard.php?page=properties&amp;review=<?= (int) $p['id'] ?>">
        <span class="sa-avatar orange"><?= e($initials($p['name'])) ?></span>
        <div class="sa-row-main">
          <div class="sa-row-title"><?= e($p['name']) ?></div>
          <div class="sa-row-sub">Property · <?= e($p['type']) ?> · <?= e($p['lessor_name']) ?> · <?= e($p['address']) ?></div>
        </div>
        <div class="sa-row-meta">
          <span class="sa-tag <?= $st === 'verified' ? 'ok' : ($st === 'pending' ? 'warn' : 'bad') ?>"><?= e($st) ?></span>
          <?= $chev ?>
        </div>
      </a>
    <?php endforeach; ?>
    <?php if ($hits === 0): ?>
      <div class="sa-empty">Nothing matched “<?= e($searchQuery) ?>”. Try a company name, a tenant, or a property.</div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- KPI CARDS (click to open the list below) -->
<div class="kpi-grid">
  <button type="button" class="card kpi-enhanced sa-kpi" data-panel="lessors" aria-expanded="false" aria-controls="saPanel-lessors">
    <div class="kpi-icon-box blue">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
    </div>
    <div class="kpi-details">
      <span class="kpi-title">Total Lessors</span>
      <h3 class="kpi-amount"><?= $totalLessors ?></h3>
      <div class="kpi-subtext">Verified property owners</div>
      <span class="sa-kpi-cta">View lessors <?= $arrow ?></span>
    </div>
  </button>

  <button type="button" class="card kpi-enhanced sa-kpi" data-panel="lessees" aria-expanded="false" aria-controls="saPanel-lessees">
    <div class="kpi-icon-box green">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><polyline points="17 11 19 13 23 9"></polyline></svg>
    </div>
    <div class="kpi-details">
      <span class="kpi-title">Total Lessees</span>
      <h3 class="kpi-amount"><?= $totalLessees ?></h3>
      <div class="kpi-subtext">Tenants on the platform</div>
      <span class="sa-kpi-cta">View lessees <?= $arrow ?></span>
    </div>
  </button>

  <button type="button" class="card kpi-enhanced sa-kpi" data-panel="pending" aria-expanded="false" aria-controls="saPanel-pending">
    <?php if ($totalPending > 0): ?><span class="sa-kpi-badge">Needs review</span><?php endif; ?>
    <div class="kpi-icon-box orange">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 22h14"></path><path d="M5 2h14"></path><path d="M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22"></path><path d="M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2"></path></svg>
    </div>
    <div class="kpi-details">
      <span class="kpi-title">Pending Verifications</span>
      <h3 class="kpi-amount"><?= $totalPending ?></h3>
      <div class="kpi-subtext"><?= $pendingLessors ?> lessor<?= $pendingLessors === 1 ? '' : 's' ?> · <?= $pendingProps ?> propert<?= $pendingProps === 1 ? 'y' : 'ies' ?></div>
      <span class="sa-kpi-cta">Review queue <?= $arrow ?></span>
    </div>
  </button>
</div>

<!-- PANEL: LESSORS -->
<div class="card sa-panel" id="saPanel-lessors" data-panel-body="lessors" hidden>
  <div class="sa-panel-head">
    <div>
      <h3>Verified lessors</h3>
      <p><?= $totalLessors ?> active property owner<?= $totalLessors === 1 ? '' : 's' ?> · click one to see their documents</p>
    </div>
    <div class="sa-panel-tools">
      <input type="search" class="pm-input" placeholder="Filter lessors…" data-filter="lessors" aria-label="Filter lessors">
      <button type="button" class="pm-icon-btn" data-close-panel title="Close" aria-label="Close">&times;</button>
    </div>
  </div>
  <div class="sa-list">
    <?php if (!$lessorList): ?>
      <div class="sa-empty">No verified lessors yet.</div>
    <?php else: foreach ($lessorList as $l): ?>
      <a class="sa-row" href="/dashboard.php?page=lessors&amp;review=<?= (int) $l['id'] ?>"
         data-search="<?= e(strtolower($l['company_name'] . ' ' . $l['full_name'] . ' ' . $l['email'] . ' ' . $l['phone'])) ?>">
        <span class="sa-avatar"><?= e($initials($l['company_name'])) ?></span>
        <div class="sa-row-main">
          <div class="sa-row-title"><?= e($l['company_name']) ?></div>
          <div class="sa-row-sub"><?= e($l['full_name']) ?> · <?= e($l['email']) ?></div>
        </div>
        <div class="sa-row-meta">
          <span class="sa-tag info"><?= (int) $l['verified_count'] ?>/<?= (int) $l['property_count'] ?> verified</span>
          <span><?= (int) $l['tenant_count'] ?> tenant<?= (int) $l['tenant_count'] === 1 ? '' : 's' ?></span>
          <?= $chev ?>
        </div>
      </a>
    <?php endforeach; endif; ?>
    <div class="sa-empty" data-no-match hidden>No lessors match your filter.</div>
  </div>
  <div class="sa-panel-foot">
    <span>Joined dates and suspensions are managed in User Management.</span>
    <a href="/dashboard.php?page=lessors">Open Verify Lessors →</a>
  </div>
</div>

<!-- PANEL: LESSEES -->
<div class="card sa-panel" id="saPanel-lessees" data-panel-body="lessees" hidden>
  <div class="sa-panel-head">
    <div>
      <h3>Lessees</h3>
      <p><?= $totalLessees ?> tenant record<?= $totalLessees === 1 ? '' : 's' ?> across all lessors</p>
    </div>
    <div class="sa-panel-tools">
      <div class="sa-seg" data-seg="lessees">
        <button type="button" class="is-active" data-value="">All</button>
        <button type="button" data-value="active">Active</button>
        <button type="button" data-value="other">Others</button>
      </div>
      <input type="search" class="pm-input" placeholder="Filter lessees…" data-filter="lessees" aria-label="Filter lessees">
      <button type="button" class="pm-icon-btn" data-close-panel title="Close" aria-label="Close">&times;</button>
    </div>
  </div>
  <div class="sa-list">
    <?php if (!$lesseeList): ?>
      <div class="sa-empty">No lessees yet.</div>
    <?php else: foreach ($lesseeList as $t): [$tl, $tc] = $tenantTag($t); ?>
      <div class="sa-row" data-status="<?= strtolower((string) $t['status']) === 'active' ? 'active' : 'other' ?>"
           data-search="<?= e(strtolower($t['tenant_name'] . ' ' . $t['business_name'] . ' ' . $t['property_name'] . ' ' . $t['lessor_name'] . ' ' . $t['lessee_email'] . ' ' . $t['unit_label'])) ?>">
        <span class="sa-avatar green"><?= e($initials($t['tenant_name'])) ?></span>
        <div class="sa-row-main">
          <div class="sa-row-title">
            <?= e($t['tenant_name']) ?><?= $t['business_name'] ? ' <span class="pm-muted">· ' . e($t['business_name']) . '</span>' : '' ?>
          </div>
          <div class="sa-row-sub">
            <?= e($t['property_name'] ?? '—') ?><?= $t['unit_label'] ? ' (' . e($t['unit_label']) . ')' : '' ?> · Lessor: <?= e($t['lessor_name'] ?? '—') ?>
          </div>
        </div>
        <div class="sa-row-meta">
          <span title="Lease period"><?= e($fmtDate($t['start_date'])) ?> – <?= $t['end_date'] ? e($fmtDate($t['end_date'])) : 'open' ?></span>
          <span class="sa-tag <?= $tc ?>"><?= e($tl) ?></span>
          <?php if (empty($t['lessee_id'])): ?><span class="sa-tag info" title="Added by the lessor, no app account">Walk-in</span><?php endif; ?>
        </div>
      </div>
    <?php endforeach; endif; ?>
    <div class="sa-empty" data-no-match hidden>No lessees match your filter.</div>
  </div>
  <div class="sa-panel-foot">
    <span>Lessees with app accounts can be suspended in User Management.</span>
    <a href="/dashboard.php?page=users">Open User Management →</a>
  </div>
</div>

<!-- PANEL: PENDING -->
<div class="card sa-panel" id="saPanel-pending" data-panel-body="pending" hidden>
  <div class="sa-panel-head">
    <div>
      <h3>Pending verifications</h3>
      <p>Newest submissions first. Click one to review, then approve or reject.</p>
    </div>
    <div class="sa-panel-tools">
      <div class="sa-seg" data-seg="pending">
        <button type="button" class="is-active" data-value="">All (<?= $totalPending ?>)</button>
        <button type="button" data-value="lessor">Lessors (<?= $pendingLessors ?>)</button>
        <button type="button" data-value="property">Properties (<?= $pendingProps ?>)</button>
      </div>
      <button type="button" class="pm-icon-btn" data-close-panel title="Close" aria-label="Close">&times;</button>
    </div>
  </div>
  <div class="sa-list">
    <?php foreach ($pendingLessorRows as $l): ?>
      <a class="sa-row" data-status="lessor" data-search="" href="/dashboard.php?page=lessors&amp;review=<?= (int) $l['id'] ?>">
        <span class="sa-avatar orange"><?= e($initials($l['company_name'])) ?></span>
        <div class="sa-row-main">
          <div class="sa-row-title"><?= e($l['company_name']) ?></div>
          <div class="sa-row-sub">Lessor account · <?= e($l['full_name']) ?> · submitted <?= e($fmtDate($l['created_at'])) ?></div>
        </div>
        <div class="sa-row-meta"><span class="badge badge-lessor">Lessor</span><span class="pm-btn pm-btn-primary pm-btn-xs">Review</span></div>
      </a>
    <?php endforeach; ?>
    <?php foreach ($pendingPropRows as $p): ?>
      <a class="sa-row" data-status="property" data-search="" href="/dashboard.php?page=properties&amp;review=<?= (int) $p['id'] ?>">
        <span class="sa-avatar"><?= e($initials($p['name'])) ?></span>
        <div class="sa-row-main">
          <div class="sa-row-title"><?= e($p['name']) ?></div>
          <div class="sa-row-sub"><?= e($p['type']) ?> · by <?= e($p['lessor_name']) ?> · submitted <?= e($fmtDate($p['created_at'])) ?></div>
        </div>
        <div class="sa-row-meta"><span class="badge badge-accent">Property</span><span class="pm-btn pm-btn-primary pm-btn-xs">Review</span></div>
      </a>
    <?php endforeach; ?>
    <?php if (!$pendingLessorRows && !$pendingPropRows): ?>
      <div class="sa-empty">All caught up — nothing is waiting for verification.</div>
    <?php endif; ?>
    <div class="sa-empty" data-no-match hidden>Nothing in this group.</div>
  </div>
</div>

<!-- AWAITING VERIFICATION (5 newest, each row opens its review) -->
<div class="card">
  <div style="display:flex; justify-content:space-between; align-items:center; gap:10px;">
    <p class="section-heading" style="margin:0;">Awaiting verification</p>
    <?php if ($totalPending > 0): ?>
      <button type="button" class="pm-btn pm-btn-ghost pm-btn-xs" data-open-panel="pending">See all <?= $totalPending ?></button>
    <?php endif; ?>
  </div>

  <?php foreach (array_slice($pendingLessorRows, 0, 5) as $l): ?>
    <a class="pending-list-row sa-pending-row" href="/dashboard.php?page=lessors&amp;review=<?= (int) $l['id'] ?>">
      <p class="pending-list-text"><?= e($l['company_name']) ?></p>
      <span class="badge badge-lessor">Lessor</span>
    </a>
  <?php endforeach; ?>

  <?php foreach (array_slice($pendingPropRows, 0, 5) as $p): ?>
    <a class="pending-list-row sa-pending-row" href="/dashboard.php?page=properties&amp;review=<?= (int) $p['id'] ?>">
      <p class="pending-list-text"><?= e($p['name']) ?></p>
      <span class="badge badge-accent">Property</span>
    </a>
  <?php endforeach; ?>

  <?php if (!$pendingLessorRows && !$pendingPropRows): ?>
    <p class="empty-state-text text-center p-24">Nothing awaiting verification.</p>
  <?php endif; ?>
</div>

<script>
(function () {
  var cards = document.querySelectorAll('.sa-kpi[data-panel]');
  var panels = document.querySelectorAll('[data-panel-body]');

  function open(name, scroll) {
    var target = null;
    panels.forEach(function (p) {
      var on = p.getAttribute('data-panel-body') === name && p.hidden;
      p.hidden = !on;
      if (on) target = p;
    });
    cards.forEach(function (c) {
      var on = !!target && c.getAttribute('data-panel') === name;
      c.classList.toggle('is-active', on);
      c.setAttribute('aria-expanded', on ? 'true' : 'false');
    });
    var url = new URL(window.location.href);
    if (target) url.searchParams.set('panel', name); else url.searchParams.delete('panel');
    if (window.history.replaceState) window.history.replaceState(null, '', url.pathname + url.search);
    if (target && scroll) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    if (target) { var f = target.querySelector('[data-filter]'); if (f && window.matchMedia('(hover: hover)').matches) f.focus({ preventScroll: true }); }
  }

  cards.forEach(function (c) {
    c.addEventListener('click', function () { open(c.getAttribute('data-panel'), true); });
  });
  document.querySelectorAll('[data-open-panel]').forEach(function (b) {
    b.addEventListener('click', function () {
      var p = document.querySelector('[data-panel-body="' + b.getAttribute('data-open-panel') + '"]');
      if (p && !p.hidden) { p.scrollIntoView({ behavior: 'smooth' }); return; }
      open(b.getAttribute('data-open-panel'), true);
    });
  });
  document.querySelectorAll('[data-close-panel]').forEach(function (b) {
    b.addEventListener('click', function () {
      var p = b.closest('[data-panel-body]');
      if (p) open(p.getAttribute('data-panel-body'), false); // toggles it closed
    });
  });

  // Filter text + segmented status inside each panel
  panels.forEach(function (panel) {
    var input = panel.querySelector('[data-filter]');
    var seg = panel.querySelector('[data-seg]');
    var rows = Array.prototype.slice.call(panel.querySelectorAll('.sa-row'));
    var noMatch = panel.querySelector('[data-no-match]');
    var segValue = '';
    function apply() {
      var q = input ? input.value.trim().toLowerCase() : '';
      var shown = 0;
      rows.forEach(function (r) {
        var okQ = !q || (r.getAttribute('data-search') || '').indexOf(q) !== -1;
        var okS = !segValue || r.getAttribute('data-status') === segValue;
        r.style.display = okQ && okS ? '' : 'none';
        if (okQ && okS) shown++;
      });
      if (noMatch) noMatch.hidden = !(rows.length && shown === 0);
    }
    if (input) input.addEventListener('input', apply);
    if (seg) seg.querySelectorAll('button').forEach(function (b) {
      b.addEventListener('click', function () {
        segValue = b.getAttribute('data-value');
        seg.querySelectorAll('button').forEach(function (x) { x.classList.toggle('is-active', x === b); });
        apply();
      });
    });
  });

  var initial = <?= js($openPanel) ?>;
  if (initial) open(initial, false);
})();
</script>
