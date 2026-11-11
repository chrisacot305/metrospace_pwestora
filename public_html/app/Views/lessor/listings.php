<?php
/**
 * app/Views/lessor/listings.php — HTML for the "listings" screen (was the bottom half of modules/listings.php).
 * Data comes from app/Controllers/Lessor/ListingsController.php
 */
?>
<script src="/assets/js/photo-uploader.js?v=<?= @filemtime(APP_ROOT . '/assets/js/photo-uploader.js') ?: 1 ?>" defer></script>
<div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
  <div>
    <h2 style="margin:0 0 4px;">My Properties</h2>
  </div>
  <a href="/dashboard.php?page=listings&add=1" class="btn btn-primary"
     style="width:auto; flex-shrink:0; display:inline-flex; align-items:center; gap:6px; white-space:nowrap; padding:11px 20px;">
    <i class="bi bi-plus-lg"></i> Add Property
  </a>
</div>

<?php if ($error && !$editingProperty && !isset($_GET['add'])): ?>
  <div class="error-msg" style="margin-bottom:16px;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- SEARCH & FILTER BAR -->
<div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; padding:14px 16px; margin-bottom:20px; border:1px solid var(--border); border-radius:12px; background:var(--surface);">
  <div style="flex:1; min-width:220px; position:relative;">
    <i class="bi bi-search" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--ink-400); font-size:13px;"></i>
    <input id="prop-search" type="search" placeholder="Search property name, address, or tenant..."
           autocomplete="off"
           style="width:100%; box-sizing:border-box; padding:8px 12px 8px 34px; border:1px solid var(--border); border-radius:8px; font:inherit; font-size:13px; background:var(--bg); color:var(--ink-900);">
  </div>
  
  <select id="prop-type-filter" style="padding:8px 12px; border:1px solid var(--border); border-radius:8px; font:inherit; font-size:13px; background:#fff; color:var(--ink-900); min-width:140px;">
    <option value="">All Types</option>
    <?php foreach ($PROPERTY_TYPES as $t): ?>
      <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
    <?php endforeach; ?>
  </select>

  <select id="prop-status-filter" style="padding:8px 12px; border:1px solid var(--border); border-radius:8px; font:inherit; font-size:13px; background:#fff; color:var(--ink-900); min-width:140px;">
    <option value="">All Statuses</option>
    <option value="verified">Verified</option>
    <option value="pending">Pending</option>
    <option value="rejected">Rejected</option>
  </select>
</div>

