<?php
namespace App\Controllers\Lessor;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Validator;
use App\Models\ContractTemplateModel;
use App\Models\LeaseContractModel;
use App\Models\MessageModel;
use App\Models\NotifyModel;
use App\Models\TenantModel;
use App\Services\ContractService;

/**
 * Lessor → Tenant Contract — shown as a MODAL on the Contracts page:
 *   /dashboard.php?page=contract_templates&tenant=ID
 * ContractTemplatesController calls handle() and renders the result inside the modal,
 * so the sidebar keeps "Contracts" highlighted. Old links (?page=contract&tenant_id=ID)
 * are redirected there.
 *
 *   No contract yet  → pick a template, terms are pre-filled from the application  (action=create)
 *   Draft            → edit terms, clauses, special provisions                       (save / finalize /
 *                      apply_template / save_as_template / discard)
 *   Finalized        → locked; tenant can accept it in the app                       (revise)
 *   Accepted         → locked; "revise" starts version+1 and archives this one        (revise)
 */
final class ContractController extends Controller
{
    public function handle(array $user): array
    {
        $lessorId = (int) $user['id'];
        $tenantId = (int) ($_GET['tenant'] ?? $_GET['tenant_id'] ?? 0);
        $base = '/dashboard.php?page=contract_templates&tenant=' . $tenantId;
        $closeUrl = '/dashboard.php?page=contract_templates';

        // Old URL → open the modal on the Contracts page instead.
        if (($_GET['page'] ?? '') === 'contract' && !$this->isPost()) {
            $this->redirect($base);
        }

        $tenants   = new TenantModel($this->pdo);
        $contracts = new LeaseContractModel($this->pdo);
        $templates = new ContractTemplateModel($this->pdo);

        $lease = $tenants->leaseDocument($tenantId);
        if (!$lease || (int) $lease['lessor_id'] !== $lessorId) {
            return ['inline_error' => "<div class='error-msg'>Tenant not found or access denied.</div>"];
        }

        $error = '';
        $notice = match ($_GET['msg'] ?? '') {
            'created'   => 'Contract draft created. Adjust anything you negotiated, then finalize it.',
            'saved'     => 'Draft saved.',
            'finalized' => 'Contract finalized. The tenant can now review and accept it in the Pwestora app.',
            'revised'   => 'A new draft version was opened. The previous version is kept in the history below.',
            'template'  => 'Clauses replaced with the selected template.',
            'tplsaved'  => 'These clauses were saved as a new template.',
            'discarded' => 'Draft discarded. The tenant is back on the standard contract.',
            default     => '',
        };

        $contract = $contracts->findForLessor($tenantId, $lessorId);
        $myTemplates = $templates->listForLessor($lessorId);

        if ($this->isPost()) {
            Csrf::check();
            $action = (string) ($_POST['action'] ?? '');

            // ---------------------------------------------------------- create
            if ($action === 'create' && !$contract) {
                [$tplId, $tplName, $clauses] = $this->resolveTemplate((int) ($_POST['template_id'] ?? 0), $lessorId, $templates);
                $contracts->create($tenantId, $lessorId, $tplId, $tplName, $this->defaultTerms($lease),
                    json_encode($clauses, JSON_UNESCAPED_UNICODE), '');
                audit_log($this->pdo, $lessorId, 'Started lease contract', $lease['tenant_name']);
                $this->redirect($base . '&msg=created');
            }

            // ------------------------------------------------- draft actions
            if ($contract && $contract['status'] === ContractService::STATUS_DRAFT) {
                if (in_array($action, ['save', 'finalize', 'save_as_template'], true)) {
                    $clauses = ContractService::clausesFromPost($_POST);
                    $provisions = mb_substr(trim((string) ($_POST['special_provisions'] ?? '')), 0, ContractService::MAX_PROVISIONS);
                    [$terms, $error] = $this->termsFromPost($_POST);

                    if (!$error && $action === 'finalize' && !$clauses) {
                        $error = 'Add at least one clause before finalizing.';
                    }
                    if (!$error) {
                        $contracts->saveDraft($tenantId, $lessorId, $terms, json_encode($clauses, JSON_UNESCAPED_UNICODE), $provisions);

                        if ($action === 'finalize') {
                            $contracts->beginTransaction();
                            try {
                                $contracts->finalize($tenantId, $lessorId);
                                $tenants->syncFromContract(
                                    $tenantId, $lessorId, (float) $terms['monthly_rent'], $terms['start_date'],
                                    ContractService::computedEndDate($terms['start_date'], (int) $terms['term_months'], $terms['end_date']),
                                    (int) $terms['due_day']
                                );
                                audit_log($this->pdo, $lessorId, 'Finalized lease contract v' . (int) $contract['version'], $lease['tenant_name']);
                                $contracts->commit();
                            } catch (\Throwable $e) {
                                $contracts->rollBack();
                                throw $e;
                            }

                            // Dispatch notifications to the lessee
                            try {
                                $ver = (int) ($contract['version'] ?? 1);
                                $propName = $lease['property_name'] ?? 'Commercial Space';
                                $rentFormatted = number_format((float) $terms['monthly_rent'], 2);
                                $termMonths = (int) $terms['term_months'];
                                $dueDay = (int) $terms['due_day'];
                                $dueDaySuffix = match ($dueDay) {
                                    1, 21, 31 => 'st',
                                    2, 22 => 'nd',
                                    3, 23 => 'rd',
                                    default => 'th',
                                };

                                // 1. Official notice in messages thread
                                $messages = new MessageModel($this->pdo);
                                $threadId = $messages->findOrCreateThread($lessorId, $tenantId);

                                $msgBody = "📋 OFFICIAL NOTICE: LEASE CONTRACT FINALIZED (Version {$ver})\n\n"
                                    . "Your lessor has finalized your customized lease contract for {$propName}.\n\n"
                                    . "• Monthly Rent: ₱{$rentFormatted}\n"
                                    . "• Lease Term: {$termMonths} Months\n"
                                    . "• Rent Payment Due: Every {$dueDay}{$dueDaySuffix} of each month\n"
                                    . (!empty($terms['start_date']) ? "• Lease Start Date: {$terms['start_date']}\n" : "")
                                    . (count($clauses) > 0 ? "• Agreed Clauses: " . count($clauses) . " terms\n" : "")
                                    . (!empty($provisions) ? "\nSpecial Provisions Included:\n\"" . mb_strimwidth($provisions, 0, 250, '...') . "\"\n" : "")
                                    . "\nAction Required: Please open your Pwestora app under Lease Agreement to review the finalized clauses and accept the agreement.";

                                $messages->addFromLessor($threadId, $msgBody, 'Contract Notice');

                                // 2. Log in notifications_log if lessee has a registered user account
                                $lesseeId = (int) ($lease['lessee_id'] ?? 0);
                                if ($lesseeId > 0) {
                                    $notify = new NotifyModel($this->pdo);
                                    $nid = $notify->logNotification(
                                        $lessorId,
                                        'tenant',
                                        'Contract Finalized',
                                        "Your lease contract for {$propName} (Version {$ver}) has been finalized and is ready for your review and acceptance."
                                    );
                                    $notify->addRecipients($nid, [$lesseeId]);
                                }

                                // 3. SMS Notification
                                $phone = $tenants->lesseePhone($tenantId);
                                if (!empty($phone)) {
                                    if (!function_exists('send_sms')) {
                                        @require_once __DIR__ . '/../../Services/Sms.php';
                                    }
                                    if (function_exists('send_sms')) {
                                        send_sms($phone, "[Pwestora] Your lessor has finalized your lease agreement for {$propName} (v{$ver}). Please review and accept the contract in your Pwestora app.");
                                    }
                                }
                            } catch (\Throwable $notifErr) {
                                error_log('Contract finalization notification failed: ' . $notifErr->getMessage());
                            }

                            $this->redirect($base . '&msg=finalized');
                        }

                        if ($action === 'save_as_template') {
                            $name = mb_substr(trim((string) ($_POST['new_template_name'] ?? '')), 0, 120);
                            if ($name === '') {
                                $error = 'Type a name for the new template.';
                            } elseif (!$clauses) {
                                $error = 'There are no clauses to save.';
                            } else {
                                $templates->create($lessorId, $name, 'Saved from ' . $lease['tenant_name'] . "'s contract",
                                    json_encode(ContractService::asTemplateClauses($clauses), JSON_UNESCAPED_UNICODE));
                                audit_log($this->pdo, $lessorId, 'Created contract template from contract', $name);
                                $this->redirect($base . '&msg=tplsaved');
                            }
                        } else {
                            $this->redirect($base . '&msg=saved');
                        }
                    }
                    // Validation failed → show the editor again with what was typed.
                    $contract = array_merge($contract, $terms, [
                        'clauses' => json_encode($clauses), 'special_provisions' => $provisions,
                    ]);
                } elseif ($action === 'apply_template') {
                    [$tplId, $tplName, $clauses] = $this->resolveTemplate((int) ($_POST['template_id'] ?? 0), $lessorId, $templates);
                    $contracts->applyTemplate($tenantId, $lessorId, $tplId, $tplName, json_encode($clauses, JSON_UNESCAPED_UNICODE));
                    $this->redirect($base . '&msg=template');
                } elseif ($action === 'discard') {
                    if ($contracts->discardFirstDraft($tenantId, $lessorId)) {
                        audit_log($this->pdo, $lessorId, 'Discarded lease contract draft', $lease['tenant_name']);
                    }
                    $this->redirect($base . '&msg=discarded');
                }
            }

            // -------------------------------------------------------- revise
            if ($action === 'revise' && $contract && $contract['status'] !== ContractService::STATUS_DRAFT) {
                $contracts->revise($tenantId, $lessorId);
                audit_log($this->pdo, $lessorId, 'Opened revision of lease contract (v' . ((int) $contract['version'] + 1) . ')', $lease['tenant_name']);

                // Notify lessee that contract revision was opened
                try {
                    $newVer = (int) $contract['version'] + 1;
                    $propName = $lease['property_name'] ?? 'Commercial Space';

                    $messages = new MessageModel($this->pdo);
                    $threadId = $messages->findOrCreateThread($lessorId, $tenantId);
                    $revMsg = "📝 NOTICE: CONTRACT REVISION IN PROGRESS (Draft v{$newVer})\n\n"
                        . "Your lessor has opened a revision for your lease contract at {$propName}. "
                        . "Terms and clauses are currently being updated. You will receive an official notification once the revised contract is finalized for your review.";
                    $messages->addFromLessor($threadId, $revMsg, 'Contract Revision Notice');

                    $lesseeId = (int) ($lease['lessee_id'] ?? 0);
                    if ($lesseeId > 0) {
                        $notify = new NotifyModel($this->pdo);
                        $nid = $notify->logNotification(
                            $lessorId,
                            'tenant',
                            'Contract Revision Opened',
                            "A revision has been opened for your lease contract at {$propName} (v{$newVer}). You will be notified once it is finalized."
                        );
                        $notify->addRecipients($nid, [$lesseeId]);
                    }
                } catch (\Throwable $notifErr) {
                    error_log('Contract revision notification failed: ' . $notifErr->getMessage());
                }

                $this->redirect($base . '&msg=revised');
            }
        }

        // ------------------------------------------------------------- view data
        $counterOffers = ContractService::counterOffers($lease['checklist_negotiation'] ?? null);
        $messages = ContractService::negotiationMessages($lease['checklist_negotiation'] ?? null);
        if (!$messages && !empty($lease['lessor_rebuttal'])) {
            $messages[] = ['sender' => 'lessor', 'sender_name' => 'You', 'message' => $lease['lessor_rebuttal'], 'sent_at' => $lease['rebuttal_at']];
        }

        // Building's template → lessor's default → built-in standard.
        $defaultTemplateId = $templates->templateIdForProperty((int) $lease['property_id'], $lessorId);
        $buildingTemplateId = $templates->buildingAssignments($lessorId)[(int) $lease['property_id']] ?? 0;
        $suggestedTerms = $this->defaultTerms($lease);

        $clauses = $contract ? ContractService::decodeClauses($contract['clauses']) : [];
        $history = $contract ? $contracts->history((int) $contract['id']) : [];
        $isDraft = $contract && $contract['status'] === ContractService::STATUS_DRAFT;
        $tenantName = $lease['tenant_full_name'] ?: $lease['tenant_name'];
        $originCounts = ['edited' => 0, 'custom' => 0];
        foreach ($clauses as $c) {
            $o = ContractService::origin($c);
            if (isset($originCounts[$o])) $originCounts[$o]++;
        }

        unset($tenants, $contracts, $templates);
        return $this->view('lessor/contract', get_defined_vars());
    }

