<?php
/**
 * app/Views/lessor/partials/property_contract_field.php
 * Included in Add Property and Edit Property drawers.
 *
 * Variables expected:
 *   $pcfFormPrefix      - string prefix for DOM IDs (e.g. 'add' or 'edit')
 *   $pcfSelectedId      - int currently selected template ID
 *   $contractTemplates  - array of custom templates
 *   $templatesJsonData  - array of full template details & clauses for live preview
 *   $defaultTemplateId  - int default template ID
 */
$prefix = $pcfFormPrefix ?? 'prop';
$curId = (int) ($pcfSelectedId ?? 0);
$selectId = $prefix . '_contract_template_id';
$previewId = $prefix . '_contract_preview';
$countId = $prefix . '_clause_count';
$clausesListId = $prefix . '_clauses_list';
$badgeId = $prefix . '_template_badge';
$descId = $prefix . '_template_desc';
?>
<section class="pm-section" id="<?= ($prefix === 'edit') ? 'pmSecContract' : 'pmSecContractAdd' ?>">
  <header class="pm-section-head">
    <span class="pm-section-icon">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
        <path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>
      </svg>
    </span>
    <div>
      <h4>Lease Contract &amp; Checklist Terms</h4>
      <p>The contract template linked to this listing. Tenants will review and negotiate these clauses during application.</p>
    </div>
  </header>

  <div class="field" style="margin-bottom:12px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; flex-wrap:wrap; gap:8px;">
      <label for="<?= $selectId ?>" style="margin-bottom:0; font-weight:600; font-size:13px;">
        Building Contract Template <span class="pm-req">*</span>
      </label>
      <a href="/dashboard.php?page=contract_templates&amp;edit=new&amp;from=0" target="_blank" rel="noopener"
         style="font-size:12px; color:var(--primary); font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
        Create new template ↗
      </a>
    </div>

    <select name="contract_template_id" id="<?= $selectId ?>"
            style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid var(--border); border-radius:8px; font:inherit; font-size:13.5px; background:#fff; color:var(--ink-900);">
      <option value="0" <?= ($curId === 0) ? 'selected' : '' ?>>
        Pwestora Standard Contract (Recommended &amp; Built-in)
      </option>
      <?php if (!empty($contractTemplates) && is_array($contractTemplates)): ?>
        <optgroup label="Your Custom Templates">
          <?php foreach ($contractTemplates as $t): ?>
            <?php $tid = (int) ($t['id'] ?? 0); if ($tid <= 0) continue; ?>
            <option value="<?= $tid ?>" <?= ($curId === $tid) ? 'selected' : '' ?>>
              <?= htmlspecialchars((string) ($t['name'] ?? 'Template')) ?><?= ((int) ($t['is_default'] ?? 0) === 1 ? ' (Default)' : '') ?>
            </option>
          <?php endforeach; ?>
        </optgroup>
      <?php endif; ?>
    </select>
  </div>

  <div style="background:var(--bg); border:1px solid var(--border); border-radius:10px; padding:12px 14px; margin-bottom:14px; font-size:12.5px; color:var(--ink-700); line-height:1.5;">
    <div style="display:flex; align-items:flex-start; gap:8px;">
      <span style="color:var(--primary); font-size:15px; line-height:1.2;">&#9432;</span>
      <div>
        <strong>Zero-Setup Guarantee:</strong> If you don't have a custom template, <strong>Pwestora Standard</strong> is ready out of the box.
        It includes common Philippine commercial leasing rules (security deposit, 5% annual escalation, 5-day grace period, maintenance boundaries, and 30-day notice).
      </div>
    </div>
  </div>

  <!-- COLLAPSIBLE CLAUSE PREVIEW -->
  <div style="border:1px solid var(--border); border-radius:10px; overflow:hidden; background:#fff;">
    <button type="button" id="<?= $previewId ?>_toggle"
            style="width:100%; display:flex; justify-content:space-between; align-items:center; padding:12px 14px; background:#fff; border:none; cursor:pointer; font:inherit; text-align:left;">
      <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
        <span style="font-weight:700; font-size:13px; color:var(--ink-900);">Contract Preview:</span>
        <span id="<?= $badgeId ?>" class="badge" style="background:var(--accent-soft); color:var(--primary); font-size:11px; font-weight:700;">
          Pwestora Standard
        </span>
        <span id="<?= $countId ?>" style="font-size:12px; color:var(--ink-500); font-weight:500;">
          8 clauses
        </span>
      </div>
      <div style="display:flex; align-items:center; gap:6px; font-size:12px; color:var(--primary); font-weight:600;">
        <span id="<?= $previewId ?>_text">Show Clauses</span>
        <svg id="<?= $previewId ?>_icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="transition:transform .2s ease;">
          <path d="m6 9 6 6 6-6"/>
        </svg>
      </div>
    </button>

    <div id="<?= $previewId ?>_body" style="display:none; padding:0 14px 14px; border-top:1px solid var(--border);">
      <p id="<?= $descId ?>" style="margin:10px 0 12px; font-size:12px; color:var(--ink-500); font-style:italic;">
        Standard commercial lease clauses compliant with Philippine law.
      </p>
      <div id="<?= $clausesListId ?>" style="display:flex; flex-direction:column; gap:8px; max-height:340px; overflow-y:auto; padding-right:4px;">
        <!-- Filled dynamically by JavaScript -->
      </div>
    </div>
  </div>