<div class="card-grid-fixed" id="prop-grid" style="margin-bottom:28px;">
  <?php if (!$properties): ?>
    <p style="color:var(--ink-500); font-size:13px; grid-column:1/-1;">You haven't submitted any properties yet so click "Add Property" to add your first one.</p>
  <?php else: foreach ($properties as $p):
      $propTotalUnits = isset($p['total_units']) ? max(1, (int)$p['total_units']) : 1;
      $propAvailableUnits = isset($p['available_units']) ? max(0, (int)$p['available_units']) : ($p['current_tenant'] ? 0 : 1);
      $propActiveTenants = isset($p['active_tenants_count']) ? (int)$p['active_tenants_count'] : ($p['current_tenant'] ? 1 : 0);
      $propIsFull = ($propAvailableUnits <= 0);

      $statusBadge = match ($p['status']) {
          'verified' => ['Verified', 'background:var(--success-soft); color:var(--success);'],
          'rejected' => ['Rejected', 'background:var(--error-soft); color:var(--error);'],
          default    => ['Pending review', 'background:var(--warning-soft); color:var(--warning);'],
      };
      
      // Search Data Blob
      $searchBlob = strtolower(trim(implode(' ', array_filter([
          $p['name'],
          $p['type'],
          $p['address'],
          $p['current_tenant'],
          $p['status']
      ]))));
  ?>
    <div class="card prop-card" 
         data-search="<?= htmlspecialchars($searchBlob) ?>" 
         data-type="<?= htmlspecialchars($p['type']) ?>" 
         data-status="<?= htmlspecialchars($p['status']) ?>"
         style="cursor:pointer; transition:transform 0.18s ease, box-shadow 0.18s ease;"
         onclick="window.location.href='/dashboard.php?page=listings&edit=<?= $p['id'] ?>'"
         onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 8px 24px rgba(0,0,0,0.08)';"
         onmouseout="this.style.transform='none'; this.style.boxShadow='none';">
      <div class="prop-card-img-wrap">
        <?php if ($p['cover_photo']): ?>
          <img src="/<?= htmlspecialchars($p['cover_photo']) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
        <?php else: ?>
          <div class="prop-card-noimg"><i class="bi bi-building"></i></div>
        <?php endif; ?>
        <span class="prop-card-badge-type"><?= htmlspecialchars($p['type']) ?></span>
        <span class="prop-card-badge-status" style="<?= $statusBadge[1] ?>"><?= $statusBadge[0] ?></span>
      </div>
      <div class="prop-card-body">
        <h3><?= htmlspecialchars($p['name']) ?></h3>
        <?php if ($p['asking_rent']): ?>
          <p style="font-size:15px; font-weight:800; color:var(--primary); margin:4px 0 0;">₱<?= number_format($p['asking_rent'], 0) ?><span style="font-size:11px; font-weight:500; color:var(--ink-500);"> /month</span></p>
        <?php endif; ?>
        <div class="prop-card-address"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($p['address']) ?></div>
        <?php if (!empty($p['landmark_name'])): ?>
          <div class="pm-card-landmark">
            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
            Near <?= htmlspecialchars($p['landmark_name']) ?><?= !empty($p['landmark_distance']) ? ' · ' . htmlspecialchars($p['landmark_distance']) : '' ?>
          </div>
        <?php endif; ?>
        <?php if ($p['latitude'] && $p['longitude']): ?>
          <a href="https://www.openstreetmap.org/?mlat=<?= $p['latitude'] ?>&mlon=<?= $p['longitude'] ?>#map=17/<?= $p['latitude'] ?>/<?= $p['longitude'] ?>"
             target="_blank" onclick="event.stopPropagation();" style="font-size:11.5px; color:var(--primary-light); font-weight:600; display:inline-block; margin-top:4px;">
            <i class="bi bi-map"></i> View on map
          </a>
        <?php endif; ?>

        <!-- VACANCY BADGE & TENANT STAT -->
        <div style="margin:8px 0 6px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:6px;">
          <?php if ($propIsFull): ?>
            <span style="display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:700; color:#b91c1c; background:#fee2e2; padding:3px 8px; border-radius:12px;">
              <i class="bi bi-x-circle-fill" style="font-size:10px;"></i> Full (0 / <?= $propTotalUnits ?> Available)
            </span>
          <?php else: ?>
            <span style="display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:700; color:#0369a1; background:#e0f2fe; padding:3px 8px; border-radius:12px;">
              <i class="bi bi-check-circle-fill" style="font-size:10px;"></i> <?= $propAvailableUnits ?> / <?= $propTotalUnits ?> Spaces Available
            </span>
          <?php endif; ?>

          <?php if ($propActiveTenants > 0): ?>
            <span style="font-size:11px; color:var(--ink-500); font-weight:600;">
              <i class="bi bi-people-fill"></i> <?= $propActiveTenants ?> active <?= $propActiveTenants === 1 ? 'tenant' : 'tenants' ?>
            </span>
          <?php endif; ?>
        </div>

        <div class="prop-stats-row">
          <div>
            <div class="prop-stat-label">Spaces / Units</div>
            <div class="prop-stat-value" style="font-weight:700;">
              <?= $propAvailableUnits ?> <span style="font-size:11px; font-weight:normal; color:var(--ink-500);">/ <?= $propTotalUnits ?> vacant</span>
            </div>
          </div>
          <div>
            <div class="prop-stat-label">Occupancy</div>
            <div class="prop-stat-value"><?= $propIsFull ? '<span style="color:#b91c1c; font-weight:700;">Full</span>' : ($propAvailableUnits < $propTotalUnits ? 'Partially Vacant' : 'Vacant') ?></div>
          </div>
          <div>
            <div class="prop-stat-label">Photos</div>
            <div class="prop-stat-value"><?= $p['photo_count'] ?></div>
          </div>
        </div>

        <div style="margin-top:14px; padding-top:10px; border-top:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
          <span style="font-size:12px; font-weight:700; color:var(--primary);">
            <i class="bi bi-sliders"></i> Customize Property
          </span>
          <span class="btn" style="padding:4px 10px; font-size:11.5px; background:var(--bg); border:1px solid var(--border); color:var(--ink-800);">
            Edit <i class="bi bi-chevron-right"></i>
          </span>
        </div>
      </div>
    </div>
  <?php endforeach; endif; ?>
  <p id="prop-empty-filter" style="display:none; grid-column:1/-1; color:var(--ink-500); text-align:center; padding:20px 0;">No properties match your filter criteria.</p>
</div>

