<?php
/**
 * POST /api/tickets_create.php
 * Header: Authorization: Bearer <token>
 * Body (JSON or multipart): { "category": "plumbing", "priority": "high", "title": "...", "description": "...", "tenant_id": 5 (optional) }
 * Files (optional): any number of photo fields, max 10 photos, 10MB each (JPG/PNG/WEBP/GIF)
 * Response: { "ok": true, "ticket_id": 12, "photos": [ ... ] }
 */
require __DIR__ . '/../app/init_api.php';

use Api\Http\Request;
use Api\Repositories\TicketRepository;
use Api\Services\FileUploadService;
use Api\Services\TenantResolver;

const TICKET_PRIORITIES  = ['low', 'medium', 'high', 'urgent'];
const PHOTO_ALLOWED_EXT  = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
const PHOTO_MAX_MB       = 10;
const PHOTO_MAX_COUNT    = 10;

Request::requireMethod('POST');

$lessee = require_lessee_auth($pdo);
// Accept JSON body, query string and form fields (form fields win), like before.
$body = array_merge(Request::json(), $_GET, $_POST);

// Category: lowercase letters, spaces, _ and - only.
$category = strtolower(Request::string($body, 'category', 50));
$category = preg_replace('/[^a-z _-]/', '', $category) ?? '';
if ($category === '') {
    $category = 'general';
}
if ($category === 'cleanliness') {
    $category = 'cleaning';
}

$priority = strtolower(Request::string($body, 'priority', 10, 'medium'));
if (!in_array($priority, TICKET_PRIORITIES, true)) {
    $priority = 'medium';
}

$title = Request::string($body, 'title', 200);
if ($title === '') {
    $title = '[' . ucfirst($category) . '] Maintenance Issue';
}

$tenant = (new TenantResolver($pdo))->forLessee($lessee, Request::positiveInt($body['tenant_id'] ?? 0));
if (!$tenant) {
    json_error('You need an active lease before reporting a maintenance issue.', 403);
}

$tickets  = new TicketRepository($pdo);
$ticketId = $tickets->create((int) $tenant['lessor_id'], (int) $tenant['tenant_id'], $category, $priority, $title);

// Save photos (invalid files are skipped and logged, like before).
$uploader    = new FileUploadService();
$savedPhotos = [];
foreach (array_slice(FileUploadService::flatten($_FILES), 0, PHOTO_MAX_COUNT) as $file) {
    try {
        $path = $uploader->save(
            $file,
            __DIR__ . "/../uploads/maintenance/{$ticketId}",
            "uploads/maintenance/{$ticketId}",
            'photo',
            PHOTO_ALLOWED_EXT,
            PHOTO_MAX_MB
        );
        if ($path !== null) {
            $savedPhotos[] = $path;
        }
    } catch (RuntimeException $e) {
        error_log('Ticket photo upload error: ' . $e->getMessage());
    }
}

if ($savedPhotos) {
    $tickets->setPhotos($ticketId, $savedPhotos);
}

json_ok([
    'ticket_id' => $ticketId,
    'photos'    => $savedPhotos,
], 201);
