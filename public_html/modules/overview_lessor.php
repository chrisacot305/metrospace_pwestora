<?php
/**
 * modules/overview_lessor.php
 * Controller for the Lessor Dashboard Overview.
 */

$modelPath = __DIR__ . '/../models/LessorOverviewModel.php';
$viewPath = __DIR__ . '/../views/lessor/overview.php';

if (!file_exists($modelPath)) die("<div class='card' style='border:2px solid red; color:red;'>Missing: models/LessorOverviewModel.php</div>");
if (!file_exists($viewPath)) die("<div class='card' style='border:2px solid red; color:red;'>Missing: views/lessor/overview.php</div>");

require_once $modelPath;
$overviewModel = new LessorOverviewModel($pdo);
$lessorId = $user['id'];
$since30 = date('Y-m-d H:i:s', strtotime('-30 days'));

// Helper for percentage math
function pct(int $part, int $total): int {
    return $total > 0 ? (int) round(($part / $total) * 100) : 0;
}

// 1. Analytics Data
$ir = $overviewModel->getInstallmentStats($lessorId, $since30);
$irApprovalRate = pct($ir['approved'], $ir['total']);

$mt = $overviewModel->getMaintenanceStats($lessorId, $since30);
$mtCompletionRate = pct($mt['completed'], $mt['total']);
$mtOnTrackRate = pct($mt['open'] - $mt['overdue'], $mt['open']);

$v = $overviewModel->getViolationStats($lessorId, $since30);
$v['strike2plus'] = $overviewModel->getStrike2Plus($lessorId); // distinct count
$vResolvedRate = pct($v['strike1'], $v['total']);

// 2. Standard KPI Data
$kpis = $overviewModel->getKpiStats($lessorId);

// 3. Pending Lists Data
$pendingInstallments = $overviewModel->getPendingInstallments($lessorId);
$openTicketRows = $overviewModel->getOpenTickets($lessorId);
$pendingPayments = $overviewModel->getPendingPayments($lessorId);

$ticketStages = ['Submitted','Under Review','Approved','Contractor Assigned','Scheduled','In Progress','Inspection','Completed'];
$pendingPaymentsCount = count($pendingPayments);
$pendingPaymentsTotal = array_sum(array_column($pendingPayments, 'amount'));

// Load View
require_once $viewPath;