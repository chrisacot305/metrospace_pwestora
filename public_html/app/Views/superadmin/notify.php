<?php
/**
 * app/Views/superadmin/notify.php
 * Presentation layer for Notify Lessors: sent notices, composer, and editable notice types.
 * Data comes from app/Controllers/SuperAdmin/NotifyController.php
 */
use App\Models\NoticeTypeModel;

$typePill = static function (string $name, ?string $color): string {
    $hex = NoticeTypeModel::hex($color);
    return '<span class="sa-type-pill" style="background:' . $hex . '14; color:' . $hex . ';">'
         . '<span class="sa-dot" style="background:' . $hex . ';"></span>' . e($name) . '</span>';
};
$megaphone = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>';
?>

<div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:14px;">
  <h2 class="page-title" style="margin:0;">Notify Lessors</h2>
  <div style="display:flex; gap:8px;">
    <?php if ($tab === 'types'): ?>
      <a href="/dashboard.php?page=notify&amp;tab=types&amp;new_type=1" class="btn btn-outline" style="display:inline-flex; align-items:center;">+ New Notice Type</a>
    <?php endif; ?>
    <a href="/dashboard.php?page=notify&amp;compose=1" class="btn btn-accent" style="display:inline-flex; align-items:center;"><?= $megaphone ?> New Notice</a>
  </div>
</div>

<nav class="sa-tabs">
  <a href="/dashboard.php?page=notify" class="<?= $tab === 'sent' ? 'is-active' : '' ?>">Sent notices</a>
  <a href="/dashboard.php?page=notify&amp;tab=types" class="<?= $tab === 'types' ? 'is-active' : '' ?>">Notice types <span class="pm-tab-count"><?= count($NOTICE_TYPES_FULL) ?></span></a>
</nav>

<?php if (!$typesReady): ?>
  <div class="error-msg" style="background:var(--warning-soft); color:#8a5a12;">
    Notice types are using the built-in defaults. Run <strong>tools/migrate.php</strong> (or the SQL file in tools/) to make them editable.
  </div>
<?php endif; ?>

<?php if ($error && !$composing && !$typeFormOpen): ?>
  <div class="error-msg"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($successMsg): ?>
  <div id="temp-success-msg" class="success-msg" style="display:flex; align-items:center; gap:8px;">
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
    <?= e($successMsg) ?>
  </div>
  <script>
    if (window.history.replaceState) {
      window.history.replaceState(null, null, <?= js($tab === 'types' ? '/dashboard.php?page=notify&tab=types' : '/dashboard.php?page=notify') ?>);
    }
    setTimeout(function () {
      var msgDiv = document.getElementById('temp-success-msg');
      if (msgDiv) {
        msgDiv.style.transition = 'opacity 0.5s ease';
        msgDiv.style.opacity = '0';
        setTimeout(function () { msgDiv.style.display = 'none'; }, 500);
      }
    }, 3500);
  </script>
<?php endif; ?>

<?php if ($tab === 'types'): ?>
<!-- ================================================================ NOTICE TYPES -->
<p class="page-subtitle" style="margin:0 0 14px;">
  Each type has a message template that fills the composer when you pick it. Lessors see the type name as the notification title.
</p>
<div class="sa-type-grid">
  <?php foreach ($NOTICE_TYPES_FULL as $t): $used = (int) ($usage[$t['name']] ?? 0); $on = (int) $t['is_active'] === 1; ?>
    <div class="card sa-type-card<?= $on ? '' : ' is-off' ?>">
      <div class="sa-type-head">
        <div class="sa-type-name">
          <span class="sa-dot" style="background:<?= NoticeTypeModel::hex($t['color']) ?>;"></span>
          <?= e($t['name']) ?>
        </div>
        <span class="sa-tag <?= $on ? 'ok' : 'warn' ?>"><?= $on ? 'Shown' : 'Hidden' ?></span>
      </div>
      <div class="sa-type-preview"><?= $t['template'] !== '' && $t['template'] !== null ? e($t['template']) : '<span class="pm-muted">No template — the message box starts empty.</span>' ?></div>
      <div class="sa-type-foot">
        <span>Used <?= $used ?> time<?= $used === 1 ? '' : 's' ?></span>
        <?php if ($typesReady && (int) $t['id'] > 0): ?>
          <div class="sa-type-actions">
            <a class="pm-btn pm-btn-ghost pm-btn-xs" href="/dashboard.php?page=notify&amp;tab=types&amp;edit_type=<?= (int) $t['id'] ?>">Edit</a>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="type_id" value="<?= (int) $t['id'] ?>">
              <button class="pm-btn pm-btn-ghost pm-btn-xs" type="submit" name="toggle_type" value="1"><?= $on ? 'Hide' : 'Show' ?></button>
            </form>
            <form method="POST" style="display:inline;" onsubmit="return confirm(<?= e(js('Delete the "' . $t['name'] . '" notice type? Notices already sent are kept.')) ?>);">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="type_id" value="<?= (int) $t['id'] ?>">
              <button class="pm-btn pm-btn-danger pm-btn-xs" type="submit" name="delete_type" value="1">Delete</button>
            </form>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$NOTICE_TYPES_FULL): ?>
    <div class="card sa-empty">No notice types yet. Create one to start sending notices.</div>
  <?php endif; ?>
