<?php
/**
 * app/Views/lessor/analytics.php — HTML for the "analytics" screen (was the bottom half of modules/analytics.php).
 * Data comes from app/Controllers/Lessor/AnalyticsController.php
 */
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
