<?php
/**
 * modules/listings.php  (Lessor → "My Properties")
 * Commercial spaces only. Land Title + Tax Declaration are single files;
 * Property Photos now accepts multiple files, stored in property_photos.
 */
$lessorId = $user['id'];
$error = '';
$listingSaved = false;

$listingsRedirect = static function (string $extra = '') {
    if (!headers_sent()) {
        header('Location: /dashboard.php?page=listings' . $extra);
        exit;
    }
};

$PROPERTY_TYPES = ['Retail', 'Office', 'Warehouse', 'Food Stall', 'Medical/Clinic', 'Coworking Space', 'Commercial Lot', 'Other'];

$SINGLE_DOCS = [
    'title_doc' => ['title_doc_path', 'Land Title'],
    'tax_dec'   => ['tax_dec_path',   'Tax Declaration'],
];
$ALLOWED_DOC_EXT   = ['pdf', 'jpg', 'jpeg', 'png'];
$ALLOWED_PHOTO_EXT = ['jpg', 'jpeg', 'png', 'webp'];
$MAX_FILE_MB   = 5;
$MAX_PHOTOS    = 10;

if (!function_exists('handle_single_doc')) {
    function handle_single_doc(string $field, int $propertyId, array $allowedExt, int $maxMb): string {
        if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('Please attach every required document.');
        }
        $file = $_FILES[$field];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed. Please try again.');
        }
        if ($file['size'] > $maxMb * 1024 * 1024) {
            throw new RuntimeException("Each file must be under {$maxMb}MB.");
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            throw new RuntimeException('Only PDF, JPG, and PNG files are accepted for documents.');
        }
        $dir = UPLOAD_DIR . "/properties/{$propertyId}";
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $filename = $field . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], "$dir/$filename")) {
            throw new RuntimeException('Could not save the uploaded file.');
        }
        return "uploads/properties/{$propertyId}/{$filename}";
    }
}

if (!function_exists('handle_multi_photos')) {
    function handle_multi_photos(int $propertyId, array $allowedExt, int $maxMb, int $maxPhotos): array {
        if (empty($_FILES['photos']) || empty($_FILES['photos']['name'][0])) {
            throw new RuntimeException('Please attach at least one property photo.');
        }
        $names = $_FILES['photos']['name'];
        $count = count($names);
        if ($count > $maxPhotos) {
            throw new RuntimeException("You can upload up to {$maxPhotos} photos at once.");
        }

        $dir = UPLOAD_DIR . "/properties/{$propertyId}";
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $saved = [];
        for ($i = 0; $i < $count; $i++) {
            if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('One of the photos failed to upload. Please try again.');
            }
            if ($_FILES['photos']['size'][$i] > $maxMb * 1024 * 1024) {
                throw new RuntimeException("Each photo must be under {$maxMb}MB.");
            }
            $ext = strtolower(pathinfo($names[$i], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt, true)) {
                throw new RuntimeException('Photos must be JPG, PNG, or WEBP.');
            }
            $filename = 'photo_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (!move_uploaded_file($_FILES['photos']['tmp_name'][$i], "$dir/$filename")) {
                throw new RuntimeException('Could not save one of the photos.');
            }
            $saved[] = "uploads/properties/{$propertyId}/{$filename}";
        }
        return $saved;
    }
}

// EDIT PROPERTY
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_property'], $_POST['property_id'])) {
    csrf_check();
    $propertyId  = (int) $_POST['property_id'];
    $name        = trim($_POST['name'] ?? '');
    $type        = trim($_POST['type'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $lat         = trim($_POST['latitude'] ?? '');
    $lng         = trim($_POST['longitude'] ?? '');
    $lat         = ($lat !== '' && is_numeric($lat)) ? (float) $lat : null;
    $lng         = ($lng !== '' && is_numeric($lng)) ? (float) $lng : null;
    $askingRent  = trim($_POST['asking_rent'] ?? '');
    $askingRent  = ($askingRent !== '' && is_numeric($askingRent)) ? (float) $askingRent : null;
    $floorArea   = trim($_POST['floor_area'] ?? '');
    $leaseTerm   = trim($_POST['lease_term'] ?? '');
    $escalation  = trim($_POST['escalation'] ?? '');
    $description = trim($_POST['description'] ?? '');

    $check = $pdo->prepare('SELECT id FROM properties WHERE id = ? AND lessor_id = ?');
    $check->execute([$propertyId, $lessorId]);
    if (!$check->fetch()) {
        $error = 'Property not found or unauthorized.';
    } elseif ($name === '' || $type === '' || $address === '' || $askingRent === null || $askingRent <= 0) {
        $error = 'Please fill in every field with valid information.';
    } else {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE properties 
                 SET name = ?, type = ?, address = ?, latitude = ?, longitude = ?, asking_rent = ?,
                     floor_area = ?, lease_term = ?, escalation = ?, description = ?
                 WHERE id = ? AND lessor_id = ?'
            );
            $stmt->execute([
                $name, $type, $address, $lat, $lng, $askingRent,
                $floorArea ?: null, $leaseTerm ?: null, $escalation ?: null, $description ?: null,
                $propertyId, $lessorId
            ]);

            if (!empty($_FILES['photos']) && !empty($_FILES['photos']['name'][0])) {
                $photoPaths = handle_multi_photos($propertyId, $ALLOWED_PHOTO_EXT, $MAX_FILE_MB, $MAX_PHOTOS);
                $photoStmt = $pdo->prepare('INSERT INTO property_photos (property_id, photo_path) VALUES (?, ?)');
                foreach ($photoPaths as $p) {
                    $photoStmt->execute([$propertyId, $p]);
                }
            }

            audit_log($pdo, $lessorId, 'Updated property details', $name);
            $pdo->commit();
            $listingsRedirect();
            $listingSaved = true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $error = ($e instanceof RuntimeException) ? $e->getMessage() : 'Something went wrong while updating. Please try again.';
        }
    }
}

