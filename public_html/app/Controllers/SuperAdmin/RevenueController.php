<?php
namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Models\RevenueModel;

/** SuperAdmin → Revenue & Commission (was modules/revenue.php) */
final class RevenueController extends Controller
{
    public function handle(array $user): array
    {
        $revenueModel = new RevenueModel($this->pdo);

        $filterType  = is_string($_GET['type'] ?? null) && $_GET['type'] !== '' ? $_GET['type'] : null;
        $searchQuery = is_string($_GET['search'] ?? null) && $_GET['search'] !== '' ? $_GET['search'] : null;

        $availableTypes = $revenueModel->getAvailablePropertyTypes();
        $feePercent = $revenueModel->getPlatformFeePercent();
        $summary = $revenueModel->getSummaryStats($filterType, $searchQuery);

        $activeLeases  = (int) $summary['active_leases'];
        $mrr           = (float) $summary['mrr'];
        $avgRent       = (float) $summary['avg_rent'];
        $estCommission = $mrr * ($feePercent / 100);

        $byType   = $revenueModel->getRevenueByType($filterType, $searchQuery);
        $byLessor = $revenueModel->getRevenueByLessor($filterType, $searchQuery);

        $typeMax   = $byType ? max(array_column($byType, 'total')) : 0;
        $lessorMax = $byLessor ? max(array_column($byLessor, 'total')) : 0;

        $barColors = ['var(--primary)', 'var(--accent)', 'var(--success)', 'var(--warning)', 'var(--error)', 'var(--steel)'];

        return $this->view('superadmin/revenue', get_defined_vars());
    }
}