</div>

<?php if ($typeFormOpen):
    $tv = static fn(string $k, $fallback = '') => $_POST[$k] ?? $fallback;
    $curColor = (string) $tv('type_color', $editingType['color'] ?? 'blue');
    $curActive = isset($_POST['save_type']) ? isset($_POST['type_active']) : (int) ($editingType['is_active'] ?? 1) === 1;
?>
<div class="drawer-overlay" style="z-index:1050;">
  <div class="drawer-content" style="width:520px;">
    <div class="drawer-header">
      <h3 class="drawer-title"><?= $editingType ? 'Edit notice type' : 'New notice type' ?></h3>
      <a href="/dashboard.php?page=notify&amp;tab=types" class="drawer-close">&times;</a>
    </div>
    <?php if ($error): ?><div class="error-msg"><?= e($error) ?></div><?php endif; ?>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="save_type" value="1">
      <input type="hidden" name="type_id" value="<?= (int) ($editingType['id'] ?? 0) ?>">

      <div class="field">
        <label>Type name</label>
        <input type="text" name="type_name" maxlength="60" required placeholder="e.g. General Announcement"
               value="<?= e($tv('type_name', $editingType['name'] ?? '')) ?>">
        <p class="sa-template-hint">Shown to lessors as the notification title.</p>
      </div>

      <p class="drawer-section-title">Colour</p>
      <div class="sa-color-row" style="margin-bottom:16px;">
        <?php foreach ($COLORS as $key => $hex): ?>
          <label class="sa-color" title="<?= e(ucfirst($key)) ?>">
            <input type="radio" name="type_color" value="<?= e($key) ?>" <?= $curColor === $key ? 'checked' : '' ?>>
            <span style="background:<?= $hex ?>;"></span>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="field">
        <label>Message template</label>
        <textarea name="type_template" id="typeTemplate" rows="11" maxlength="5000"
                  placeholder="Write the default message. Put parts the sender must change in [square brackets]."><?= e($tv('type_template', $editingType['template'] ?? '')) ?></textarea>
        <p class="sa-template-hint">Anything in <strong>[square brackets]</strong> must be replaced before the notice can be sent.</p>
      </div>

      <label class="checkbox-card">
        <span class="checkbox-label">Show this type in the composer</span>
        <input type="checkbox" name="type_active" value="1" <?= $curActive ? 'checked' : '' ?>>
      </label>

      <button class="btn btn-accent w-100 mt-16" type="submit"><?= $editingType ? 'Save changes' : 'Create notice type' ?></button>
    </form>
  </div>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ================================================================ SENT NOTICES -->
<div class="card card-table">
  <div class="filter-bar">
    <div class="search-wrapper">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="search-icon"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
      <input id="notice-search" class="search-input" type="search" placeholder="Search message or recipients..." autocomplete="off">
    </div>
    <select id="notice-type-filter" class="filter-select">
      <option value="">All Notice Types</option>
      <?php foreach ($filterTypes as $type): ?>
        <option value="<?= e($type) ?>"><?= e($type) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="table-scroll">
    <table>
      <thead>
        <tr><th>Type</th><th>Recipients</th><th>Message</th><th>Sent</th></tr>
      </thead>
      <tbody id="notice-table-body">
        <?php if (!$sentLog): ?>
          <tr><td colspan="4" class="empty-state-text text-center p-24">Nothing sent yet.</td></tr>
        <?php else: foreach ($sentLog as $n):
            $searchBlob = strtolower(trim($n['title'] . ' ' . $n['audience'] . ' ' . $n['body']));
        ?>
          <tr class="notice-row" data-search="<?= e($searchBlob) ?>" data-type="<?= e($n['title']) ?>">
            <td><?= $typePill($n['title'], $typeColors[$n['title']] ?? 'slate') ?></td>
            <td><?= e($n['audience']) ?></td>
            <td style="white-space:pre-line; max-width:460px;"><?= e(mb_strimwidth($n['body'], 0, 220, '…')) ?></td>
            <td><?= (new DateTime($n['sent_at']))->format('M j, g:i A') ?></td>
          </tr>
        <?php endforeach; endif; ?>
        <tr id="notice-empty-filter" class="hidden-row">
          <td colspan="4" class="empty-state-text text-center p-24">No notices match your search or filter.</td>
        </tr>
      </tbody>
    </table>
  </div>

  <?php if ($sentLog): ?>
  <div id="notice-pagination-bar" class="pagination-bar">
    <div id="notice-page-controls" class="page-controls"></div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($composing): ?>
