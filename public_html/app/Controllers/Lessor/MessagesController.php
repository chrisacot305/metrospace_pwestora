<?php
namespace App\Controllers\Lessor;

use App\Core\Controller;
use App\Core\Csrf;
use App\Models\MessageModel;
use App\Models\NotifyModel;
use App\Models\PropertyModel;
use App\Models\TenantModel;

/**
 * Lessor → Messages (was modules/messages.php)
 * Direct chat per tenant (filterable by building) + formal broadcast notices.
 */
final class MessagesController extends Controller
{
    public function handle(array $user): array
    {
        $lessorId = (int) $user['id'];
        $error = '';
        $NOTICE_TYPES = ['General Notice', 'Payment Reminder', 'Maintenance Update'];

        $messagesModel = new MessageModel($this->pdo);
        $properties = (new PropertyModel($this->pdo))->listForLessor($lessorId);
        $allTenants = (new TenantModel($this->pdo))->listWithProperty($lessorId);

        // Filter by building
        $selectedPropertyId = (isset($_GET['property_id']) && $_GET['property_id'] !== '') ? (int) $_GET['property_id'] : null;
        $tenants = $allTenants;
        if ($selectedPropertyId !== null) {
            $tenants = array_values(array_filter($allTenants, function ($t) use ($selectedPropertyId) {
                return (int) $t['property_id'] === $selectedPropertyId;
            }));
        }
        $ownTenantIds = array_map('intval', array_column($allTenants, 'id'));

        $composing = isset($_GET['compose']);

        // ---- Send a direct message ----
        if ($this->isPost() && isset($_POST['send_message'])) {
            Csrf::check();
            $tenantId = (int) ($_POST['tenant_id'] ?? 0);
            $body = trim((string) ($_POST['body'] ?? ''));
            try {
                if (in_array($tenantId, $ownTenantIds, true) && $body !== '') {
                    $threadId = $messagesModel->findOrCreateThread($lessorId, $tenantId);
                    $messagesModel->addFromLessor($threadId, $body);
                }
                $propUrl = $selectedPropertyId ? '&property_id=' . $selectedPropertyId : '';
                echo '<script>if(window.history.replaceState){window.history.replaceState(null,null,"/dashboard.php?page=messages' . $propUrl . '&tenant=' . $tenantId . '");}</script>';
                $_GET['tenant'] = $tenantId; // keep the active tenant selected
            } catch (\Throwable $e) {
                error_log('Send message failed: ' . $e->getMessage());
                $error = 'Could not send this message — the error has been logged. Please try again.';
            }
        }

        // ---- Broadcast a formal notification ----
        if ($this->isPost() && isset($_POST['send_notification'])) {
            Csrf::check();
            $targetBuilding = $_POST['target_building'] ?? 'all';
            $isAllTenants = isset($_POST['all_tenants']);
            $type = in_array($_POST['notice_type'] ?? '', $NOTICE_TYPES, true) ? $_POST['notice_type'] : 'General Notice';
            $body = trim((string) ($_POST['notice_body'] ?? ''));

            if ($isAllTenants) {
                if ($targetBuilding !== 'all') {
                    $targetPropId = (int) $targetBuilding;
                    $recipientIds = array_column(array_filter($allTenants, fn($t) => (int) $t['property_id'] === $targetPropId), 'id');
                    $bName = 'Building';
                    foreach ($properties as $p) {
                        if ((int) $p['id'] === $targetPropId) { $bName = $p['name']; break; }
                    }
                    $audience = "All in $bName";
                } else {
                    $recipientIds = array_column($allTenants, 'id');
                    $audience = 'all_tenants';
                }
            } else {
                $recipientIds = array_map('intval', is_array($_POST['recipient_ids'] ?? null) ? $_POST['recipient_ids'] : []);
                $valid = array_column($allTenants, 'id');
                $recipientIds = array_values(array_intersect($recipientIds, $valid));
                $audience = count($recipientIds) . ' selected';
            }

            if (!$recipientIds || $body === '') {
                $error = 'Select at least one recipient and write a message.';
            } else {
                try {
                    foreach ($recipientIds as $tid) {
                        $threadId = $messagesModel->findOrCreateThread($lessorId, (int) $tid);
                        $messagesModel->addFromLessor($threadId, $body, $type);
                    }
                    (new NotifyModel($this->pdo))->logNotification($lessorId, $audience, $type, $body);
                    audit_log($this->pdo, $lessorId, 'Sent notification', "$type to " . count($recipientIds) . ' tenant(s)');

                    $propUrl = $selectedPropertyId ? '&property_id=' . $selectedPropertyId : '';
                    $activeTenantUrl = isset($_GET['tenant']) ? '&tenant=' . (int) $_GET['tenant'] : '';
                    echo '<script>if(window.history.replaceState){window.history.replaceState(null,null,"/dashboard.php?page=messages' . $propUrl . $activeTenantUrl . '");}</script>';
                    $composing = false;
                } catch (\Throwable $e) {
                    error_log('Send notification failed: ' . $e->getMessage());
                    $error = 'Could not send this notification — the error has been logged. Please try again.';
                }
            }
        }

        // Active conversation
        $activeTenantId = isset($_GET['tenant']) ? (int) $_GET['tenant'] : null;
        $activeTenant = null;
        if ($activeTenantId) {
            foreach ($tenants as $t) {
                if ((int) $t['id'] === $activeTenantId) { $activeTenant = $t; break; }
            }
        }
        if (!$activeTenant && !empty($tenants)) {
            $activeTenant = $tenants[0];
            $activeTenantId = (int) $activeTenant['id'];
        }

        $thread = $activeTenant ? $messagesModel->conversation($lessorId, $activeTenantId) : [];
        $sentLog = (new NotifyModel($this->pdo))->sentBy($lessorId, 20);

        unset($messagesModel, $ownTenantIds);
        return $this->view('lessor/messages', get_defined_vars());
    }
}
