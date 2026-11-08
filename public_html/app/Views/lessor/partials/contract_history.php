<?php
/**
 * Side card: archived versions of this contract. Expects $history, $tenantId.
 */
if (!$history) {
    return;
}
?>
<div class="card" style="margin-bottom:16px;">
  <p class="side-title">Previous versions</p>
  <?php foreach ($history as $h): $s = $h['snapshot']; ?>
    <div class="kv">
      <span>
        v<?= (int) $h['version'] ?> ·
        <?= $h['status_at_archive'] === 'accepted' ? 'Accepted' : 'Not accepted' ?>
        <?php if (!empty($s['accepted_at'])): ?> (<?= (new DateTime($s['accepted_at']))->format('M j, Y') ?>)<?php endif; ?>
      </span>
      <a href="/lease_contract.php?tenant_id=<?= $tenantId ?>&version=<?= (int) $h['version'] ?>" target="_blank" style="font-size:12px;">View</a>
    </div>
  <?php endforeach; ?>
</div>
