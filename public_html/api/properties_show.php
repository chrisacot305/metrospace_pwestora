<?php
/**
 * GET /api/properties_show.php?id=123
 * Response: { "ok": true, "property": { id, name, type, address, lessor_name, photos: [urls], saved } }
 */
require __DIR__ . '/../app/init_api.php';

use Api\Formatters\Formatter;
use Api\Http\Request;
use Api\Repositories\PropertyRepository;
use Api\Repositories\UserRepository;
use Api\Services\AuthService;

Request::requireMethod('GET');

$id = Request::positiveInt($_GET['id'] ?? 0);
if ($id <= 0) {
    json_error('Missing or invalid id.');
}

$properties = new PropertyRepository($pdo);
$property = $properties->findVerifiedWithLessor($id);

if (!$property) {
    json_error('Property not found or not yet verified.', 404);
}

// Optional login: report whether this lessee already saved it.
$user  = (new AuthService(new UserRepository($pdo)))->currentLessee();
$saved = $user ? $properties->isSaved((int) $user['id'], $id) : false;

json_ok(['property' => Formatter::propertyDetail($property, $properties->photoPaths($id), $saved)]);
