<?php
namespace Api\Formatters;

use Api\Support\AppConfig;

/**
 * Turns raw database rows into the JSON the app expects.
 * MySQL/PDO returns DECIMAL and INT columns as strings — these cast them so the
 * JSON has real numbers (68000.0, not "68000.00").
 */
final class Formatter
{
    public static function property(array $r, array $savedIds = []): array
    {
        $r['building_name'] = $r['building_name'] ?? $r['name'];
        $r['unit_number']   = $r['unit_number'] ?? null;
        $r['cover_photo']   = !empty($r['cover_photo']) ? AppConfig::publicUrl($r['cover_photo']) : null;
        $r['latitude']      = $r['latitude'] !== null ? (float) $r['latitude'] : null;
        $r['longitude']     = $r['longitude'] !== null ? (float) $r['longitude'] : null;
        $r['asking_rent']   = $r['asking_rent'] !== null ? (float) $r['asking_rent'] : null;
        $r['total_units']   = isset($r['total_units']) ? (int) $r['total_units'] : 1;
        $r['available_units'] = isset($r['available_units']) ? (int) $r['available_units'] : 1;
        $r['saved']         = in_array($r['id'], $savedIds, true);
        return $r;
    }

    public static function propertyDetail(array $p, array $photoPaths, bool $saved): array
    {
        $p['latitude']        = $p['latitude'] !== null ? (float) $p['latitude'] : null;
        $p['longitude']       = $p['longitude'] !== null ? (float) $p['longitude'] : null;
        $p['asking_rent']     = $p['asking_rent'] !== null ? (float) $p['asking_rent'] : null;
        $p['total_units']     = isset($p['total_units']) ? (int) $p['total_units'] : 1;
        $p['available_units'] = isset($p['available_units']) ? (int) $p['available_units'] : 1;
        $p['photos']          = array_map([AppConfig::class, 'publicUrl'], $photoPaths);
        $p['saved']       = $saved;
        return $p;
    }

    public static function application(array $a): array
    {
        $a['rent']        = (float) $a['rent'];
        $a['term_months'] = (int) $a['term_months'];

        if (isset($a['checklist_negotiation']) && is_string($a['checklist_negotiation'])) {
            $decoded = json_decode($a['checklist_negotiation'], true);
            if (is_array($decoded)) {
                $a['checklist_negotiation'] = $decoded;
            }
        }
        $messages = $a['checklist_negotiation']['messages'] ?? [];
        $a['negotiation_messages'] = is_array($messages) ? $messages : [];
        return $a;
    }

    public static function payment(array $p): array
    {
        $p['id']     = (int) $p['id'];
        $p['amount'] = (float) $p['amount'];

        $note = is_string($p['note'] ?? null) ? $p['note'] : '';
        $isPending  = ($p['logged_by'] === null
            || str_contains(strtoupper($note), '[PENDING]')
            || stripos($note, 'pending verification') !== false);
        $isDeclined = stripos($note, '[DECLINED') !== false;
        $p['status'] = $isDeclined ? 'declined' : ($isPending ? 'pending' : 'approved');

        $p['reference_no'] = preg_match('/Ref:\s*([^|\r\n]+)/i', $note, $m) ? trim($m[1]) : '';
        $p['target']       = preg_match('/Target:\s*([^|\r\n]+)/i', $note, $m) ? trim($m[1]) : '';
        return $p;
    }

    public static function ticket(array $t): array
    {
        $t['stage'] = (int) $t['stage'];
        $photos = [];
        if (!empty($t['photos'])) {
            $decoded = json_decode($t['photos'], true);
            $photos = is_array($decoded) ? $decoded : [$t['photos']];
        }
        $t['photos'] = $photos;
        return $t;
    }

    public static function violation(array $v): array
    {
        $v['id']           = (int) $v['id'];
        $v['tenant_id']    = (int) $v['tenant_id'];
        $v['lessor_id']    = (int) $v['lessor_id'];
        $v['strike']       = (int) $v['strike'];
        $v['acknowledged'] = (bool) $v['acknowledged'];
        return $v;
    }

    public static function installmentRequest(array $r): array
    {
        $r['id']     = (int) $r['id'];
        $r['amount'] = (float) $r['amount'];
        return $r;
    }

    public static function lesseeSummary(array $user): array
    {
        return [
            'id'        => (int) $user['id'],
            'full_name' => $user['full_name'] ?? ($user['name'] ?? ''),
            'email'     => $user['email'] ?? '',
            'phone'     => $user['phone'] ?? '',
        ];
    }
}