</section>

<script>
(function() {
  var allData = <?= json_encode(!empty($templatesJsonData) && is_array($templatesJsonData) ? $templatesJsonData : (object)[]) ?>;
  var select = document.getElementById(<?= json_encode($selectId) ?>);
  var toggleBtn = document.getElementById(<?= json_encode($previewId . '_toggle') ?>);
  var body = document.getElementById(<?= json_encode($previewId . '_body') ?>);
  var textSpan = document.getElementById(<?= json_encode($previewId . '_text') ?>);
  var icon = document.getElementById(<?= json_encode($previewId . '_icon') ?>);
  var badge = document.getElementById(<?= json_encode($badgeId) ?>);
  var countSpan = document.getElementById(<?= json_encode($countId) ?>);
  var descP = document.getElementById(<?= json_encode($descId) ?>);
  var list = document.getElementById(<?= json_encode($clausesListId) ?>);

  if (!select || !toggleBtn || !body) return;

  var isOpen = false;
  toggleBtn.addEventListener('click', function(e) {
    e.preventDefault();
    isOpen = !isOpen;
    body.style.display = isOpen ? 'block' : 'none';
    textSpan.textContent = isOpen ? 'Hide Clauses' : 'Show Clauses';
    if (icon) icon.style.transform = isOpen ? 'rotate(180deg)' : 'none';
  });

  function renderTemplate(id) {
    var key = String(id || 0);
    var t = (allData && (allData[key] || allData['0'])) || { name: 'Pwestora Standard', description: '', clauses: [] };
    
    if (badge) badge.textContent = t.name || 'Pwestora Standard';
    var clauses = t.clauses || [];
    if (countSpan) countSpan.textContent = clauses.length + ' clause' + (clauses.length === 1 ? '' : 's');
    if (descP) descP.textContent = t.description || 'Standard commercial lease clauses compliant with Philippine law.';

    if (list) {
      list.innerHTML = '';
      if (!clauses.length) {
        list.innerHTML = '<div style="font-size:12px; color:var(--ink-500); padding:8px 0;">No clauses found in this template.</div>';
        return;
      }
      clauses.forEach(function(c, i) {
        var card = document.createElement('div');
        card.style.cssText = 'padding:10px 12px; background:var(--bg); border:1px solid var(--border); border-radius:8px; font-size:12.5px;';
        
        var isDeposit = (c.key === 'security_deposit');
        var headHtml = '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px; flex-wrap:wrap; gap:6px;">' +
          '<strong style="color:var(--ink-900); font-size:12.5px;">' + (i + 1) + '. ' + escapeHtml(c.label || 'Clause') + '</strong>';
        if (isDeposit) {
          headHtml += '<span class="badge" style="background:var(--success-soft); color:var(--success); font-size:10.5px; font-weight:700;">Recommended Default</span>';
        }
        headHtml += '</div>';

        var bodyHtml = '<div style="color:var(--ink-700); font-size:12px; margin-bottom:4px; line-height:1.45;">' + escapeHtml(c.text || '') + '</div>';
        var refHtml = '';
        if (c.ref) {
          refHtml = '<div style="font-size:11px; color:var(--ink-400); line-height:1.35;"><strong>Legal Basis:</strong> ' + escapeHtml(c.ref) + '</div>';
        }

        card.innerHTML = headHtml + bodyHtml + refHtml;
        list.appendChild(card);
      });
    }
  }

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  select.addEventListener('change', function() {
    renderTemplate(this.value);
  });

  renderTemplate(select.value);
})();
</script>
