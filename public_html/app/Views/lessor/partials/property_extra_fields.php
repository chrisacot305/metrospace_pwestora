<?php
/**
 * app/Views/lessor/partials/property_extra_fields.php — expanded property info + nearby landmark.
 * Used by both the "Add property" and "Customize property" drawers.
 *
 * Set before including:
 *   $pxSrc   current values (the property row when editing, [] when adding)
 *   $pxEdit  true when editing (shows the landmark photo already on file)
 *   $AMENITIES, $FLOOR_LEVELS, $MAX_FILE_MB
 */
$pxSrc  = $pxSrc ?? [];
$pxEdit = $pxEdit ?? false;
$pxVal = static function (string $k) use ($pxSrc): string {
    $v = $_POST[$k] ?? ($pxSrc[$k] ?? '');
    return is_scalar($v) ? (string) $v : '';
};

// Amenities currently selected (posted checkboxes win after a failed save)
$pxAmenities = isset($_POST['amenities']) && is_array($_POST['amenities'])
    ? array_map('strval', $_POST['amenities'])
    : array_filter(array_map('trim', explode(',', (string) ($pxSrc['amenities'] ?? ''))));
$pxOther = isset($_POST['amenities_other'])
    ? (string) $_POST['amenities_other']
    : implode(', ', array_diff($pxAmenities, $AMENITIES));
$pxLandmarkPhoto = $pxEdit ? ($pxSrc['landmark_photo_path'] ?? '') : '';
$pxId = $pxEdit ? 'e' : 'a';
?>

<!-- SPACE DETAILS -->
<section class="pm-section">
  <header class="pm-section-head">
    <span class="pm-section-icon"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg></span>
    <div>
      <h4>Space details</h4>
      <p>Help tenants picture the space before they visit.</p>
    </div>
  </header>
  <div class="pm-grid-2">
    <div class="field">
      <label>Floor / Level</label>
      <input type="text" name="floor_level" list="pxFloorLevels<?= $pxId ?>" maxlength="60" placeholder="e.g. Ground Floor" value="<?= e($pxVal('floor_level')) ?>">
      <datalist id="pxFloorLevels<?= $pxId ?>">
        <?php foreach ($FLOOR_LEVELS as $lvl): ?><option value="<?= e($lvl) ?>"><?php endforeach; ?>
      </datalist>
    </div>
    <div class="field">
      <label>Frontage / Dimensions</label>
      <input type="text" name="frontage" maxlength="60" placeholder="e.g. 6m frontage × 12m deep" value="<?= e($pxVal('frontage')) ?>">
    </div>
    <div class="field">
      <label>Parking Slots</label>
      <input type="number" name="parking_slots" min="0" max="9999" step="1" placeholder="e.g. 4" value="<?= e($pxVal('parking_slots')) ?>">
    </div>
    <div class="field">
      <label>Operating / Access Hours</label>
      <input type="text" name="operating_hours" maxlength="100" placeholder="e.g. 7:00 AM – 10:00 PM daily" value="<?= e($pxVal('operating_hours')) ?>">
    </div>
  </div>
</section>

<!-- AMENITIES & UTILITIES -->
<section class="pm-section">
  <header class="pm-section-head">
    <span class="pm-section-icon"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg></span>
    <div>
      <h4>Amenities &amp; utilities</h4>
      <p>Tick everything the space already has.</p>
    </div>
  </header>
  <div class="pm-chips">
    <?php foreach ($AMENITIES as $i => $am): $on = in_array($am, $pxAmenities, true); ?>
      <label class="pm-chip-check">
        <input type="checkbox" name="amenities[]" value="<?= e($am) ?>" <?= $on ? 'checked' : '' ?>>
        <span><?= e($am) ?></span>
      </label>
    <?php endforeach; ?>
  </div>
  <div class="field" style="margin-top:12px;">
    <label>Other amenities <span class="pm-optional">comma-separated</span></label>
    <input type="text" name="amenities_other" maxlength="300" placeholder="e.g. Mezzanine, Roof deck access" value="<?= e($pxOther) ?>">
  </div>
  <div class="field">
    <label>Utilities</label>
    <input type="text" name="utilities" maxlength="255" placeholder="e.g. Separate electric meter (CENECO), shared water meter" value="<?= e($pxVal('utilities')) ?>">
  </div>
</section>

<!-- BUSINESS & LEASE TERMS -->
<section class="pm-section">
  <header class="pm-section-head">
    <span class="pm-section-icon"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg></span>
    <div>
      <h4>Business &amp; lease terms</h4>
      <p>What the space can be used for and what tenants pay upfront.</p>
    </div>
  </header>
  <div class="field">
    <label>Permitted Business Use</label>
    <input type="text" name="permitted_use" maxlength="255" placeholder="e.g. Retail, food &amp; beverage, services — no heavy cooking" value="<?= e($pxVal('permitted_use')) ?>">
  </div>
  <div class="field">
    <label>Deposit &amp; Advance</label>
    <input type="text" name="deposit_terms" maxlength="150" placeholder="e.g. 2 months deposit + 1 month advance" value="<?= e($pxVal('deposit_terms')) ?>">
  </div>
</section>

<!-- NEARBY LANDMARK -->
<section class="pm-section">
  <header class="pm-section-head">
    <span class="pm-section-icon"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg></span>
    <div>
      <h4>Nearby landmark</h4>
      <p>A well-known place tenants can use to find you (school, mall, church, hospital).</p>
    </div>
  </header>
  <div class="pm-grid-2">
    <div class="field">
      <label>Landmark Name</label>
      <input type="text" name="landmark_name" maxlength="150" placeholder="e.g. University of St. La Salle" value="<?= e($pxVal('landmark_name')) ?>">
    </div>
    <div class="field">
      <label>Distance / Direction</label>
      <input type="text" name="landmark_distance" maxlength="60" placeholder="e.g. 50m, across the street" value="<?= e($pxVal('landmark_distance')) ?>">
    </div>
  </div>

  <?php if ($pxLandmarkPhoto): ?>
    <div class="pm-landmark-current">
      <a href="#" class="pm-landmark-thumb" data-pm-zoom="/<?= e(ltrim($pxLandmarkPhoto, '/')) ?>" data-pm-caption="<?= e($pxVal('landmark_name') ?: 'Landmark') ?>">
        <img src="/<?= e(ltrim($pxLandmarkPhoto, '/')) ?>" alt="Landmark photo">
      </a>
      <div class="pm-landmark-meta">
        <strong>Landmark photo on file</strong>
        <span>Upload a new one below to replace it.</span>
        <label class="pm-inline-check">
          <input type="checkbox" name="remove_landmark_photo" value="1"> Remove this photo when I save
        </label>
      </div>
    </div>
  <?php endif; ?>

  <label class="pm-label">Landmark Photo <span class="pm-optional">optional</span></label>
  <?php
    $pmName = 'landmark_photo'; $pmSingle = true; $pmMaxFiles = 1; $pmRequired = false;
    $pmTitle = $pxLandmarkPhoto ? 'Drop a new landmark photo to replace it' : 'Drop a photo of the landmark here';
    $pmHint = 'One photo · JPG, PNG or WEBP · up to ' . (int) $MAX_FILE_MB . ' MB';
    $pmCategories = []; $pmFirstIsCover = false;
    require __DIR__ . '/photo_uploader.php';
  ?>
</section>
