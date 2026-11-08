<?php
namespace App\Services;

use App\Models\LeaseContractModel;
use App\Models\TenantModel;
use PDO;

/**
 * ContractService — everything about "which clauses and terms does this tenant's contract have".
 *
 * A clause is stored as:
 *   { "key": "security_deposit", "label": "Security Deposit", "text": "...", "ref": "Civil Code Art. ...", "base": "<sha1>" }
 *     ref  — legal basis of the clause (printed on the contract and on violation notices)
 *     key  — template clause key (links it to the applicant's counter-offer), or "custom_xxxx"
 *     base — fingerprint of the clause as it came from the template; if label/text no longer
 *            match it, the clause was edited during negotiation.
 *
 * Origins (computed, not stored): template | edited | custom
 */
final class ContractService
{
    public const MAX_CLAUSES        = 60;
    public const MAX_LABEL          = 150;
    public const MAX_TEXT           = 5000;
    public const MAX_REF            = 300;
    public const MAX_PROVISIONS     = 10000;
    public const BUILTIN_TEMPLATE_ID   = 0;
    public const BUILTIN_TEMPLATE_NAME = 'Pwestora Standard';

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_FINALIZED = 'finalized';
    public const STATUS_ACCEPTED  = 'accepted';
    public const STATUS_REJECTED  = 'rejected';

    // ------------------------------------------------------------------ clauses

    public static function fingerprint(string $label, string $text): string
    {
        return sha1(trim($label) . "\n" . trim($text));
    }

    /** The built-in template as a clause list (from app/Config/lease_checklist.php). */
    public static function builtinClauses(): array
    {
        $out = [];
        foreach (LeaseChecklist::items() as $key => $item) {
            $out[] = self::makeClause((string) $key, $item['label'], $item['text'], null, (string) ($item['reference'] ?? ''));
        }
        return $out;
    }

    public static function makeClause(string $key, string $label, string $text, ?string $base = null, string $ref = ''): array
    {
        return [
            'key'   => $key,
            'label' => $label,
            'text'  => $text,
            'ref'   => $ref,
            'base'  => $base ?? self::fingerprint($label, $text),
        ];
    }

    /** Legal basis of a built-in clause key, or '' (used for clauses saved before references existed). */
    public static function builtinReference(string $key): string
    {
        return (string) (LeaseChecklist::items()[$key]['reference'] ?? '');
    }

    public static function newCustomKey(): string
    {
        return 'custom_' . bin2hex(random_bytes(4));
    }

    /** Decodes a clauses JSON column into a clean list. Bad data becomes []. */
    public static function decodeClauses(?string $json): array
    {
        $decoded = $json ? json_decode($json, true) : null;
        return is_array($decoded) ? self::normalize($decoded) : [];
    }

