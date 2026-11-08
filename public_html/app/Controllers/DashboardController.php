<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\View;
use App\Models\NotifyModel;
use App\Models\PaymentModel;

/**
 * DashboardController — /dashboard.php?page=...
 *
 * 1. Checks the user is a logged-in, active lessor or superadmin (RBAC).
 * 2. Picks the page controller allowed for that role (whitelist).
 * 3. Runs it BEFORE any HTML is sent, so it can redirect properly after a form post.
 * 4. Renders: layout header → page view → layout footer.
 */
final class DashboardController extends Controller
{
    /** page id => controller class, per role. Anything not listed falls back to "overview". */
    private const PAGES = [
        'lessor' => [
            'overview'     => Lessor\OverviewController::class,
            'listings'     => Lessor\ListingsController::class,
            'applications' => Lessor\ApplicationsController::class,
            'tenants'      => Lessor\TenantsController::class,
            'edit_tenant'  => Lessor\EditTenantController::class,
            'contract'     => Lessor\ContractController::class,
            'contract_templates' => Lessor\ContractTemplatesController::class,
            'installments' => Lessor\InstallmentsController::class,
            'deposits'     => Lessor\DepositsController::class,
            'payments'     => Lessor\PaymentsController::class,
            'maintenance'  => Lessor\MaintenanceController::class,
            'messages'     => Lessor\MessagesController::class,
            'violations'   => Lessor\ViolationsController::class,
        ],
        'superadmin' => [
            'overview'   => SuperAdmin\OverviewController::class,
            'lessors'    => SuperAdmin\LessorsController::class,
            'properties' => SuperAdmin\PropertiesController::class,
            'users'      => SuperAdmin\UsersController::class,
            'notify'     => SuperAdmin\NotifyController::class,
            'revenue'    => SuperAdmin\RevenueController::class,
            'audit'      => SuperAdmin\AuditController::class,
            'settings'   => SuperAdmin\SettingsController::class,
        ],
    ];

    public function show(): void
    {
        Auth::requireRole(['lessor', 'superadmin']);
        $user = Auth::user();

        $page = preg_replace('/[^a-z_]/', '', is_string($_GET['page'] ?? null) ? $_GET['page'] : 'overview');
        $pages = self::PAGES[$user['role']];
        if (!isset($pages[$page])) {
            $page = 'overview';
        }

        // Notification bell actions (lessors) — handled first, exactly like the old header.php did.
        if ($user['role'] === 'lessor') {
            $this->handleNotificationActions((int) $user['id']);
        }

        // Run the page controller. Anything it prints (small inline scripts) is kept and
        // placed right after the layout header, where it used to appear.
        ob_start();
        $controllerClass = $pages[$page];
        $result = (new $controllerClass($this->pdo))->handle($user);
        $earlyOutput = ob_get_clean();

        $layout = $this->layoutData($user, $page);
        View::render('layouts/app_header', $layout);
        echo $earlyOutput;

        if (isset($result['inline_error'])) {
            echo $result['inline_error']; // e.g. "Tenant not found or access denied."
        } elseif (isset($result['view'])) {
            View::render($result['view'], $result['data']);
        }

        View::render('layouts/app_footer');
    }

    private function handleNotificationActions(int $userId): void
    {
        if (!$this->isPost()) {
            return;
        }
        $notify = new NotifyModel($this->pdo);
        if (isset($_POST['mark_all_read'])) {
            Csrf::check();
            $notify->markAllRead($userId);
            $this->redirect($_SERVER['REQUEST_URI'] ?? '/dashboard.php');
        }
        if (isset($_POST['mark_read_id'])) {
            Csrf::check();
            $notify->markRead((int) $_POST['mark_read_id'], $userId);
            $this->redirect($_SERVER['REQUEST_URI'] ?? '/dashboard.php');
        }
    }

    /** Data for the header: sidebar menu, notification bell, pending-payments badge. */
    private function layoutData(array $user, string $page): array
    {
        $navByRole = require APP_PATH . '/Config/navigation.php';
        $data = [
            'user'        => $user,
            'page'        => $page,
            'nav'         => $navByRole[$user['role']] ?? [],
            'roleLabel'   => $user['role'] === 'superadmin' ? 'Super Admin' : 'Admin (Lessor)',
            'notices'     => [],
            'allNotices'  => [],
            'unreadCount' => 0,
        ];

        if ($user['role'] === 'lessor') {
            $notify = new NotifyModel($this->pdo);
            $data['allNotices']  = $notify->inboxFor((int) $user['id'], 50);
            $data['notices']     = array_slice($data['allNotices'], 0, 5);
            $data['unreadCount'] = $notify->unreadCount((int) $user['id']);
            $data['pendingPaymentsCount'] = (new PaymentModel($this->pdo))->pendingCountForLessor((int) $user['id']);
        }
        return $data;
    }
}
