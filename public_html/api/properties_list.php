<?php
/**
 * GET /api/properties_list.php
 * No auth required — anyone browsing the app can see verified listings.
 * If a Bearer token IS sent, each property also includes "saved": true|false.
 * Response: { "ok": true, "properties": [ { id, name, type, address, lessor_name, cover_photo, saved }, ... ] }
 */
require __DIR__ . '/../app/init_api.php';

use Api\Formatters\Formatter;
use Api\Http\Request;
use Api\Repositories\PropertyRepository;
use Api\Repositories\UserRepository;
use Api\Services\AuthService;

Request::requireMethod('GET');

$properties = new PropertyRepository($pdo);
$rows = $properties->listVerifiedWithLessor();

// Optional login: mark which listings this lessee has saved.
$user = (new AuthService(new UserRepository($pdo)))->currentLessee();
$savedIds = $user ? $properties->savedPropertyIds((int) $user['id']) : [];

$rows = array_map(static fn(array $r) => Formatter::property($r, $savedIds), $rows);

json_ok(['properties' => $rows]);
