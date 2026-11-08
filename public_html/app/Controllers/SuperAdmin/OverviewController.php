<?php
namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Models\OverviewModel;

/** SuperAdmin → Dashboard (was modules/overview_superadmin.php) */
final class OverviewController extends Controller
{
    public function handle(array $user): array
    {
        $overviewModel = new OverviewModel($this->pdo);

        $totalLessors   = $overviewModel->getTotalActiveLessors();
        $totalLessees   = $overviewModel->getTotalUniqueLessees();
        $pendingLessors = $overviewModel->getPendingLessorsCount();
        $pendingProps   = $overviewModel->getPendingPropertiesCount();
        $totalPending   = $pendingLessors + $pendingProps;

        $pendingLessorRows = $overviewModel->getRecentPendingLessors();
        $pendingPropRows   = $overviewModel->getRecentPendingProperties();

        return $this->view('superadmin/overview', get_defined_vars());
    }
}