<!-- FILTER LOGIC SCRIPT -->
<script>
(function () {
  var search = document.getElementById('prop-search');
  var typeFilter = document.getElementById('prop-type-filter');
  var statusFilter = document.getElementById('prop-status-filter');
  var cards = Array.prototype.slice.call(document.querySelectorAll('.prop-card'));
  var emptyMsg = document.getElementById('prop-empty-filter');

  function applyFilter() {
    var q = (search.value || '').trim().toLowerCase();
    var ty = typeFilter.value || '';
    var st = statusFilter.value || '';
    var shown = 0;

    cards.forEach(function (card) {
      var matchQ = !q || (card.getAttribute('data-search') || '').indexOf(q) !== -1;
      var matchT = !ty || card.getAttribute('data-type') === ty;
      var matchS = !st || card.getAttribute('data-status') === st;
      var visible = matchQ && matchT && matchS;

      card.style.display = visible ? '' : 'none';
      if (visible) shown++;
    });

    if (emptyMsg && cards.length > 0) {
      emptyMsg.style.display = shown === 0 ? 'block' : 'none';
    }
  }

  if (search && typeFilter && statusFilter) {
    search.addEventListener('input', applyFilter);
    typeFilter.addEventListener('change', applyFilter);
    statusFilter.addEventListener('change', applyFilter);
    applyFilter();
  }
})();
</script>

<!-- Leaflet Map CSS/JS for Pin Repositioning -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<?php if ($editingProperty && !$listingSaved):
    $remainingSlots = max(0, $MAX_PHOTOS - count($editingPhotos));
