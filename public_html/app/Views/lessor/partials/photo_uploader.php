<?php
/**
 * app/Views/lessor/partials/photo_uploader.php — drag & drop photo picker (assets/js/photo-uploader.js).
 *
 * Set before including:
 *   $pmName        input name, e.g. "photos[]" or "landmark_photo"
 *   $pmSingle      true = one photo only (landmark)
 *   $pmMaxFiles    how many more photos may be added
 *   $pmRequired    true = at least one photo needed before submit
 *   $pmTitle       big line in the drop zone
 *   $pmHint        small line in the drop zone
 *   $pmCategories  [key => label] for per-photo category (gallery only), or []
 *   $pmFirstIsCover true = the first queued photo gets a "Cover" chip (new property)
 *   $MAX_FILE_MB
 */
$pmSingle       = $pmSingle ?? false;
$pmRequired     = $pmRequired ?? false;
$pmCategories   = $pmCategories ?? [];
$pmFirstIsCover = $pmFirstIsCover ?? false;
$pmMaxFiles     = max(0, (int) ($pmMaxFiles ?? 10));
$pmDisabled     = !$pmSingle && $pmMaxFiles === 0;
?>
<div class="pm-uploader<?= $pmDisabled ? ' is-disabled' : '' ?>"
     data-single="<?= $pmSingle ? '1' : '0' ?>"
     data-max-files="<?= $pmMaxFiles ?>"
     data-max-mb="<?= (int) $MAX_FILE_MB ?>"
     data-accept="jpg,jpeg,png,webp"
     data-required="<?= $pmRequired ? '1' : '0' ?>"
     data-first-is-cover="<?= $pmFirstIsCover ? '1' : '0' ?>"
     data-categories="<?= e(json_encode((object) $pmCategories)) ?>">
  <input type="file" class="pm-file-input" name="<?= e($pmName) ?>" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
         <?= $pmSingle ? '' : 'multiple' ?> hidden <?= $pmDisabled ? 'disabled' : '' ?>>

  <?php if ($pmDisabled): ?>
    <div class="pm-dropzone pm-dropzone-full" aria-disabled="true">
      <div class="pm-dz-icon">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8 12h8"/></svg>
      </div>
      <div class="pm-dz-title">Photo limit reached</div>
      <div class="pm-dz-hint">Delete a photo from the gallery to upload a new one.</div>
    </div>
  <?php else: ?>
    <div class="pm-dropzone" role="button" tabindex="0" aria-label="<?= e($pmTitle) ?>">
      <div class="pm-dz-icon">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/></svg>
      </div>
      <div class="pm-dz-title"><?= e($pmTitle) ?></div>
      <div class="pm-dz-hint"><?= e($pmHint) ?></div>
      <span class="pm-dz-btn">Browse files</span>
    </div>
  <?php endif; ?>

  <div class="pm-error" hidden></div>
  <div class="pm-queue-head">
    <span class="pm-counter"></span>
  </div>
  <div class="pm-queue"></div>
</div>
