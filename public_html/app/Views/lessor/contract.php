<?php
/**
 * app/Views/lessor/contract.php — one tenant's negotiated lease contract.
 * Data comes from app/Controllers/Lessor/ContractController.php
 */
use App\Services\ContractService;

$statusColors = [
    'draft'     => 'background:var(--bg); color:var(--ink-500);',
    'finalized' => 'background:var(--warning-soft); color:#9a6412;',
    'accepted'  => 'background:var(--success-soft); color:var(--success);',
    'rejected'  => 'background:#fee2e2; color:#dc2626; border:1px solid #fca5a5;',
];
$money = static fn($v) => number_format((float) $v, 2, '.', '');
?>
<style>
  .contract-grid { display:grid; grid-template-columns:minmax(0, 1fr) 320px; gap:18px; align-items:start; }
  @media (max-width: 1100px) { .contract-grid { grid-template-columns:1fr; } }
  .terms-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:12px; }
  .terms-grid label { display:block; font-size:12px; font-weight:600; margin-bottom:4px; color:var(--ink-900); }
  .terms-grid input { width:100%; box-sizing:border-box; padding:8px 10px; border:1px solid var(--border); border-radius:6px; font:inherit; font-size:13px; }
  .terms-grid .hint { font-size:11px; color:var(--ink-400); margin-top:3px; }
  .kv { display:flex; justify-content:space-between; gap:10px; font-size:13px; padding:5px 0; border-bottom:1px dashed var(--border); }
  .kv:last-child { border-bottom:none; }
  .kv span:first-child { color:var(--ink-500); }
  .side-title { margin:0 0 10px; font-size:12.5px; font-weight:700; color:var(--ink-900); }
  .msg { padding:8px 10px; border-radius:6px; font-size:12px; margin-bottom:6px; }
</style>

<div class="cm-head">
  <div>
    <h2 style="margin:0 0 4px; font-size:20px;">Lease Contract: <?= htmlspecialchars($tenantName) ?></h2>
    <p style="margin:0; font-size:12.5px; color:var(--ink-500);">
      <?= htmlspecialchars($lease['property_name']) ?> · LC-<?= str_pad((string) $tenantId, 4, '0', STR_PAD_LEFT) ?>
      <?php if ($contract): ?>
        · Version <?= (int) $contract['version'] ?>
        <span class="badge" style="<?= $statusColors[$contract['status']] ?? '' ?> margin-left:6px;"><?= ContractService::statusLabel($contract['status']) ?></span>
      <?php endif; ?>
    </p>
  </div>
  <div style="display:flex; gap:8px; align-items:center;">
    <a href="/lease_contract.php?tenant_id=<?= $tenantId ?>" target="_blank" class="btn" style="width:auto; background:var(--bg); border:1px solid var(--border); color:var(--ink-900);">
      <?= $contract ? ($isDraft ? 'Preview' : 'View / Print') : 'View current contract' ?>
    </a>
    <a href="<?= htmlspecialchars($closeUrl) ?>" class="cm-close" title="Close (Esc)" aria-label="Close">&times;</a>
  </div>
</div>

<?php if ($error): ?><div class="error-msg" style="margin-bottom:16px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($notice): ?>
  <div style="background:var(--success-soft); color:var(--success); padding:12px 16px; border-radius:8px; margin-bottom:16px; font-weight:500;">
    <?= htmlspecialchars($notice) ?>
  </div>
<?php endif; ?>

