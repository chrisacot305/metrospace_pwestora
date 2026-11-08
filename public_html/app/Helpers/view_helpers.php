<?php
/**
 * app/Helpers/view_helpers.php — small formatting helpers used inside views.
 * (Before, these were declared inside the module files themselves.)
 */

if (!function_exists('pct')) {
    /** Percentage helper used on the dashboards. */
    function pct(int $part, int $total): int
    {
        return $total > 0 ? (int) round(($part / $total) * 100) : 0;
    }
}

if (!function_exists('parse_payment_note')) {
    /** Reads status/target/reference out of a payment note like "[PENDING] Target: Monthly Rent | Ref: 123". */
    function parse_payment_note(?string $note): array
    {
        $res = [
            'target' => 'Monthly Rent',
            'ref'    => '—',
            'raw'    => $note ?? '',
            'status' => 'recorded',
        ];
        if (!$note) return $res;

        if (str_starts_with($note, '[PENDING]')) {
            $res['status'] = 'pending';
        } elseif (str_starts_with($note, '[VERIFIED]')) {
            $res['status'] = 'verified';
        } elseif (str_starts_with($note, '[DECLINED')) {
            $res['status'] = 'declined';
        }

        if (preg_match('/Target:\s*([^|]+)/i', $note, $m)) {
            $res['target'] = trim($m[1]);
        }
        if (preg_match('/Ref:\s*([^|]+)/i', $note, $m)) {
            $res['ref'] = trim($m[1]);
        }
        return $res;
    }
}

if (!function_exists('sla_label')) {
    /** Compute the "Met / Xh left / Overdue" label for a maintenance ticket. */
    function sla_label(array $t, int $lastStage): array
    {
        if ((int) $t['stage'] === $lastStage) {
            return ['Met', true];
        }
        if (!$t['sla_due_at']) {
            return ['—', true];
        }
        $hoursLeft = (strtotime($t['sla_due_at']) - time()) / 3600;
        if ($hoursLeft <= 0) {
            return ['Overdue', false];
        }
        return [ceil($hoursLeft) . 'h left', false];
    }
}

if (!function_exists('get_priority_meta')) {
    /** 3-Color priority basis: Normal (Blue), High (Orange), Urgent (Red) */
    function get_priority_meta(?string $priority): array
    {
        $p = strtolower(trim((string) $priority));
        if ($p === 'urgent') {
            return ['label' => 'Urgent', 'color' => '#DC2626', 'bg' => '#FEF2F2', 'border' => '#FCA5A5'];
        } elseif ($p === 'high') {
            return ['label' => 'High', 'color' => '#D97706', 'bg' => '#FFFBEB', 'border' => '#FDE68A'];
        }
        return ['label' => 'Normal', 'color' => '#2563EB', 'bg' => '#EFF6FF', 'border' => '#BFDBFE'];
    }
}

if (!function_exists('analytics_bar')) {
    /** Renders one metric row: label, value, and a proportional bar. */
    function analytics_bar(string $label, $value, int $max, string $color, string $suffix = ''): string
    {
        $pctWidth = $max > 0 ? min(100, round(($value / $max) * 100)) : 0;
        return '
    <div style="margin-bottom:12px;">
      <div style="display:flex; justify-content:space-between; font-size:11.5px; margin-bottom:4px;">
        <span style="color:var(--ink-500);">' . htmlspecialchars($label) . '</span>
        <span style="font-weight:700; color:var(--ink-900);">' . htmlspecialchars((string) $value) . $suffix . '</span>
      </div>
      <div style="width:100%; height:6px; border-radius:999px; background:var(--border);">
        <div style="height:6px; border-radius:999px; width:' . $pctWidth . '%; background:' . $color . ';"></div>
      </div>
    </div>';
    }
}
