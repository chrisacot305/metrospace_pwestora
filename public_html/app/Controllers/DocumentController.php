<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\View;
use App\Models\LeaseContractModel;
use App\Models\LessorProfileModel;
use App\Models\TenantModel;
use App\Models\ViolationModel;
use App\Services\ContractService;

/**
 * DocumentController — printable documents and protected files.
 *   /eviction_notice.php?id=     → evictionNotice()   (lessor, own 3rd-strike violation)
 *   /lease_contract.php?tenant_id=[&version=] → leaseContract()  (owning lessor or the tenant)
 *   /view_document.php?file=     → verificationFile() (superadmin or the lessor who uploaded it)
 */
final class DocumentController extends Controller
{
    public function evictionNotice(): void
    {
        Auth::requireRole(['lessor']);
        $user = Auth::user();

        $v = (new ViolationModel($this->pdo))->findNoticeForLessor((int) ($_GET['id'] ?? 0), (int) $user['id']);
        if (!$v || $v['strike'] < 3) {
            http_response_code(404);
            die('Notice not available — this violation does not meet the 3-strike threshold.');
        }
        View::render('documents/eviction_notice', ['user' => $user, 'v' => $v]);
    }

    public function leaseContract(): void
    {
        Auth::requireLogin();
        $user = Auth::user();

        $lease = (new TenantModel($this->pdo))->leaseDocument((int) ($_GET['tenant_id'] ?? 0));
        if (!$lease) {
            http_response_code(404);
            die('Lease not found.');
        }

        // Access control: only the lessor who owns this tenant, or the tenant themself.
        $isOwnerLessor = $user['role'] === 'lessor' && (int) $lease['lessor_id'] === (int) $user['id'];
        $isThisTenant  = $user['role'] === 'lessee' && (int) $lease['lessee_id'] === (int) $user['id'];
        if (!$isOwnerLessor && !$isThisTenant) {
            http_response_code(403);
            die('You do not have access to this document.');
        }

        // The tenant's own negotiated contract if there is one, else the standard template.
        //   Tenants never see drafts. ?version=N shows an archived version.
        $contract = ContractService::effective($this->pdo, $lease, !$isOwnerLessor);
        $version = (int) ($_GET['version'] ?? 0);
        if ($version > 0) {
            $row = (new LeaseContractModel($this->pdo))->findByTenant((int) $lease['id']);
            $snap = $row ? (new LeaseContractModel($this->pdo))->snapshotVersion((int) $row['id'], $version) : null;
            if (!$snap) {
                http_response_code(404);
                die('That contract version does not exist.');
            }
            $contract = ContractService::fromRow($snap, 'archived');
        }

        View::render('documents/lease_contract', [
            'user'     => $user,
            'tenantId' => (int) $lease['id'],
            'lease'    => $lease,
            'contract' => $contract,
            'endDate'  => ContractService::computedEndDate($contract['start_date'], $contract['term_months'], $contract['end_date']),
            'isArchivedView' => $version > 0,
        ]);
    }

    /**
     * Lessor verification documents (government ID, permits, land title) are private.
     * They are blocked from direct download by uploads/verification/.htaccess and
     * served only through here.
     */
    public function verificationFile(): void
    {
        Auth::requireLogin();
        $user = Auth::user();

        $file = is_string($_GET['file'] ?? null) ? ltrim($_GET['file'], '/') : '';
        if (!preg_match('#^uploads/verification/\d+/[A-Za-z0-9_\-]+\.(pdf|jpg|jpeg|png|webp)$#', $file)) {
            http_response_code(404);
            die('File not found.');
        }

        $isAdmin = $user['role'] === 'superadmin' && $user['status'] === 'active';
        $isOwner = (new LessorProfileModel($this->pdo))->ownerOfDocument($file) === (int) $user['id'];
        if (!$isAdmin && !$isOwner) {
            http_response_code(403);
            die('You do not have access to this document.');
        }

        $full = realpath(APP_ROOT . '/' . $file);
        $base = realpath(UPLOAD_DIR . '/verification');
        if ($full === false || $base === false || !str_starts_with($full, $base . DIRECTORY_SEPARATOR) || !is_file($full)) {
            http_response_code(404);
            die('File not found.');
        }

        $types = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        header('Content-Type: ' . $types[$ext]);
        header('Content-Length: ' . filesize($full));
        header('Content-Disposition: inline; filename="' . basename($full) . '"');
        header('Cache-Control: private, no-store');
        readfile($full);
        exit;
    }
}
