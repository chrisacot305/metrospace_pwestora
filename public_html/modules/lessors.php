<?php
/**
 * modules/lessors.php  (SuperAdmin → "Lessor Verification")
 * Controller for Lessor Verification.
 */

$modelPath = __DIR__ . '/../models/LessorModel.php';
$viewPath = __DIR__ . '/../views/superadmin/lessors.php';

if (!file_exists($modelPath)) die("<div class='card' style='border:2px solid red; color:red;'>Missing: models/LessorModel.php</div>");
if (!file_exists($viewPath)) die("<div class='card' style='border:2px solid red; color:red;'>Missing: views/superadmin/lessors.php</div>");

require_once $modelPath;
$lessorModel = new LessorModel($pdo);

$adminId = $user['id'];
$error = '';

$docLabels = [
    'business_permit_path' => 'Business Permit',
    'sec_dti_path'         => 'SEC / DTI Registration',
    'bir_cert_path'        => 'BIR Certificate',
    'land_title_path'      => 'Land Title',
    'gov_id_path'          => 'Government ID'
];

// Handle Form Submissions (Approve / Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    
    $action = $_POST['action'] ?? '';
    $targetId = (int) ($_POST['lessor_id'] ?? 0);
    
    if ($targetId > 0) {
        try {
            if ($action === 'approve') {
                $lessorModel->approveLessor($targetId, $adminId);
                audit_log($pdo, $adminId, "Approved lessor ID $targetId");
            } elseif ($action === 'reject') {
                $lessorModel->rejectLessor($targetId);
                audit_log($pdo, $adminId, "Rejected lessor ID $targetId");
            }
            
            // FIXED: Using a JavaScript redirect bypasses the "headers already sent" block
            // This will instantly and seamlessly reload the dashboard without getting stuck!
            echo "<script>window.location.href='/dashboard.php?page=lessors';</script>";
            exit;
            
        } catch (Throwable $e) {
            error_log("Lessor verification error: " . $e->getMessage());
            $error = 'Could not process this decision — the error has been logged. Please try again.';
        }
    }
}

// Check if a specific Lessor is being reviewed in the drawer
$reviewing = null;
if (isset($_GET['review'])) {
    $reviewingId = (int) $_GET['review'];
    $reviewing = $lessorModel->getLessorById($reviewingId);
}

// Fetch all Lessors for the main table
$allLessors = $lessorModel->getAllLessors();

// Load the View
require_once $viewPath;