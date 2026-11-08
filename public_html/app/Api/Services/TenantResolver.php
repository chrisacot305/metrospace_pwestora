<?php
namespace Api\Services;

use Api\Repositories\TenantRepository;
use Api\Support\AppConfig;
use PDO;

/**
 * Finds which tenancy (lease) a request is about, and makes sure it belongs
 * to the logged-in lessee. A lessee can never read or write another person's lease.
 */
final class TenantResolver
{
    private TenantRepository $tenants;

    public function __construct(PDO $pdo)
    {
        $this->tenants = new TenantRepository($pdo);
    }

    /** Lease where tenants.lessee_id = this user. */
    public function forLessee(array $lessee, int $tenantId = 0): ?array
    {
        return $this->tenants->findForLessee((int) $lessee['id'], $tenantId);
    }

    /**
     * Used by payments and tickets: also accepts a lease created from an
     * application this lessee submitted.
     * In demo mode only, it also matches by name and finally falls back to any tenant.
     */
    public function forBilling(array $lessee, int $tenantId = 0): ?array
    {
        $demo = AppConfig::demoMode();
        $demoName = $demo ? (string) ($lessee['full_name'] ?? ($lessee['name'] ?? '')) : null;

        $tenant = $this->tenants->findForLesseeIncludingApplications((int) $lessee['id'], $tenantId, $demoName);

        if (!$tenant && $demo) {
            $tenant = $this->tenants->findLatestAny();
        }
        return $tenant;
    }
}
