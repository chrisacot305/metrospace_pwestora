<?php
namespace App\Controllers\Lessor;

use App\Core\Controller;
use App\Core\Csrf;
use App\Models\MaintenanceModel;
use App\Models\PropertyModel;
use App\Models\TenantModel;

/** Lessor → Maintenance tickets (was modules/maintenance.php) */
final class MaintenanceController extends Controller
{
    private const STAGES = ['Submitted', 'Under Review', 'Approved', 'Contractor Assigned', 'Scheduled', 'In Progress', 'Inspection', 'Completed'];

    private const CATEGORIES = [
        'plumbing'   => ['Plumbing',   '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path></svg>'],
        'electrical' => ['Electrical', '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>'],
        'structural' => ['Structural', '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>'],
        'internet'   => ['Internet',   '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.55a11 11 0 0 1 14.08 0"></path><path d="M1.42 9a16 16 0 0 1 21.16 0"></path><path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path><line x1="12" y1="20" x2="12.01" y2="20"></line></svg>'],
        'hvac'       => ['HVAC',       '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="2" x2="12" y2="22"></line><line x1="17" y1="5" x2="12" y2="10"></line><line x1="7" y1="5" x2="12" y2="10"></line><line x1="17" y1="19" x2="12" y2="14"></line><line x1="7" y1="19" x2="12" y2="14"></line><line x1="2" y1="12" x2="22" y2="12"></line><line x1="5" y1="7" x2="10" y2="12"></line><line x1="5" y1="17" x2="10" y2="12"></line><line x1="19" y1="7" x2="14" y2="12"></line><line x1="19" y1="17" x2="14" y2="12"></line></svg>'],
        'cleaning'   => ['Cleaning',   '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>'],
        'security'   => ['Security',   '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><polyline points="9 12 11 14 15 10"></polyline></svg>'],
        'lighting'   => ['Lighting',   '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="9" y1="18" x2="15" y2="18"></line><line x1="10" y1="22" x2="14" y2="22"></line><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5A4.61 4.61 0 0 1 8.91 14"></path></svg>'],
    ];

    public function handle(array $user): array
    {
        $lessorId = (int) $user['id'];
        $error = '';
        $msg = is_string($_GET['msg'] ?? null) ? $_GET['msg'] : '';
        $STAGES = self::STAGES;
        $LAST_STAGE = count($STAGES) - 1;
        $CATEGORIES = self::CATEGORIES;
        $loggingTicket = isset($_GET['log']);

        $ticketsModel = new MaintenanceModel($this->pdo);

        // 1. ADVANCE TICKET (and assign contractor at the "Approved" step)
        if ($this->isPost() && isset($_POST['advance_ticket_id'])) {
            Csrf::check();
            $ticketId = (int) $_POST['advance_ticket_id'];
            $assignContractor = mb_substr(trim((string) ($_POST['assign_contractor'] ?? '')), 0, 150);
            try {
                $ticket = $ticketsModel->findForLessor($ticketId, $lessorId);
                if ($ticket && $ticket['stage'] < $LAST_STAGE) {
                    $newStage = $ticket['stage'] + 1;
                    if ($ticket['stage'] == 2 && $assignContractor !== '') {
                        $ticketsModel->setStage($ticketId, $newStage, $assignContractor);
                    } else {
                        $ticketsModel->setStage($ticketId, $newStage);
                    }
                    audit_log($this->pdo, $lessorId, 'Advanced maintenance ticket', $ticket['title'] . ' → ' . $STAGES[$newStage]);
                }
                $msg = 'advanced';
                $_GET['review'] = $ticketId;
            } catch (\Throwable $e) {
                error_log('Advance ticket failed: ' . $e->getMessage());
                $error = 'Could not advance this ticket — the error has been logged. Please try again.';
            }
        }

        // 2. REJECT / DELETE TICKET
        if ($this->isPost() && isset($_POST['reject_ticket_id'])) {
            Csrf::check();
            $ticketId = (int) $_POST['reject_ticket_id'];
            try {
                $ticket = $ticketsModel->findForLessor($ticketId, $lessorId);
                if ($ticket) {
                    $ticketsModel->delete($ticketId);
                    audit_log($this->pdo, $lessorId, 'Rejected/Deleted maintenance ticket', $ticket['title']);
                }
                $msg = 'rejected';
                echo '<script>if(window.history.replaceState){window.history.replaceState(null,null,"/dashboard.php?page=maintenance&msg=rejected");}</script>';
            } catch (\Throwable $e) {
                error_log('Reject ticket failed: ' . $e->getMessage());
                $error = 'Could not reject this ticket. Please try again.';
            }
        }

        // 3. LOG NEW TICKET
        if ($this->isPost() && isset($_POST['log_ticket'])) {
            Csrf::check();
            $tenantId = (int) ($_POST['tenant_id'] ?? 0);
            $category = $_POST['category'] ?? '';
            $priority = strtolower(trim((string) ($_POST['priority'] ?? 'medium')));
            if (!in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
                $priority = 'medium';
            }
            $title      = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 255);
            $contractor = mb_substr(trim((string) ($_POST['contractor'] ?? '')), 0, 150);
            $slaHours   = (int) ($_POST['sla_hours'] ?? 0);

            if (!(new TenantModel($this->pdo))->findOwned($tenantId, $lessorId) || !is_string($category) || !isset($CATEGORIES[$category]) || $title === '') {
                $error = 'Please fill in every required field.';
            } else {
                try {
                    $slaDue = $slaHours > 0 ? date('Y-m-d H:i:s', strtotime("+{$slaHours} hours")) : null;
                    $ticketsModel->create($lessorId, $tenantId, $category, $priority, $title, $contractor ?: null, $slaDue);
                    $msg = 'logged';
                    $loggingTicket = false;
                    unset($_GET['log']);
                } catch (\Throwable $e) {
                    error_log('Log ticket failed: ' . $e->getMessage());
                    $error = 'Could not log this ticket — the error has been logged. Please try again.';
                }
            }
        }

        $tickets    = $ticketsModel->listForLessor($lessorId);
        $properties = (new PropertyModel($this->pdo))->listForLessor($lessorId);
        $tenants    = (new TenantModel($this->pdo))->listForLessor($lessorId);

        // Summary KPIs
        $openTicketsCount = 0;
        $urgentHighCount = 0;
        $overdueCount = 0;
        $completedCount = 0;
        foreach ($tickets as $t) {
            if ((int) $t['stage'] === $LAST_STAGE) {
                $completedCount++;
            } else {
                $openTicketsCount++;
                $p = strtolower(trim((string) ($t['priority'] ?? 'medium')));
                if ($p === 'urgent' || $p === 'high') {
                    $urgentHighCount++;
                }
                [$sText, $sGood] = sla_label($t, $LAST_STAGE);
                if ($sText === 'Overdue') {
                    $overdueCount++;
                }
            }
        }

        $reviewId = isset($_GET['review']) ? (int) $_GET['review'] : null;
        $reviewing = null;
        if ($reviewId) {
            foreach ($tickets as $t) {
                if ((int) $t['id'] === $reviewId) { $reviewing = $t; break; }
            }
        }

        unset($ticketsModel);
        return $this->view('lessor/maintenance', get_defined_vars());
    }
}
