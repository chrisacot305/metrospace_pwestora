<?php
namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Core\Csrf;
use App\Models\NotifyModel;

/** SuperAdmin → Notify Lessors (was modules/notify.php) */
final class NotifyController extends Controller
{
    public function handle(array $user): array
    {
        $adminId = (int) $user['id'];
        $error = '';
        $successMsg = '';
        $NOTICE_TYPES = ['Policy Update', 'Verification Reminder', 'Compliance Notice'];

        $notifyModel = new NotifyModel($this->pdo);
        $lessors = $notifyModel->getActiveLessors();

        if ($this->isPost() && isset($_POST['send_notice'])) {
            Csrf::check();

            $allLessors = isset($_POST['all_lessors']);
            $recipientIds = $allLessors
                ? array_column($lessors, 'id')
                : array_map('intval', is_array($_POST['recipient_ids'] ?? null) ? $_POST['recipient_ids'] : []);

            $type = in_array($_POST['notice_type'] ?? '', $NOTICE_TYPES, true) ? $_POST['notice_type'] : 'Policy Update';
            $body = trim((string) ($_POST['notice_body'] ?? ''));

            // Only allow recipients that are genuinely active lessors.
            $valid = array_column($lessors, 'id');
            $recipientIds = array_values(array_intersect($recipientIds, $valid));

            if (!$recipientIds || $body === '') {
                $error = 'Select at least one lessor and write a message.';
            } else {
                try {
                    $names = [];
                    foreach ($lessors as $l) {
                        if (in_array($l['id'], $recipientIds, true)) {
                            $names[] = $l['company_name'];
                        }
                    }
                    $audience = $allLessors ? 'All lessors (' . count($recipientIds) . ')' : implode(', ', $names);

                    $notificationId = $notifyModel->logNotification($adminId, $audience, $type, $body);
                    $notifyModel->addRecipients($notificationId, $recipientIds);

                    audit_log($this->pdo, $adminId, 'Sent lessor notice', "$type to " . count($recipientIds) . ' lessor(s)');
                    $successMsg = 'Notice successfully sent to ' . count($recipientIds) . ' lessor(s).';
                } catch (\Throwable $e) {
                    error_log('Send lessor notice failed: ' . $e->getMessage());
                    $error = 'Could not send this notice — the error has been logged. Please try again.';
                }
            }
        }

        $sentLog = $notifyModel->getSentLogs($adminId);
        $composing = isset($_GET['compose']) && empty($successMsg);

        return $this->view('superadmin/notify', get_defined_vars());
    }
}
