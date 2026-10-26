<?php
/**
 * modules/analytics.php  (Lessor → "Analytics")
 * Same 3-card visual structure as the original prototype, but every number
 * here is a real SQL aggregate against this lessor's own data — not demo data.
 */
$lessorId = $user['id'];
$since30 = date('Y-m-d H:i:s', strtotime('-30 days'));

function pct(int $part, int $total): int {
    return $total > 0 ? (int) round(($part / $total) * 100) : 0;
}

// ---- Rent Concessions (Installments) ----
$stmt = $pdo->prepare('SELECT COUNT(*) FROM installment_requests WHERE lessor_id = ?'); $stmt->execute([$lessorId]); $irTotal = (int) $stmt->fetchColumn();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM installment_requests WHERE lessor_id = ? AND created_at >= ?'); $stmt->execute([$lessorId, $since30]); $ir30d = (int) $stmt->fetchColumn();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM installment_requests WHERE lessor_id = ? AND status = "approved"'); $stmt->execute([$lessorId]); $irApproved = (int) $stmt->fetchColumn();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM installment_requests WHERE lessor_id = ? AND status = "pending"'); $stmt->execute([$lessorId]); $irPending = (int) $stmt->fetchColumn();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM installment_requests WHERE lessor_id = ? AND status = "rejected"'); $stmt->execute([$lessorId]); $irRejected = (int) $stmt->fetchColumn();
$irApprovalRate = pct($irApproved, $irTotal);

// ---- Facility Maintenance ----
$stmt = $pdo->prepare('SELECT COUNT(*) FROM maintenance_tickets WHERE lessor_id = ?'); $stmt->execute([$lessorId]); $mtTotal = (int) $stmt->fetchColumn();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM maintenance_tickets WHERE lessor_id = ? AND created_at >= ?'); $stmt->execute([$lessorId, $since30]); $mt30d = (int) $stmt->fetchColumn();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM maintenance_tickets WHERE lessor_id = ? AND stage = 7'); $stmt->execute([$lessorId]); $mtCompleted = (int) $stmt->fetchColumn();
$mtCompletionRate = pct($mtCompleted, $mtTotal);
$stmt = $pdo->prepare('SELECT COUNT(*) FROM maintenance_tickets WHERE lessor_id = ? AND stage < 7'); $stmt->execute([$lessorId]); $mtOpen = (int) $stmt->fetchColumn();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM maintenance_tickets WHERE lessor_id = ? AND stage < 7 AND sla_due_at IS NOT NULL AND sla_due_at < NOW()'); $stmt->execute([$lessorId]); $mtOverdue = (int) $stmt->fetchColumn();
$mtOnTrackRate = pct($mtOpen - $mtOverdue, $mtOpen);

// ---- Conflict Resolution (Violations) ----
$stmt = $pdo->prepare('SELECT COUNT(*) FROM violations WHERE lessor_id = ?'); $stmt->execute([$lessorId]); $vTotal = (int) $stmt->fetchColumn();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM violations WHERE lessor_id = ? AND issued_at >= ?'); $stmt->execute([$lessorId, $since30]); $v30d = (int) $stmt->fetchColumn();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM violations WHERE lessor_id = ? AND strike = 1'); $stmt->execute([$lessorId]); $vStrike1 = (int) $stmt->fetchColumn();
$vResolvedRate = pct($vStrike1, $vTotal);
$stmt = $pdo->prepare('SELECT COUNT(DISTINCT tenant_id) FROM violations WHERE lessor_id = ? AND strike >= 2'); $stmt->execute([$lessorId]); $vStrike2plus = (int) $stmt->fetchColumn();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM violations WHERE lessor_id = ? AND strike >= 3'); $stmt->execute([$lessorId]); $vEvictions = (int) $stmt->fetchColumn();

/** Renders one metric row: label, value, and a proportional bar. */
function analytics_bar(string $label, $value, int $max, string $color, string $suffix = ''): string {
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
?>
<h2 style="margin:0 0 4px;">Analytics</h2>

<div class="card-grid">
  <div class="card">
    <p style="font-size:13px; font-weight:800; color:var(--primary); margin:0 0 2px;">Rent Concessions</p>
    <p style="font-size:11px; color:var(--ink-500); margin:0 0 16px;"><?= $irTotal ?> installment request<?= $irTotal === 1 ? '' : 's' ?> on record all-time</p>
    <?= analytics_bar('Installment requests (30d)', $ir30d, 20, 'var(--accent)') ?>
    <?= analytics_bar('Approval rate', $irApprovalRate, 100, 'var(--success)', '%') ?>
    <?= analytics_bar('Pending requests', $irPending, 10, 'var(--primary)') ?>
    <?= analytics_bar('Rejected requests', $irRejected, 10, 'var(--error)') ?>
  </div>

  <div class="card">
    <p style="font-size:13px; font-weight:800; color:var(--primary); margin:0 0 2px;">Facility Maintenance</p>
    <p style="font-size:11px; color:var(--ink-500); margin:0 0 16px;"><?= $mtTotal ?> ticket<?= $mtTotal === 1 ? '' : 's' ?> on record all-time</p>
    <?= analytics_bar('Total tickets (30d)', $mt30d, 30, 'var(--accent)') ?>
    <?= analytics_bar('Completion rate', $mtCompletionRate, 100, 'var(--success)', '%') ?>
    <?= analytics_bar('Open tickets on track', $mtOnTrackRate, 100, 'var(--primary)', '%') ?>
    <?= analytics_bar('Currently overdue', $mtOverdue, 10, 'var(--error)') ?>
  </div>

  <div class="card">
    <p style="font-size:13px; font-weight:800; color:var(--primary); margin:0 0 2px;">Conflict Resolution</p>
    <p style="font-size:11px; color:var(--ink-500); margin:0 0 16px;"><?= $vTotal ?> violation<?= $vTotal === 1 ? '' : 's' ?> on record all-time</p>
    <?= analytics_bar('Violations issued (30d)', $v30d, 20, 'var(--warning)') ?>
    <?= analytics_bar('Resolved at Strike 1', $vResolvedRate, 100, 'var(--success)', '%') ?>
    <?= analytics_bar('Tenants at Strike 2+', $vStrike2plus, 10, 'var(--primary)') ?>
    <?= analytics_bar('Notices of eviction generated', $vEvictions, 10, 'var(--error)') ?>
  </div>
</div>
