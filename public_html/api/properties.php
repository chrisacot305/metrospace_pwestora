<?php
/**
 * GET /api/properties.php?type=Retail
 * Older listing endpoint (kept for compatibility). No auth required.
 * Response: { "success": true, "data": [ { id, name, type, address, latitude, longitude, asking_rent, cover_photo }, ... ] }
 */
require __DIR__ . '/../app/init_api.php';

use Api\Http\Request;
use Api\Http\Response;
use Api\Repositories\PropertyRepository;

$type = Request::string($_GET, 'type', 50);

try {
    $rows = (new PropertyRepository($pdo))->listVerifiedByType($type === '' ? null : $type);
    Response::send(['success' => true, 'data' => $rows]);
} catch (Throwable $e) {
    error_log('[API properties.php] ' . $e->getMessage());
    Response::send(['success' => false, 'message' => 'Could not load properties. Please try again later.'], 500);
}
