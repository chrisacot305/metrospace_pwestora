<?php
namespace App\Controllers\Lessor;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Upload;
use App\Models\PropertyModel;
use RuntimeException;

/**
 * Lessor → My Properties (was modules/listings.php)
 * Commercial spaces. Land Title + Tax Declaration are single files;
 * Property Photos accepts multiple files (stored in property_photos, each with a
 * category + caption, cover photo first). A nearby landmark can have its own photo.
 *
 * POST actions (one per form):
 *   add_property      — new listing (documents + photos required, landmark optional)
 *   edit_property     — update details, add photos, replace/remove landmark photo
 *   delete_photo      — remove one gallery photo
 *   set_cover_photo   — make one gallery photo the cover
 *   update_photo      — change a photo's category / caption
 */
final class ListingsController extends Controller
{
    private const PROPERTY_TYPES = ['Retail', 'Office', 'Warehouse', 'Food Stall', 'Medical/Clinic', 'Coworking Space', 'Commercial Lot', 'Other'];
    private const ALLOWED_DOC_EXT   = ['pdf', 'jpg', 'jpeg', 'png'];
    private const ALLOWED_PHOTO_EXT = ['jpg', 'jpeg', 'png', 'webp'];
    private const MAX_FILE_MB = 5;
    private const MAX_PHOTOS  = 15;

    /** Checkbox amenities (anything else goes in the free-text "other amenities" field). */
    private const AMENITIES = [
        'Air-conditioning', 'Restroom', 'Pantry / Sink', 'CCTV', '24/7 Security', 'Fire Sprinklers',
        'Generator Backup', 'Elevator', 'Loading Bay', 'Internet Ready', 'PWD Access', 'Signage Space',
    ];
    private const FLOOR_LEVELS = ['Ground Floor', '2nd Floor', '3rd Floor', 'Upper Floor', 'Basement', 'Whole Building', 'Open Lot'];

    private PropertyModel $properties;

