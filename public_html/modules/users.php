<?php
/**
 * modules/users.php  (SuperAdmin → "User Management")
 * Controller for User Management (Lessor & Lessee).
 */
require_once __DIR__ . '/../models/UserModel.php';

$adminId = $user['id'];
$error = '';
$msg = '';
$userModel = new UserModel($pdo);

// Handle Status Toggle (Suspend / Reinstate) via Background AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user_id'])) {
    csrf_check();
    $targetId = (int) $_POST['toggle_user_id'];
    $isAjax = isset($_POST['ajax']) && $_POST['ajax'] === '1';

    try {
        $target = $userModel->getUserById($targetId);

        if ($target) {
            $newStatus = $target['status'] === 'suspended' ? 'active' : 'suspended';
            $userModel->updateUserStatus($targetId, $newStatus);
            audit_log($pdo, $adminId, ucfirst($newStatus) . ' user', $target['email']);
            
            $msg = $newStatus === 'suspended' ? 'User suspended successfully.' : 'User access reinstated.';
            
            // Bulletproof JSON wrapper
            if ($isAjax) {
                echo '<!--AJAX_RES_START-->';
                echo json_encode(['success' => true, 'newStatus' => $newStatus, 'msg' => $msg, 'userId' => $targetId]);
                echo '<!--AJAX_RES_END-->';
                exit;
            }
            
            echo '<script>if(window.history.replaceState){window.history.replaceState(null,null,"/dashboard.php?page=users");}</script>';
        }
    } catch (Throwable $e) {
        error_log('Toggle user status failed: ' . $e->getMessage());
        $error = 'Could not update this user — the error has been logged. Please try again.';
        
        if ($isAjax) {
            echo '<!--AJAX_RES_START-->';
            echo json_encode(['success' => false, 'error' => $error]);
            echo '<!--AJAX_RES_END-->';
            exit;
        }
    }
}

// Fetch Data for the View
$usersList = $userModel->getAllUsers();

// Load the View
require_once __DIR__ . '/../views/superadmin/users.php';