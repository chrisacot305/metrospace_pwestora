<?php
namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Models\OverviewModel;

/**
 * SuperAdmin → Dashboard (was modules/overview_superadmin.php)
 *
 * The three KPI cards are clickable: each opens a list (verified lessors, lessees,
 * pending verifications). ?panel=lessors|lessees|pending opens one directly.
 * The toolbar search (?search=…&type=lessor|lessee) now actually searches.
 */
final class OverviewController extends Controller
{
    private const PANELS = ['lessors', 'lessees', 'pending'];

    public function handle(array $user): array
    {
        $overviewModel = new OverviewModel($this->pdo);

        $totalLessors   = $overviewModel->getTotalActiveLessors();
        $totalLessees   = $overviewModel->getTotalUniqueLessees();
        $pendingLessors = $overviewModel->getPendingLessorsCount();
        $pendingProps   = $overviewModel->getPendingPropertiesCount();
        $totalPending   = $pendingLessors + $pendingProps;

        $pendingLessorRows = $overviewModel->getRecentPendingLessors(100);
        $pendingPropRows   = $overviewModel->getRecentPendingProperties(100);

        // Lists behind the KPI cards
        $lessorList = $overviewModel->listActiveLessors();
        $lesseeList = $overviewModel->listLessees();

        // Toolbar search + account filter
        $searchQuery = trim(is_string($_GET['search'] ?? null) ? $_GET['search'] : '');
        $searchQuery = mb_substr($searchQuery, 0, 80);
        $filterType  = in_array($_GET['type'] ?? '', ['lessor', 'lessee'], true) ? $_GET['type'] : '';

        $searchResults = null;
        if ($searchQuery !== '') {
            $searchResults = [
                'lessors'    => $filterType === 'lessee' ? [] : $overviewModel->searchLessors($searchQuery),
                'lessees'    => $filterType === 'lessor' ? [] : $overviewModel->searchLessees($searchQuery),
                'properties' => $filterType !== '' ? [] : $overviewModel->searchProperties($searchQuery),
            ];
        }

        // Which panel starts open: ?panel=…, or the account filter without a search.
        $openPanel = in_array($_GET['panel'] ?? '', self::PANELS, true) ? $_GET['panel'] : '';
        if ($openPanel === '' && $searchQuery === '' && $filterType !== '') {
            $openPanel = $filterType === 'lessor' ? 'lessors' : 'lessees';
        }

        return $this->view('superadmin/overview', get_defined_vars());
    }
}