<?php if (!$contract): ?>
  <!-- ======================= START A CONTRACT ======================= -->
  <div class="contract-grid">
    <div class="card">
      <h3 style="margin-top:0; font-size:15px;">Prepare this tenant's contract</h3>
      <p style="font-size:13px; color:var(--ink-500); margin-top:0;">
        Right now this tenant is on the <strong>standard contract</strong>. Pick a template to start from.
        You'll get a draft you can edit to match what you negotiated. The tenant won't see it until you finalize it.
      </p>
      <form method="POST" action="<?= htmlspecialchars($base) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="field">
          <label>Start from template</label>
          <?php if ($buildingTemplateId && $buildingTemplateId === $defaultTemplateId): ?>
            <p style="font-size:11.5px; color:var(--ink-500); margin:0 0 6px;">Pre-selected because it is the template set for <?= htmlspecialchars($lease['property_name']) ?>.</p>
          <?php endif; ?>
          <select name="template_id">
            <option value="0" <?= $defaultTemplateId === 0 ? 'selected' : '' ?>><?= ContractService::BUILTIN_TEMPLATE_NAME ?> (built-in)</option>
            <?php foreach ($myTemplates as $t): ?>
              <option value="<?= (int) $t['id'] ?>" <?= (int) $t['id'] === $defaultTemplateId ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="card" style="background:var(--bg); margin:12px 0;">
          <p class="side-title">Terms that will be pre-filled (you can change them next)</p>
          <div class="kv"><span>Monthly rent</span><strong>₱<?= number_format($suggestedTerms['monthly_rent'], 2) ?></strong></div>
          <div class="kv"><span>Term</span><strong><?= (int) $suggestedTerms['term_months'] ?> months</strong></div>
          <div class="kv"><span>Start date</span><strong><?= (new DateTime($suggestedTerms['start_date']))->format('M j, Y') ?></strong></div>
          <div class="kv"><span>Security deposit</span><strong>₱<?= number_format($suggestedTerms['security_deposit'], 2) ?></strong></div>
          <div class="kv"><span>Advance rent</span><strong>₱<?= number_format($suggestedTerms['advance_rent'], 2) ?></strong></div>
          <div class="kv"><span>Rent due day</span><strong><?= (int) $suggestedTerms['due_day'] ?></strong></div>
        </div>
        <button class="btn btn-primary" type="submit" style="width:100%; padding:10px;">Create Contract Draft</button>
      </form>
      <p style="font-size:12px; color:var(--ink-500); margin-bottom:0;">
        Need a different starting point? <a href="/dashboard.php?page=contract_templates&tab=templates">Manage templates</a>.
      </p>
    </div>

    <div>
      <?php require __DIR__ . '/partials/contract_negotiation.php'; ?>
    </div>
  </div>

