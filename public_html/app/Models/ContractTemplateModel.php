<?php
namespace App\Models;

/** Table: contract_templates (a lessor's reusable clause sets) */
class ContractTemplateModel extends Model
{
    public function listForLessor(int $lessorId): array
    {
        return $this->all(
            'SELECT * FROM contract_templates WHERE lessor_id = ? ORDER BY is_default DESC, name ASC',
            [$lessorId]
        );
    }

    public function findForLessor(int $id, int $lessorId): ?array
    {
        return $this->one('SELECT * FROM contract_templates WHERE id = ? AND lessor_id = ?', [$id, $lessorId]);
    }

    public function defaultForLessor(int $lessorId): ?array
    {
        return $this->one('SELECT * FROM contract_templates WHERE lessor_id = ? AND is_default = 1 LIMIT 1', [$lessorId]);
    }

    public function create(int $lessorId, string $name, string $description, string $clausesJson): int
    {
        return $this->insert(
            'INSERT INTO contract_templates (lessor_id, name, description, clauses) VALUES (?, ?, ?, ?)',
            [$lessorId, $name, $description, $clausesJson]
        );
    }

    public function update(int $id, int $lessorId, string $name, string $description, string $clausesJson): void
    {
        $this->run(
            'UPDATE contract_templates SET name = ?, description = ?, clauses = ? WHERE id = ? AND lessor_id = ?',
            [$name, $description, $clausesJson, $id, $lessorId]
        );
    }

    public function delete(int $id, int $lessorId): void
    {
        $this->run('DELETE FROM contract_template_properties WHERE template_id = ? AND lessor_id = ?', [$id, $lessorId]);
        $this->run('DELETE FROM contract_templates WHERE id = ? AND lessor_id = ?', [$id, $lessorId]);
    }

    // ------------------------------------------------------------ buildings

    /** property_id => template_id for this lessor. */
    public function buildingAssignments(int $lessorId): array
    {
        $out = [];
        foreach ($this->all('SELECT property_id, template_id FROM contract_template_properties WHERE lessor_id = ?', [$lessorId]) as $r) {
            $out[(int) $r['property_id']] = (int) $r['template_id'];
        }
        return $out;
    }

    /**
     * Makes $templateId the template for exactly these buildings.
     * Buildings ticked here are moved away from any other template (one template per building).
     * Only buildings owned by the lessor are accepted.
     */
    public function setBuildings(int $templateId, int $lessorId, array $propertyIds): void
    {
        $this->run('DELETE FROM contract_template_properties WHERE template_id = ? AND lessor_id = ?', [$templateId, $lessorId]);
        foreach (array_unique(array_map('intval', $propertyIds)) as $pid) {
            if ($pid <= 0 || $this->value('SELECT 1 FROM properties WHERE id = ? AND lessor_id = ?', [$pid, $lessorId]) === false) {
                continue;
            }
            $this->run(
                'INSERT INTO contract_template_properties (property_id, template_id, lessor_id) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE template_id = VALUES(template_id), lessor_id = VALUES(lessor_id)',
                [$pid, $templateId, $lessorId]
            );
        }
    }

    /** Sets one building's template; $templateId = 0 removes it (building uses your default). */
    public function assignBuilding(int $propertyId, int $templateId, int $lessorId): void
    {
        if ($this->value('SELECT 1 FROM properties WHERE id = ? AND lessor_id = ?', [$propertyId, $lessorId]) === false) {
            return;
        }
        if ($templateId <= 0) {
            $this->run('DELETE FROM contract_template_properties WHERE property_id = ? AND lessor_id = ?', [$propertyId, $lessorId]);
            return;
        }
        if (!$this->findForLessor($templateId, $lessorId)) {
            return;
        }
        $this->run(
            'INSERT INTO contract_template_properties (property_id, template_id, lessor_id) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE template_id = VALUES(template_id), lessor_id = VALUES(lessor_id)',
            [$propertyId, $templateId, $lessorId]
        );
    }

    /**
     * The template a new contract in this building should start from:
     * building's template → lessor's default → 0 (built-in standard).
     */
    public function templateIdForProperty(int $propertyId, int $lessorId): int
    {
        $id = $this->value(
            'SELECT ctp.template_id FROM contract_template_properties ctp
             JOIN contract_templates ct ON ct.id = ctp.template_id AND ct.lessor_id = ctp.lessor_id
             WHERE ctp.property_id = ? AND ctp.lessor_id = ?',
            [$propertyId, $lessorId]
        );
        if ($id !== false) {
            return (int) $id;
        }
        $default = $this->defaultForLessor($lessorId);
        return $default ? (int) $default['id'] : 0;
    }

    /** Makes one template the lessor's default; $id = 0 → back to the built-in standard. */
    public function setDefault(int $id, int $lessorId): void
    {
        $this->run('UPDATE contract_templates SET is_default = 0 WHERE lessor_id = ?', [$lessorId]);
        if ($id > 0) {
            $this->run('UPDATE contract_templates SET is_default = 1 WHERE id = ? AND lessor_id = ?', [$id, $lessorId]);
        }
    }

    /** How many contracts were started from each template (for the list screen). */
    public function usageCounts(int $lessorId): array
    {
        $rows = $this->all(
            'SELECT template_id, COUNT(*) AS n FROM lease_contracts WHERE lessor_id = ? AND template_id IS NOT NULL GROUP BY template_id',
            [$lessorId]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['template_id']] = (int) $r['n'];
        }
        return $out;
    }
}