// DELETE PHOTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_photo'], $_POST['photo_id'], $_POST['property_id'])) {
    csrf_check();
    $photoId    = (int) $_POST['photo_id'];
    $propertyId = (int) $_POST['property_id'];
    $check = $pdo->prepare('SELECT pp.id FROM property_photos pp JOIN properties p ON p.id = pp.property_id WHERE pp.id = ? AND p.id = ? AND p.lessor_id = ?');
    $check->execute([$photoId, $propertyId, $lessorId]);
    if ($check->fetch()) {
        $pdo->prepare('DELETE FROM property_photos WHERE id = ?')->execute([$photoId]);
    }
    $listingsRedirect('&edit=' . $propertyId);
}

// ADD NEW PROPERTY
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['edit_property']) && !isset($_POST['delete_photo'])) {
    csrf_check();
    $name        = trim($_POST['name'] ?? '');
    $type        = trim($_POST['type'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $lat         = trim($_POST['latitude'] ?? '');
    $lng         = trim($_POST['longitude'] ?? '');
    $lat         = ($lat !== '' && is_numeric($lat)) ? (float) $lat : null;
    $lng         = ($lng !== '' && is_numeric($lng)) ? (float) $lng : null;
    $askingRent  = trim($_POST['asking_rent'] ?? '');
    $askingRent  = ($askingRent !== '' && is_numeric($askingRent)) ? (float) $askingRent : null;
    $floorArea   = trim($_POST['floor_area'] ?? '');
    $leaseTerm   = trim($_POST['lease_term'] ?? '');
    $escalation  = trim($_POST['escalation'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '' || $type === '' || $address === '' || $askingRent === null || $askingRent <= 0) {
        $error = 'Please fill in every required field, including a valid asking rent.';
    } else {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO properties (lessor_id, name, type, address, latitude, longitude, asking_rent, floor_area, lease_term, escalation, description, status) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending")'
            );
            $stmt->execute([
                $lessorId, $name, $type, $address, $lat, $lng, $askingRent,
                $floorArea ?: null, $leaseTerm ?: null, $escalation ?: null, $description ?: null
            ]);
            $propertyId = (int) $pdo->lastInsertId();

            $docPaths = [];
            foreach ($SINGLE_DOCS as $field => [$column, $label]) {
                $docPaths[$column] = handle_single_doc($field, $propertyId, $ALLOWED_DOC_EXT, $MAX_FILE_MB);
            }
            $pdo->prepare('UPDATE properties SET title_doc_path = ?, tax_dec_path = ? WHERE id = ?')
                ->execute([$docPaths['title_doc_path'], $docPaths['tax_dec_path'], $propertyId]);

            $photoPaths = handle_multi_photos($propertyId, $ALLOWED_PHOTO_EXT, $MAX_FILE_MB, $MAX_PHOTOS);
            $photoStmt = $pdo->prepare('INSERT INTO property_photos (property_id, photo_path) VALUES (?, ?)');
            foreach ($photoPaths as $p) {
                $photoStmt->execute([$propertyId, $p]);
            }

            audit_log($pdo, $lessorId, 'Submitted property for verification', $name);
            $pdo->commit();
            $listingsRedirect();
            $listingSaved = true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $error = ($e instanceof RuntimeException) ? $e->getMessage() : 'Something went wrong. Please try again.';
        }
    }
}

try {
    $properties = $pdo->prepare(
        'SELECT p.*, (SELECT COUNT(*) FROM property_photos pp WHERE pp.property_id = p.id) AS photo_count,
                (SELECT photo_path FROM property_photos pp WHERE pp.property_id = p.id ORDER BY pp.id ASC LIMIT 1) AS cover_photo,
                (SELECT t.tenant_name FROM tenants t WHERE t.property_id = p.id ORDER BY t.created_at DESC LIMIT 1) AS current_tenant
         FROM properties p WHERE p.lessor_id = ? ORDER BY p.created_at DESC'
    );
    $properties->execute([$lessorId]);
    $properties = $properties->fetchAll();
} catch (Throwable $e) {
    $error = 'Database error: Ensure the new columns exist. ' . $e->getMessage();
    $properties = [];
}

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
$editingProperty = null;
$editingPhotos = [];
if ($editId) {
    foreach ($properties as $p) {
        if ((int) $p['id'] === $editId) { $editingProperty = $p; break; }
    }
    if ($editingProperty) {
        $photoStmt = $pdo->prepare('SELECT id, photo_path FROM property_photos WHERE property_id = ? ORDER BY id ASC');
        $photoStmt->execute([$editId]);
        $editingPhotos = $photoStmt->fetchAll();
    }
}
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
        <div class="prop-stats-row">
          <div>
            <div class="prop-stat-label">Occupancy</div>
            <div class="prop-stat-value"><?= $p['current_tenant'] ? 'Occupied' : 'Vacant' ?></div>
          </div>
          <div>
            <div class="prop-stat-label">Tenant</div>
            <div class="prop-stat-value"><?= $p['current_tenant'] ? htmlspecialchars($p['current_tenant']) : '—' ?></div>
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

  setTimeout(function() { map.invalidateSize(); }, 300);
})();
</script>
<?php endif; ?>