<?php
/**
 * Side card: what the applicant asked for during the application (reference while drafting).
 * Expects $counterOffers, $messages, $lease.
 */
?>
<div class="card" style="margin-bottom:16px;">
  <p class="side-title">Negotiation reference</p>
  <?php if (!$counterOffers && !$messages): ?>
    <p style="font-size:12px; color:var(--ink-500); margin:0;">The applicant agreed to all standard clauses with no counter-offers.</p>
  <?php endif; ?>

  <?php if ($counterOffers): ?>
    <p style="font-size:12px; color:var(--ink-500); margin:0 0 6px;">Counter-offers from the application (also shown on the matching clauses):</p>
    <?php foreach ($counterOffers as $co): ?>
      <div class="msg" style="background:var(--warning-soft); color:#7a5212;">
        <strong><?= htmlspecialchars($co['label']) ?>:</strong> <?= htmlspecialchars($co['note']) ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($messages): ?>
    <p style="font-size:12px; color:var(--ink-500); margin:10px 0 6px;">Conversation:</p>
    <div style="max-height:340px; overflow-y:auto;">
      <?php foreach ($messages as $m): $isLessor = ($m['sender'] ?? '') === 'lessor'; ?>
        <div class="msg" style="background:<?= $isLessor ? '#f0f7ff' : 'var(--bg)' ?>; border:1px solid <?= $isLessor ? '#cce3ff' : 'var(--border)' ?>;">
          <div style="display:flex; justify-content:space-between; gap:6px; margin-bottom:2px;">
            <strong style="font-size:11.5px; color:<?= $isLessor ? '#0b57d0' : 'var(--ink-900)' ?>;"><?= $isLessor ? 'You' : htmlspecialchars($m['sender_name'] ?? 'Applicant') ?></strong>
            <span style="font-size:10.5px; color:var(--ink-500);"><?= !empty($m['sent_at']) ? (new DateTime($m['sent_at']))->format('M j, g:i A') : '' ?></span>
          </div>
          <?= nl2br(htmlspecialchars($m['message'] ?? '')) ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
