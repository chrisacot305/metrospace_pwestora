<?php
/**
 * app/Views/lessor/partials/clause_editor.php — reusable clause list editor.
 * Used by contract_templates.php and contract.php. Must be placed INSIDE a <form>.
 *
 * Expects:
 *   $editorClauses       list of clauses {key,label,text,base}
 *   $editorReadonly      bool
 *   $editorCounterOffers [clauseKey => ['label'=>..,'note'=>..]]  (optional)
 *   $editorShowOrigin    bool — show Edited / Custom badges (contracts only)
 *
 * Posts parallel arrays clause_key[], clause_base[], clause_label[], clause_text[], clause_ref[] in screen order.
 */
use App\Services\ContractService;

$editorCounterOffers = $editorCounterOffers ?? [];
$editorShowOrigin    = $editorShowOrigin ?? false;
$usedOfferKeys       = [];
?>
<style>
  .clause-list { display:flex; flex-direction:column; gap:10px; }
  .clause-card { border:1px solid var(--border); border-radius:10px; background:#fff; padding:12px 14px; }
  .clause-card.is-negotiated { border-left:3px solid var(--warning); }
  .clause-head { display:flex; align-items:center; gap:8px; margin-bottom:8px; }
  .clause-num { font-weight:700; color:var(--ink-500); font-size:13px; min-width:22px; }
  .clause-head input { flex:1; padding:7px 10px; border:1px solid var(--border); border-radius:6px; font:inherit; font-size:13px; font-weight:600; }
  .clause-card textarea { width:100%; box-sizing:border-box; min-height:70px; padding:8px 10px; border:1px solid var(--border); border-radius:6px; font:inherit; font-size:12.5px; line-height:1.5; resize:vertical; }
  .clause-tools { display:flex; gap:4px; }
  .clause-tools button { border:1px solid var(--border); background:var(--bg); border-radius:6px; padding:4px 8px; cursor:pointer; font-size:12px; color:var(--ink-900); }
  .clause-tools button:hover { background:#fff; }
  .clause-tools button.danger { color:var(--error); }
  .clause-badge { font-size:10.5px; font-weight:700; padding:2px 7px; border-radius:20px; white-space:nowrap; }
  .clause-badge.edited { background:var(--accent-soft); color:var(--primary-light); }
  .clause-badge.custom { background:var(--success-soft); color:var(--success); }
  .clause-offer { margin-top:8px; padding:8px 10px; background:var(--warning-soft); border-radius:6px; font-size:12px; color:#7a5212; }
  .clause-ro p { margin:4px 0 0; font-size:12.5px; color:var(--ink-500); line-height:1.55; white-space:pre-line; }
  .clause-ref { display:flex; align-items:center; gap:8px; margin-top:6px; }
  .clause-ref span { font-size:11px; font-weight:700; color:var(--ink-500); white-space:nowrap; text-transform:uppercase; letter-spacing:.03em; }
  .clause-ref input { flex:1; padding:6px 9px; border:1px solid var(--border); border-radius:6px; font:inherit; font-size:11.5px; color:var(--ink-500); background:var(--bg); }
  .clause-ref-ro { margin-top:4px; font-size:11px; color:var(--ink-400); font-style:italic; }
</style>

<?php if ($editorReadonly): ?>
  <div class="clause-list">
    <?php foreach ($editorClauses as $i => $c): ?>
      <div class="clause-card clause-ro">
        <strong style="font-size:13px;"><?= $i + 1 ?>. <?= htmlspecialchars($c['label']) ?></strong>
        <?php if ($editorShowOrigin && ($o = ContractService::origin($c)) !== 'template'): ?>
          <span class="clause-badge <?= $o ?>"><?= $o === 'custom' ? 'Added' : 'Edited' ?></span>
        <?php endif; ?>
        <p><?= htmlspecialchars($c['text']) ?></p>
        <?php if (($c['ref'] ?? '') !== ''): ?><div class="clause-ref-ro">Legal basis: <?= htmlspecialchars($c['ref']) ?></div><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="clause-list" id="clause-list">
    <?php foreach ($editorClauses as $i => $c):
      $origin = ContractService::origin($c);
      $offer  = $editorCounterOffers[$c['key']] ?? null;
      if ($offer) { $usedOfferKeys[$c['key']] = true; }
    ?>
      <div class="clause-card<?= $offer ? ' is-negotiated' : '' ?>">
        <input type="hidden" name="clause_key[]" value="<?= htmlspecialchars($c['key']) ?>">
        <input type="hidden" name="clause_base[]" value="<?= htmlspecialchars($c['base']) ?>">
        <div class="clause-head">
          <span class="clause-num"><?= $i + 1 ?>.</span>
          <input type="text" name="clause_label[]" value="<?= htmlspecialchars($c['label']) ?>" maxlength="<?= ContractService::MAX_LABEL ?>" placeholder="Clause title" required>
          <?php if ($editorShowOrigin): ?>
            <span class="clause-badge edited" data-role="edited-badge" style="<?= $origin === 'edited' ? '' : 'display:none;' ?>">Edited</span>
            <?php if ($origin === 'custom'): ?><span class="clause-badge custom">Added</span><?php endif; ?>
          <?php endif; ?>
          <div class="clause-tools">
            <button type="button" data-act="up" title="Move up">&uarr;</button>
            <button type="button" data-act="down" title="Move down">&darr;</button>
            <button type="button" data-act="remove" class="danger" title="Remove clause">Remove</button>
          </div>
        </div>
        <textarea name="clause_text[]" maxlength="<?= ContractService::MAX_TEXT ?>" placeholder="Clause wording..."><?= htmlspecialchars($c['text']) ?></textarea>
        <div class="clause-ref">
          <span>Legal basis</span>
          <input type="text" name="clause_ref[]" maxlength="<?= ContractService::MAX_REF ?>" value="<?= htmlspecialchars($c['ref'] ?? '') ?>" placeholder="e.g. Civil Code Art. 1306 (stipulation of the parties)">
        </div>
        <?php if ($offer): ?>
          <div class="clause-offer"><strong>Applicant's counter-offer:</strong> <?= htmlspecialchars($offer['note']) ?></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <?php
  // Counter-offers on clauses that are not in this contract (e.g. the template doesn't have them).
  $orphanOffers = array_diff_key($editorCounterOffers, $usedOfferKeys);
  if ($orphanOffers): ?>
    <div class="clause-offer" style="margin-top:10px;">
      <strong>Counter-offers on clauses not in this contract:</strong>
      <?php foreach ($orphanOffers as $o): ?>
        <div style="margin-top:4px;">• <?= htmlspecialchars($o['label']) ?>: “<?= htmlspecialchars($o['note']) ?>”</div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <button type="button" id="clause-add" class="btn" style="width:100%; margin-top:10px; background:var(--bg); border:1px dashed var(--ink-300); color:var(--ink-900); padding:9px;">
    Add clause
  </button>

  <template id="clause-template">
    <div class="clause-card">
      <input type="hidden" name="clause_key[]" value="">
      <input type="hidden" name="clause_base[]" value="">
      <div class="clause-head">
        <span class="clause-num"></span>
        <input type="text" name="clause_label[]" value="" maxlength="<?= ContractService::MAX_LABEL ?>" placeholder="Clause title (e.g. Parking Allocation)" required>
        <?php if ($editorShowOrigin): ?><span class="clause-badge custom">Added</span><?php endif; ?>
        <div class="clause-tools">
          <button type="button" data-act="up" title="Move up">&uarr;</button>
          <button type="button" data-act="down" title="Move down">&darr;</button>
          <button type="button" data-act="remove" class="danger" title="Remove clause">Remove</button>
        </div>
      </div>
      <textarea name="clause_text[]" maxlength="<?= ContractService::MAX_TEXT ?>" placeholder="Clause wording..."></textarea>
      <div class="clause-ref">
        <span>Legal basis</span>
        <input type="text" name="clause_ref[]" maxlength="<?= ContractService::MAX_REF ?>" value="Civil Code Art. 1306 (stipulation of the parties)">
      </div>
    </div>
  </template>

  <script>
  (function () {
    var list = document.getElementById('clause-list');
    var tpl = document.getElementById('clause-template');
    var MAX = <?= ContractService::MAX_CLAUSES ?>;

    function renumber() {
      Array.prototype.forEach.call(list.children, function (card, i) {
        var n = card.querySelector('.clause-num');
        if (n) n.textContent = (i + 1) + '.';
      });
    }

    list.addEventListener('click', function (e) {
      var btn = e.target.closest('button[data-act]');
      if (!btn) return;
      var card = btn.closest('.clause-card');
      var act = btn.getAttribute('data-act');
      if (act === 'up' && card.previousElementSibling) list.insertBefore(card, card.previousElementSibling);
      if (act === 'down' && card.nextElementSibling) list.insertBefore(card.nextElementSibling, card);
      if (act === 'remove') {
        var title = (card.querySelector('input[name="clause_label[]"]').value || 'this clause');
        if (!confirm('Remove "' + title + '" from the contract?')) return;
        card.remove();
      }
      renumber();
    });

    // Show "Edited" as soon as a template clause is changed.
    list.addEventListener('input', function (e) {
      var card = e.target.closest('.clause-card');
      var badge = card && card.querySelector('[data-role="edited-badge"]');
      if (badge) badge.style.display = '';
    });

    document.getElementById('clause-add').addEventListener('click', function () {
      if (list.children.length >= MAX) { alert('A contract can have at most ' + MAX + ' clauses.'); return; }
      var node = tpl.content.firstElementChild.cloneNode(true);
      list.appendChild(node);
      renumber();
      node.querySelector('input[type="text"]').focus();
    });

    renumber();
  })();
  </script>
<?php endif; ?>
