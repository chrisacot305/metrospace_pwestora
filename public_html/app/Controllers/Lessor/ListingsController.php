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
 * Property Photos accepts multiple files (stored in property_photos).
 */
final class ListingsController extends Controller
{
    private const PROPERTY_TYPES = ['Retail', 'Office', 'Warehouse', 'Food Stall', 'Medical/Clinic', 'Coworking Space', 'Commercial Lot', 'Other'];
    private const ALLOWED_DOC_EXT   = ['pdf', 'jpg', 'jpeg', 'png'];
    private const ALLOWED_PHOTO_EXT = ['jpg', 'jpeg', 'png', 'webp'];
    private const MAX_FILE_MB = 5;
    private const MAX_PHOTOS  = 10;

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

        // EDIT PROPERTY
        if ($this->isPost() && isset($_POST['edit_property'], $_POST['property_id'])) {
            Csrf::check();
            $propertyId = (int) $_POST['property_id'];
            $f = $this->readForm();

            if (!$this->properties->isOwnedBy($propertyId, $lessorId)) {
                $error = 'Property not found or unauthorized.';
            } elseif ($f['name'] === '' || $f['type'] === '' || $f['address'] === '' || $f['asking_rent'] === null || $f['asking_rent'] <= 0) {
                $error = 'Please fill in every field with valid information.';
            } else {
                $this->properties->beginTransaction();
                try {
                    $this->properties->updateDetails($propertyId, $lessorId, $f);
                    if (!empty($_FILES['photos']) && !empty($_FILES['photos']['name'][0])) {
                        foreach ($this->savePhotos($propertyId) as $path) {
                            $this->properties->addPhoto($propertyId, $path);
                        }
                    }
                    audit_log($this->pdo, $lessorId, 'Updated property details', $f['name']);
                    $this->properties->commit();
                    $this->redirect('/dashboard.php?page=listings');
                } catch (\Throwable $e) {
                    $this->properties->rollBack();
                    Upload::discardSaved();
                    $error = ($e instanceof RuntimeException) ? $e->getMessage() : 'Something went wrong while updating. Please try again.';
                }
            }
        }

        // DELETE PHOTO
        if ($this->isPost() && isset($_POST['delete_photo'], $_POST['photo_id'], $_POST['property_id'])) {
            Csrf::check();
            $photoId    = (int) $_POST['photo_id'];
            $propertyId = (int) $_POST['property_id'];
            if ($this->properties->photoBelongsToLessor($photoId, $propertyId, $lessorId)) {
                $this->properties->deletePhoto($photoId);
            }
            $this->redirect('/dashboard.php?page=listings&edit=' . $propertyId);
        }

        // ADD NEW PROPERTY
        if ($this->isPost() && !isset($_POST['edit_property']) && !isset($_POST['delete_photo'])) {
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

                    foreach ($this->savePhotos($propertyId) as $path) {
                        $this->properties->addPhoto($propertyId, $path);
                    }

                    audit_log($this->pdo, $lessorId, 'Submitted property for verification', $f['name']);
                    $this->properties->commit();
                    $this->redirect('/dashboard.php?page=listings');
                } catch (\Throwable $e) {
                    $this->properties->rollBack();
                    Upload::discardSaved();
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

        unset($f);
        return $this->view('lessor/listings', get_defined_vars());
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

    /** @return string[] stored paths */
    private function savePhotos(int $propertyId): array
    {
        if (empty($_FILES['photos']) || empty($_FILES['photos']['name'][0])) {
            throw new RuntimeException('Please attach at least one property photo.');
        }
        $names = (array) $_FILES['photos']['name'];
        $count = count($names);
        if ($count > self::MAX_PHOTOS) {
            throw new RuntimeException('You can upload up to ' . self::MAX_PHOTOS . ' photos at once.');
        }
        $saved = [];
        for ($i = 0; $i < $count; $i++) {
            $file = [
                'name'     => $names[$i],
                'tmp_name' => $_FILES['photos']['tmp_name'][$i] ?? '',
                'error'    => $_FILES['photos']['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $_FILES['photos']['size'][$i] ?? 0,
            ];
            $saved[] = Upload::save(
                $file, "properties/{$propertyId}", 'photo', self::ALLOWED_PHOTO_EXT, self::MAX_FILE_MB,
                'Photos must be JPG, PNG, or WEBP.',
                'Each photo must be under ' . self::MAX_FILE_MB . 'MB.',
                'One of the photos failed to upload. Please try again.'
            );
        }
        return $saved;
    }
}
