<?php
namespace App\Controllers\Lessor;

use App\Core\Controller;
use App\Core\Csrf;
use App\Models\ContractTemplateModel;
use App\Models\LeaseContractModel;
use App\Models\PropertyModel;
use App\Services\ContractService;

/**
 * Lessor → Contracts   /dashboard.php?page=contract_templates
 *
 *   &tab=contracts (default)     every tenant's contract — filter by building / status / tenant, sort
 *   &tab=templates               your templates — filter by building, set default, assign buildings
 *   &edit=new[&from=ID]          new template (optionally copied from another; from=0 → built-in)
 *   &edit=ID                     edit one of your templates (incl. which buildings use it)
 *   &edit=0                      view the built-in "Pwestora Standard" (read-only)
 *   &tenant=ID                   one tenant's contract (ContractController) — opens as a modal
 *
 * Editors open as MODALS over the list, so the sidebar keeps "Contracts" highlighted.
 *
 * Which template a NEW contract starts from: building's template → your default → built-in.
 */
final class ContractTemplatesController extends Controller
{
    private const BASE = '/dashboard.php?page=contract_templates';

    public function handle(array $user): array
    {
        $lessorId   = (int) $user['id'];
        $templates  = new ContractTemplateModel($this->pdo);
        $properties = (new PropertyModel($this->pdo))->listForLessor($lessorId);
        $tab = ($_GET['tab'] ?? 'contracts') === 'templates' ? 'templates' : 'contracts';
        $error = '';

        // ---- Tenant contract modal: ContractController does all the work (it may redirect after a POST).
        $contractModal = null;
        if (isset($_GET['tenant'])) {
            $result = (new ContractController($this->pdo))->handle($user);
            if (isset($result['inline_error'])) {
                $error = 'Tenant not found or access denied.';
            } else {
                $contractModal = $result['data'];
            }
            $tab = 'contracts';
        }

        $notice = $contractModal ? '' : match ($_GET['msg'] ?? '') {
            'saved'   => 'Template saved.',
            'deleted' => 'Template deleted. Contracts already made from it are not affected.',
            'building' => 'Building template updated. New contracts in that building will start from it.',
            'default' => 'Default template updated. New contracts will start from it (unless the building has its own template).',
            default   => '',
        };

        if ($this->isPost() && !isset($_GET['tenant'])) {
            Csrf::check();
            $action = (string) ($_POST['action'] ?? '');
            $id = (int) ($_POST['template_id'] ?? 0);

            if ($action === 'save') {
                $name      = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 120);
                $desc      = mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 255);
                $clauses   = ContractService::asTemplateClauses(ContractService::clausesFromPost($_POST));
                $buildings = is_array($_POST['buildings'] ?? null) ? $_POST['buildings'] : [];

                if ($name === '') {
                    $error = 'Please give the template a name.';
                } elseif (!$clauses) {
                    $error = 'A template needs at least one clause.';
                } elseif ($id > 0 && !$templates->findForLessor($id, $lessorId)) {
                    $error = 'Template not found.';
                } else {
                    $json = json_encode($clauses, JSON_UNESCAPED_UNICODE);
                    $templates->beginTransaction();
                    try {
                        if ($id > 0) {
                            $templates->update($id, $lessorId, $name, $desc, $json);
                            audit_log($this->pdo, $lessorId, 'Updated contract template', $name);
                        } else {
                            $id = $templates->create($lessorId, $name, $desc, $json);
                            audit_log($this->pdo, $lessorId, 'Created contract template', $name);
                        }
                        $templates->setBuildings($id, $lessorId, $buildings);
                        $templates->commit();
                    } catch (\Throwable $e) {
                        $templates->rollBack();
                        throw $e;
                    }
                    $this->redirect(self::BASE . '&tab=templates&msg=saved');
                }
                // Validation failed → show the editor again with what was typed.
                $editing = [
                    'id' => $id, 'name' => $name, 'description' => $desc, 'readonly' => false,
                    'clauses' => ContractService::clausesFromPost($_POST),
                    'buildings' => array_map('intval', $buildings),
                ];
            } elseif ($action === 'delete' && $id > 0) {
                $t = $templates->findForLessor($id, $lessorId);
                if ($t) {
                    $templates->delete($id, $lessorId);
                    audit_log($this->pdo, $lessorId, 'Deleted contract template', $t['name']);
                }
                $this->redirect(self::BASE . '&tab=templates&msg=deleted');
            } elseif ($action === 'assign_building') {
                $pid = (int) ($_POST['property_id'] ?? 0);
                $templates->assignBuilding($pid, $id, $lessorId);
                audit_log($this->pdo, $lessorId, 'Set building contract template', 'property ' . $pid . ' → template ' . ($id ?: 'default'));
                $this->redirect(self::BASE . '&tab=templates&msg=building');
            } elseif ($action === 'set_default') {
                if ($id === 0 || $templates->findForLessor($id, $lessorId)) {
                    $templates->setDefault($id, $lessorId);
                }
                $this->redirect(self::BASE . '&tab=templates&msg=default');
            }
        }

        // ------------------------------------------------------------- shared data
        $list        = $templates->listForLessor($lessorId);
        $usage       = $templates->usageCounts($lessorId);
        $assignments = $templates->buildingAssignments($lessorId);        // property_id => template_id
        $hasCustomDefault = (bool) array_filter($list, fn($t) => (int) $t['is_default'] === 1);
        $defaultName = ContractService::BUILTIN_TEMPLATE_NAME;
        foreach ($list as $t) {
            if ((int) $t['is_default'] === 1) $defaultName = $t['name'];
        }
        $builtinClauses = ContractService::builtinClauses();

        $templateNames = [];
        foreach ($list as $t) {
            $templateNames[(int) $t['id']] = $t['name'];
        }
        $propertyNames = [];
        foreach ($properties as $p) {
            $propertyNames[(int) $p['id']] = $p['name'];
        }
        // template_id => [property names]
        $buildingsByTemplate = [];
        foreach ($assignments as $pid => $tid) {
            if (isset($propertyNames[$pid])) {
                $buildingsByTemplate[$tid][] = ['id' => $pid, 'name' => $propertyNames[$pid]];
            }
        }

        // -------------------------------------------------------- template editor
        if (!isset($editing) && isset($_GET['edit'])) {
            $edit = (string) $_GET['edit'];
            if ($edit === 'new') {
                $from = isset($_GET['from']) ? (int) $_GET['from'] : -1;
                $clauses = [];
                $name = '';
                if ($from === 0) {
                    $clauses = $builtinClauses;
                    $name = 'Copy of ' . ContractService::BUILTIN_TEMPLATE_NAME;
                } elseif ($from > 0 && ($src = $templates->findForLessor($from, $lessorId))) {
                    $clauses = ContractService::decodeClauses($src['clauses']);
                    $name = 'Copy of ' . $src['name'];
                }
                $preBuilding = (int) ($_GET['building'] ?? 0);
                $editing = ['id' => 0, 'name' => $name, 'description' => '', 'clauses' => $clauses, 'readonly' => false,
                            'buildings' => isset($propertyNames[$preBuilding]) ? [$preBuilding] : []];
            } elseif ($edit === '0') {
                $editing = ['id' => 0, 'name' => ContractService::BUILTIN_TEMPLATE_NAME,
                            'description' => 'Built-in common Philippine commercial-lease clauses.',
                            'clauses' => $builtinClauses, 'readonly' => true, 'buildings' => []];
            } elseif (($t = $templates->findForLessor((int) $edit, $lessorId))) {
                $editing = ['id' => (int) $t['id'], 'name' => $t['name'], 'description' => (string) $t['description'],
                            'clauses' => ContractService::decodeClauses($t['clauses']), 'readonly' => false,
                            'buildings' => array_keys(array_filter($assignments, fn($tid) => $tid === (int) $t['id']))];
            } else {
                $error = 'Template not found.';
            }
        }
        $editing = $editing ?? null;
        if ($editing) {
            $tab = 'templates';   // the editor modal opens over the Templates tab
        }

        // ------------------------------------------------------ tenant contracts tab
        $contractRows = [];
        $statusCounts = ['standard' => 0, 'draft' => 0, 'finalized' => 0, 'accepted' => 0];
        $tenantsPerBuilding = [];
        $usage[0] = 0; // contracts started from the built-in template (stored with template_id NULL)
        {
            foreach ((new LeaseContractModel($this->pdo))->overviewForLessor($lessorId) as $r) {
                $tenantsPerBuilding[(int) $r['property_id']] = ($tenantsPerBuilding[(int) $r['property_id']] ?? 0) + 1;
                if ($r['contract_id'] && $r['template_id'] === null) {
                    $usage[0]++;
                }
                $status = $r['status'] ?: 'standard';
                $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;

                $tplId = $r['contract_id'] ? (int) ($r['template_id'] ?? 0) : null;
                // Template that a new contract for this tenant WOULD use (for "standard" rows).
                $wouldUse = $assignments[(int) $r['property_id']] ?? null;

                $r['status_key']    = $status;
                $r['template_label'] = $r['contract_id']
                    ? ($r['template_name'] ?: ContractService::BUILTIN_TEMPLATE_NAME)
                    : ContractService::BUILTIN_TEMPLATE_NAME . ' (not customized)';
                // Link to edit the template this contract came from (only if it still exists).
                $r['template_edit_id'] = $tplId === null ? null
                    : ($tplId > 0 ? ($r['live_template_name'] !== null ? $tplId : null) : 0);
                $r['building_template'] = $wouldUse !== null ? ($templateNames[$wouldUse] ?? null) : null;
                $r['rent_display'] = (float) ($r['monthly_rent'] ?? 0) ?: ((float) ($r['application_rent'] ?? 0) ?: (float) ($r['tenant_rent'] ?? 0));
                $r['sort_updated'] = $r['updated_at'] ?: $r['tenant_since'];
                $contractRows[] = $r;
            }
        }
        $presetBuilding = (int) ($_GET['building'] ?? 0);
        $presetStatus   = in_array($_GET['status'] ?? '', ['standard', 'draft', 'finalized', 'accepted'], true) ? $_GET['status'] : '';

        unset($templates);
        return $this->view('lessor/contract_templates', get_defined_vars());
    }
}