    /** [template_id|null, template_name, clauses] — id 0 or unknown → built-in standard. */
    private function resolveTemplate(int $id, int $lessorId, ContractTemplateModel $templates): array
    {
        if ($id > 0 && ($t = $templates->findForLessor($id, $lessorId))) {
            $clauses = ContractService::decodeClauses($t['clauses']);
            if ($clauses) {
                return [(int) $t['id'], $t['name'], $clauses];
            }
        }
        return [null, ContractService::BUILTIN_TEMPLATE_NAME, ContractService::builtinClauses()];
    }

    /** Starting terms, taken from the application and tenant record. */
    private function defaultTerms(array $lease): array
    {
        $rent  = (float) ($lease['rent'] ?? 0) ?: (float) ($lease['monthly_rent'] ?? 0);
        $start = $lease['start_date'] ?: substr((string) $lease['created_at'], 0, 10);
        $due   = (int) ($lease['due_day'] ?? 0);
        return [
            'monthly_rent'     => $rent,
            'term_months'      => (int) ($lease['term_months'] ?? 12) ?: 12,
            'start_date'       => $start,
            'end_date'         => $lease['end_date'] ?: null,
            'security_deposit' => round($rent * 2, 2),   // matches the standard 2-month deposit clause
            'advance_rent'     => round($rent, 2),       // 1 month advance (common practice) — editable
            'due_day'          => ($due >= 1 && $due <= 31) ? $due : (int) date('j', strtotime($start)),
        ];
    }

