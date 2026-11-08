<?php
namespace App\Controllers\Lessor;

use App\Core\Controller;
use App\Core\Csrf;
use App\Models\DepositModel;
use App\Models\PropertyModel;
use App\Models\TenantModel;

/** Lessor → Security Deposits (was modules/deposits.php) */
final class DepositsController extends Controller
{
    public function handle(array $user): array
    {
        $lessorId = (int) $user['id'];
        $error = '';
        $loggingDeposit = isset($_GET['log']);

        $depositsModel = new DepositModel($this->pdo);
        $tenantsModel  = new TenantModel($this->pdo);

        // Release a locked deposit
        if ($this->isPost() && isset($_POST['confirm_release'])) {
            Csrf::check();
            $depositId = (int) ($_POST['deposit_id'] ?? 0);
            $reason = trim((string) ($_POST['release_reason'] ?? 'Emergency release'));
            try {
                $deposit = $depositsModel->findLocked($depositId, $lessorId);
                if ($deposit) {
                    $depositsModel->release($depositId, 'Released: ' . $reason);
                    $tenantsModel->subtractFromSecurityDeposit((int) $deposit['tenant_id'], (float) $deposit['amount']);
                    audit_log($this->pdo, $lessorId, 'Deposit released', $deposit['tenant_id'] . ' — ₱' . $deposit['amount'] . ' (' . $reason . ')');
                }
                echo '<script>if(window.history.replaceState){window.history.replaceState(null,null,"/dashboard.php?page=deposits");}</script>';
                unset($_GET['release'], $_GET['confirm']);
            } catch (\Throwable $e) {
                error_log('Deposit release failed: ' . $e->getMessage());
                $error = 'Could not release this deposit — the error has been logged. Please try again.';
            }
        }

        // Log a new deposit
        if ($this->isPost() && isset($_POST['log_deposit'])) {
            Csrf::check();
            $tenantId = (int) ($_POST['tenant_id'] ?? 0);
            $amountSubmitted = (float) str_replace(',', '', (string) ($_POST['amount'] ?? '0'));
            $method = mb_substr(trim((string) ($_POST['method'] ?? 'Cash')), 0, 50);

            if (!$tenantsModel->findOwned($tenantId, $lessorId) || $amountSubmitted <= 0) {
                $error = 'Please select a valid tenant and enter an amount greater than zero.';
            } else {
                try {
                    $depositsModel->create($lessorId, $tenantId, $amountSubmitted, $method);
                    $tenantsModel->addToSecurityDeposit($tenantId, $amountSubmitted);
                    echo '<script>if(window.history.replaceState){window.history.replaceState(null,null,"/dashboard.php?page=deposits");}</script>';
                    $loggingDeposit = false;
                    unset($_GET['log']);
                } catch (\Throwable $e) {
                    error_log('Log deposit failed: ' . $e->getMessage());
                    $error = 'Could not log this deposit — the error has been logged. Please try again.';
                }
            }
        }

        $deposits = $depositsModel->listForLessor($lessorId);

        $totalLockedAmount = 0;
        $lockedCount = 0;
        $releasedCount = 0;
        foreach ($deposits as $d) {
            if ($d['status'] === 'locked') {
                $totalLockedAmount += $d['amount'];
                $lockedCount++;
            } else {
                $releasedCount++;
            }
        }

        $properties  = (new PropertyModel($this->pdo))->listForLessor($lessorId);
        $tenantsList = $tenantsModel->listWithDeposit($lessorId);

        $releaseId = isset($_GET['release']) ? (int) $_GET['release'] : null;
        $confirming = isset($_GET['confirm']);
        $releasing = null;
        if ($releaseId) {
            foreach ($deposits as $d) {
                if ((int) $d['id'] === $releaseId && $d['status'] === 'locked') { $releasing = $d; break; }
            }
        }

        unset($depositsModel, $tenantsModel);
        return $this->view('lessor/deposits', get_defined_vars());
    }
}
