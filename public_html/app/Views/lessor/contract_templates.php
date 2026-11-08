<?php
/**
 * app/Views/lessor/contract_templates.php — "Contracts" screen:
 *   Tenant Contracts tab, Templates tab, and the template editor.
 * Data comes from app/Controllers/Lessor/ContractTemplatesController.php
 */
use App\Services\ContractService;

$statusMeta = [
    'standard'  => ['label' => 'Standard',              'style' => 'background:var(--bg); color:var(--ink-500);'],
    'draft'     => ['label' => 'Draft',                 'style' => 'background:var(--accent-soft); color:var(--primary-light);'],
    'finalized' => ['label' => 'Awaiting acceptance',   'style' => 'background:var(--warning-soft); color:#9a6412;'],
    'accepted'  => ['label' => 'Accepted',              'style' => 'background:var(--success-soft); color:var(--success);'],
    'rejected'  => ['label' => 'Rejected',              'style' => 'background:#fee2e2; color:#dc2626;'],
];
$ghostBtn = 'width:auto; display:inline-flex; padding:5px 10px; font-size:12px; background:var(--bg); border:1px solid var(--border); color:var(--ink-900); text-decoration:none;';
$filterInput = 'padding:8px 12px; border:1px solid var(--border); border-radius:8px; font:inherit; font-size:13px; background:#fff; color:var(--ink-900);';
?>
<style>
  .ct-tabs { display:flex; gap:4px; border-bottom:1px solid var(--border); margin-bottom:18px; }
  .ct-tabs a { padding:10px 16px; font-size:13.5px; font-weight:600; color:var(--ink-500); text-decoration:none; border-bottom:2px solid transparent; margin-bottom:-1px; }
  .ct-tabs a.active { color:var(--primary); border-bottom-color:var(--primary); }
  .ct-tabs a .count { font-weight:500; color:var(--ink-400); margin-left:4px; }
  .ct-stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:12px; margin-bottom:16px; }
  .ct-stat { background:#fff; border:1px solid var(--border); border-radius:10px; padding:12px 14px; cursor:pointer; text-align:left; font:inherit; }
  .ct-stat.active { border-color:var(--primary); box-shadow:0 0 0 1px var(--primary) inset; }
  .ct-stat .n { font-size:22px; font-weight:700; color:var(--ink-900); line-height:1.2; }
  .ct-stat .l { font-size:12px; color:var(--ink-500); }
  .ct-filters { display:flex; gap:10px; align-items:center; padding:14px 16px; border-bottom:1px solid var(--border); flex-wrap:wrap; }
  .ct-sub { font-size:11.5px; color:var(--ink-500); font-weight:400; }
  .ct-chip { display:inline-block; font-size:11px; padding:2px 8px; border-radius:20px; background:var(--accent-soft); color:var(--primary-light); margin:2px 4px 2px 0; white-space:nowrap; }
  .ct-buildings { display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:8px; }
  .ct-buildings label { display:flex; gap:8px; align-items:flex-start; padding:9px 11px; border:1px solid var(--border); border-radius:8px; font-size:12.5px; cursor:pointer; background:#fff; }
  .ct-buildings label input { margin-top:2px; }
  th.sortable { cursor:pointer; user-select:none; white-space:nowrap; }
  th.sortable::after { content:' \2195'; color:var(--ink-300); font-size:11px; }
  th.sortable.asc::after { content:' \2191'; color:var(--primary); }
  th.sortable.desc::after { content:' \2193'; color:var(--primary); }

  /* ---------- modals (contract + template editors) ---------- */
  .cm-overlay { position:fixed; inset:0; background:rgba(16,24,40,.5); z-index:60; display:flex; align-items:flex-start; justify-content:center; padding:3vh 2vw; }
  .cm-panel { background:var(--bg); width:min(1200px, 96vw); max-height:94vh; border-radius:14px; box-shadow:0 20px 60px rgba(0,0,0,.25); display:flex; flex-direction:column; overflow:hidden; }
  .cm-panel.narrow { width:min(900px, 96vw); background:#fff; }
  .cm-body { overflow-y:auto; padding:0 24px 24px; }
  .cm-head { position:sticky; top:0; z-index:8; display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;
             background:inherit; padding:20px 0 14px; margin-bottom:6px; border-bottom:1px solid var(--border); }
  .cm-panel .cm-head { background:var(--bg); }
  .cm-panel.narrow .cm-head { background:#fff; }
  .cm-close { display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:8px; font-size:24px; line-height:1;
              color:var(--ink-500); text-decoration:none; border:1px solid var(--border); background:#fff; }
  .cm-close:hover { color:var(--ink-900); background:var(--bg); }
  body.cm-open { overflow:hidden; }
</style>

<!-- =============================== LIST SCREENS (always shown; editors open as modals on top) =============================== -->
  <!-- =============================== LIST SCREENS =============================== -->
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; gap:12px; flex-wrap:wrap;">
    <h2 style="margin:0;">Contracts</h2>
    <a href="/dashboard.php?page=contract_templates&edit=new&from=0" class="btn btn-primary" style="width:auto; display:inline-flex; padding:8px 14px; font-size:13px;">
      + New Template
    </a>
  </div>
  <p style="margin:0 0 14px; font-size:12.5px; color:var(--ink-500); max-width:760px;">
    Every tenant starts on a template. Open a tenant's contract to adjust it to what you negotiated, or edit a template to change
    what future contracts start with. New contracts use the building's template first, then your default (<strong><?= htmlspecialchars($defaultName) ?></strong>).
  </p>

  <div class="ct-tabs">
    <a href="/dashboard.php?page=contract_templates&tab=contracts" class="<?= $tab === 'contracts' ? 'active' : '' ?>">
      Tenant Contracts<span class="count"><?= array_sum($statusCounts) ?></span>
    </a>
    <a href="/dashboard.php?page=contract_templates&tab=templates" class="<?= $tab === 'templates' ? 'active' : '' ?>">
      Templates<span class="count"><?= count($list) + 1 ?></span>
    </a>
  </div>

  <?php if ($error && !$editing): ?><div class="error-msg" style="margin-bottom:16px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($notice): ?>
    <div style="background:var(--success-soft); color:var(--success); padding:12px 16px; border-radius:8px; margin-bottom:16px; font-weight:500;">
      <?= htmlspecialchars($notice) ?>
    </div>
  <?php endif; ?>

  <?php if ($tab === 'contracts'): ?>
    <!-- ------------------------------ TENANT CONTRACTS ------------------------------ -->
    <div class="ct-stats">
      <button type="button" class="ct-stat" data-status="">
        <div class="n"><?= array_sum($statusCounts) ?></div><div class="l">All tenants</div>
      </button>
      <?php foreach ($statusMeta as $key => $m): ?>
        <button type="button" class="ct-stat" data-status="<?= $key ?>">
          <div class="n"><?= (int) ($statusCounts[$key] ?? 0) ?></div><div class="l"><?= $m['label'] ?></div>
        </button>
      <?php endforeach; ?>
    </div>

    <div class="card" style="padding:0; overflow:hidden;">
      <div class="ct-filters">
        <input id="ct-search" type="search" placeholder="Search tenant, business, or unit..." autocomplete="off"
               style="<?= $filterInput ?> flex:1; min-width:200px;">
        <select id="ct-building" style="<?= $filterInput ?> min-width:170px;">
          <option value="">All buildings</option>
          <?php foreach ($properties as $p): ?>
            <option value="<?= (int) $p['id'] ?>" <?= $presetBuilding === (int) $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <select id="ct-status" style="<?= $filterInput ?> min-width:160px;">
          <option value="">All statuses</option>
          <?php foreach ($statusMeta as $key => $m): ?>
            <option value="<?= $key ?>" <?= $presetStatus === $key ? 'selected' : '' ?>><?= $m['label'] ?></option>
          <?php endforeach; ?>
        </select>
        <select id="ct-sort" style="<?= $filterInput ?> min-width:170px;">
          <option value="building:asc">Sort: Building A–Z</option>
          <option value="tenant:asc">Sort: Tenant A–Z</option>
          <option value="tenant:desc">Sort: Tenant Z–A</option>
          <option value="status:asc">Sort: Status</option>
          <option value="updated:desc">Sort: Recently updated</option>
          <option value="rent:desc">Sort: Rent (high–low)</option>
        </select>
        <span id="ct-count" style="font-size:12.5px; color:var(--ink-500); white-space:nowrap;"></span>
      </div>

      <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th class="sortable" data-sort="tenant">Tenant</th>
            <th class="sortable" data-sort="building">Building</th>
            <th>Template</th>
            <th class="sortable" data-sort="status">Status</th>
            <th class="sortable" data-sort="rent">Rent</th>
            <th class="sortable" data-sort="updated">Updated</th>
            <th style="text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody id="ct-body">
          <?php if (!$contractRows): ?>
            <tr><td colspan="7" style="text-align:center; color:var(--ink-500);">No tenants yet. Approve an application to add one.</td></tr>
          <?php endif; ?>
          <?php
          $statusOrder = ['draft' => 1, 'finalized' => 2, 'standard' => 3, 'accepted' => 4];
          foreach ($contractRows as $r):
            $m = $statusMeta[$r['status_key']] ?? $statusMeta['standard'];
            $search = strtolower(implode(' ', array_filter([$r['display_name'], $r['tenant_name'], $r['business_name'], $r['unit_label'], $r['property_name']])));
            $tid = (int) $r['tenant_id'];
          ?>
            <tr class="ct-row"
                data-building="<?= (int) $r['property_id'] ?>"
                data-status="<?= htmlspecialchars($r['status_key']) ?>"
                data-search="<?= htmlspecialchars($search) ?>"
                data-k-tenant="<?= htmlspecialchars(strtolower($r['display_name'])) ?>"
                data-k-building="<?= htmlspecialchars(strtolower($r['property_name'] . ' ' . $r['display_name'])) ?>"
                data-k-status="<?= $statusOrder[$r['status_key']] ?? 9 ?>"
                data-k-rent="<?= (float) $r['rent_display'] ?>"
                data-k-updated="<?= htmlspecialchars((string) $r['sort_updated']) ?>">
              <td style="font-weight:600;">
                <?= htmlspecialchars($r['display_name']) ?>
                <div class="ct-sub">
                  <?= htmlspecialchars($r['business_name'] ?: '') ?><?= $r['business_name'] && $r['unit_label'] ? ' · ' : '' ?><?= $r['unit_label'] ? 'Unit ' . htmlspecialchars($r['unit_label']) : '' ?>
                </div>
              </td>
              <td style="color:var(--ink-500);"><?= htmlspecialchars($r['property_name']) ?></td>
              <td style="font-size:12.5px;">
                <?= htmlspecialchars($r['template_label']) ?>
                <?php if ($r['contract_id']): ?>
                  <div class="ct-sub">Version <?= (int) $r['version'] ?></div>
                <?php elseif ($r['building_template']): ?>
                  <div class="ct-sub">Building template: <?= htmlspecialchars($r['building_template']) ?></div>
                <?php endif; ?>
              </td>
              <td><span class="badge" style="<?= $m['style'] ?>"><?= $m['label'] ?></span></td>
              <td style="color:var(--ink-500);">₱<?= number_format($r['rent_display'], 0) ?></td>
              <td style="color:var(--ink-500); font-size:12.5px;"><?= $r['sort_updated'] ? (new DateTime($r['sort_updated']))->format('M j, Y') : '—' ?></td>
              <td style="text-align:right; white-space:nowrap;">
                <a class="btn btn-primary" href="/dashboard.php?page=contract_templates&tenant=<?= $tid ?>" style="width:auto; display:inline-flex; padding:5px 10px; font-size:12px;">
                  <?= !$r['contract_id'] ? 'Customize' : ($r['status_key'] === 'draft' ? 'Edit draft' : 'Open') ?>
                </a>
                <?php if ($r['template_edit_id'] !== null): ?>
                  <a href="/dashboard.php?page=contract_templates&edit=<?= (int) $r['template_edit_id'] ?>" style="<?= $ghostBtn ?>" class="btn"
                     title="<?= $r['template_edit_id'] === 0 ? 'View the built-in template' : 'Edit the template this contract came from (does not change this contract)' ?>">
                    Template
                  </a>
                <?php endif; ?>
                <a href="/lease_contract.php?tenant_id=<?= $tid ?>" target="_blank" style="<?= $ghostBtn ?>" class="btn">Print</a>
              </td>
            </tr>
          <?php endforeach; ?>
          <tr id="ct-empty" style="display:none;"><td colspan="7" style="text-align:center; color:var(--ink-500);">No tenants match your filters.</td></tr>
        </tbody>
      </table>
      </div>
      <div id="ct-pager" style="display:flex; justify-content:center; gap:6px; padding:12px 16px; border-top:1px solid var(--border);"></div>
    </div>

    <script>
    (function () {
      var rows = Array.prototype.slice.call(document.querySelectorAll('.ct-row'));
      var body = document.getElementById('ct-body');
      var empty = document.getElementById('ct-empty');
      var search = document.getElementById('ct-search');
      var building = document.getElementById('ct-building');
      var status = document.getElementById('ct-status');
      var sort = document.getElementById('ct-sort');
      var count = document.getElementById('ct-count');
      var pager = document.getElementById('ct-pager');
      var stats = Array.prototype.slice.call(document.querySelectorAll('.ct-stat'));
      var heads = Array.prototype.slice.call(document.querySelectorAll('th.sortable'));
      var perPage = 10, page = 1;
      var numeric = { status: true, rent: true };

      function cmp(field, dir) {
        return function (a, b) {
          var x = a.getAttribute('data-k-' + field), y = b.getAttribute('data-k-' + field);
          if (numeric[field]) { x = parseFloat(x) || 0; y = parseFloat(y) || 0; }
          var r = x < y ? -1 : x > y ? 1 : 0;
          return dir === 'desc' ? -r : r;
        };
      }

      function pagerButton(label, target, disabled, active) {
        var b = document.createElement('button');
        b.type = 'button'; b.textContent = label; b.disabled = disabled;
        b.style.cssText = 'padding:5px 10px; font-size:12px; border-radius:6px; font-family:inherit; min-width:30px; cursor:' + (disabled ? 'not-allowed' : 'pointer') +
          '; border:1px solid ' + (active ? 'var(--primary)' : 'var(--border)') + '; background:' + (active ? 'var(--primary)' : (disabled ? 'var(--bg)' : '#fff')) +
          '; color:' + (active ? '#fff' : (disabled ? 'var(--ink-300)' : 'var(--ink-900)')) + ';';
        b.addEventListener('click', function () { page = target; update(); });
        return b;
      }

      function update() {
        var q = (search.value || '').trim().toLowerCase();
        var bld = building.value, st = status.value;
        var parts = sort.value.split(':');

        var matches = rows.filter(function (r) {
          return (!q || r.getAttribute('data-search').indexOf(q) !== -1)
              && (!bld || r.getAttribute('data-building') === bld)
              && (!st || r.getAttribute('data-status') === st);
        }).sort(cmp(parts[0], parts[1]));

        rows.forEach(function (r) { r.style.display = 'none'; });
        matches.forEach(function (r) { body.insertBefore(r, empty); });   // apply sort order

        var pages = Math.max(1, Math.ceil(matches.length / perPage));
        if (page > pages) page = pages;
        matches.slice((page - 1) * perPage, page * perPage).forEach(function (r) { r.style.display = ''; });

        if (empty) empty.style.display = (rows.length && !matches.length) ? '' : 'none';
        count.textContent = (q || bld || st) ? (matches.length + ' of ' + rows.length) : (rows.length + ' tenant' + (rows.length === 1 ? '' : 's'));

        stats.forEach(function (s) { s.classList.toggle('active', s.getAttribute('data-status') === st); });
        heads.forEach(function (h) {
          h.classList.remove('asc', 'desc');
          if (h.getAttribute('data-sort') === parts[0]) h.classList.add(parts[1]);
        });

        pager.innerHTML = '';
        pager.style.display = matches.length > perPage ? 'flex' : 'none';
        if (matches.length > perPage) {
          pager.appendChild(pagerButton('Prev', page - 1, page === 1, false));
          for (var p = 1; p <= pages; p++) pager.appendChild(pagerButton(String(p), p, false, p === page));
          pager.appendChild(pagerButton('Next', page + 1, page === pages, false));
        }
      }

      [search].forEach(function (el) { el.addEventListener('input', function () { page = 1; update(); }); });
      [building, status, sort].forEach(function (el) { el.addEventListener('change', function () { page = 1; update(); }); });
      stats.forEach(function (s) {
        s.addEventListener('click', function () { status.value = s.getAttribute('data-status'); page = 1; update(); });
      });
      heads.forEach(function (h) {
        h.addEventListener('click', function () {
          var field = h.getAttribute('data-sort');
          var cur = sort.value.split(':');
          var dir = (cur[0] === field && cur[1] === 'asc') ? 'desc' : 'asc';
          var opt = field + ':' + dir;
          if (!sort.querySelector('option[value="' + opt + '"]')) {
            var o = document.createElement('option');
            o.value = opt; o.textContent = 'Sort: ' + h.textContent.trim() + (dir === 'asc' ? ' ↑' : ' ↓');
            sort.appendChild(o);
          }
          sort.value = opt; page = 1; update();
        });
      });
      update();
    })();
    </script>

  <?php else: ?>
    <!-- ------------------------------ TEMPLATES ------------------------------ -->
    <div class="card" style="padding:0; overflow:hidden; margin-bottom:20px;">
      <div class="ct-filters">
        <input id="tp-search" type="search" placeholder="Search templates..." autocomplete="off" style="<?= $filterInput ?> flex:1; min-width:200px;">
        <select id="tp-building" style="<?= $filterInput ?> min-width:190px;">
          <option value="">All buildings</option>
          <?php foreach ($properties as $p): ?>
            <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <select id="tp-sort" style="<?= $filterInput ?> min-width:170px;">
          <option value="name:asc">Sort: Name A–Z</option>
          <option value="name:desc">Sort: Name Z–A</option>
          <option value="used:desc">Sort: Most used</option>
          <option value="updated:desc">Sort: Recently edited</option>
        </select>
      </div>
      <div class="table-scroll">
      <table>
        <thead><tr><th>Template</th><th>Buildings</th><th>Clauses</th><th>Used in</th><th>Default</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody id="tp-body">
          <!-- Built-in (always first) -->
          <tr class="tp-builtin">
            <td style="font-weight:600;">
              <?= ContractService::BUILTIN_TEMPLATE_NAME ?>
              <span class="badge" style="background:var(--bg); color:var(--ink-500); margin-left:6px;">Built-in</span>
              <div class="ct-sub">Common Philippine commercial-lease clauses</div>
            </td>
            <td class="ct-sub">Used as fallback</td>
            <td style="color:var(--ink-500);"><?= count($builtinClauses) ?></td>
            <td style="color:var(--ink-500);"><?= ($usage[0] ?? 0) ? $usage[0] . ' contract' . ($usage[0] === 1 ? '' : 's') : '—' ?></td>
            <td><?= !$hasCustomDefault ? '<span class="badge badge-approved">Default</span>' : '' ?></td>
            <td style="text-align:right; white-space:nowrap;">
              <a class="btn" href="/dashboard.php?page=contract_templates&edit=0" style="<?= $ghostBtn ?>">View</a>
              <a class="btn" href="/dashboard.php?page=contract_templates&edit=new&from=0" style="<?= $ghostBtn ?>">Duplicate</a>
              <?php if ($hasCustomDefault): ?>
                <form method="POST" action="/dashboard.php?page=contract_templates" style="display:inline;">
                  <?= csrf_field() ?><input type="hidden" name="action" value="set_default"><input type="hidden" name="template_id" value="0">
                  <button class="btn" type="submit" style="<?= $ghostBtn ?>">Make default</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>

          <?php foreach ($list as $t):
            $tid = (int) $t['id'];
            $count = count(ContractService::decodeClauses($t['clauses']));
            $used = $usage[$tid] ?? 0;
            $blds = $buildingsByTemplate[$tid] ?? [];
          ?>
            <tr class="tp-row"
                data-buildings=",<?= implode(',', array_column($blds, 'id')) ?>,"
                data-search="<?= htmlspecialchars(strtolower($t['name'] . ' ' . $t['description'] . ' ' . implode(' ', array_column($blds, 'name')))) ?>"
                data-k-name="<?= htmlspecialchars(strtolower($t['name'])) ?>"
                data-k-used="<?= $used ?>"
                data-k-updated="<?= htmlspecialchars((string) $t['updated_at']) ?>">
              <td style="font-weight:600;">
                <?= htmlspecialchars($t['name']) ?>
                <?php if ($t['description']): ?><div class="ct-sub"><?= htmlspecialchars($t['description']) ?></div><?php endif; ?>
              </td>
              <td>
                <?php if (!$blds): ?><span class="ct-sub">—</span><?php endif; ?>
                <?php foreach ($blds as $b): ?><span class="ct-chip"><?= htmlspecialchars($b['name']) ?></span><?php endforeach; ?>
              </td>
              <td style="color:var(--ink-500);"><?= $count ?></td>
              <td style="color:var(--ink-500);">
                <?php if ($used): ?>
                  <?= $used ?> contract<?= $used === 1 ? '' : 's' ?>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td><?= (int) $t['is_default'] === 1 ? '<span class="badge badge-approved">Default</span>' : '' ?></td>
              <td style="text-align:right; white-space:nowrap;">
                <a class="btn btn-primary" href="/dashboard.php?page=contract_templates&edit=<?= $tid ?>" style="width:auto; display:inline-flex; padding:5px 10px; font-size:12px;">Edit</a>
                <a class="btn" href="/dashboard.php?page=contract_templates&edit=new&from=<?= $tid ?>" style="<?= $ghostBtn ?>">Duplicate</a>
                <?php if ((int) $t['is_default'] !== 1): ?>
                  <form method="POST" action="/dashboard.php?page=contract_templates" style="display:inline;">
                    <?= csrf_field() ?><input type="hidden" name="action" value="set_default"><input type="hidden" name="template_id" value="<?= $tid ?>">
                    <button class="btn" type="submit" style="<?= $ghostBtn ?>">Make default</button>
                  </form>
                <?php endif; ?>
                <form method="POST" action="/dashboard.php?page=contract_templates" style="display:inline;"
                      onsubmit="return confirm('Delete this template? Contracts already created from it keep their clauses, and its buildings go back to your default template.');">
                  <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="template_id" value="<?= $tid ?>">
                  <button class="btn" type="submit" style="width:auto; display:inline-flex; padding:5px 10px; font-size:12px; background:var(--error-soft); color:var(--error); border:none;">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <tr id="tp-empty" style="display:none;"><td colspan="6" style="text-align:center; color:var(--ink-500);">No templates match. The building uses your default template.</td></tr>
        </tbody>
      </table>
      </div>
    </div>

    <!-- Template per building -->
    <div class="card">
      <h3 style="margin:0 0 4px; font-size:15px;">Template per building</h3>
      <p style="font-size:12.5px; color:var(--ink-500); margin:0 0 14px;">
        Choose which template new contracts start from in each building. "Your default" means <strong><?= htmlspecialchars($defaultName) ?></strong>.
      </p>
      <?php if (!$properties): ?>
        <p style="font-size:12.5px; color:var(--ink-500); margin:0;">You have no properties yet.</p>
      <?php else: ?>
        <div class="table-scroll">
        <table>
          <thead><tr><th>Building</th><th>Template for new contracts</th><th style="text-align:right;">Tenants</th></tr></thead>
          <tbody>
            <?php foreach ($properties as $p): $pid = (int) $p['id']; $cur = $assignments[$pid] ?? 0; ?>
              <tr>
                <td style="font-weight:600;"><?= htmlspecialchars($p['name']) ?></td>
                <td>
                  <form method="POST" action="/dashboard.php?page=contract_templates" style="display:flex; gap:8px; align-items:center; margin:0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="assign_building">
                    <input type="hidden" name="property_id" value="<?= $pid ?>">
                    <select name="template_id" onchange="this.form.submit()" style="<?= $filterInput ?> min-width:220px;">
                      <option value="0" <?= $cur === 0 ? 'selected' : '' ?>>Your default (<?= htmlspecialchars($defaultName) ?>)</option>
                      <?php foreach ($list as $t): ?>
                        <option value="<?= (int) $t['id'] ?>" <?= $cur === (int) $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <noscript><button class="btn" type="submit" style="<?= $ghostBtn ?>">Save</button></noscript>
                    <a href="/dashboard.php?page=contract_templates&edit=new&from=<?= $cur ?: 0 ?>&building=<?= $pid ?>" style="font-size:12px; white-space:nowrap;">+ New for this building</a>
                  </form>
                </td>
                <td style="text-align:right;">
                  <a href="/dashboard.php?page=contract_templates&tab=contracts&building=<?= $pid ?>" style="font-size:12.5px;">
                    <?= (int) ($tenantsPerBuilding[$pid] ?? 0) ?> tenant<?= ($tenantsPerBuilding[$pid] ?? 0) === 1 ? '' : 's' ?> &rarr;
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      <?php endif; ?>
    </div>

    <script>
    (function () {
      var rows = Array.prototype.slice.call(document.querySelectorAll('.tp-row'));
      var body = document.getElementById('tp-body');
      var empty = document.getElementById('tp-empty');
      var search = document.getElementById('tp-search');
      var building = document.getElementById('tp-building');
      var sort = document.getElementById('tp-sort');
      function update() {
        var q = (search.value || '').trim().toLowerCase(), b = building.value;
        var parts = sort.value.split(':');
        var matches = rows.filter(function (r) {
          return (!q || r.getAttribute('data-search').indexOf(q) !== -1)
              && (!b || r.getAttribute('data-buildings').indexOf(',' + b + ',') !== -1);
        }).sort(function (x, y) {
          var a = x.getAttribute('data-k-' + parts[0]), c = y.getAttribute('data-k-' + parts[0]);
          if (parts[0] === 'used') { a = +a; c = +c; }
          var r = a < c ? -1 : a > c ? 1 : 0;
          return parts[1] === 'desc' ? -r : r;
        });
        rows.forEach(function (r) { r.style.display = 'none'; });
        matches.forEach(function (r) { r.style.display = ''; body.insertBefore(r, empty); });
        empty.style.display = (b && !matches.length) ? '' : 'none';
      }
      search.addEventListener('input', update);
      building.addEventListener('change', update);
      sort.addEventListener('change', update);
      update();
    })();
    </script>
  <?php endif; ?>

<?php if ($editing): ?>
<div class="cm-overlay" data-close="/dashboard.php?page=contract_templates&tab=templates">
  <div class="cm-panel narrow" role="dialog" aria-modal="true">
    <div class="cm-body">
  <!-- =============================== TEMPLATE EDITOR =============================== -->
  <div class="cm-head">
    <div>
      <h2 style="margin:0 0 4px; font-size:20px;"><?= $editing['readonly'] ? 'View Template' : ($editing['id'] ? 'Edit Template' : 'New Template') ?></h2>
      <p style="margin:0; font-size:12.5px; color:var(--ink-500);">
        <?= $editing['readonly']
            ? 'The built-in template cannot be changed. Duplicate it to make your own version.'
            : 'Changes here only affect contracts created from now on. Existing tenant contracts keep their own copy.' ?>
      </p>
    </div>
    <a href="/dashboard.php?page=contract_templates&tab=templates" class="cm-close" title="Close (Esc)" aria-label="Close">&times;</a>
  </div>

  <?php if ($error): ?><div class="error-msg" style="margin-bottom:16px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="card" style="border:none; box-shadow:none; padding:0;">
    <?php if ($editing['readonly']): ?>
      <h3 style="margin-top:0;"><?= htmlspecialchars($editing['name']) ?></h3>
      <?php
        $editorClauses = $editing['clauses']; $editorReadonly = true;
        require __DIR__ . '/partials/clause_editor.php';
      ?>
      <a href="/dashboard.php?page=contract_templates&edit=new&from=0" class="btn btn-primary" style="margin-top:16px; display:inline-flex; width:auto;">
        Duplicate as my template
      </a>
    <?php else: ?>
      <form method="POST" action="/dashboard.php?page=contract_templates<?= $editing['id'] ? '&edit=' . (int) $editing['id'] : '&edit=new' ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="template_id" value="<?= (int) $editing['id'] ?>">

        <div class="field">
          <label>Template name</label>
          <input type="text" name="name" maxlength="120" required value="<?= htmlspecialchars($editing['name']) ?>" placeholder="e.g. Retail Stall — Ground Floor">
        </div>
        <div class="field">
          <label>Short description (optional)</label>
          <input type="text" name="description" maxlength="255" value="<?= htmlspecialchars($editing['description']) ?>" placeholder="When to use this template">
        </div>

        <h3 style="font-size:14px; margin:18px 0 4px;">Use for these buildings</h3>
        <p style="font-size:12px; color:var(--ink-500); margin:0 0 10px;">
          New contracts for tenants in a ticked building will start from this template. A building can only have one template,
          so ticking it here moves it away from its current one.
        </p>
        <?php if (!$properties): ?>
          <p style="font-size:12.5px; color:var(--ink-500);">You have no properties yet.</p>
        <?php else: ?>
          <div class="ct-buildings">
            <?php foreach ($properties as $p):
              $pid = (int) $p['id'];
              $checked = in_array($pid, $editing['buildings'], true);
              $ownerId = $assignments[$pid] ?? null;
              $ownedByOther = $ownerId !== null && $ownerId !== (int) $editing['id'];
            ?>
              <label>
                <input type="checkbox" name="buildings[]" value="<?= $pid ?>" <?= $checked ? 'checked' : '' ?>>
                <span>
                  <strong style="font-weight:600;"><?= htmlspecialchars($p['name']) ?></strong>
                  <?php if ($ownedByOther && !$checked): ?>
                    <br><span class="ct-sub">Now uses: <?= htmlspecialchars($templateNames[$ownerId] ?? '—') ?></span>
                  <?php elseif ($ownerId === null && !$checked): ?>
                    <br><span class="ct-sub">Now uses your default</span>
                  <?php endif; ?>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <h3 style="font-size:14px; margin:22px 0 10px;">Clauses</h3>
        <?php
          $editorClauses = $editing['clauses']; $editorReadonly = false; $editorShowOrigin = false; $editorCounterOffers = [];
          require __DIR__ . '/partials/clause_editor.php';
        ?>

        <button class="btn btn-primary" type="submit" style="width:100%; margin-top:18px; padding:10px;">Save Template</button>
      </form>
    <?php endif; ?>
  </div>

    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($contractModal): ?>
<div class="cm-overlay" data-close="/dashboard.php?page=contract_templates">
  <div class="cm-panel" role="dialog" aria-modal="true">
    <div class="cm-body">
      <?= \App\Core\View::capture('lessor/contract', $contractModal) ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($editing || $contractModal): ?>
<script>
(function () {
  var overlay = document.querySelector('.cm-overlay');
  if (!overlay) return;
  var closeUrl = overlay.getAttribute('data-close');
  var dirty = false;
  document.body.classList.add('cm-open');
  overlay.addEventListener('input', function () { dirty = true; });
  overlay.addEventListener('submit', function () { dirty = false; });
  function close() {
    if (dirty && !confirm('Close without saving your changes?')) return;
    dirty = false;
    window.location.href = closeUrl;
  }
  // Click outside the panel, the × button, or press Esc.
  overlay.addEventListener('click', function (e) {
    if (e.target === overlay) close();
    var x = e.target.closest('.cm-close');
    if (x) { e.preventDefault(); close(); }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !document.querySelector('.doc-viewer.open')) close();
  });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
})();
</script>
<?php endif; ?>