?>
<!-- CUSTOMIZE / EDIT PROPERTY DRAWER -->
<div class="pm-overlay">
  <div class="pm-drawer" id="pmEditDrawer">
    <div class="pm-drawer-head">
      <div>
        <div class="pm-title-row">
          <h3>Customize Property</h3>
          <span class="pm-status pm-status-<?= e($editingProperty['status']) ?>"><?= e(ucfirst($editingProperty['status'])) ?></span>
        </div>
        <p class="pm-sub">Update details, landmark, map pin and photos for <strong><?= e($editingProperty['name']) ?></strong>.</p>
      </div>
      <a href="/dashboard.php?page=listings" class="pm-close" aria-label="Close">&times;</a>
    </div>

    <nav class="pm-tabs" aria-label="Sections">
      <button type="button" data-pm-jump="pmSecBasics" class="is-active">Basics</button>
      <button type="button" data-pm-jump="pmSecMore">More info</button>
      <button type="button" data-pm-jump="pmSecLocation">Location</button>
      <button type="button" data-pm-jump="gallery">Photos <span class="pm-tab-count"><?= count($editingPhotos) ?></span></button>
      <button type="button" data-pm-jump="pmSecDocs">Documents</button>
    </nav>

    <div class="pm-drawer-body" id="pmEditBody">
      <?php if ($justSaved): ?>
        <div class="pm-toast" id="pmSavedToast">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
          Changes saved.
        </div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="error-msg" data-pm-error style="margin-bottom:16px;"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data" id="editPropertyForm"
            action="/dashboard.php?page=listings&amp;edit=<?= (int) $editingProperty['id'] ?>"
            data-pm-progress data-max-total-mb="<?= (int) $MAX_POST_MB ?>">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="edit_property" value="1">
        <input type="hidden" name="property_id" value="<?= (int) $editingProperty['id'] ?>">
        <div class="pm-form-error error-msg" hidden></div>

        <!-- BASICS -->
        <section class="pm-section" id="pmSecBasics">
          <header class="pm-section-head">
            <span class="pm-section-icon"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 9h1M14 9h1M9 13h1M14 13h1M9 17h1M14 17h1"/></svg></span>
            <div><h4>Basics</h4><p>Name, type, rent and terms shown on the listing.</p></div>
          </header>

          <div class="field">
            <label>Property Name</label>
            <input type="text" name="name" required maxlength="150" value="<?= e($_POST['name'] ?? $editingProperty['name']) ?>">
          </div>

          <div class="pm-grid-2">
            <div class="field">
              <label>Commercial Type</label>
              <select name="type" required>
                <?php foreach ($PROPERTY_TYPES as $t): ?>
                  <option value="<?= e($t) ?>" <?= (($_POST['type'] ?? $editingProperty['type']) === $t) ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label>Monthly Rent (₱)</label>
              <input type="number" name="asking_rent" min="1" step="0.01" required value="<?= e($_POST['asking_rent'] ?? $editingProperty['asking_rent']) ?>">
            </div>
            <div class="field">
              <label>Floor Area</label>
              <input type="text" name="floor_area" placeholder="e.g. 600 SQM (3 floors)" value="<?= e($_POST['floor_area'] ?? $editingProperty['floor_area'] ?? '') ?>">
            </div>
            <div class="field">
              <label>Lease Term</label>
              <input type="text" name="lease_term" placeholder="e.g. Minimum of 2 years" value="<?= e($_POST['lease_term'] ?? $editingProperty['lease_term'] ?? '') ?>">
            </div>
          </div>

          <div class="field">
            <label>Annual Increase</label>
            <input type="text" name="escalation" placeholder="e.g. 4% annual increase" value="<?= e($_POST['escalation'] ?? $editingProperty['escalation'] ?? '') ?>">
          </div>

          <div class="field">
            <label>Property Description</label>
            <textarea name="description" rows="4" placeholder="Describe the space, foot traffic, security, nearby businesses…"><?= e($_POST['description'] ?? $editingProperty['description'] ?? '') ?></textarea>
          </div>

          <!-- RENTAL SPACES & VACANCY CAPACITY -->
          <div class="pm-subcard">
            <div class="pm-subcard-head">
              <span>Rental spaces &amp; vacancy</span>
              <?php $currentActive = (int)($editingProperty['active_tenants_count'] ?? 0); ?>
              <span class="pm-pill"><?= $currentActive ?> active tenant<?= $currentActive === 1 ? '' : 's' ?></span>
            </div>
            <div class="pm-grid-2">
              <div class="field" style="margin-bottom:0;">
                <label>Total Spaces / Capacity</label>
                <input type="number" name="total_units" id="edit_total_units" min="1" step="1" required
                       value="<?= e($_POST['total_units'] ?? $editingProperty['total_units'] ?? 1) ?>">
                <p class="pm-help">Total rentable stalls/spaces (e.g. 20 → 21 if you added one).</p>
              </div>
              <div class="field" style="margin-bottom:0;">
                <label>Available Vacancies</label>
                <input type="number" name="available_units" id="edit_available_units" min="0" step="1" required
                       value="<?= e($_POST['available_units'] ?? $editingProperty['available_units'] ?? 1) ?>">
                <p class="pm-help">Adjust if you take walk-in tenants outside the app.</p>
              </div>
            </div>
          </div>
        </section>

        <!-- EXPANDED INFO + LANDMARK -->
        <div id="pmSecMore">
          <?php $pxSrc = $editingProperty; $pxEdit = true; require __DIR__ . '/partials/property_extra_fields.php'; ?>
        </div>

        <!-- LOCATION -->
        <section class="pm-section" id="pmSecLocation">
          <header class="pm-section-head">
            <span class="pm-section-icon"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3z"/><path d="M9 3v15M15 6v15"/></svg></span>
            <div><h4>Location</h4><p>Address and the exact pin tenants navigate to.</p></div>
          </header>
          <div class="field">
            <label>Physical Address</label>
            <input type="text" name="address" required maxlength="255" value="<?= e($_POST['address'] ?? $editingProperty['address']) ?>">
          </div>
          <div class="field">
            <label class="pm-label-row"><span>Pin Location on Map</span><span class="pm-optional">click the map or drag the pin</span></label>
            <input type="text" id="editMapSearch" placeholder="Search address or area in Bacolod…" style="margin-bottom:8px;">
            <div id="editPropertyMap" class="pm-map"></div>
            <input type="hidden" name="latitude" id="editLatInput" value="<?= e($_POST['latitude'] ?? $editingProperty['latitude'] ?? '') ?>">
            <input type="hidden" name="longitude" id="editLngInput" value="<?= e($_POST['longitude'] ?? $editingProperty['longitude'] ?? '') ?>">
            <p class="pm-help" id="coordDisplay">GPS: <?= $editingProperty['latitude'] ? e($editingProperty['latitude'] . ', ' . $editingProperty['longitude']) : 'Not pinned yet' ?></p>
          </div>
        </section>

        <!-- PHOTOS -->
        <section class="pm-section">
          <?php require __DIR__ . '/partials/photo_gallery.php'; ?>

          <label class="pm-label" style="margin-top:16px;">Add photos</label>
          <?php
            $pmName = 'photos[]'; $pmSingle = false; $pmMaxFiles = $remainingSlots; $pmRequired = false;
            $pmTitle = 'Drag photos here or click to browse';
            $pmHint = 'JPG, PNG or WEBP · up to ' . (int) $MAX_FILE_MB . ' MB each · ' . $remainingSlots . ' slot' . ($remainingSlots === 1 ? '' : 's') . ' left';
            $pmCategories = $PHOTO_CATEGORIES; $pmFirstIsCover = !$editingPhotos;
            require __DIR__ . '/partials/photo_uploader.php';
          ?>
          <p class="pm-help">Tip: include the facade, the inside of the space, the street, and a floor plan.</p>
        </section>

        <!-- DOCUMENTS -->
        <section class="pm-section" id="pmSecDocs">
          <header class="pm-section-head">
            <span class="pm-section-icon"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 15l2 2 4-4"/></svg></span>
            <div><h4>Verification documents on file</h4><p>Reviewed by the Pwestora SuperAdmin. Not shown to tenants.</p></div>
          </header>
          <div class="pm-doclist">
            <?php foreach (['title_doc_path' => 'Land Title Document', 'tax_dec_path' => 'Tax Declaration Document'] as $col => $label): ?>
              <?php if (!empty($editingProperty[$col])): ?>
                <a href="/<?= e(ltrim($editingProperty[$col], '/')) ?>" target="_blank" rel="noopener" class="pm-doc">
                  <span class="pm-doc-icon ok"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                  <span class="pm-doc-name"><?= e($label) ?></span>
                  <span class="pm-doc-open">Open ↗</span>
                </a>
              <?php else: ?>
                <div class="pm-doc">
                  <span class="pm-doc-icon miss">!</span>
                  <span class="pm-doc-name"><?= e($label) ?></span>
                  <span class="pm-muted">Not uploaded</span>
                </div>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </section>

        <div class="pm-drawer-foot">
          <a href="/dashboard.php?page=listings" class="pm-btn pm-btn-ghost">Cancel</a>
          <button class="pm-btn pm-btn-primary" type="submit">Save Changes</button>
        </div>
      </form>

      <!-- One hidden form for every gallery button (set cover / edit / delete) -->
      <form id="pmPhotoActionForm" method="POST" action="/dashboard.php?page=listings&amp;edit=<?= (int) $editingProperty['id'] ?>" hidden>
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="property_id" value="<?= (int) $editingProperty['id'] ?>">
        <input type="hidden" name="photo_id" value="">
        <input type="hidden" name="category" value="">
        <input type="hidden" name="caption" value="">
      </form>
    </div>
  </div>
