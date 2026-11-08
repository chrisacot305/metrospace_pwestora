<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\AuditModel;

/** /export_audit_log.php — full audit log as CSV (SuperAdmin only). */
final class ReportController extends Controller
{
    public function exportAuditLog(): void
    {
        Auth::requireRole(['superadmin']);
        $logs = (new AuditModel($this->pdo))->allForExport();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="pwestora-audit-log-' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Actor', 'Role', 'Action', 'Target', 'Timestamp']);
        foreach ($logs as $l) {
            $actor = $l['actor_role']
                ? ($l['actor_role'] === 'lessor' ? ($l['company_name'] ?: 'Lessor') : ($l['full_name'] ?: 'User'))
                : 'System';
            fputcsv($out, array_map([self::class, 'csvSafe'], [
                $actor,
                $l['actor_role'] ?: '—',
                $l['action'],
                $l['details'] ?: '—',
                $l['created_at'],
            ]));
        }
        fclose($out);
        exit;
    }

    /** Stops "CSV injection": a cell starting with = + - @ is run as a formula by Excel. */
    private static function csvSafe($value): string
    {
        $value = (string) $value;
        return ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) ? "'" . $value : $value;
    }
}
