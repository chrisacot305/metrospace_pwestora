<?php
namespace Api\Repositories;

/** Table: installment_requests */
final class InstallmentRequestRepository extends Repository
{
    public function create(int $lessorId, int $tenantId, string $reason, string $planLabel, float $amount): int
    {
        return $this->insert(
            'INSERT INTO installment_requests (lessor_id, tenant_id, reason, plan_label, amount) VALUES (?, ?, ?, ?, ?)',
            [$lessorId, $tenantId, $reason, $planLabel, $amount]
        );
    }

    public function listForTenant(int $tenantId): array
    {
        return $this->fetchAll(
            'SELECT id, reason, plan_label, amount, status, created_at
             FROM installment_requests WHERE tenant_id = ? ORDER BY created_at DESC',
            [$tenantId]
        );
    }

    /** Deletes the request only if it is pending AND belongs to one of $tenantIds. */
    public function deletePending(int $requestId, array $tenantIds): bool
    {
        if (!$tenantIds) {
            return false;
        }
        return $this->execute(
            'DELETE FROM installment_requests
             WHERE id = ? AND tenant_id IN (' . self::placeholders($tenantIds) . ") AND LOWER(status) = 'pending'",
            array_merge([$requestId], $tenantIds)
        ) > 0;
    }

    /** Status of a request, but only if it belongs to one of $tenantIds (null otherwise). */
    public function statusForTenants(int $requestId, array $tenantIds): ?string
    {
        if (!$tenantIds) {
            return null;
        }
        $status = $this->fetchValue(
            'SELECT status FROM installment_requests WHERE id = ? AND tenant_id IN (' . self::placeholders($tenantIds) . ')',
            array_merge([$requestId], $tenantIds)
        );
        return $status === false ? null : (string) $status;
    }
}
