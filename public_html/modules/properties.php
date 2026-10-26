<?php
/**
 * modules/properties.php  (SuperAdmin → "Verify Properties")
 * Controller for Property Verification.
 */
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../models/PropertyModel.php';

$error = '';
$propertyModel = new PropertyModel($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['property_id'])) {
    csrf_check();
    $propertyId = (int) $_POST['property_id'];
    $action     = $_POST['action'];

    try {
        $propRow = $propertyModel->getPropertyWithLessor($propertyId);

        if ($action === 'approve') {
            $propertyModel->updatePropertyStatus($propertyId, 'verified');
            audit_log($pdo, $user['id'], 'Approved property verification', "Property #$propertyId");
            if ($propRow) send_property_status_email($propRow['email'], $propRow['full_name'], $propRow['name'], true);
            
        } elseif ($action === 'reject') {
            $propertyModel->updatePropertyStatus($propertyId, 'rejected');
            audit_log($pdo, $user['id'], 'Rejected property verification', "Property #$propertyId");
            if ($propRow) send_property_status_email($propRow['email'], $propRow['full_name'], $propRow['name'], false);
        }
        
        // FIXED: Using a JavaScript redirect bypasses the "headers already sent" block
        echo "<script>window.location.href='/dashboard.php?page=properties';</script>";
        exit;
    } catch (Throwable $e) {
        error_log('Property verification decision failed: ' . $e->getMessage());
        $error = 'Could not process this decision — the error has been logged. Please try again.';
    }
}

// Fetch all properties for the table
$allProperties = $propertyModel->getAllProperties();

// Handle the Review Drawer data
$reviewId = isset($_GET['review']) ? (int) $_GET['review'] : null;
$reviewing = null;
$reviewingPhotos = [];

if ($reviewId) {
    foreach ($allProperties as $p) {
        if ((int) $p['id'] === $reviewId) { 
            $reviewing = $p; 
            break; 
        }
    }
    if ($reviewing) {
        $reviewingPhotos = $propertyModel->getPropertyPhotos($reviewId);
    }
}

$docLabels = [
    'title_doc_path' => 'Land Title',
    'tax_dec_path'   => 'Tax Declaration',
];

// Load the View
require_once __DIR__ . '/../views/superadmin/properties.php';