</div>

<script>
function pmToggleEdit(id) {
  var box = document.getElementById('pmEdit' + id);
  if (box) box.hidden = !box.hidden;
}
function pmPhotoAction(action, id) {
  var f = document.getElementById('pmPhotoActionForm');
  if (!f) return;
  f.querySelectorAll('input.pm-action').forEach(function (n) { n.remove(); });
  var a = document.createElement('input');
  a.type = 'hidden'; a.name = action; a.value = '1'; a.className = 'pm-action';
  f.appendChild(a);
  f.photo_id.value = id;
  if (action === 'update_photo') {
    var box = document.getElementById('pmEdit' + id);
    f.category.value = box.querySelector('[data-field=category]').value;
    f.caption.value = box.querySelector('[data-field=caption]').value;
  }
  f.submit();
}
(function () {
  // Section tabs: scroll inside the drawer and highlight the section in view.
  var body = document.getElementById('pmEditBody');
  var tabs = document.querySelectorAll('#pmEditDrawer [data-pm-jump]');
  var lockUntil = 0;
  function mark(tab) { tabs.forEach(function (t) { t.classList.toggle('is-active', t === tab); }); }
  tabs.forEach(function (t) {
    t.addEventListener('click', function () {
      var target = document.getElementById(t.getAttribute('data-pm-jump'));
      if (!target) return;
      mark(t);
      lockUntil = Date.now() + 900; // don't let the scroll-spy fight the smooth scroll
      body.scrollTo({ top: target.offsetTop - 12, behavior: 'smooth' });
    });
  });
  body.addEventListener('scroll', function () {
    if (Date.now() < lockUntil) return;
    var current = tabs[0];
    tabs.forEach(function (t) {
      var s = document.getElementById(t.getAttribute('data-pm-jump'));
      if (s && s.offsetTop - 40 <= body.scrollTop) current = t;
    });
    // At the very bottom, the last section is the one in view.
    if (body.scrollTop + body.clientHeight >= body.scrollHeight - 4) current = tabs[tabs.length - 1];
    mark(current);
  });
  if (location.hash === '#gallery') {
    var g = document.getElementById('gallery');
    if (g) setTimeout(function () { body.scrollTop = g.offsetTop - 12; }, 50);
  }
  var toast = document.getElementById('pmSavedToast');
  if (toast) {
    if (window.history.replaceState) window.history.replaceState(null, '', '/dashboard.php?page=listings&edit=<?= (int) $editingProperty['id'] ?>');
    setTimeout(function () { toast.classList.add('is-hiding'); }, 2600);
  }
})();
</script>
<script>
(function () {
  if (typeof L === 'undefined') return;

  var initLat = <?= json_encode((float) ($editingProperty['latitude'] ?? 10.6713)) ?>;
  var initLng = <?= json_encode((float) ($editingProperty['longitude'] ?? 122.9511)) ?>;
  var hasCoord = <?= json_encode(!empty($editingProperty['latitude']) && !empty($editingProperty['longitude'])) ?>;

  var map = L.map('editPropertyMap').setView([initLat, initLng], hasCoord ? 16 : 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors', maxZoom: 19
  }).addTo(map);

  var latInput = document.getElementById('editLatInput');
  var lngInput = document.getElementById('editLngInput');
  var coordDisplay = document.getElementById('coordDisplay');
  var marker = null;

  function setMarker(lat, lng) {
    if (marker) {
      marker.setLatLng([lat, lng]);
    } else {
      marker = L.marker([lat, lng], { draggable: true }).addTo(map);
      marker.on('dragend', function () {
        var p = marker.getLatLng();
        latInput.value = p.lat.toFixed(7);
        lngInput.value = p.lng.toFixed(7);
        if (coordDisplay) coordDisplay.textContent = 'GPS: ' + latInput.value + ', ' + lngInput.value;
      });
    }
    latInput.value = lat.toFixed(7);
    lngInput.value = lng.toFixed(7);
    if (coordDisplay) coordDisplay.textContent = 'GPS: ' + latInput.value + ', ' + lngInput.value;
  }

  if (hasCoord) {
    setMarker(initLat, initLng);
  }

  map.on('click', function (e) { setMarker(e.latlng.lat, e.latlng.lng); });

  var searchTimeout;
  document.getElementById('editMapSearch').addEventListener('input', function () {
    clearTimeout(searchTimeout);
    var q = this.value.trim();
    if (q.length < 3) return;
    searchTimeout = setTimeout(function () {
      fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(q))
        .then(function (r) { return r.json(); })
        .then(function (results) {
          if (results && results.length) {
            var lat = parseFloat(results[0].lat), lng = parseFloat(results[0].lon);
            map.setView([lat, lng], 16);
            setMarker(lat, lng);
          }
        });
    }, 600);
  });

  // Total & Available units validation for Customize / Edit Property
  var editTotal = document.getElementById('edit_total_units');
  var editAvail = document.getElementById('edit_available_units');
  if (editTotal && editAvail) {
    editTotal.addEventListener('input', function() {
      var t = parseInt(editTotal.value, 10);
      var a = parseInt(editAvail.value, 10);
      if (!isNaN(t) && !isNaN(a) && a > t) {
        editAvail.value = t;
      }
    });
    editAvail.addEventListener('change', function() {
      var t = parseInt(editTotal.value, 10) || 1;
      var a = parseInt(editAvail.value, 10) || 0;
      if (a > t) {
        alert('Available vacancies (' + a + ') cannot exceed total capacity (' + t + '). Adjusting to ' + t + '.');
        editAvail.value = t;
      }
    });
  }

  setTimeout(function() { map.invalidateSize(); }, 300);
})();
</script>
<?php endif; ?>

