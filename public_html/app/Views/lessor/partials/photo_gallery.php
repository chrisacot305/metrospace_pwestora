<?php
/**
 * app/Views/lessor/partials/photo_gallery.php — photos already uploaded for a property
 * (cover badge, category, caption, set-as-cover, edit, delete, zoom).
 *
 * Uses: $editingPhotos, $editingProperty, $PHOTO_CATEGORIES, $MAX_PHOTOS
 * The buttons fill and submit #pmPhotoActionForm (rendered OUTSIDE the main form,
 * because HTML forms cannot be nested).
 */
?>
<div class="pm-gallery-head" id="gallery">
  <div>
    <h4>Photo gallery</h4>
    <p><?= count($editingPhotos) ?> of <?= (int) $MAX_PHOTOS ?> photos · the first photo is the cover tenants see first.</p>
  </div>
  <div class="pm-meter" title="<?= count($editingPhotos) ?> / <?= (int) $MAX_PHOTOS ?> photos">
    <span style="width:<?= min(100, round(count($editingPhotos) / max(1, $MAX_PHOTOS) * 100)) ?>%"></span>
  </div>
</div>

<?php if (!$editingPhotos): ?>
  <div class="pm-empty">
    <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
    <span>No photos yet. Add some below — listings with 5+ photos get more inquiries.</span>
  </div>
<?php else: ?>
  <div class="pm-gallery">
    <?php foreach ($editingPhotos as $i => $ph):
        $src = '/' . ltrim($ph['photo_path'], '/');
        $cat = $ph['category'] ?? 'exterior';
        $catLabel = $PHOTO_CATEGORIES[$cat] ?? 'Other';
        $isCover = $i === 0;
    ?>
      <figure class="pm-tile<?= $isCover ? ' is-cover' : '' ?>">
        <a href="#" class="pm-tile-img" data-pm-zoom="<?= e($src) ?>" data-pm-caption="<?= e($ph['caption'] ?: $catLabel) ?>">
          <img src="<?= e($src) ?>" alt="<?= e($ph['caption'] ?: $catLabel) ?>" loading="lazy">
          <?php if ($isCover): ?><span class="pm-chip pm-chip-cover">Cover</span><?php endif; ?>
          <span class="pm-chip pm-chip-cat"><?= e($catLabel) ?></span>
        </a>

        <div class="pm-tile-actions">
          <?php if (!$isCover): ?>
            <button type="button" class="pm-icon-btn" title="Set as cover photo"
                    onclick="pmPhotoAction('set_cover_photo', <?= (int) $ph['id'] ?>)">
              <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/></svg>
            </button>
          <?php endif; ?>
          <button type="button" class="pm-icon-btn" title="Edit category &amp; caption"
                  onclick="pmToggleEdit(<?= (int) $ph['id'] ?>)">
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
          </button>
          <button type="button" class="pm-icon-btn pm-icon-btn-danger" title="Delete photo"
                  onclick="if (confirm('Delete this photo? This cannot be undone.')) pmPhotoAction('delete_photo', <?= (int) $ph['id'] ?>)">
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/></svg>
          </button>
        </div>

        <figcaption class="pm-tile-cap"><?= $ph['caption'] ? e($ph['caption']) : '<span class="pm-muted">No caption</span>' ?></figcaption>

        <div class="pm-tile-edit" id="pmEdit<?= (int) $ph['id'] ?>" hidden>
          <select class="pm-input pm-input-sm" data-field="category">
            <?php foreach ($PHOTO_CATEGORIES as $k => $label): ?>
              <option value="<?= e($k) ?>" <?= $k === $cat ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="text" class="pm-input pm-input-sm" data-field="caption" maxlength="150" placeholder="Caption" value="<?= e($ph['caption'] ?? '') ?>">
          <div class="pm-tile-edit-actions">
            <button type="button" class="pm-btn pm-btn-ghost pm-btn-xs" onclick="pmToggleEdit(<?= (int) $ph['id'] ?>)">Cancel</button>
            <button type="button" class="pm-btn pm-btn-primary pm-btn-xs" onclick="pmPhotoAction('update_photo', <?= (int) $ph['id'] ?>)">Save</button>
          </div>
        </div>
      </figure>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
