<?php
namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Models\AuditModel;

/** SuperAdmin → Audit Logs (was modules/audit.php) */
final class AuditController extends Controller
{
    public function handle(array $user): array
    {
        $auditModel = new AuditModel($this->pdo);
        $rawLogs = $auditModel->getRecentLogs(500);

        $uniqueActions = [];
        $logs = array_map(function ($l) use (&$uniqueActions) {
            if (!$l['actor_role']) {
                $l['actor_display'] = 'System';
            } else {
                $l['actor_display'] = $l['actor_role'] === 'lessor'
                    ? ($l['company_name'] ?: 'Lessor')
                    : ($l['full_name'] ?: 'User');
            }
            if (!empty($l['action']) && !in_array($l['action'], $uniqueActions)) {
                $uniqueActions[] = $l['action'];
            }
            return $l;
        }, $rawLogs);

        sort($uniqueActions);

        return $this->view('superadmin/audit', get_defined_vars());
    }
}
