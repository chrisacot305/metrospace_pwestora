<?php
/**
 * modules/notify.php  (SuperAdmin → "Notify Lessors")
 * Controller for Notify Lessors.
 */
require_once __DIR__ . '/../models/NotifyModel.php';

$adminId = $user['id'];
$error = '';
$successMsg = ''; // New success variable
$NOTICE_TYPES = ['Policy Update', 'Verification Reminder', 'Compliance Notice'];

$notifyModel = new NotifyModel($pdo);
$lessors = $notifyModel->getActiveLessors();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notice'])) {
    csrf_check();
    
    $allLessors = isset($_POST['all_lessors']);
    $recipientIds = $allLessors
        ? array_column($lessors, 'id')
        : array_map('intval', $_POST['recipient_ids'] ?? []);
        
    $type = in_array($_POST['notice_type'] ?? '', $NOTICE_TYPES, true) ? $_POST['notice_type'] : 'Policy Update';
    $body = trim($_POST['notice_body'] ?? '');

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

            // Execute inserts via Model
            $notificationId = $notifyModel->logNotification($adminId, $audience, $type, $body);
            $notifyModel->addRecipients($notificationId, $recipientIds);

            audit_log($pdo, $adminId, 'Sent lessor notice', "$type to " . count($recipientIds) . ' lessor(s)');

            // Set success message instead of redirecting
            $successMsg = 'Notice successfully sent to ' . count($recipientIds) . ' lessor(s).';

        } catch (Throwable $e) {
            error_log('Send lessor notice failed: ' . $e->getMessage());
            $error = 'Could not send this notice — the error has been logged. Please try again.';
        }
    }
}

// Fetch Log Data for the View
$sentLog = $notifyModel->getSentLogs($adminId);

// Only open the compose drawer if requested AND we didn't just submit a successful notice
$composing = isset($_GET['compose']) && empty($successMsg);

// Load the View
require_once __DIR__ . '/../views/superadmin/notify.php';