<?php elseif ($isDraft): ?>
  <!-- ======================= DRAFT EDITOR ======================= -->
  <div class="contract-grid">
    <div>
      <form method="POST" action="<?= htmlspecialchars($base) ?>" id="contract-form">
        <?= csrf_field() ?>

        <div class="card" style="margin-bottom:16px;">
          <h3 style="margin-top:0; font-size:15px;">Key terms</h3>
          <div class="terms-grid">
            <div><label>Monthly rent (₱)</label><input type="number" step="0.01" min="1" name="monthly_rent" required value="<?= $money($contract['monthly_rent']) ?>"></div>
            <div><label>Term (months)</label><input type="number" min="1" max="120" name="term_months" required value="<?= (int) $contract['term_months'] ?>"></div>
            <div><label>Start date</label><input type="date" name="start_date" required value="<?= htmlspecialchars((string) $contract['start_date']) ?>"></div>
            <div><label>End date</label><input type="date" name="end_date" value="<?= htmlspecialchars((string) $contract['end_date']) ?>"><div class="hint">Blank = start + term</div></div>
            <div><label>Security deposit (₱)</label><input type="number" step="0.01" min="0" name="security_deposit" value="<?= $money($contract['security_deposit']) ?>"></div>
            <div><label>Advance rent (₱)</label><input type="number" step="0.01" min="0" name="advance_rent" value="<?= $money($contract['advance_rent']) ?>"></div>
            <div><label>Rent due day</label><input type="number" min="1" max="31" name="due_day" value="<?= (int) $contract['due_day'] ?>"><div class="hint">Day of the month</div></div>
          </div>
          <p style="font-size:11.5px; color:var(--ink-500); margin:10px 0 0;">
            If you changed the deposit, escalation, or other amounts, update the matching clause wording below too so they agree.
          </p>
        </div>

        <div class="card" style="margin-bottom:16px;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; gap:8px; flex-wrap:wrap;">
            <h3 style="margin:0; font-size:15px;">Clauses</h3>
            <span style="font-size:12px; color:var(--ink-500);">
              From: <strong><?= htmlspecialchars($contract['template_name'] ?: ContractService::BUILTIN_TEMPLATE_NAME) ?></strong>
              <?php if ($originCounts['edited'] || $originCounts['custom']): ?>
                · <?= $originCounts['edited'] ?> edited · <?= $originCounts['custom'] ?> added
              <?php endif; ?>
            </span>
          </div>
          <?php
            $editorClauses = $clauses; $editorReadonly = false; $editorShowOrigin = true; $editorCounterOffers = $counterOffers;
            require __DIR__ . '/partials/clause_editor.php';
          ?>
        </div>

        <div class="card" style="margin-bottom:16px;">
          <h3 style="margin-top:0; font-size:15px;">Special provisions <span style="font-weight:400; color:var(--ink-500); font-size:12px;">(optional)</span></h3>
          <textarea name="special_provisions" rows="4" maxlength="<?= ContractService::MAX_PROVISIONS ?>"
                    style="width:100%; box-sizing:border-box; padding:8px 10px; border:1px solid var(--border); border-radius:6px; font:inherit; font-size:12.5px;"
                    placeholder="Anything specific to this tenant, e.g. rent-free fit-out period, signage rights, parking slot no. 3..."><?= htmlspecialchars((string) $contract['special_provisions']) ?></textarea>
        </div>

        <div class="card" style="position:sticky; bottom:0; z-index:5; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
          <button class="btn" type="submit" name="action" value="save" formnovalidate
                  style="flex:1; min-width:140px; background:var(--bg); border:1px solid var(--border); color:var(--ink-900); padding:10px;">
            Save Draft
          </button>
          <button class="btn btn-primary" type="submit" name="action" value="finalize" style="flex:1; min-width:180px; padding:10px;"
                  onclick="return confirm('Finalize this contract?\n\nIt will be locked and the tenant can review and accept it in the app. Rent, start/end date and due day will also be updated on the tenant record.');">
            Finalize &amp; Send to Tenant
          </button>
        </div>

        <!-- Save as template (submits the same form) -->
        <div class="card" style="margin-top:16px;">
          <p class="side-title">Reuse these clauses</p>
          <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <input type="text" name="new_template_name" maxlength="120" placeholder="New template name"
                   style="flex:1; min-width:180px; padding:8px 10px; border:1px solid var(--border); border-radius:6px; font:inherit; font-size:13px;">
            <button class="btn" type="submit" name="action" value="save_as_template" formnovalidate
                    style="width:auto; background:var(--bg); border:1px solid var(--border); color:var(--ink-900);">
              Save as template
            </button>
          </div>
        </div>
      </form>
    </div>

    <div>
      <div class="card" style="margin-bottom:16px;">
        <p class="side-title">Start over from a template</p>
        <form method="POST" action="<?= htmlspecialchars($base) ?>"
              onsubmit="return confirm('Replace ALL clauses with this template? Unsaved edits and your current clause changes will be lost. Key terms stay as they are.');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="apply_template">
          <select name="template_id" style="width:100%; padding:8px 10px; border:1px solid var(--border); border-radius:6px; font:inherit; font-size:13px; margin-bottom:8px;">
            <option value="0"><?= ContractService::BUILTIN_TEMPLATE_NAME ?> (built-in)</option>
            <?php foreach ($myTemplates as $t): ?>
              <option value="<?= (int) $t['id'] ?>" <?= (int) $t['id'] === (int) $contract['template_id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn" type="submit" style="width:100%; background:var(--bg); border:1px solid var(--border); color:var(--ink-900);">Replace clauses</button>
        </form>
        <?php if ((int) $contract['version'] === 1): ?>
          <form method="POST" action="<?= htmlspecialchars($base) ?>" style="margin-top:8px;"
                onsubmit="return confirm('Discard this draft? The tenant will stay on the standard contract.');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="discard">
            <button class="btn" type="submit" style="width:100%; background:var(--error-soft); color:var(--error); border:none;">Discard draft</button>
          </form>
        <?php endif; ?>
      </div>

      <?php require __DIR__ . '/partials/contract_negotiation.php'; ?>
      <?php require __DIR__ . '/partials/contract_history.php'; ?>
    </div>
  </div>

<?php else: ?>
  <!-- ======================= FINALIZED / ACCEPTED (locked) ======================= -->
  <div class="contract-grid">
    <div>
      <div class="card" style="margin-bottom:16px;">
        <h3 style="margin-top:0; font-size:15px;">Key terms</h3>
        <div class="kv"><span>Monthly rent</span><strong>₱<?= number_format((float) $contract['monthly_rent'], 2) ?></strong></div>
        <div class="kv"><span>Term</span><strong><?= (int) $contract['term_months'] ?> months</strong></div>
        <div class="kv"><span>Start date</span><strong><?= $contract['start_date'] ? (new DateTime($contract['start_date']))->format('M j, Y') : '—' ?></strong></div>
        <?php $end = ContractService::computedEndDate($contract['start_date'], (int) $contract['term_months'], $contract['end_date']); ?>
        <div class="kv"><span>End date</span><strong><?= $end ? (new DateTime($end))->format('M j, Y') : '—' ?></strong></div>
        <div class="kv"><span>Security deposit</span><strong>₱<?= number_format((float) $contract['security_deposit'], 2) ?></strong></div>
        <div class="kv"><span>Advance rent</span><strong>₱<?= number_format((float) $contract['advance_rent'], 2) ?></strong></div>
        <div class="kv"><span>Rent due day</span><strong><?= (int) $contract['due_day'] ?></strong></div>
      </div>

      <div class="card" style="margin-bottom:16px;">
        <h3 style="margin-top:0; font-size:15px;">Clauses</h3>
        <?php
          $editorClauses = $clauses; $editorReadonly = true; $editorShowOrigin = true;
          require __DIR__ . '/partials/clause_editor.php';
        ?>
        <?php if (trim((string) $contract['special_provisions']) !== ''): ?>
          <h4 style="font-size:13px; margin:16px 0 6px;">Special provisions</h4>
          <p style="font-size:12.5px; color:var(--ink-500); white-space:pre-line; margin:0;"><?= htmlspecialchars($contract['special_provisions']) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <div>
      <div class="card" style="margin-bottom:16px;">
        <p class="side-title">Status</p>
        <div class="kv"><span>Finalized</span><strong><?= $contract['finalized_at'] ? (new DateTime($contract['finalized_at']))->format('M j, Y g:i A') : '—' ?></strong></div>
        <div class="kv"><span>Tenant status</span>
          <strong>
            <?php if ($contract['status'] === 'accepted' && $contract['accepted_at']): ?>
              <span style="color:var(--success); font-weight:700;">Accepted (<?= (new DateTime($contract['accepted_at']))->format('M j, Y') ?>)</span>
            <?php elseif ($contract['status'] === 'rejected'): ?>
              <span style="color:var(--error, #dc2626); font-weight:700;">Declined / Revisions Requested</span>
            <?php else: ?>
              <span style="color:#9a6412;">Awaiting Acceptance</span>
            <?php endif; ?>
          </strong>
        </div>

        <?php if ($contract['status'] === 'rejected'): ?>
          <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:12px; margin:14px 0;">
            <strong style="color:#b91c1c; display:flex; align-items:center; gap:6px; font-size:12.5px; margin-bottom:4px;">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
              Tenant Requested Revisions
            </strong>
            <p style="font-size:11.5px; color:var(--ink-700); margin:0 0 6px;">
              The lessee reviewed version <?= (int) $contract['version'] ?> and declined acceptance with the following feedback:
            </p>
            <?php if (!empty($contract['rejection_reason'])): ?>
              <div style="background:white; padding:8px 10px; border-radius:6px; font-size:12px; color:var(--ink-900); border:1px solid #fca5a5; font-style:italic; line-height:1.4;">
                "<?= htmlspecialchars($contract['rejection_reason']) ?>"
              </div>
            <?php endif; ?>
            <?php if (!empty($contract['rejected_at'])): ?>
              <div style="font-size:10.5px; color:var(--ink-500); margin-top:6px;">
                Declined on <?= (new DateTime($contract['rejected_at']))->format('F j, Y \a\t g:i A') ?>
              </div>
            <?php endif; ?>
          </div>
        <?php elseif ($contract['status'] === 'accepted'): ?>
          <div style="background:var(--success-soft); border:1px solid var(--success); border-radius:8px; padding:12px; margin:14px 0;">
            <strong style="color:var(--success); display:flex; align-items:center; gap:6px; font-size:12.5px; margin-bottom:2px;">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
              Agreement Fully Accepted
            </strong>
            <p style="font-size:11.5px; color:var(--ink-700); margin:0;">
              Digitally accepted by the lessee on <?= (new DateTime($contract['accepted_at']))->format('F j, Y \a\t g:i A') ?>.
            </p>
          </div>
        <?php endif; ?>

        <?php if (!$lease['lessee_id']): ?>
          <p style="font-size:11.5px; color:var(--ink-500); margin:8px 0 0;">This is a walk-in tenant without an app account, so print the contract and have it signed on paper.</p>
        <?php endif; ?>
        <form method="POST" action="<?= htmlspecialchars($base) ?>" style="margin-top:12px;"
              onsubmit="return confirm('Open a revision?\n\nThis version is kept in the history, and a new draft (version <?= (int) $contract['version'] + 1 ?>) is created. The tenant will need to accept the new version.');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="revise">
          <button class="btn <?= $contract['status'] === 'rejected' ? 'btn-primary' : '' ?>" type="submit"
                  style="width:100%; <?= $contract['status'] !== 'rejected' ? 'background:var(--bg); border:1px solid var(--border); color:var(--ink-900);' : '' ?>">
            <?= $contract['status'] === 'rejected' ? 'Revise Contract (Version ' . ((int) $contract['version'] + 1) . ')' : 'Revise / Amend' ?>
          </button>
        </form>
      </div>

      <?php require __DIR__ . '/partials/contract_negotiation.php'; ?>
      <?php require __DIR__ . '/partials/contract_history.php'; ?>
    </div>
  </div>
<?php endif; ?>
