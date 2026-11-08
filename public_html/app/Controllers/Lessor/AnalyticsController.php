<?php
namespace App\Controllers\Lessor;

use App\Core\Controller;
use App\Models\LessorOverviewModel;

/**
 * Lessor → Analytics (was modules/analytics.php).
 * NOTE: like before, this page is not in the sidebar or the dashboard whitelist —
 * its numbers are shown on the Dashboard overview. Kept so nothing is lost.
 */
final class AnalyticsController extends Controller
{
    public function handle(array $user): array
    {
        $lessorId = (int) $user['id'];
        $since30 = date('Y-m-d H:i:s', strtotime('-30 days'));
        $m = new LessorOverviewModel($this->pdo);

        $ir = $m->getInstallmentStats($lessorId, $since30);
        $irTotal = $ir['total']; $ir30d = $ir['last_30d']; $irApproved = $ir['approved'];
        $irPending = $ir['pending']; $irRejected = $ir['rejected'];
        $irApprovalRate = pct($irApproved, $irTotal);

        $mt = $m->getMaintenanceStats($lessorId, $since30);
        $mtTotal = $mt['total']; $mt30d = $mt['last_30d']; $mtCompleted = $mt['completed'];
        $mtCompletionRate = pct($mtCompleted, $mtTotal);
        $mtOpen = $mt['open']; $mtOverdue = $mt['overdue'];
        $mtOnTrackRate = pct($mtOpen - $mtOverdue, $mtOpen);

        $v = $m->getViolationStats($lessorId, $since30);
        $vTotal = $v['total']; $v30d = $v['last_30d']; $vStrike1 = $v['strike1'];
        $vResolvedRate = pct($vStrike1, $vTotal);
        $vStrike2plus = $m->getStrike2Plus($lessorId);
        $vEvictions = $v['evictions'];

        unset($m, $ir, $mt, $v);
        return $this->view('lessor/analytics', get_defined_vars());
    }
}