    public function handle(array $user): array
    {
        $lessorId = (int) $user['id'];
        $error = '';
        $listingSaved = false;
        $this->properties = new PropertyModel($this->pdo);

        $PROPERTY_TYPES = self::PROPERTY_TYPES;
        $SINGLE_DOCS = [
            'title_doc' => ['title_doc_path', 'Land Title'],
            'tax_dec'   => ['tax_dec_path',   'Tax Declaration'],
        ];
        $ALLOWED_DOC_EXT   = self::ALLOWED_DOC_EXT;
        $ALLOWED_PHOTO_EXT = self::ALLOWED_PHOTO_EXT;
        $MAX_FILE_MB = self::MAX_FILE_MB;
        $MAX_PHOTOS  = self::MAX_PHOTOS;
        $AMENITIES   = self::AMENITIES;
        $FLOOR_LEVELS = self::FLOOR_LEVELS;
        $PHOTO_CATEGORIES = PropertyModel::PHOTO_CATEGORIES;
        $MAX_POST_MB = 28; // keep under post_max_size (30M in .user.ini)

        // Upload bigger than post_max_size: PHP drops the whole form, so explain instead of doing nothing.
        if ($this->isPost() && empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $error = 'The files you selected are too large to upload in one save (limit about ' . $MAX_POST_MB . ' MB in total). Please upload fewer photos at a time.';
        }

        // ---------------------------------------------------------------- single-photo actions
        if ($this->isPost() && isset($_POST['property_id']) &&
            (isset($_POST['delete_photo']) || isset($_POST['set_cover_photo']) || isset($_POST['update_photo']))) {
            Csrf::check();
            $photoId    = (int) ($_POST['photo_id'] ?? 0);
            $propertyId = (int) $_POST['property_id'];
            if ($this->properties->photoBelongsToLessor($photoId, $propertyId, $lessorId)) {
                if (isset($_POST['delete_photo'])) {
                    $path = $this->properties->photoPath($photoId);
                    $this->properties->deletePhoto($photoId);
                    $this->deleteStoredFile($path);
                    audit_log($this->pdo, $lessorId, 'Deleted property photo', "Property #$propertyId");
                } elseif (isset($_POST['set_cover_photo'])) {
                    $this->properties->setCoverPhoto($photoId, $propertyId);
                } else {
                    $category = is_string($_POST['category'] ?? null) ? $_POST['category'] : 'other';
                    $caption  = $this->cleanText($_POST['caption'] ?? '', 150);
                    $this->properties->updatePhotoMeta($photoId, $propertyId, $category, $caption);
                }
            }
            $this->redirect('/dashboard.php?page=listings&edit=' . $propertyId . '#gallery');
        }

        // ---------------------------------------------------------------- EDIT PROPERTY
        if ($this->isPost() && isset($_POST['edit_property'], $_POST['property_id'])) {
            Csrf::check();
            $propertyId = (int) $_POST['property_id'];
            $f = $this->readForm();

            if (!$this->properties->isOwnedBy($propertyId, $lessorId)) {
                $error = 'Property not found or unauthorized.';
            } elseif ($f['name'] === '' || $f['type'] === '' || $f['address'] === '' || $f['asking_rent'] === null || $f['asking_rent'] <= 0) {
                $error = 'Please fill in every field with valid information.';
            } else {
                $oldLandmark = $this->properties->landmarkPhotoPath($propertyId);
                $removeOldLandmark = false;
                $this->properties->beginTransaction();
                try {
                    $this->properties->updateDetails($propertyId, $lessorId, $f);

                    if ($this->hasNewPhotos()) {
                        $existing = $this->properties->photoCount($propertyId);
                        foreach ($this->savePhotos($propertyId, $existing) as [$path, $category, $caption]) {
                            $this->properties->addPhoto($propertyId, $path, $category, $caption);
                        }
                    }

                    $newLandmark = $this->saveLandmarkPhoto($propertyId);
                    if ($newLandmark !== null) {
                        $this->properties->setLandmarkPhoto($propertyId, $lessorId, $newLandmark);
                        $removeOldLandmark = true;
                    } elseif (isset($_POST['remove_landmark_photo'])) {
                        $this->properties->setLandmarkPhoto($propertyId, $lessorId, null);
                        $removeOldLandmark = true;
                    }

                    audit_log($this->pdo, $lessorId, 'Updated property details', $f['name']);
                    $this->properties->commit();
                    if ($removeOldLandmark) {
                        $this->deleteStoredFile($oldLandmark);
                    }
                    $this->redirect('/dashboard.php?page=listings&edit=' . $propertyId . '&saved=1');
                } catch (\Throwable $e) {
                    $this->properties->rollBack();
                    Upload::discardSaved();
                    if (!($e instanceof RuntimeException)) error_log('Update property failed: ' . $e->getMessage());
                    $error = ($e instanceof RuntimeException) ? $e->getMessage() : 'Something went wrong while updating. Please try again.';
                }
            }
        }

        // ---------------------------------------------------------------- ADD NEW PROPERTY
        if ($this->isPost() && isset($_POST['add_property'])) {
            Csrf::check();
            $f = $this->readForm();

            if ($f['name'] === '' || $f['type'] === '' || $f['address'] === '' || $f['asking_rent'] === null || $f['asking_rent'] <= 0) {
                $error = 'Please fill in every required field, including a valid asking rent.';
            } else {
                $this->properties->beginTransaction();
                try {
                    $propertyId = $this->properties->create($lessorId, $f);

                    $docPaths = [];
                    foreach ($SINGLE_DOCS as $field => [$column, $label]) {
                        $docPaths[$column] = $this->saveSingleDoc($field, $propertyId);
                    }
                    $this->properties->setDocuments($propertyId, $docPaths['title_doc_path'], $docPaths['tax_dec_path']);

                    if (!$this->hasNewPhotos()) {
                        throw new RuntimeException('Please attach at least one property photo.');
                    }
                    foreach ($this->savePhotos($propertyId, 0) as [$path, $category, $caption]) {
                        $this->properties->addPhoto($propertyId, $path, $category, $caption);
                    }

                    $landmark = $this->saveLandmarkPhoto($propertyId);
                    if ($landmark !== null) {
                        $this->properties->setLandmarkPhoto($propertyId, $lessorId, $landmark);
                    }

                    audit_log($this->pdo, $lessorId, 'Submitted property for verification', $f['name']);
                    $this->properties->commit();
                    $this->redirect('/dashboard.php?page=listings');
                } catch (\Throwable $e) {
                    $this->properties->rollBack();
                    Upload::discardSaved();
                    if (!($e instanceof RuntimeException)) error_log('Add property failed: ' . $e->getMessage());
                    $error = ($e instanceof RuntimeException) ? $e->getMessage() : 'Something went wrong. Please try again.';
                }
            }
        }

        try {
            $properties = $this->properties->listingsForLessor($lessorId);
        } catch (\Throwable $e) {
            error_log('Listings query failed: ' . $e->getMessage());
            $error = 'Could not load your properties — the error has been logged. Please try again.';
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
                $editingPhotos = $this->properties->photosWithIds($editId);
            }
        }
        $justSaved = isset($_GET['saved']) && $editingProperty && $error === '';

