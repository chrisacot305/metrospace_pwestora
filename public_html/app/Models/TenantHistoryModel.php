<?php
namespace App\Models;

/**
 * TenantHistoryModel — the "History" popup on Lessor → Tenants.
 *
 * Collects every transaction between ONE lessor and each of their tenants
 * (a tenant record = one tenant in one property), newest first:
 *   - rent / other payments   (payments)
 *   - security deposits       (deposits)
 *   - installment requests    (installment_requests)
 *   - violations & fines      (violations)
 *
 * Every query is scoped by lessor_id, so a lessor only ever sees their own records.
 * If one table is missing or different on an older database, that section is skipped
 * (logged) instead of breaking the Tenants page.
 */
class TenantHistoryModel extends Model
{
    private const METHODS = [
        'cash'          => 'Cash',
        'bank_transfer' => 'Bank Transfer / InstaPay',
        'gcash'         => 'GCash',
        'other'         => 'Other',
    ];

    /** @return array<int, array{events: array, summary: array}> keyed by tenant id */
    public function forLessor(int $lessorId): array
    {
        $out = [];
        $add = function (int $tenantId, array $event) use (&$out): void {
            $out[$tenantId]['events'][] = $event;
        };

        // ---- Payments -------------------------------------------------------
        foreach ($this->safe('payments', 'SELECT * FROM payments WHERE lessor_id = ? ORDER BY paid_at DESC, id DESC', [$lessorId]) as $p) {
            $note = (string) ($p['note'] ?? '');
            $isDeclined = str_starts_with($note, '[DECLINED');
            $isPending  = $p['logged_by'] === null && !$isDeclined;

            $target = 'Monthly Rent';
            if (preg_match('/Target:\s*([^|\]]+)/i', $note, $m)) {
                $target = trim($m[1]);
            } elseif (stripos($note, 'Security Deposit Top-Up') !== false) {
                $target = 'Security Deposit Top-Up';
            } elseif (stripos($note, '[AUTO-DEPOSIT]') !== false) {
                $target = 'Paid from security deposit';
            }

            $details = [self::METHODS[$p['method'] ?? ''] ?? ucfirst((string) ($p['method'] ?? ''))];
            if (preg_match('/Ref:\s*([^|\]]+)/i', $note, $m) && trim($m[1]) !== '' && trim($m[1]) !== '—') {
                $details[] = 'Ref: ' . trim($m[1]);
            }
            // Declined notes look like "[DECLINED: reason] ..." (reason was saved HTML-escaped).
            if ($isDeclined && preg_match('/^\[DECLINED:\s*([^\]]*)\]/', $note, $m) && trim($m[1]) !== '') {
                $details[] = 'Reason: ' . html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
            }

            $add((int) $p['tenant_id'], [
                'type'   => 'payment',
                'date'   => (string) $p['paid_at'],
                'title'  => $target,
                'amount' => (float) $p['amount'],
                'status' => $isDeclined ? 'Declined' : ($isPending ? 'Pending review' : 'Verified'),
                'tone'   => $isDeclined ? 'bad' : ($isPending ? 'warn' : 'good'),
                'detail' => implode(' · ', array_filter($details)),
                'counts' => !$isDeclined && !$isPending,
            ]);
        }

        // ---- Security deposits ---------------------------------------------
        foreach ($this->safe('deposits', 'SELECT * FROM deposits WHERE lessor_id = ? ORDER BY id DESC', [$lessorId]) as $d) {
            $released = ($d['status'] ?? '') === 'released';
            $detail = self::METHODS[$d['method'] ?? ''] ?? ucfirst((string) ($d['method'] ?? ''));
            if ($released && !empty($d['released_at'])) {
                $detail .= ' · Released ' . date('M j, Y', strtotime((string) $d['released_at']));
            }
            if (!empty($d['note'])) {
                $detail .= ' · ' . $d['note'];
            }
            $add((int) $d['tenant_id'], [
                'type'   => 'deposit',
                'date'   => (string) ($d['created_at'] ?? ''),
                'title'  => 'Security deposit',
                'amount' => (float) $d['amount'],
                'status' => $released ? 'Released' : 'Held',
                'tone'   => $released ? 'muted' : 'good',
                'detail' => $detail,
                'held'   => !$released,
            ]);
        }

        // ---- Installment requests ------------------------------------------
        foreach ($this->safe('installments', 'SELECT * FROM installment_requests WHERE lessor_id = ? ORDER BY created_at DESC', [$lessorId]) as $r) {
            $st = (string) ($r['status'] ?? 'pending');
            $add((int) $r['tenant_id'], [
                'type'   => 'installment',
                'date'   => (string) ($r['created_at'] ?? ''),
                'title'  => 'Installment request · ' . ($r['plan_label'] ?? ''),
                'amount' => (float) $r['amount'],
                'status' => ucfirst($st),
                'tone'   => $st === 'approved' ? 'good' : ($st === 'pending' ? 'warn' : 'bad'),
                'detail' => (string) ($r['reason'] ?? ''),
            ]);
        }

        // ---- Violations & fines --------------------------------------------
        foreach ($this->safe('violations', 'SELECT * FROM violations WHERE lessor_id = ? ORDER BY issued_at DESC', [$lessorId]) as $v) {
            $fine = (float) ($v['fine_amount'] ?? 0);
            if (!empty($v['is_revoked'])) {
                [$status, $tone] = ['Revoked', 'muted'];
            } elseif (!empty($v['disputed'])) {
                [$status, $tone] = ['Disputed', 'warn'];
            } elseif ($fine > 0 && !empty($v['is_paid'])) {
                [$status, $tone] = ['Fine paid', 'good'];
            } elseif ($fine > 0) {
                [$status, $tone] = ['Fine unpaid', 'bad'];
            } elseif (!empty($v['acknowledged'])) {
                [$status, $tone] = ['Acknowledged', 'good'];
            } else {
                [$status, $tone] = ['Pending Acknowledgment', 'muted'];
            }
            $add((int) $v['tenant_id'], [
                'type'   => 'violation',
                'date'   => (string) ($v['issued_at'] ?? ''),
                'title'  => 'Violation · ' . ($v['category'] ?? '') . ' (Strike ' . (int) ($v['strike'] ?? 0) . ')',
                'amount' => $fine > 0 ? $fine : null,
                'status' => $status,
                'tone'   => $tone,
                'detail' => trim((string) ($v['dispute_reason'] ?? '') !== '' ? 'Dispute: ' . $v['dispute_reason'] : ''),
                'unpaid' => $fine > 0 && empty($v['is_paid']) && empty($v['is_revoked']) ? $fine : 0,
                'strike' => empty($v['is_revoked']) ? 1 : 0,
            ]);
        }

        // ---- Sort + totals per tenant --------------------------------------
        foreach ($out as $tenantId => &$h) {
            usort($h['events'], fn ($a, $b) => strcmp($b['date'], $a['date']));
            $paid = $held = $unpaid = 0.0;
            $pending = $strikes = 0;
            foreach ($h['events'] as $e) {
                if (!empty($e['counts']))                 $paid += $e['amount'];
                if (($e['type'] === 'payment') && $e['tone'] === 'warn') $pending++;
                if (!empty($e['held']))                   $held += $e['amount'];
                $unpaid  += (float) ($e['unpaid'] ?? 0);
                $strikes += (int) ($e['strike'] ?? 0);
            }
            $h['summary'] = [
                'paid'    => $paid,
                'pending' => $pending,
                'held'    => $held,
                'unpaid'  => $unpaid,
                'strikes' => $strikes,
            ];
            // Internal helper fields aren't needed in the browser.
            foreach ($h['events'] as &$e) {
                unset($e['counts'], $e['held'], $e['unpaid'], $e['strike']);
            }
            unset($e);
        }
        unset($h);

        return $out;
    }

    private function safe(string $label, string $sql, array $params): array
    {
        try {
            return $this->all($sql, $params);
        } catch (\Throwable $e) {
            error_log("[TenantHistory:$label] " . $e->getMessage());
            return [];
        }
    }
}
