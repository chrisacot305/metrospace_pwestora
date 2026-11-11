<?php
/**
 * tools/migrate.php — one-time database setup. Safe to run more than once.
 *
 * Adds what the refactored system needs:
 *   - rate_limits table (login / OTP brute-force protection)
 *   - phone_verifications, sms_reminder_logs tables (only if missing)
 *   - columns the mobile API uses (only if missing)
 *
 * Run ONE of these ways:
 *   1) SSH / terminal:  php tools/migrate.php
 *   2) Browser: set MIGRATE_KEY=<long random text> in .env, open
 *      https://pwestora.com/tools/migrate.php?key=<that text>, then delete MIGRATE_KEY from .env.
 */
define('APP_NO_SESSION', true);
require __DIR__ . '/../app/init.php';

if (PHP_SAPI !== 'cli') {
    $given = is_string($_GET['key'] ?? null) ? $_GET['key'] : '';
    if (!defined('MIGRATE_KEY') || strlen(MIGRATE_KEY) < 16 || !hash_equals(MIGRATE_KEY, $given)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

function add_column(PDO $pdo, string $table, string $column, string $definition): void
{
    if (column_exists($pdo, $table, $column)) {
        echo "  = $table.$column already exists\n";
        return;
    }
    $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition"); // fixed names from this file only
    echo "  + added $table.$column\n";
}

echo "Running Pwestora migrations...\n";

$pdo->exec('CREATE TABLE IF NOT EXISTS rate_limits (
    rl_key CHAR(64) PRIMARY KEY,
    hits INT UNSIGNED NOT NULL DEFAULT 0,
    window_start DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
echo "  ok table rate_limits\n";

$pdo->exec('CREATE TABLE IF NOT EXISTS phone_verifications (
    phone VARCHAR(20) PRIMARY KEY,
    otp_code VARCHAR(6) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)');
echo "  ok table phone_verifications\n";

// The mobile API reads saved_properties ("saved" heart on listings). It was missing in the
// live database, which made /api/properties_list.php fail for logged-in users.
$pdo->exec('CREATE TABLE IF NOT EXISTS saved_properties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    property_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_property (user_id, property_id),
    KEY idx_property (property_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
echo "  ok table saved_properties\n";

require_once APP_PATH . '/Services/RentNotificationService.php';
ensure_sms_reminder_logs_table($pdo);
echo "  ok table sms_reminder_logs\n";

add_column($pdo, 'applications', 'checklist_negotiation', 'LONGTEXT NULL');
add_column($pdo, 'applications', 'lessor_rebuttal', 'TEXT NULL');
add_column($pdo, 'applications', 'rebuttal_at', 'DATETIME NULL');
add_column($pdo, 'applications', 'valid_id_path', 'VARCHAR(255) NULL');
add_column($pdo, 'applications', 'sec_dti_path', 'VARCHAR(255) NULL');
add_column($pdo, 'applications', 'business_permit_path', 'VARCHAR(255) NULL');
add_column($pdo, 'applications', 'bir_cert_path', 'VARCHAR(255) NULL');
add_column($pdo, 'maintenance_tickets', 'photos', 'TEXT NULL');
add_column($pdo, 'violations', 'is_paid', 'TINYINT(1) NOT NULL DEFAULT 0');
add_column($pdo, 'lease_contracts', 'rejected_at', 'DATETIME NULL');
add_column($pdo, 'lease_contracts', 'rejection_reason', 'TEXT NULL');

// --- Customizable contracts -------------------------------------------------
// contract_templates: a lessor's reusable clause sets ("Retail stall", "Food tenant", ...).
//   The built-in "Pwestora Standard" template is NOT stored here; it comes from
//   app/Config/lease_checklist.php so it can never be edited or deleted by accident.
$pdo->exec('CREATE TABLE IF NOT EXISTS contract_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lessor_id INT NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    clauses LONGTEXT NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_lessor (lessor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
echo "  ok table contract_templates\n";

// lease_contracts: ONE contract per tenant. Clauses are COPIED from the template
//   (a snapshot), so editing a template later never changes an existing contract.
//   status: draft (lessor only) → finalized (tenant can see + accept) → accepted
$pdo->exec("CREATE TABLE IF NOT EXISTS lease_contracts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    lessor_id INT NOT NULL,
    template_id INT NULL,
    template_name VARCHAR(120) NULL,
    monthly_rent DECIMAL(12,2) NOT NULL DEFAULT 0,
    term_months INT NOT NULL DEFAULT 0,
    start_date DATE NULL,
    end_date DATE NULL,
    security_deposit DECIMAL(12,2) NOT NULL DEFAULT 0,
    advance_rent DECIMAL(12,2) NOT NULL DEFAULT 0,
    due_day TINYINT NOT NULL DEFAULT 1,
    clauses LONGTEXT NOT NULL,
    special_provisions TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    version INT NOT NULL DEFAULT 1,
    finalized_at DATETIME NULL,
    accepted_at DATETIME NULL,
    rejected_at DATETIME NULL,
    rejection_reason TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_tenant (tenant_id),
    KEY idx_lessor (lessor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "  ok table lease_contracts\n";

// lease_contract_history: every finalized version is archived here when the lessor
//   revises it, so you can always show what the tenant agreed to before.
$pdo->exec('CREATE TABLE IF NOT EXISTS lease_contract_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contract_id INT NOT NULL,
    tenant_id INT NOT NULL,
    version INT NOT NULL,
    snapshot LONGTEXT NOT NULL,
    status_at_archive VARCHAR(20) NOT NULL,
    archived_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_contract (contract_id),
    KEY idx_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
echo "  ok table lease_contract_history\n";

// contract_template_properties: which template a building (property) uses by default.
//   One template per building (property_id is the primary key). A building with no row
//   falls back to the lessor's default template, then to the built-in Pwestora Standard.
$pdo->exec('CREATE TABLE IF NOT EXISTS contract_template_properties (
    property_id INT NOT NULL PRIMARY KEY,
    template_id INT NOT NULL,
    lessor_id INT NOT NULL,
    KEY idx_template (template_id),
    KEY idx_lessor (lessor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
echo "  ok table contract_template_properties\n";

// Violations now cite the exact contract clause the tenant broke (snapshot at time of issue,
// so the record stays correct even if the contract is revised later).
add_column($pdo, 'violations', 'clause_key', 'VARCHAR(80) NULL');
add_column($pdo, 'violations', 'contract_ref', 'VARCHAR(60) NULL');
add_column($pdo, 'violations', 'clause_text', 'TEXT NULL');
add_column($pdo, 'violations', 'clause_ref', 'VARCHAR(300) NULL');

// --- Property media & expanded property info (Nov 2026) ---------------------
// Landmark: a nearby, well-known place that helps tenants find the space, with its own photo.
add_column($pdo, 'properties', 'landmark_name', 'VARCHAR(150) NULL');
add_column($pdo, 'properties', 'landmark_distance', 'VARCHAR(60) NULL');
add_column($pdo, 'properties', 'landmark_photo_path', 'VARCHAR(255) NULL');
// Expanded property information shown to tenants and to the SuperAdmin reviewer.
add_column($pdo, 'properties', 'floor_level', 'VARCHAR(60) NULL');
add_column($pdo, 'properties', 'frontage', 'VARCHAR(60) NULL');
add_column($pdo, 'properties', 'parking_slots', 'INT NULL');
add_column($pdo, 'properties', 'utilities', 'VARCHAR(255) NULL');
add_column($pdo, 'properties', 'amenities', 'TEXT NULL');
add_column($pdo, 'properties', 'permitted_use', 'VARCHAR(255) NULL');
add_column($pdo, 'properties', 'operating_hours', 'VARCHAR(100) NULL');
add_column($pdo, 'properties', 'deposit_terms', 'VARCHAR(150) NULL');
// Photo gallery: category (exterior, interior, ...), caption and order (sort_order 0 = cover).
add_column($pdo, 'property_photos', 'category', "VARCHAR(30) NOT NULL DEFAULT 'exterior'");
add_column($pdo, 'property_photos', 'caption', 'VARCHAR(150) NULL');
add_column($pdo, 'property_photos', 'sort_order', 'INT NOT NULL DEFAULT 0');

// --- Editable notice types (SuperAdmin → Notify Lessors) --------------------
// Each type has a message template that pre-fills the composer. SuperAdmin can add,
// rename, edit the template of, hide, or delete types.
$pdo->exec("CREATE TABLE IF NOT EXISTS notice_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(60) NOT NULL,
    template TEXT NULL,
    color VARCHAR(20) NOT NULL DEFAULT 'blue',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "  ok table notice_types\n";

$seed = [
    ['General Announcement', "Good day, Pwestora Partner!\n\nWe would like to share an update with all Pwestora partners:\n\n[Write your announcement here]\n\nThank you for being part of Pwestora.\n— Pwestora Admin", 'blue', 1],
    ['Policy Update', "Good day, Pwestora Partner!\n\nPlease be informed that Pwestora has updated the following policy:\n\n[Describe the policy change and its effective date]\n\nKindly review and comply. Reply through the platform if you have questions.\n— Pwestora Admin", 'purple', 2],
    ['Verification Reminder', "Good day, Pwestora Partner!\n\nThis is a reminder to complete or update your verification documents on Pwestora.\n\n[List the missing or expiring documents]\n\nUnverified listings will not be shown to tenants.\n— Pwestora Admin", 'orange', 3],
    ['Compliance Notice', "Good day, Pwestora Partner!\n\nWe noticed an item on your account that needs your attention:\n\n[Describe the compliance issue and the deadline]\n\nPlease settle this as soon as possible to avoid restrictions.\n— Pwestora Admin", 'red', 4],
];
// Seed only on first run, so types the SuperAdmin deleted don't come back.
if ((int) $pdo->query('SELECT COUNT(*) FROM notice_types')->fetchColumn() === 0) {
    $ins = $pdo->prepare('INSERT INTO notice_types (name, template, color, sort_order) VALUES (?, ?, ?, ?)');
    foreach ($seed as $row) {
        $ins->execute($row);
    }
    echo "  + seeded default notice types\n";
} else {
    echo "  = notice types already set up\n";
}

echo "Done.\n";
