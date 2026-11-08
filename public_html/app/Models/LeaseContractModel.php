<?php
namespace App\Models;

/**
 * Tables: lease_contracts (one per tenant) + lease_contract_history (archived versions).
 * Status flow: draft → finalized → accepted; "revise" archives the current version and
 * opens version+1 as a draft.
 */
class LeaseContractModel extends Model
{
    private const TERM_FIELDS = ['monthly_rent', 'term_months', 'start_date', 'end_date', 'security_deposit', 'advance_rent', 'due_day'];

    public function findByTenant(int $tenantId): ?array
    {
        return $this->one('SELECT * FROM lease_contracts WHERE tenant_id = ?', [$tenantId]);
    }

    public function findForLessor(int $tenantId, int $lessorId): ?array
    {
        return $this->one('SELECT * FROM lease_contracts WHERE tenant_id = ? AND lessor_id = ?', [$tenantId, $lessorId]);
    }

    /**
     * Every tenant of the lessor with their contract state (Contracts → Tenant Contracts tab).
     * Tenants without a lease_contracts row are on the standard contract.
     */
    public function overviewForLessor(int $lessorId): array
    {
        return $this->all(
            "SELECT t.id AS tenant_id, t.tenant_name, t.property_id, t.unit_label, t.created_at AS tenant_since,
                    COALESCE(NULLIF(t.business_name, ''), a.business_name, '') AS business_name,
                    COALESCE(lessee.full_name, t.tenant_name) AS display_name,
                    p.name AS property_name,
                    c.id AS contract_id, c.template_id, c.template_name, c.status, c.version,
                    c.monthly_rent, c.finalized_at, c.accepted_at, c.updated_at,
                    a.rent AS application_rent, t.monthly_rent AS tenant_rent,
                    ct.name AS live_template_name
             FROM tenants t
             JOIN properties p ON p.id = t.property_id
             LEFT JOIN users lessee ON lessee.id = t.lessee_id
             LEFT JOIN applications a ON a.id = t.application_id
             LEFT JOIN lease_contracts c ON c.tenant_id = t.id
             LEFT JOIN contract_templates ct ON ct.id = c.template_id AND ct.lessor_id = t.lessor_id
             WHERE t.lessor_id = ?
             ORDER BY p.name ASC, t.tenant_name ASC",
            [$lessorId]
        );
    }

    /** tenant_id => [status, version] for every contract of this lessor (roster badges). */
    public function statusesForLessor(int $lessorId): array
    {
        $out = [];
        foreach ($this->all('SELECT tenant_id, status, version FROM lease_contracts WHERE lessor_id = ?', [$lessorId]) as $r) {
            $out[(int) $r['tenant_id']] = ['status' => $r['status'], 'version' => (int) $r['version']];
        }
        return $out;
    }

    /** $terms: monthly_rent, term_months, start_date, end_date, security_deposit, advance_rent, due_day */
    public function create(int $tenantId, int $lessorId, ?int $templateId, string $templateName, array $terms, string $clausesJson, string $provisions): int
    {
        return $this->insert(
            'INSERT INTO lease_contracts
               (tenant_id, lessor_id, template_id, template_name, monthly_rent, term_months, start_date, end_date,
                security_deposit, advance_rent, due_day, clauses, special_provisions, status, version)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'draft\', 1)',
            array_merge([$tenantId, $lessorId, $templateId, $templateName], $this->termValues($terms), [$clausesJson, $provisions])
        );
    }

    /** Saves a draft. Returns false if the contract is not a draft (finalized contracts are locked). */
    public function saveDraft(int $tenantId, int $lessorId, array $terms, string $clausesJson, string $provisions): bool
    {
        $n = $this->run(
            "UPDATE lease_contracts
             SET monthly_rent = ?, term_months = ?, start_date = ?, end_date = ?, security_deposit = ?, advance_rent = ?, due_day = ?,
                 clauses = ?, special_provisions = ?, updated_at = NOW()
             WHERE tenant_id = ? AND lessor_id = ? AND status = 'draft'",
            array_merge($this->termValues($terms), [$clausesJson, $provisions, $tenantId, $lessorId])
        );
        return $n > 0 || (bool) $this->value("SELECT 1 FROM lease_contracts WHERE tenant_id = ? AND lessor_id = ? AND status = 'draft'", [$tenantId, $lessorId]);
    }

    /** Replaces the clauses of a draft with a (new) template's clauses. */
    public function applyTemplate(int $tenantId, int $lessorId, ?int $templateId, string $templateName, string $clausesJson): void
    {
        $this->run(
            "UPDATE lease_contracts SET template_id = ?, template_name = ?, clauses = ? WHERE tenant_id = ? AND lessor_id = ? AND status = 'draft'",
            [$templateId, $templateName, $clausesJson, $tenantId, $lessorId]
        );
    }

