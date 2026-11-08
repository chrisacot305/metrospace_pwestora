<?php
/**
 * app/Views/lessor/listings.php — HTML for the "listings" screen (was the bottom half of modules/listings.php).
 * Data comes from app/Controllers/Lessor/ListingsController.php
 */
?>
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

<?php if ($editingProperty && !$listingSaved): ?>
<!-- CUSTOMIZE / EDIT PROPERTY DRAWER -->
<div style="position:fixed; inset:0; background:rgba(0,0,0,.45); display:flex; justify-content:flex-end; z-index:1050; backdrop-filter:blur(2px);">
  <div style="background:#fff; width:540px; max-width:95vw; height:100%; padding:26px; overflow-y:auto; box-shadow:-4px 0 24px rgba(0,0,0,0.15);">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px; border-bottom:1px solid var(--border); padding-bottom:14px;">
      <div>
        <div style="display:flex; align-items:center; gap:8px;">
          <h3 style="margin:0; font-size:18px;">Customize Property</h3>
          <span class="badge" style="font-size:11px; padding:3px 8px; <?= $editingProperty['status'] === 'verified' ? 'background:var(--success-soft); color:var(--success);' : 'background:var(--warning-soft); color:var(--warning);' ?>">
            <?= ucfirst($editingProperty['status']) ?>
          </span>
        </div>
        <p style="font-size:12.5px; color:var(--ink-500); margin:4px 0 0;">Update details, reposition map pin, or manage photos for <strong><?= htmlspecialchars($editingProperty['name']) ?></strong>.</p>
      </div>
      <a href="/dashboard.php?page=listings" style="font-size:24px; color:var(--ink-500); text-decoration:none; line-height:1; padding:2px 6px;">&times;</a>
    </div>

    <?php if ($error): ?>
      <div class="error-msg" style="margin-bottom:16px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="edit_property" value="1">
      <input type="hidden" name="property_id" value="<?= $editingProperty['id'] ?>">

      <div class="field">
        <label style="font-weight:600; font-size:12.5px;">Property Name</label>
        <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? $editingProperty['name']) ?>">
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
        <div class="field">
          <label style="font-weight:600; font-size:12.5px;">Commercial Type</label>
          <select name="type" required>
            <?php foreach ($PROPERTY_TYPES as $t): ?>
              <option value="<?= $t ?>" <?= (($_POST['type'] ?? $editingProperty['type']) === $t) ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label style="font-weight:600; font-size:12.5px;">Monthly Rent (₱)</label>
          <input type="number" name="asking_rent" min="1" step="0.01" required
                 value="<?= htmlspecialchars($_POST['asking_rent'] ?? $editingProperty['asking_rent']) ?>">
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
        <div class="field">
          <label style="font-weight:600; font-size:12.5px;">Floor Area</label>
          <input type="text" name="floor_area" placeholder="e.g. 600 SQM (3 floors)"
                 value="<?= htmlspecialchars($_POST['floor_area'] ?? $editingProperty['floor_area'] ?? '') ?>">
        </div>

        <div class="field">
          <label style="font-weight:600; font-size:12.5px;">Lease Term</label>
          <input type="text" name="lease_term" placeholder="e.g. Minimum of 2 years"
                 value="<?= htmlspecialchars($_POST['lease_term'] ?? $editingProperty['lease_term'] ?? '') ?>">
        </div>
      </div>

      <div class="field">
        <label style="font-weight:600; font-size:12.5px;">Annual Increase</label>
        <input type="text" name="escalation" placeholder="e.g. 4% annual increase"
               value="<?= htmlspecialchars($_POST['escalation'] ?? $editingProperty['escalation'] ?? '') ?>">
      </div>

      <div class="field">
        <label style="font-weight:600; font-size:12.5px;">Property Description</label>
        <textarea name="description" rows="3" placeholder="Describe the space, amenities, security, etc."
                  style="width:100%; box-sizing:border-box; padding:8px 10px; font-family:inherit; font-size:13px; border:1px solid var(--border); border-radius:6px;"><?= htmlspecialchars($_POST['description'] ?? $editingProperty['description'] ?? '') ?></textarea>
      </div>

      <!-- RENTAL SPACES & VACANCY CAPACITY -->
      <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px 16px; margin-bottom:14px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px;">
          <span style="font-size:13px; font-weight:700; color:var(--ink-800); display:flex; align-items:center; gap:6px;">
            <i class="bi bi-grid-3x3-gap-fill" style="color:var(--primary);"></i> Rental Spaces & Vacancy Availability
          </span>
          <?php $currentActive = (int)($editingProperty['active_tenants_count'] ?? 0); ?>
          <span style="font-size:11px; background:#e0f2fe; color:#0369a1; padding:3px 9px; border-radius:12px; font-weight:600;">
            <i class="bi bi-people-fill"></i> <?= $currentActive ?> active tenant<?= $currentActive === 1 ? '' : 's' ?> in Pwestora
          </span>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
          <div class="field" style="margin-bottom:0;">
            <label style="font-weight:600; font-size:12px;">Total Spaces / Capacity</label>
            <input type="number" name="total_units" id="edit_total_units" min="1" step="1" required
                   value="<?= htmlspecialchars($_POST['total_units'] ?? $editingProperty['total_units'] ?? 1) ?>"
                   style="font-size:13px; font-weight:600; width:100%; box-sizing:border-box;">
            <p style="font-size:11px; color:var(--ink-500); margin:4px 0 0;">
              Total rentable stalls/spaces (e.g. increase from 20 to 21 if you added a new space).
            </p>
          </div>

          <div class="field" style="margin-bottom:0;">
            <label style="font-weight:600; font-size:12px;">Available Vacancies</label>
            <input type="number" name="available_units" id="edit_available_units" min="0" step="1" required
                   value="<?= htmlspecialchars($_POST['available_units'] ?? $editingProperty['available_units'] ?? 1) ?>"
                   style="font-size:13px; font-weight:600; width:100%; box-sizing:border-box;">
            <p style="font-size:11px; color:var(--ink-500); margin:4px 0 0;">
              Vacancies open for rent. Adjust if you take in walk-in tenants outside the app.
            </p>
          </div>
        </div>
      </div>

      <div class="field">
        <label style="font-weight:600; font-size:12.5px;">Physical Address</label>
        <input type="text" name="address" required value="<?= htmlspecialchars($_POST['address'] ?? $editingProperty['address']) ?>">
      </div>

      <!-- MAP PIN CUSTOMIZATION -->
      <div class="field" style="margin-top:10px;">
        <label style="font-weight:600; font-size:12.5px; display:flex; justify-content:space-between; align-items:center;">
          <span>Pin Location on Map</span>
          <span style="font-size:11px; color:var(--ink-500); font-weight:normal;">Click map or drag pin</span>
        </label>
        <input type="text" id="editMapSearch" placeholder="Search address or area in Bacolod…" style="margin-bottom:8px; font-size:12.5px;">
        <div id="editPropertyMap" style="height:220px; border-radius:10px; border:1px solid var(--border); z-index:1;"></div>
        <input type="hidden" name="latitude" id="editLatInput" value="<?= htmlspecialchars($_POST['latitude'] ?? $editingProperty['latitude'] ?? '') ?>">
        <input type="hidden" name="longitude" id="editLngInput" value="<?= htmlspecialchars($_POST['longitude'] ?? $editingProperty['longitude'] ?? '') ?>">
        <div style="font-size:11px; color:var(--ink-500); margin-top:5px; display:flex; justify-content:space-between;">
          <span id="coordDisplay">GPS: <?= $editingProperty['latitude'] ? $editingProperty['latitude'] . ', ' . $editingProperty['longitude'] : 'Not pinned yet' ?></span>
        </div>
      </div>

      <!-- PHOTO GALLERY MANAGEMENT -->
      <div class="field" style="margin-top:16px;">
        <label style="font-weight:600; font-size:12.5px; margin-bottom:8px; display:block;">
          Property Photos Gallery (<?= count($editingPhotos) ?> uploaded)
        </label>

        <?php if ($editingPhotos): ?>
          <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(100px, 1fr)); gap:10px; margin-bottom:14px;">
            <?php foreach ($editingPhotos as $ph): ?>
              <div style="position:relative; border-radius:8px; overflow:hidden; border:1px solid var(--border); background:#f8fafc; height:80px;">
                <img src="/<?= htmlspecialchars($ph['photo_path']) ?>" style="width:100%; height:100%; object-fit:cover;">
                <button type="submit" name="delete_photo_trigger" value="<?= $ph['id'] ?>"
                        onclick="if(confirm('Delete this photo?')){ document.getElementById('deletePhotoForm_<?= $ph['id'] ?>').submit(); } return false;"
                        title="Delete photo"
                        style="position:absolute; top:3px; right:3px; background:rgba(220,38,38,0.85); color:#fff; border:none; border-radius:4px; width:22px; height:22px; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:11px;">
                  <i class="bi bi-trash"></i>
                </button>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p style="font-size:12px; color:var(--ink-500); margin-bottom:10px;">No gallery photos uploaded yet.</p>
        <?php endif; ?>

        <label style="font-weight:600; font-size:12px; color:var(--ink-700);">Upload Additional Photos</label>
        <input type="file" name="photos[]" accept=".jpg,.jpeg,.png,.webp" multiple style="font-size:12.5px;">
        <p style="font-size:11px; color:var(--ink-500); margin-top:4px;">Accepts JPG, PNG, WEBP (up to <?= $MAX_PHOTOS ?> total photos).</p>
      </div>

      <!-- ATTACHED VERIFICATION DOCS -->
      <div style="background:#f8fafc; border:1px solid var(--border); border-radius:8px; padding:12px; margin-top:16px;">
        <div style="font-size:12px; font-weight:700; color:var(--ink-700); margin-bottom:6px;">Verification Documents on File</div>
        <div style="display:flex; flex-direction:column; gap:6px; font-size:12px;">
          <?php if ($editingProperty['title_doc_path']): ?>
            <a href="/<?= htmlspecialchars($editingProperty['title_doc_path']) ?>" target="_blank" style="color:var(--primary); text-decoration:none; display:flex; align-items:center; gap:6px;">
              <i class="bi bi-file-earmark-check"></i> Land Title Document <i class="bi bi-box-arrow-up-right" style="font-size:10px;"></i>
            </a>
          <?php endif; ?>
          <?php if ($editingProperty['tax_dec_path']): ?>
            <a href="/<?= htmlspecialchars($editingProperty['tax_dec_path']) ?>" target="_blank" style="color:var(--primary); text-decoration:none; display:flex; align-items:center; gap:6px;">
              <i class="bi bi-file-earmark-check"></i> Tax Declaration Document <i class="bi bi-box-arrow-up-right" style="font-size:10px;"></i>
            </a>
          <?php endif; ?>
        </div>
      </div>

      <div style="display:flex; gap:10px; margin-top:24px; padding-top:14px; border-top:1px solid var(--border);">
        <button class="btn btn-primary" type="submit" style="flex:1;">Save Changes</button>
        <a href="/dashboard.php?page=listings" class="btn" style="border:1px solid var(--border); text-align:center; padding:11px 18px; color:var(--ink-800); text-decoration:none;">Cancel</a>
      </div>
    </form>

    <!-- Hidden Photo Delete Forms -->
    <?php foreach ($editingPhotos as $ph): ?>
      <form id="deletePhotoForm_<?= $ph['id'] ?>" method="POST" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="delete_photo" value="1">
        <input type="hidden" name="photo_id" value="<?= $ph['id'] ?>">
        <input type="hidden" name="property_id" value="<?= $editingProperty['id'] ?>">
      </form>
    <?php endforeach; ?>

  </div>