        unset($f);
        return $this->view('lessor/listings', get_defined_vars());
    }

    private function cleanText($value, int $max): ?string
    {
        $v = trim(is_scalar($value) ? (string) $value : '');
        $v = mb_substr($v, 0, $max);
        return $v === '' ? null : $v;
    }

    /** Reads and cleans the property form fields. */
    private function readForm(): array
    {
        $s = static fn(string $k): string => mb_substr(trim(is_scalar($_POST[$k] ?? null) ? (string) $_POST[$k] : ''), 0, 5000);
        $lat = $s('latitude');
        $lng = $s('longitude');
        $rent = $s('asking_rent');
        $totalUnits = isset($_POST['total_units']) && is_numeric($_POST['total_units']) ? max(1, (int)$_POST['total_units']) : 1;
        $availUnits = isset($_POST['available_units']) && is_numeric($_POST['available_units']) ? max(0, min($totalUnits, (int)$_POST['available_units'])) : $totalUnits;

        // Amenities: whitelisted checkboxes + optional free text, stored as "A, B, C".
        $picked = array_values(array_intersect(self::AMENITIES, is_array($_POST['amenities'] ?? null) ? $_POST['amenities'] : []));
        foreach (preg_split('/\s*,\s*/', $s('amenities_other')) as $extra) {
            $extra = mb_substr(trim($extra), 0, 40);
            if ($extra !== '' && !in_array($extra, $picked, true)) $picked[] = $extra;
        }
        $parking = $s('parking_slots');

        return [
            'name'            => mb_substr($s('name'), 0, 150),
            'type'            => mb_substr($s('type'), 0, 50),
            'address'         => mb_substr($s('address'), 0, 255),
            'lat'             => ($lat !== '' && is_numeric($lat)) ? (float) $lat : null,
            'lng'             => ($lng !== '' && is_numeric($lng)) ? (float) $lng : null,
            'asking_rent'     => ($rent !== '' && is_numeric($rent)) ? (float) $rent : null,
            'floor_area'      => $s('floor_area') ?: null,
            'lease_term'      => $s('lease_term') ?: null,
            'escalation'      => $s('escalation') ?: null,
            'description'     => $s('description') ?: null,
            'total_units'     => $totalUnits,
            'available_units' => $availUnits,
            // expanded info
            'landmark_name'     => $this->cleanText($s('landmark_name'), 150),
            'landmark_distance' => $this->cleanText($s('landmark_distance'), 60),
            'floor_level'       => $this->cleanText($s('floor_level'), 60),
            'frontage'          => $this->cleanText($s('frontage'), 60),
            'parking_slots'     => ($parking !== '' && is_numeric($parking)) ? max(0, min(9999, (int) $parking)) : null,
            'utilities'         => $this->cleanText($s('utilities'), 255),
            'amenities'         => $picked ? mb_substr(implode(', ', $picked), 0, 2000) : null,
            'permitted_use'     => $this->cleanText($s('permitted_use'), 255),
            'operating_hours'   => $this->cleanText($s('operating_hours'), 100),
            'deposit_terms'     => $this->cleanText($s('deposit_terms'), 150),
        ];
    }

    private function saveSingleDoc(string $field, int $propertyId): string
    {
        if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('Please attach every required document.');
        }
        return Upload::save(
            $_FILES[$field], "properties/{$propertyId}", $field, self::ALLOWED_DOC_EXT, self::MAX_FILE_MB,
            'Only PDF, JPG, and PNG files are accepted for documents.',
            'Each file must be under ' . self::MAX_FILE_MB . 'MB.'
        );
    }

    /** Optional landmark photo. Returns the stored path, or null when none was attached. */
    private function saveLandmarkPhoto(int $propertyId): ?string
    {
        $file = $_FILES['landmark_photo'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return Upload::save(
            $file, "properties/{$propertyId}", 'landmark', self::ALLOWED_PHOTO_EXT, self::MAX_FILE_MB,
            'The landmark photo must be JPG, PNG, or WEBP.',
            'The landmark photo must be under ' . self::MAX_FILE_MB . 'MB.',
            'The landmark photo failed to upload. Please try again.'
        );
    }

    private function hasNewPhotos(): bool
    {
        if (empty($_FILES['photos']['name']) || !is_array($_FILES['photos']['name'])) {
            return false;
        }
        foreach ($_FILES['photos']['error'] as $err) {
            if ((int) $err !== UPLOAD_ERR_NO_FILE) return true;
        }
        return false;
    }

    /**
     * Saves photos[] with their photo_categories[] / photo_captions[] (same order).
     * @return array<int, array{0:string,1:string,2:?string}> [path, category, caption]
     */
    private function savePhotos(int $propertyId, int $alreadyStored): array
    {
        $names = (array) $_FILES['photos']['name'];
        $count = 0;
        foreach ((array) $_FILES['photos']['error'] as $err) {
            if ((int) $err !== UPLOAD_ERR_NO_FILE) $count++;
        }
        if ($alreadyStored + $count > self::MAX_PHOTOS) {
            $left = max(0, self::MAX_PHOTOS - $alreadyStored);
            throw new RuntimeException('A property can have up to ' . self::MAX_PHOTOS . ' photos. You can add ' . $left . ' more.');
        }
        $cats = is_array($_POST['photo_categories'] ?? null) ? $_POST['photo_categories'] : [];
        $caps = is_array($_POST['photo_captions'] ?? null) ? $_POST['photo_captions'] : [];

        $saved = [];
        foreach ($names as $i => $name) {
            $err = $_FILES['photos']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ((int) $err === UPLOAD_ERR_NO_FILE) continue;
            $file = [
                'name'     => $name,
                'tmp_name' => $_FILES['photos']['tmp_name'][$i] ?? '',
                'error'    => $err,
                'size'     => $_FILES['photos']['size'][$i] ?? 0,
            ];
            $path = Upload::save(
                $file, "properties/{$propertyId}", 'photo', self::ALLOWED_PHOTO_EXT, self::MAX_FILE_MB,
                'Photos must be JPG, PNG, or WEBP.',
                'Each photo must be under ' . self::MAX_FILE_MB . 'MB.',
                'One of the photos failed to upload. Please try again.'
            );
            $category = is_string($cats[$i] ?? null) && isset(PropertyModel::PHOTO_CATEGORIES[$cats[$i]]) ? $cats[$i] : 'exterior';
            $saved[] = [$path, $category, $this->cleanText($caps[$i] ?? '', 150)];
        }
        return $saved;
    }

    /** Removes a stored property image from disk (only inside uploads/properties/). */
    private function deleteStoredFile(?string $path): void
    {
        $path = ltrim((string) $path, '/');
        if ($path === '' || !str_starts_with($path, 'uploads/properties/') || str_contains($path, '..')) {
            return;
        }
        $full = APP_ROOT . '/' . $path;
        if (is_file($full)) {
            @unlink($full);
        }
    }
}