    /** [terms, error] from the editor form. */
    private function termsFromPost(array $p): array
    {
        $terms = [
            'monthly_rent'     => Validator::money($p, 'monthly_rent'),
            'term_months'      => Validator::int($p, 'term_months'),
            'start_date'       => Validator::date(is_string($p['start_date'] ?? null) ? $p['start_date'] : null),
            'end_date'         => Validator::date(is_string($p['end_date'] ?? null) ? $p['end_date'] : null),
            'security_deposit' => Validator::money($p, 'security_deposit'),
            'advance_rent'     => Validator::money($p, 'advance_rent'),
            'due_day'          => Validator::int($p, 'due_day', 1),
        ];
        $error = '';
        if ($terms['monthly_rent'] <= 0) {
            $error = 'Monthly rent must be more than zero.';
        } elseif ($terms['term_months'] < 1 || $terms['term_months'] > 120) {
            $error = 'Lease term must be between 1 and 120 months.';
        } elseif (!$terms['start_date']) {
            $error = 'Please enter a valid start date.';
        } elseif ($terms['end_date'] && $terms['end_date'] < $terms['start_date']) {
            $error = 'End date cannot be before the start date.';
        } elseif ($terms['due_day'] < 1 || $terms['due_day'] > 31) {
            $error = 'Rent due day must be between 1 and 31.';
        } elseif ($terms['security_deposit'] < 0 || $terms['advance_rent'] < 0) {
            $error = 'Deposit and advance cannot be negative.';
        }
        return [$terms, $error];
    }
}