    /** Cleans a clause list: trims, cuts to limits, drops empty clauses, fixes keys. */
    public static function normalize(array $clauses): array
    {
        $out = [];
        $seen = [];
        foreach ($clauses as $c) {
            if (!is_array($c)) {
                continue;
            }
            $label = mb_substr(trim((string) ($c['label'] ?? '')), 0, self::MAX_LABEL);
            $text  = mb_substr(trim((string) ($c['text'] ?? '')), 0, self::MAX_TEXT);
            if ($label === '' && $text === '') {
                continue;
            }
            if ($label === '') {
                $label = 'Clause';
            }
            $key = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($c['key'] ?? '')));
            if ($key === '' || isset($seen[$key])) {
                $key = self::newCustomKey();
            }
            $seen[$key] = true;
            $base = preg_match('/^[a-f0-9]{40}$/', (string) ($c['base'] ?? '')) ? $c['base'] : null;
            // Older clauses have no 'ref', and a built-in clause should never lose its citation:
            // an empty legal basis on a built-in clause falls back to the standard reference.
            $ref = mb_substr(trim((string) ($c['ref'] ?? '')), 0, self::MAX_REF);
            if ($ref === '') {
                $ref = self::builtinReference($key);
            }
            // A custom clause has no template original.
            if (str_starts_with($key, 'custom_')) {
                $base = '';
            }
            $out[] = ['key' => $key, 'label' => $label, 'text' => $text, 'ref' => $ref, 'base' => $base ?? self::fingerprint($label, $text)];
            if (count($out) >= self::MAX_CLAUSES) {
                break;
            }
        }
        return $out;
    }

    /** Reads the parallel clause_key[] / clause_label[] / clause_text[] / clause_base[] form arrays. */
    public static function clausesFromPost(array $post): array
    {
        $keys   = is_array($post['clause_key'] ?? null) ? $post['clause_key'] : [];
        $labels = is_array($post['clause_label'] ?? null) ? $post['clause_label'] : [];
        $texts  = is_array($post['clause_text'] ?? null) ? $post['clause_text'] : [];
        $bases  = is_array($post['clause_base'] ?? null) ? $post['clause_base'] : [];
        $refs   = is_array($post['clause_ref'] ?? null) ? $post['clause_ref'] : [];

        $raw = [];
        foreach (array_values($labels) as $i => $label) {
            $raw[] = [
                'key'   => is_scalar($keys[$i] ?? null) ? (string) $keys[$i] : '',
                'label' => is_scalar($label) ? (string) $label : '',
                'text'  => is_scalar($texts[$i] ?? null) ? (string) $texts[$i] : '',
                'base'  => is_scalar($bases[$i] ?? null) ? (string) $bases[$i] : '',
                'ref'   => is_scalar($refs[$i] ?? null) ? (string) $refs[$i] : '',
            ];
        }
        return self::normalize($raw);
    }

    public static function origin(array $clause): string
    {
        if (str_starts_with($clause['key'], 'custom_') || ($clause['base'] ?? '') === '') {
            return 'custom';
        }
        return self::fingerprint($clause['label'], $clause['text']) === $clause['base'] ? 'template' : 'edited';
    }

    /** Clauses for saving as a TEMPLATE: every clause becomes the new "original". */
    public static function asTemplateClauses(array $clauses): array
    {
        $out = [];
        foreach ($clauses as $c) {
            $key = str_starts_with($c['key'], 'custom_') ? self::slugKey($c['label'], $out) : $c['key'];
            $out[] = self::makeClause($key, $c['label'], $c['text'], null, (string) ($c['ref'] ?? ''));
        }
        return $out;
    }

    private static function slugKey(string $label, array $existing): string
    {
        $slug = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($label)), '_') ?: 'clause';
        $slug = substr($slug, 0, 40);
        $taken = array_column($existing, 'key');
        $key = $slug;
        for ($i = 2; in_array($key, $taken, true); $i++) {
            $key = $slug . '_' . $i;
        }
        return $key;
    }

    // ----------------------------------------------------------- legal basis

    /** Basis of the rent obligation (Term & Rent section) — also a violation category. */
    public const RENT_REFERENCE = 'Civil Code Art. 1657(1) (lessee must pay the rent as stipulated); Art. 1673 (non-payment is a ground for judicial ejectment)';

    /**
     * Laws the contract is built on — printed in the "Legal Basis and References" section.
     * Citations are to Philippine law; the platform template itself is NOT a lawyer-reviewed
     * or government-accredited form (see the disclaimer on the contract).
     */
    public static function legalReferences(): array
    {
        return [
            ['law' => 'Civil Code of the Philippines (Republic Act No. 386), Book IV, Title VIII "Lease", Arts. 1642–1688',
             'use' => 'Main law on the lease of things: obligations of the lessor (Art. 1654) and lessee (Art. 1657), assignment and sublease (Arts. 1649–1650), rescission (Art. 1659) and grounds for ejectment (Art. 1673).'],
            ['law' => 'Civil Code Arts. 1159 and 1306',
             'use' => 'Contracts have the force of law between the parties (Art. 1159). The parties may agree on any terms not contrary to law, morals, good customs, public order or public policy (Art. 1306). This is the basis for negotiated and customized clauses.'],
            ['law' => 'Civil Code Art. 1403(2)(e), the Statute of Frauds',
             'use' => 'A lease for longer than one year must be in writing to be enforceable, which is why the agreed terms are recorded in this document.'],
            ['law' => 'Civil Code Art. 1648',
             'use' => 'A lease of real property that is not recorded in the Registry of Property does not bind third persons. Notarization and registration are recommended for long-term leases.'],
            ['law' => 'Electronic Commerce Act of 2000 (Republic Act No. 8792), Secs. 7–8',
             'use' => 'Electronic documents and electronic signatures have legal recognition. This covers the tenant\'s in-app acceptance of this contract.'],
            ['law' => 'Data Privacy Act of 2012 (Republic Act No. 10173)',
             'use' => 'Governs how the personal information of the parties is collected and processed on the platform.'],
            ['law' => '1997 Rules of Civil Procedure, Rule 70 (Forcible Entry and Unlawful Detainer)',
             'use' => 'Court procedure for ejectment if the lease is violated or the tenant refuses to vacate.'],
            ['law' => 'Rent Control Act of 2009 (Republic Act No. 9653)',
             'use' => 'Applies to residential units only. It does not limit rent or escalation for commercial spaces like this one.'],
        ];
    }

    /** Short fingerprint of the contract content, printed on the document so any copy can be checked against the system. */
    public static function documentFingerprint(int $tenantId, array $c): string
    {
        $payload = json_encode([
            $tenantId, $c['version'], $c['monthly_rent'], $c['term_months'], $c['start_date'], $c['end_date'],
            $c['security_deposit'], $c['advance_rent'], $c['due_day'],
            array_map(fn($x) => [$x['label'], $x['text']], $c['clauses']), $c['special_provisions'],
        ]);
        return strtoupper(substr(hash('sha256', (string) $payload), 0, 16));
    }

    // ------------------------------------------------------------ violations

    /**
     * Violation categories for ONE tenant, taken from the contract the tenant actually agreed to
     * (drafts are ignored — same rule as what the tenant sees). Because this is read fresh every
     * time, finalizing a revised contract changes the categories automatically.
     *
     * Returns ['contract' => [ref, label, status], 'categories' => [ {key, number, label, text, ref}, ... ]]
     * The first category is always the rent obligation from the Term & Rent section.
     */
    public static function violationCategories(PDO $pdo, array $lease): array
    {
        $c = self::effective($pdo, $lease, true);
        $tenantId = (int) $lease['id'];
        $ref = 'LC-' . str_pad((string) $tenantId, 4, '0', STR_PAD_LEFT) . ($c['version'] > 0 ? '-v' . $c['version'] : '');

        $categories = [[
            'key'    => 'rent',
            'number' => 'Term & Rent',
            'label'  => 'Non-payment of Rent',
            'text'   => 'Monthly rent of PHP ' . number_format($c['monthly_rent'], 2)
                      . ($c['due_day'] > 0 ? ', due on or before day ' . $c['due_day'] . ' of each month.' : ', due monthly.'),
            'ref'    => self::RENT_REFERENCE,
        ]];
        foreach ($c['clauses'] as $i => $clause) {
            $categories[] = [
                'key'    => $clause['key'],
                'number' => 'Clause ' . ($i + 1),
                'label'  => $clause['label'],
                'text'   => $clause['text'],
                'ref'    => $clause['ref'] ?? '',
            ];
        }
        if (trim($c['special_provisions']) !== '') {
            $categories[] = [
                'key'    => 'special_provisions',
                'number' => 'Special Provisions',
                'label'  => 'Special Provisions',
                'text'   => $c['special_provisions'],
                'ref'    => 'Civil Code Art. 1306 (stipulation of the parties)',
            ];
        }

        return [
            'contract' => [
                'ref'    => $ref,
                'label'  => $c['source'] === 'standard'
                    ? 'Standard contract (not customized)'
                    : ($c['template_name'] ?: self::BUILTIN_TEMPLATE_NAME) . ', version ' . $c['version'],
                'status' => self::statusLabel($c['status']),
            ],
            'categories' => $categories,
        ];
    }

    // ------------------------------------------------------------- negotiation

    /**
     * Counter-offers from applications.checklist_negotiation, keyed by clause key:
     *   [ 'security_deposit' => ['label' => ..., 'note' => ...], ... ]
     * (Same rules the Applications/Tenants screens already use.)
     */
    public static function counterOffers(?string $negotiationJson): array
    {
        $negotiation = $negotiationJson ? json_decode($negotiationJson, true) : null;
        if (!is_array($negotiation)) {
            return [];
        }
        $items = LeaseChecklist::items();
        $out = [];
        foreach ($negotiation as $key => $status) {
            if ($key === 'messages' || !is_array($status)) {
                continue;
            }
            $a = $status['agreed'] ?? true;
            $agreed = $a === true || $a === 1 || $a === 'true' || $a === '1';
            $note = trim((string) ($status['rebuttal_note'] ?? $status['counter_note'] ?? $status['note'] ?? ''));
            if (!$agreed || $note !== '') {
                $out[(string) $key] = [
                    'label' => $items[$key]['label'] ?? ucwords(str_replace('_', ' ', (string) $key)),
                    'note'  => $note !== '' ? $note : 'Counter-offer proposed (no note).',
                ];
            }
        }
        return $out;
    }

    public static function negotiationMessages(?string $negotiationJson): array
    {
        $negotiation = $negotiationJson ? json_decode($negotiationJson, true) : null;
        return (is_array($negotiation) && is_array($negotiation['messages'] ?? null)) ? $negotiation['messages'] : [];
    }

    // ---------------------------------------------------------- effective view

    /**
     * The contract to SHOW for a tenant.
     *   $forTenant = true  → drafts are hidden; the tenant sees the last finalized version
     *                        (from history while the lessor is revising), else the standard.
     *   $forTenant = false → the lessor sees the current contract, drafts included.
     *
     * $lease is a row from TenantModel::leaseDocument() (used for fallback values).
     * Returns: source (custom|archived|standard), status, version, terms, clauses, ...
     */
    public static function effective(PDO $pdo, array $lease, bool $forTenant): array
    {
        $tenantId = (int) $lease['id'];
        $contracts = new LeaseContractModel($pdo);
        $row = $contracts->findByTenant($tenantId);

        if ($row && (!$forTenant || $row['status'] !== self::STATUS_DRAFT)) {
            return self::fromRow($row, 'custom');
        }
        if ($row && $forTenant) {
            $snap = $contracts->latestSnapshot((int) $row['id']);
            if ($snap) {
                return self::fromRow($snap, 'archived');
            }
        }
        return self::standard($lease);
    }

    /** Fallback: standard clauses + the terms from the application / tenant record. */
    public static function standard(array $lease): array
    {
        $start = $lease['start_date'] ?: substr((string) $lease['created_at'], 0, 10);
        $rent = (float) ($lease['rent'] ?? 0) ?: (float) ($lease['monthly_rent'] ?? 0);
        return [
            'source'             => 'standard',
            'status'             => 'standard',
            'version'            => 0,
            'template_name'      => self::BUILTIN_TEMPLATE_NAME,
            'monthly_rent'       => $rent,
            'term_months'        => (int) ($lease['term_months'] ?? 0),
            'start_date'         => $start,
            'end_date'           => $lease['end_date'] ?? null,
            'security_deposit'   => 0.0,
            'advance_rent'       => 0.0,
            'due_day'            => (int) ($lease['due_day'] ?? 0),
            'clauses'            => self::builtinClauses(),
            'special_provisions' => '',
            'finalized_at'       => null,
            'accepted_at'        => null,
        ];
    }

    public static function fromRow(array $row, string $source): array
    {
        return [
            'source'             => $source,
            'status'             => (string) $row['status'],
            'version'            => (int) $row['version'],
            'template_name'      => $row['template_name'] ?? null,
            'monthly_rent'       => (float) $row['monthly_rent'],
            'term_months'        => (int) $row['term_months'],
            'start_date'         => $row['start_date'] ?: null,
            'end_date'           => $row['end_date'] ?: null,
            'security_deposit'   => (float) $row['security_deposit'],
            'advance_rent'       => (float) $row['advance_rent'],
            'due_day'            => (int) $row['due_day'],
            'clauses'            => is_array($row['clauses']) ? $row['clauses'] : self::decodeClauses($row['clauses']),
            'special_provisions' => (string) ($row['special_provisions'] ?? ''),
            'finalized_at'       => $row['finalized_at'] ?? null,
            'accepted_at'        => $row['accepted_at'] ?? null,
            'rejected_at'        => $row['rejected_at'] ?? null,
            'rejection_reason'   => (string) ($row['rejection_reason'] ?? ''),
        ];
    }

    /** End date: explicit end date, else start + term months. */
    public static function computedEndDate(?string $start, int $termMonths, ?string $end): ?string
    {
        if ($end) {
            return $end;
        }
        if (!$start || $termMonths <= 0) {
            return null;
        }
        return (new \DateTime($start))->modify('+' . $termMonths . ' months')->modify('-1 day')->format('Y-m-d');
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT     => 'Draft',
            self::STATUS_FINALIZED => 'Awaiting tenant acceptance',
            self::STATUS_ACCEPTED  => 'Accepted by tenant',
            self::STATUS_REJECTED  => 'Rejected / Changes Requested',
            default                => 'Standard (not customized)',
        };
    }
}
