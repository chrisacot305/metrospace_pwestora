<?php
namespace App\Controllers\Lessor;

use App\Core\Controller;
use App\Models\LessorOverviewModel;

/** Lessor → Dashboard (was modules/overview_lessor.php) */
final class OverviewController extends Controller
{
    public function handle(array $user): array
    {
        $overviewModel = new LessorOverviewModel($this->pdo);
        $lessorId = (int) $user['id'];
        $since30 = date('Y-m-d H:i:s', strtotime('-30 days'));

        // 1. Analytics
        $ir = $overviewModel->getInstallmentStats($lessorId, $since30);
        $irApprovalRate = pct($ir['approved'], $ir['total']);

        $mt = $overviewModel->getMaintenanceStats($lessorId, $since30);
        $mtCompletionRate = pct($mt['completed'], $mt['total']);
        $mtOnTrackRate = pct($mt['open'] - $mt['overdue'], $mt['open']);

        $v = $overviewModel->getViolationStats($lessorId, $since30);
        $v['strike2plus'] = $overviewModel->getStrike2Plus($lessorId);
        $vResolvedRate = pct($v['strike1'], $v['total']);

        // 2. KPIs
        $kpis = $overviewModel->getKpiStats($lessorId);

        // 3. Pending lists
        $pendingInstallments = $overviewModel->getPendingInstallments($lessorId);
        $openTicketRows = $overviewModel->getOpenTickets($lessorId);
        $pendingPayments = $overviewModel->getPendingPayments($lessorId);

        $ticketStages = ['Submitted', 'Under Review', 'Approved', 'Contractor Assigned', 'Scheduled', 'In Progress', 'Inspection', 'Completed'];
        $pendingPaymentsCount = count($pendingPayments);
        $pendingPaymentsTotal = array_sum(array_column($pendingPayments, 'amount'));

        return $this->view('lessor/overview', get_defined_vars());
    }
}
