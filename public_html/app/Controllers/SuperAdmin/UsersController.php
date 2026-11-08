<?php
namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Core\Csrf;
use App\Models\UserModel;

/** SuperAdmin → User Management (was modules/users.php) */
final class UsersController extends Controller
{
    public function handle(array $user): array
    {
        $adminId = (int) $user['id'];
        $error = '';
        $msg = '';
        $userModel = new UserModel($this->pdo);

        // Suspend / Reinstate (the page calls this in the background with ajax=1)
        if ($this->isPost() && isset($_POST['toggle_user_id'])) {
            Csrf::check();
            $targetId = (int) $_POST['toggle_user_id'];
            $isAjax = isset($_POST['ajax']) && $_POST['ajax'] === '1';

            try {
                $target = $userModel->getUserById($targetId);
                if ($target) {
                    $newStatus = $target['status'] === 'suspended' ? 'active' : 'suspended';
                    $userModel->updateUserStatus($targetId, $newStatus);
                    audit_log($this->pdo, $adminId, ucfirst($newStatus) . ' user', $target['email']);

                    $msg = $newStatus === 'suspended' ? 'User suspended successfully.' : 'User access reinstated.';

                    if ($isAjax) {
                        // The page's JavaScript reads the JSON between these two markers.
                        echo '<!--AJAX_RES_START-->';
                        echo json_encode(['success' => true, 'newStatus' => $newStatus, 'msg' => $msg, 'userId' => $targetId]);
                        echo '<!--AJAX_RES_END-->';
                        exit;
                    }
                    echo '<script>if(window.history.replaceState){window.history.replaceState(null,null,"/dashboard.php?page=users");}</script>';
                }
            } catch (\Throwable $e) {
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

        $usersList = $userModel->getAllUsers();

        return $this->view('superadmin/users', get_defined_vars());
    }
}
