<?php
/**
 * POST /api/applications_create.php
 * Header: Authorization: Bearer <token>
 * Body: JSON or multipart/form-data with fields:
 *   property_id (int)
 *   business_name (string)
 *   term_months (int)
 *   rent (float)
 *   checklist_negotiation (JSON string/object)
 * Files (optional, max 10MB each):
 *   valid_id, sec_dti, business_permit, bir_cert  (PDF/JPG/PNG/WEBP)
 * Response: { "ok": true, "application_id": 45 }
 */
require __DIR__ . '/../app/init_api.php';

use Api\Http\Request;
use Api\Repositories\ApplicationRepository;
use Api\Repositories\PropertyRepository;
use Api\Services\FileUploadService;

const DOC_ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
const DOC_MAX_MB      = 10;
const NEGOTIATION_MAX_BYTES = 65535;

Request::requireMethod('POST');

$lessee = require_lessee_auth($pdo);

$propertyId   = Request::positiveInt(Request::input('property_id', 0));
$termMonths   = Request::positiveInt(Request::input('term_months', 0));
$rent         = Request::positiveFloat(Request::input('rent', 0));
$businessName = Request::inputString('business_name', 150);

if ($propertyId <= 0 || $termMonths <= 0 || $rent <= 0) {
    json_error('property_id, term_months, and rent are required and must be positive.');
}
if ($termMonths > 120) {
    json_error('term_months cannot be more than 120.');
}

$property = (new PropertyRepository($pdo))->findVerifiedForApplication($propertyId);
if (!$property) {
    json_error('Property not found or not currently accepting applications.', 404);
}

$applications = new ApplicationRepository($pdo);

// Prevent the same tenant from spamming duplicate pending applications on the same listing.
if ($applications->hasPending($propertyId, (int) $lessee['id'])) {
    json_error('You already have a pending application for this property.', 409);
}

// checklist_negotiation may arrive as a JSON string (form-data) or an object (JSON body).
$negotiation = Request::input('checklist_negotiation');
$negotiationJson = null;
if ($negotiation) {
    $decoded = is_string($negotiation) ? json_decode($negotiation, true) : $negotiation;
    if (!is_array($decoded)) {
        json_error('checklist_negotiation must be valid JSON.');
    }
    $negotiationJson = json_encode($decoded);
    if (strlen($negotiationJson) > NEGOTIATION_MAX_BYTES) {
        json_error('checklist_negotiation is too large.');
    }
}

$appId = $applications->create([
    'property_id'           => $propertyId,
    'lessor_id'             => $property['lessor_id'],
    'lessee_id'             => $lessee['id'],
    'tenant_name'           => $lessee['full_name'],
    'business_name'         => $businessName,
    'term_months'           => $termMonths,
    'rent'                  => $rent,
    'checklist_negotiation' => $negotiationJson,
]);

// Save uploaded verification documents (a bad file is logged and skipped, like before).
$uploader = new FileUploadService();
$paths = [];
foreach (array_keys(ApplicationRepository::DOCUMENT_COLUMNS) as $field) {
    if (empty($_FILES[$field]) || is_array($_FILES[$field]['name'] ?? null)) {
        continue;
    }
    try {
        $path = $uploader->save(
            $_FILES[$field],
            UPLOAD_DIR . "/applications/{$appId}",
            "uploads/applications/{$appId}",
            $field,
            DOC_ALLOWED_EXT,
            DOC_MAX_MB
        );
        if ($path !== null) {
            $paths[$field] = $path;
        }
    } catch (RuntimeException $e) {
        error_log('Application doc upload error: ' . $e->getMessage());
    }
}
$applications->setDocumentPaths($appId, $paths);

json_ok(['application_id' => $appId], 201);