<!-- ================================================================ COMPOSER -->
<div class="drawer-overlay" style="z-index:1050;">
  <div class="drawer-content" style="width:560px;">
    <div class="drawer-header">
      <h3 class="drawer-title">Compose Notice</h3>
      <a href="/dashboard.php?page=notify" class="drawer-close">&times;</a>
    </div>

    <?php if ($error): ?><div class="error-msg"><?= e($error) ?></div><?php endif; ?>

    <?php if (!$lessors): ?>
      <p class="drawer-empty-text">No active lessors on the platform yet.</p>
    <?php elseif (!$activeTypes): ?>
      <p class="drawer-empty-text">No notice types are shown. <a href="/dashboard.php?page=notify&amp;tab=types">Create or show one</a> first.</p>
    <?php else:
        $postedType = (string) ($_POST['notice_type'] ?? $activeTypes[0]['name']);
        $postedIds  = array_map('intval', is_array($_POST['recipient_ids'] ?? null) ? $_POST['recipient_ids'] : []);
        $templateFor = '';
        foreach ($activeTypes as $t) { if ($t['name'] === $postedType) $templateFor = (string) $t['template']; }
    ?>
      <form method="POST" id="composeForm">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="send_notice" value="1">

        <p class="drawer-section-title">Notice type</p>
        <div class="sa-type-chooser">
          <?php foreach ($activeTypes as $t): ?>
            <label class="sa-type-option">
              <input type="radio" name="notice_type" value="<?= e($t['name']) ?>" data-template="<?= e($t['template'] ?? '') ?>"
                     <?= $t['name'] === $postedType ? 'checked' : '' ?>>
              <span><span class="sa-dot" style="background:<?= NoticeTypeModel::hex($t['color']) ?>;"></span><?= e($t['name']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>

        <div class="field">
          <label style="display:flex; justify-content:space-between; align-items:baseline;">
            <span>Message</span>
            <button type="button" class="pm-btn pm-btn-ghost pm-btn-xs" id="resetTemplate">Reset to template</button>
          </label>
          <textarea name="notice_body" id="noticeBody" rows="11" maxlength="5000" required><?= e($_POST['notice_body'] ?? $templateFor) ?></textarea>
          <div class="sa-char"><span id="noticeChars">0</span> / 5000</div>
          <p class="sa-template-hint">Replace the text in <strong>[square brackets]</strong> before sending. Edit the templates in the <a href="/dashboard.php?page=notify&amp;tab=types">Notice types</a> tab.</p>
        </div>

        <p class="drawer-section-title">Send to</p>
        <label class="checkbox-card">
          <span class="checkbox-label" style="display:flex; align-items:center;">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            All lessors (<?= count($lessors) ?>)
          </span>
          <input type="checkbox" name="all_lessors" id="allLessorsBox" <?= isset($_POST['all_lessors']) || !isset($_POST['send_notice']) ? 'checked' : '' ?>>
        </label>
        <input type="search" class="pm-input" id="lessorFilter" placeholder="Find a lessor…" style="margin:4px 0 8px;">
        <div id="lessorCheckboxes" style="max-height:240px; overflow-y:auto;">
          <?php foreach ($lessors as $l): ?>
            <label class="checkbox-card" data-name="<?= e(strtolower($l['company_name'])) ?>">
              <span class="checkbox-label"><?= e($l['company_name']) ?></span>
              <input type="checkbox" name="recipient_ids[]" value="<?= (int) $l['id'] ?>" <?= in_array((int) $l['id'], $postedIds, true) ? 'checked' : '' ?>>
            </label>
          <?php endforeach; ?>
        </div>

        <button class="btn btn-accent w-100 mt-16" type="submit" style="display:flex; justify-content:center; align-items:center;">
          <?= $megaphone ?> <span id="sendLabel">Send Notice</span>
        </button>
      </form>

      <script>
      (function () {
        var body = document.getElementById('noticeBody');
        var chars = document.getElementById('noticeChars');
        var radios = document.querySelectorAll('input[name="notice_type"]');
        var checkedType = document.querySelector('input[name="notice_type"]:checked');
        var lastTemplate = checkedType ? (checkedType.getAttribute('data-template') || '') : '';
        function count() { chars.textContent = body.value.length; }
        radios.forEach(function (r) {
          r.addEventListener('change', function () {
            var next = r.getAttribute('data-template') || '';
            // Only replace the message if the user hasn't typed their own yet.
            var untouched = body.value.trim() === '' || body.value === lastTemplate;
            if (untouched || confirm('Replace your current message with the "' + r.value + '" template?')) {
              body.value = next;
            }
            lastTemplate = next;
            count();
          });
        });
        document.getElementById('resetTemplate').addEventListener('click', function () {
          var cur = document.querySelector('input[name="notice_type"]:checked');
          if (cur && (body.value === '' || confirm('Discard your edits and restore the template?'))) {
            body.value = cur.getAttribute('data-template') || ''; lastTemplate = body.value; count();
          }
        });
        body.addEventListener('input', count);
        count();

        var all = document.getElementById('allLessorsBox');
        var boxes = document.querySelectorAll('#lessorCheckboxes input[type="checkbox"]');
        var label = document.getElementById('sendLabel');
        function sync() {
          boxes.forEach(function (cb) { cb.disabled = all.checked; });
          var n = all.checked ? boxes.length : Array.prototype.filter.call(boxes, function (b) { return b.checked; }).length;
          label.textContent = n ? 'Send to ' + n + ' lessor' + (n === 1 ? '' : 's') : 'Send Notice';
        }
        all.addEventListener('change', sync);
        boxes.forEach(function (b) { b.addEventListener('change', sync); });
        sync();
        document.getElementById('lessorFilter').addEventListener('input', function () {
          var q = this.value.trim().toLowerCase();
          document.querySelectorAll('#lessorCheckboxes .checkbox-card').forEach(function (c) {
            c.style.display = !q || c.getAttribute('data-name').indexOf(q) !== -1 ? '' : 'none';
          });
        });
      })();
      </script>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($tab === 'sent'): ?>
<!-- PAGINATION & FILTER LOGIC -->
<script>
(function () {
  var search = document.getElementById('notice-search');
  var typeFilter = document.getElementById('notice-type-filter');
  var rows = Array.prototype.slice.call(document.querySelectorAll('.notice-row'));
  var emptyFilter = document.getElementById('notice-empty-filter');
  var total = rows.length;
  var paginationBar = document.getElementById('notice-pagination-bar');
  var pageControls = document.getElementById('notice-page-controls');
  var currentPage = 1;
  var perPage = 10;
  if (!search || !typeFilter) return;

  function btn(label, disabled, onClick, active) {
    var b = document.createElement('button');
    b.type = 'button'; b.className = 'page-btn' + (active ? ' active' : '');
    b.textContent = label; b.disabled = !!disabled;
    b.addEventListener('click', onClick);
    return b;
  }
  function renderPagination(totalMatching, totalPages) {
    if (!paginationBar || !pageControls) return;
    if (totalMatching === 0) { paginationBar.classList.add('hidden-row'); return; }
    paginationBar.classList.remove('hidden-row');
    pageControls.innerHTML = '';
    pageControls.appendChild(btn('‹ Prev', currentPage === 1, function () { currentPage--; filterTable(); }));
    for (var p = 1; p <= totalPages; p++) {
      (function (n) { pageControls.appendChild(btn(String(n), false, function () { currentPage = n; filterTable(); }, n === currentPage)); })(p);
    }
    pageControls.appendChild(btn('Next ›', currentPage === totalPages, function () { currentPage++; filterTable(); }));
  }
  function filterTable() {
    var q = search.value.trim().toLowerCase();
    var t = typeFilter.value.toLowerCase();
    var matching = rows.filter(function (row) {
      var okQ = !q || (row.getAttribute('data-search') || '').indexOf(q) !== -1;
      var okT = !t || (row.getAttribute('data-type') || '').toLowerCase() === t;
      return okQ && okT;
    });
    var totalPages = Math.max(1, Math.ceil(matching.length / perPage));
    if (currentPage > totalPages) currentPage = totalPages;
    rows.forEach(function (row) { row.classList.add('hidden-row'); });
    matching.slice((currentPage - 1) * perPage, currentPage * perPage).forEach(function (row) { row.classList.remove('hidden-row'); });
    if (emptyFilter) emptyFilter.classList.toggle('hidden-row', !(matching.length === 0 && total > 0));
    renderPagination(matching.length, totalPages);
  }
  search.addEventListener('input', function () { currentPage = 1; filterTable(); });
  typeFilter.addEventListener('change', function () { currentPage = 1; filterTable(); });
  filterTable();
})();
</script>
<?php endif; ?>