</div>

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
<div style="position:fixed; inset:0; background:rgba(0,0,0,.4); display:flex; justify-content:flex-end; z-index:1050; backdrop-filter:blur(2px);">
  <div style="background:#fff; width:480px; max-width:92vw; height:100%; padding:24px; overflow-y:auto; box-shadow:-4px 0 24px rgba(0,0,0,0.15);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
      <h3 style="margin:0;">Add a new commercial property</h3>
      <a href="/dashboard.php?page=listings" style="font-size:20px; color:var(--ink-500); text-decoration:none;">&times;</a>
    </div>
    <p style="font-size:12.5px; color:var(--ink-500); margin-bottom:16px;">
      Submitted for SuperAdmin review, it won't be visible to tenants until verified.
      <strong>All fields and all 3 document uploads are required.</strong> If any is missing, nothing gets saved.
    </p>

    <?php if ($error): ?>
      <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div class="field">
        <label>Property Name</label>
        <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
      </div>
      <div class="field">
        <label>Type</label>
        <select name="type" required>
          <option value="">Select type…</option>
          <?php foreach ($PROPERTY_TYPES as $t): ?>
            <option value="<?= $t ?>" <?= (($_POST['type'] ?? '') === $t) ? 'selected' : '' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <!-- NEW FIELDS: ADDED HERE -->
      <div class="field">
        <label>Floor Area</label>
        <input type="text" name="floor_area" placeholder="e.g. 600 SQM (3 floors)" required value="<?= htmlspecialchars($_POST['floor_area'] ?? '') ?>">
      </div>

      <div class="field">
        <label>Lease Term</label>
        <input type="text" name="lease_term" placeholder="e.g. Minimum of 2 years" required value="<?= htmlspecialchars($_POST['lease_term'] ?? '') ?>">
      </div>

      <div class="field">
        <label>Escalation Rate</label>
        <input type="text" name="escalation" placeholder="e.g. 4% annual increase" required value="<?= htmlspecialchars($_POST['escalation'] ?? '') ?>">
      </div>

      <div class="field">
        <label>Property Description</label>
        <textarea name="description" rows="3" placeholder="Prime office space in Bacolod's business district..." required style="width:100%; padding:8px; border:1px solid var(--border); border-radius:4px; font-family:inherit; box-sizing:border-box;"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
      </div>
      <!-- END NEW FIELDS -->

      <!-- RENTAL SPACES & VACANCY CAPACITY -->
      <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px 16px; margin-bottom:14px;">
        <div style="font-size:13px; font-weight:700; color:var(--ink-800); margin-bottom:10px; display:flex; align-items:center; gap:6px;">
          <i class="bi bi-grid-3x3-gap-fill" style="color:var(--primary);"></i> Rental Spaces & Vacancy Availability
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
          <div class="field" style="margin-bottom:0;">
            <label style="font-weight:600; font-size:12px;">Total Spaces / Capacity</label>
            <input type="number" name="total_units" id="add_total_units" min="1" step="1" required
                   value="<?= htmlspecialchars($_POST['total_units'] ?? '1') ?>"
                   placeholder="e.g. 20"
                   style="font-size:13px; font-weight:600; width:100%; box-sizing:border-box;">
            <p style="font-size:11px; color:var(--ink-500); margin:4px 0 0;">
              Total rentable stalls, rooms, or units (e.g. 20).
            </p>
          </div>

          <div class="field" style="margin-bottom:0;">
            <label style="font-weight:600; font-size:12px;">Initial Available Spaces</label>
            <input type="number" name="available_units" id="add_available_units" min="0" step="1" required
                   value="<?= htmlspecialchars($_POST['available_units'] ?? '1') ?>"
                   placeholder="e.g. 20"
                   style="font-size:13px; font-weight:600; width:100%; box-sizing:border-box;">
            <p style="font-size:11px; color:var(--ink-500); margin:4px 0 0;">
              How many are vacant now. Adjust anytime in Customize Property.
            </p>
          </div>
        </div>
      </div>

      <div class="field">
        <label>Asking Monthly Rent (₱)</label>
        <input type="number" name="asking_rent" min="1" step="0.01" required
               value="<?= htmlspecialchars($_POST['asking_rent'] ?? '') ?>" placeholder="e.g. 35000">
        <p style="font-size:11px; color:var(--ink-500); margin-top:4px;">Shown to tenants browsing, but the actual lease rent will be finalized when you approve an application.</p>
      </div>

      <div class="field">
        <label>Address</label>
        <input type="text" name="address" required value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
      </div>

      <div class="field">
        <label>Pin the exact location</label>
        <input type="text" id="mapSearchInput" placeholder="Search an address to jump the map…" style="margin-bottom:8px;">
        <div id="propertyMap" style="height:240px; border-radius:10px; border:1px solid var(--border);"></div>
        <input type="hidden" name="latitude" id="latInput" value="<?= htmlspecialchars($_POST['latitude'] ?? '') ?>">
        <input type="hidden" name="longitude" id="lngInput" value="<?= htmlspecialchars($_POST['longitude'] ?? '') ?>">
        <p style="font-size:11px; color:var(--ink-500); margin-top:6px;">
          Optional but recommended
        </p>
      </div>

      <?php foreach ($SINGLE_DOCS as $field => [$column, $label]): ?>
        <div class="field">
          <label><?= htmlspecialchars($label) ?> <span style="color:var(--error);">*</span></label>
          <input type="file" name="<?= $field ?>" accept=".pdf,.jpg,.jpeg,.png" required>
        </div>
      <?php endforeach; ?>

      <div class="field">
        <label>Property Photos (1–<?= $MAX_PHOTOS ?>) <span style="color:var(--error);">*</span></label>
        <input type="file" name="photos[]" accept=".jpg,.jpeg,.png,.webp" multiple required>
      </div>

      <button class="btn btn-primary" type="submit">Submit for Verification</button>
    </form>
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