    public function finalize(int $tenantId, int $lessorId): bool
    {
        return $this->run(
            "UPDATE lease_contracts SET status = 'finalized', finalized_at = NOW(), accepted_at = NULL
             WHERE tenant_id = ? AND lessor_id = ? AND status = 'draft'",
            [$tenantId, $lessorId]
        ) > 0;
    }

    /** Archives the current finalized/accepted version and reopens it as a draft (version + 1). */
    public function revise(int $tenantId, int $lessorId): bool
    {
        $row = $this->findForLessor($tenantId, $lessorId);
        if (!$row || $row['status'] === 'draft') {
            return false;
        }
        $this->insert(
            'INSERT INTO lease_contract_history (contract_id, tenant_id, version, snapshot, status_at_archive) VALUES (?, ?, ?, ?, ?)',
            [(int) $row['id'], $tenantId, (int) $row['version'], json_encode($row), $row['status']]
        );
        try {
            $this->run(
                "UPDATE lease_contracts SET status = 'draft', version = version + 1, finalized_at = NULL, accepted_at = NULL, rejected_at = NULL, rejection_reason = NULL WHERE id = ?",
                [(int) $row['id']]
            );
        } catch (\PDOException $e) {
            $this->run(
                "UPDATE lease_contracts SET status = 'draft', version = version + 1, finalized_at = NULL, accepted_at = NULL WHERE id = ?",
                [(int) $row['id']]
            );
        }
        return true;
    }

    /** Deletes a draft that was never finalized (version 1 only), so the tenant goes back to the standard contract. */
    public function discardFirstDraft(int $tenantId, int $lessorId): bool
    {
        return $this->run(
            "DELETE FROM lease_contracts WHERE tenant_id = ? AND lessor_id = ? AND status = 'draft' AND version = 1",
            [$tenantId, $lessorId]
        ) > 0;
    }

    /** Tenant accepts. $version must match, so they can only accept the exact version they saw. */
    public function accept(int $tenantId, int $version): bool
    {
        return $this->run(
            "UPDATE lease_contracts SET status = 'accepted', accepted_at = NOW()
             WHERE tenant_id = ? AND version = ? AND status = 'finalized'",
            [$tenantId, $version]
        ) > 0;
    }

    /** Tenant declines / requests revisions. */
    public function reject(int $tenantId, int $version, string $reason = ''): bool
    {
        try {
            return $this->run(
                "UPDATE lease_contracts SET status = 'rejected', rejected_at = NOW(), rejection_reason = ?
                 WHERE tenant_id = ? AND version = ? AND status = 'finalized'",
                [$reason, $tenantId, $version]
            ) > 0;
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'Unknown column')) {
                try {
                    $this->run('ALTER TABLE lease_contracts ADD COLUMN rejected_at DATETIME NULL');
                } catch (\Throwable $ignore) {}
                try {
                    $this->run('ALTER TABLE lease_contracts ADD COLUMN rejection_reason TEXT NULL');
                } catch (\Throwable $ignore) {}

                return $this->run(
                    "UPDATE lease_contracts SET status = 'rejected', rejected_at = NOW(), rejection_reason = ?
                     WHERE tenant_id = ? AND version = ? AND status = 'finalized'",
                    [$reason, $tenantId, $version]
                ) > 0;
            }
            throw $e;
        }
    }

    /** Newest archived version as a row-like array (clauses still JSON), or null. */
    public function latestSnapshot(int $contractId): ?array
    {
        $json = $this->value('SELECT snapshot FROM lease_contract_history WHERE contract_id = ? ORDER BY version DESC LIMIT 1', [$contractId]);
        $snap = $json ? json_decode($json, true) : null;
        return is_array($snap) ? $snap : null;
    }

    /** Archived versions, newest first: [version, status_at_archive, archived_at, snapshot(array)] */
    public function history(int $contractId): array
    {
        $rows = $this->all(
            'SELECT version, status_at_archive, archived_at, snapshot FROM lease_contract_history WHERE contract_id = ? ORDER BY version DESC',
            [$contractId]
        );
        foreach ($rows as &$r) {
            $r['snapshot'] = json_decode($r['snapshot'], true) ?: [];
        }
        return $rows;
    }

    public function snapshotVersion(int $contractId, int $version): ?array
    {
        $json = $this->value('SELECT snapshot FROM lease_contract_history WHERE contract_id = ? AND version = ?', [$contractId, $version]);
        $snap = $json ? json_decode($json, true) : null;
        return is_array($snap) ? $snap : null;
    }

    private function termValues(array $t): array
    {
        $out = [];
        foreach (self::TERM_FIELDS as $f) {
            $out[] = $t[$f] ?? null;
        }
        return $out;
    }
}
