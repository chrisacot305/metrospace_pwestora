<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_role(['lessor', 'superadmin']);

$user = current_user();
$page = preg_replace('/[^a-z_]/', '', $_GET['page'] ?? 'overview'); // whitelist-safe

// Which module files each role is allowed to open
$allowed = [
    'lessor'     => ['overview','listings','applications','tenants','edit_tenant','installments','deposits','payments','maintenance','messages','violations'],
    'superadmin' => ['overview','lessors','properties','users','notify','revenue','audit','settings'],
];

if (!in_array($page, $allowed[$user['role']], true)) {
    $page = 'overview';
}

// overview.php differs per role, so namespace it: overview_lessor / overview_superadmin
$moduleFile = ($page === 'overview')
    ? __DIR__ . "/modules/overview_{$user['role']}.php"
    : __DIR__ . "/modules/{$page}.php";

require __DIR__ . '/includes/header.php';

// Load the module (which now handles its own Models and Views)
if (file_exists($moduleFile)) {
    require $moduleFile;
} else {
    echo '<div class="placeholder-note" style="padding:40px; text-align:center; color:var(--ink-500);">
            <i class="bi bi-cone-striped" style="font-size:32px; display:block; margin-bottom:12px; color:var(--ink-300);"></i>
            <p>This module (' . htmlspecialchars($page) . ') hasn\'t been built yet — coming in the next phase.</p>
          </div>';
}

require __DIR__ . '/includes/footer.php';