<?php if (!$listingSaved && (isset($_GET['add']) || ($error && !$editingProperty))): ?>
<div class="pm-overlay">
  <div class="pm-drawer">
    <div class="pm-drawer-head">
      <div>
        <h3>Add a new commercial property</h3>
        <p class="pm-sub">Submitted for SuperAdmin review — tenants won't see it until it's verified.</p>
      </div>
      <a href="/dashboard.php?page=listings" class="pm-close" aria-label="Close">&times;</a>
    </div>

    <ol class="pm-steps">
      <li><span>1</span> Details</li>
      <li><span>2</span> Location</li>
      <li><span>3</span> Photos</li>
      <li><span>4</span> Documents</li>
    </ol>

    <div class="pm-drawer-body">
      <?php if ($error): ?>
        <div class="error-msg" data-pm-error style="margin-bottom:16px;"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data" id="addPropertyForm"
            action="/dashboard.php?page=listings&amp;add=1"
            data-pm-progress data-max-total-mb="<?= (int) $MAX_POST_MB ?>">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="add_property" value="1">
        <div class="pm-form-error error-msg" hidden></div>

        <section class="pm-section">
          <header class="pm-section-head">
            <span class="pm-section-icon">1</span>
            <div><h4>Property details</h4><p>Fields marked <span class="pm-req">*</span> are required.</p></div>
          </header>
          <div class="field">
            <label>Property Name <span class="pm-req">*</span></label>
            <input type="text" name="name" required maxlength="150" value="<?= e($_POST['name'] ?? '') ?>">
          </div>
          <div class="pm-grid-2">
            <div class="field">
              <label>Type <span class="pm-req">*</span></label>
              <select name="type" required>
                <option value="">Select type…</option>
                <?php foreach ($PROPERTY_TYPES as $t): ?>
                  <option value="<?= e($t) ?>" <?= (($_POST['type'] ?? '') === $t) ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label>Asking Monthly Rent (₱) <span class="pm-req">*</span></label>
              <input type="number" name="asking_rent" min="1" step="0.01" required value="<?= e($_POST['asking_rent'] ?? '') ?>" placeholder="e.g. 35000">
            </div>
            <div class="field">
              <label>Floor Area <span class="pm-req">*</span></label>
              <input type="text" name="floor_area" placeholder="e.g. 600 SQM (3 floors)" required value="<?= e($_POST['floor_area'] ?? '') ?>">
            </div>
            <div class="field">
              <label>Lease Term <span class="pm-req">*</span></label>
              <input type="text" name="lease_term" placeholder="e.g. Minimum of 2 years" required value="<?= e($_POST['lease_term'] ?? '') ?>">
            </div>
          </div>
          <div class="field">
            <label>Escalation Rate <span class="pm-req">*</span></label>
            <input type="text" name="escalation" placeholder="e.g. 4% annual increase" required value="<?= e($_POST['escalation'] ?? '') ?>">
          </div>
          <div class="field">
            <label>Property Description <span class="pm-req">*</span></label>
            <textarea name="description" rows="4" placeholder="Prime office space in Bacolod's business district…" required><?= e($_POST['description'] ?? '') ?></textarea>
            <p class="pm-help">The rent shown here is the asking rate; the actual lease rent is finalized when you approve an application.</p>
          </div>

          <div class="pm-subcard">
            <div class="pm-subcard-head"><span>Rental spaces &amp; vacancy</span></div>
            <div class="pm-grid-2">
              <div class="field" style="margin-bottom:0;">
                <label>Total Spaces / Capacity <span class="pm-req">*</span></label>
                <input type="number" name="total_units" id="add_total_units" min="1" step="1" required value="<?= e($_POST['total_units'] ?? '1') ?>" placeholder="e.g. 20">
                <p class="pm-help">Total rentable stalls, rooms or units.</p>
              </div>
              <div class="field" style="margin-bottom:0;">
                <label>Initial Available Spaces <span class="pm-req">*</span></label>
                <input type="number" name="available_units" id="add_available_units" min="0" step="1" required value="<?= e($_POST['available_units'] ?? '1') ?>" placeholder="e.g. 20">
                <p class="pm-help">How many are vacant now.</p>
              </div>
            </div>
          </div>
        </section>

        <?php $pxSrc = []; $pxEdit = false; require __DIR__ . '/partials/property_extra_fields.php'; ?>

        <section class="pm-section">
          <header class="pm-section-head">
            <span class="pm-section-icon">2</span>
            <div><h4>Location</h4><p>Pinning the exact spot is optional but strongly recommended.</p></div>
          </header>
          <div class="field">
            <label>Address <span class="pm-req">*</span></label>
            <input type="text" name="address" required maxlength="255" value="<?= e($_POST['address'] ?? '') ?>">
          </div>
          <div class="field">
            <label>Pin the exact location</label>
            <input type="text" id="mapSearchInput" placeholder="Search an address to jump the map…" style="margin-bottom:8px;">
            <div id="propertyMap" class="pm-map"></div>
            <input type="hidden" name="latitude" id="latInput" value="<?= e($_POST['latitude'] ?? '') ?>">
            <input type="hidden" name="longitude" id="lngInput" value="<?= e($_POST['longitude'] ?? '') ?>">
          </div>
        </section>

        <section class="pm-section">
          <header class="pm-section-head">
            <span class="pm-section-icon">3</span>
            <div><h4>Property photos <span class="pm-req">*</span></h4><p>1–<?= (int) $MAX_PHOTOS ?> photos. The first one becomes the cover; you can change it later.</p></div>
          </header>
          <?php
            $pmName = 'photos[]'; $pmSingle = false; $pmMaxFiles = $MAX_PHOTOS; $pmRequired = true;
            $pmTitle = 'Drag photos here or click to browse';
            $pmHint = 'JPG, PNG or WEBP · up to ' . (int) $MAX_FILE_MB . ' MB each · max ' . (int) $MAX_PHOTOS . ' photos';
            $pmCategories = $PHOTO_CATEGORIES; $pmFirstIsCover = true;
            require __DIR__ . '/partials/photo_uploader.php';
          ?>
        </section>

        <section class="pm-section">
          <header class="pm-section-head">
            <span class="pm-section-icon">4</span>
            <div><h4>Verification documents <span class="pm-req">*</span></h4><p>PDF, JPG or PNG, up to <?= (int) $MAX_FILE_MB ?> MB each. Only the SuperAdmin sees these.</p></div>
          </header>
          <?php foreach ($SINGLE_DOCS as $field => [$column, $label]): ?>
            <label class="pm-filebox">
              <span class="pm-filebox-icon"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg></span>
              <span class="pm-filebox-text">
                <strong><?= e($label) ?> <span class="pm-req">*</span></strong>
                <span class="pm-filebox-name" data-empty="No file chosen">No file chosen</span>
              </span>
              <span class="pm-btn pm-btn-ghost pm-btn-xs">Choose file</span>
              <input type="file" name="<?= e($field) ?>" accept=".pdf,.jpg,.jpeg,.png" required
                     onchange="var n=this.closest('.pm-filebox').querySelector('.pm-filebox-name'); n.textContent=this.files.length?this.files[0].name:n.getAttribute('data-empty'); this.closest('.pm-filebox').classList.toggle('is-set', !!this.files.length);">
            </label>
          <?php endforeach; ?>
        </section>

        <div class="pm-drawer-foot">
          <a href="/dashboard.php?page=listings" class="pm-btn pm-btn-ghost">Cancel</a>
          <button class="pm-btn pm-btn-primary" type="submit">Submit for Verification</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
