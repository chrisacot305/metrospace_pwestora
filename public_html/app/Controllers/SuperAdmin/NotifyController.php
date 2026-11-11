<?php
namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Core\Csrf;
use App\Models\NoticeTypeModel;
use App\Models\NotifyModel;

/**
 * SuperAdmin → Notify Lessors (was modules/notify.php)
 *
 * Tabs:
 *   ?page=notify                 sent notices + "New Notice" composer (?compose=1)
 *   ?page=notify&tab=types       manage notice types (?new_type=1 / ?edit_type=ID)
 *
 * Notice types are editable: each one has a message template that pre-fills the
 * composer (e.g. "General Announcement"). The type name is what lessors see as the
 * notification title in their bell.
 */
final class NotifyController extends Controller
{
    public function handle(array $user): array
    {
        $adminId = (int) $user['id'];
        $error = '';
        $successMsg = '';

        $notifyModel = new NotifyModel($this->pdo);
        $typeModel   = new NoticeTypeModel($this->pdo);
        $typesReady  = $typeModel->isReady();
        $lessors = $notifyModel->getActiveLessors();
        $tab = ($_GET['tab'] ?? '') === 'types' ? 'types' : 'sent';
        $COLORS = NoticeTypeModel::COLORS;

        // ---------------------------------------------------------------- notice type management
        if ($this->isPost() && (isset($_POST['save_type']) || isset($_POST['delete_type']) || isset($_POST['toggle_type']))) {
            Csrf::check();
            $tab = 'types';
            if (!$typesReady) {
                $error = 'Run tools/migrate.php first — the notice_types table does not exist yet.';
            } else {
                try {
                    $typeId = (int) ($_POST['type_id'] ?? 0);
                    if (isset($_POST['delete_type'])) {
                        $t = $typeModel->find($typeId);
                        if ($t) {
                            $typeModel->delete($typeId);
                            audit_log($this->pdo, $adminId, 'Deleted notice type', $t['name']);
                        }
                        $this->redirect('/dashboard.php?page=notify&tab=types&msg=deleted');
                    }
                    if (isset($_POST['toggle_type'])) {
                        $t = $typeModel->find($typeId);
                        if ($t) {
                            $typeModel->setActive($typeId, !(int) $t['is_active']);
                            audit_log($this->pdo, $adminId, ((int) $t['is_active'] ? 'Hid' : 'Showed') . ' notice type', $t['name']);
                        }
                        $this->redirect('/dashboard.php?page=notify&tab=types');
                    }

                    // save (create or update)
                    $name     = mb_substr(trim((string) ($_POST['type_name'] ?? '')), 0, 60);
                    $template = mb_substr(str_replace("\r\n", "\n", trim((string) ($_POST['type_template'] ?? ''))), 0, 5000);
                    $color    = (string) ($_POST['type_color'] ?? 'blue');
                    $active   = isset($_POST['type_active']);

                    if ($name === '') {
                        $error = 'Give the notice type a name.';
                    } elseif ($typeModel->nameTaken($name, $typeId)) {
                        $error = 'A notice type named "' . $name . '" already exists.';
                    } elseif ($typeId > 0 && $typeModel->find($typeId)) {
                        $typeModel->update($typeId, $name, $template, $color, $active);
                        audit_log($this->pdo, $adminId, 'Updated notice type', $name);
                        $this->redirect('/dashboard.php?page=notify&tab=types&msg=saved');
                    } else {
                        $typeModel->create($name, $template, $color, $active);
                        audit_log($this->pdo, $adminId, 'Created notice type', $name);
                        $this->redirect('/dashboard.php?page=notify&tab=types&msg=created');
                    }
                } catch (\Throwable $e) {
                    error_log('Notice type action failed: ' . $e->getMessage());
                    $error = 'Could not save the notice type — the error has been logged. Please try again.';
                }
            }
        }

        $NOTICE_TYPES_FULL = $typeModel->allTypes();      // for the types tab + filters
        $activeTypes       = $typeModel->activeTypes();   // for the composer
        $NOTICE_TYPES      = array_column($NOTICE_TYPES_FULL, 'name');

        // ---------------------------------------------------------------- send a notice
        if ($this->isPost() && isset($_POST['send_notice'])) {
            Csrf::check();

            $allLessors = isset($_POST['all_lessors']);
            $recipientIds = $allLessors
                ? array_column($lessors, 'id')
                : array_map('intval', is_array($_POST['recipient_ids'] ?? null) ? $_POST['recipient_ids'] : []);

            $activeNames = array_column($activeTypes, 'name');
            $type = in_array($_POST['notice_type'] ?? '', $activeNames, true) ? $_POST['notice_type'] : ($activeNames[0] ?? 'General Announcement');
            $body = trim((string) ($_POST['notice_body'] ?? ''));
            $body = mb_substr(str_replace("\r\n", "\n", $body), 0, 5000);

            // Only allow recipients that are genuinely active lessors.
            $valid = array_map('intval', array_column($lessors, 'id'));
            $recipientIds = array_values(array_intersect($recipientIds, $valid));

            if (!$recipientIds || $body === '') {
                $error = 'Select at least one lessor and write a message.';
            } elseif ($this->leftoverPlaceholder($body, $activeTypes, $type) !== null) {
                // The template's "[Write your announcement here]" style text was not replaced.
                $error = 'Replace the placeholder "' . $this->leftoverPlaceholder($body, $activeTypes, $type) . '" from the template before sending.';
            } else {
                try {
                    $names = [];
                    foreach ($lessors as $l) {
                        if (in_array((int) $l['id'], $recipientIds, true)) {
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

        $flash = [
            'saved'   => 'Notice type saved.',
            'created' => 'Notice type created. It now appears in the composer.',
            'deleted' => 'Notice type deleted. Notices already sent keep their type name.',
        ][$_GET['msg'] ?? ''] ?? '';
        if ($flash && !$successMsg && !$error) $successMsg = $flash;

        $sentLog = $notifyModel->getSentLogs($adminId);
        // Types used in old notices (even deleted ones) stay filterable.
        $filterTypes = array_values(array_unique(array_merge($NOTICE_TYPES, array_column($sentLog, 'title'))));
        $typeColors = array_column($NOTICE_TYPES_FULL, 'color', 'name');

        $composing = (isset($_GET['compose']) && empty($successMsg)) || ($error !== '' && isset($_POST['send_notice']));
        $editingType = null;
        $typeFormOpen = false;
        if ($tab === 'types') {
            if (isset($_GET['edit_type'])) {
                $editingType = $typeModel->find((int) $_GET['edit_type']);
                $typeFormOpen = (bool) $editingType;
            } elseif (isset($_GET['new_type'])) {
                $typeFormOpen = true;
            }
            if ($error !== '' && isset($_POST['save_type'])) {
                $typeFormOpen = true;
                $editingType = $typeModel->find((int) ($_POST['type_id'] ?? 0));
            }
        }
        $usage = $tab === 'types' ? $typeModel->usageCounts() : [];

        return $this->view('superadmin/notify', get_defined_vars());
    }

    /** First "[...]" placeholder of the chosen type's template that is still in the message, or null. */
    private function leftoverPlaceholder(string $body, array $types, string $typeName): ?string
    {
        foreach ($types as $t) {
            if ($t['name'] !== $typeName) continue;
            if (preg_match_all('/\[[^\]\n]{3,}\]/', (string) ($t['template'] ?? ''), $m)) {
                foreach ($m[0] as $ph) {
                    if (str_contains($body, $ph)) return $ph;
                }
            }
        }
        return null;
    }
}