(function () {
  if (typeof L === 'undefined') return;

  var startLat = <?= json_encode((float) ($_POST['latitude'] ?? 10.6713)) ?>;
  var startLng = <?= json_encode((float) ($_POST['longitude'] ?? 122.9511)) ?>;

  var map = L.map('propertyMap').setView([startLat, startLng], 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors', maxZoom: 19
  }).addTo(map);

  var latInput = document.getElementById('latInput');
  var lngInput = document.getElementById('lngInput');
  var marker = null;

  function setMarker(lat, lng) {
    if (marker) {
      marker.setLatLng([lat, lng]);
    } else {
      marker = L.marker([lat, lng], { draggable: true }).addTo(map);
      marker.on('dragend', function () {
        var p = marker.getLatLng();
        latInput.value = p.lat.toFixed(7);
        lngInput.value = p.lng.toFixed(7);
      });
    }
    latInput.value = lat.toFixed(7);
    lngInput.value = lng.toFixed(7);
  }

  if (latInput.value && lngInput.value) {
    setMarker(parseFloat(latInput.value), parseFloat(lngInput.value));
  }

  map.on('click', function (e) { setMarker(e.latlng.lat, e.latlng.lng); });

  var searchTimeout;
  document.getElementById('mapSearchInput').addEventListener('input', function () {
    clearTimeout(searchTimeout);
    var q = this.value.trim();
    if (q.length < 3) return;
    searchTimeout = setTimeout(function () {
      fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(q))
        .then(function (r) { return r.json(); })
        .then(function (results) {
          if (results && results.length) {
            var lat = parseFloat(results[0].lat), lng = parseFloat(results[0].lon);
            map.setView([lat, lng], 16);
            setMarker(lat, lng);
          }
        });
    }, 600);
  });

  // Auto-sync and validation for Add Property
  var addTotal = document.getElementById('add_total_units');
  var addAvail = document.getElementById('add_available_units');
  if (addTotal && addAvail) {
    var userEditedAvail = false;
    addAvail.addEventListener('input', function() {
      userEditedAvail = true;
      var t = parseInt(addTotal.value, 10) || 1;
      var a = parseInt(addAvail.value, 10) || 0;
      if (a > t) {
        addAvail.setCustomValidity('Available spaces cannot exceed total capacity (' + t + ').');
      } else {
        addAvail.setCustomValidity('');
      }
    });
    addTotal.addEventListener('input', function() {
      var t = parseInt(addTotal.value, 10) || 1;
      if (!userEditedAvail) {
        addAvail.value = t;
      } else {
        var a = parseInt(addAvail.value, 10) || 0;
        if (a > t) {
          addAvail.setCustomValidity('Available spaces cannot exceed total capacity (' + t + ').');
        } else {
          addAvail.setCustomValidity('');
        }
      }
    });
  }

  setTimeout(function() { map.invalidateSize(); }, 300);
})();
</script>
<?php endif; ?>
<!-- Upload progress (assets/js/photo-uploader.js) -->
<div class="pm-progress" id="pmProgress" hidden>
  <div class="pm-progress-card">
    <div class="pm-progress-title">Saving your property</div>
    <div class="pm-progress-track"><div class="pm-progress-fill"></div></div>
    <div class="pm-progress-label">Preparing upload…</div>
  </div>
</div>

<!-- Photo viewer -->
<div class="pm-lightbox" id="pmLightbox" hidden>
  <button type="button" class="pm-lightbox-close" aria-label="Close">&times;</button>
  <figure><img src="" alt=""><figcaption class="pm-lightbox-cap"></figcaption></figure>
</div>
