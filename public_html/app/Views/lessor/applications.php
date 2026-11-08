<?php
/**
 * app/Views/lessor/applications.php — HTML for the "applications" screen (was the bottom half of modules/applications.php).
 * Data comes from app/Controllers/Lessor/ApplicationsController.php
 */
?>

<div style="margin-bottom:20px;">
  <h2 style="margin:0 0 4px;">Tenant Applications</h2>
</div>

<?php if ($error && !$loggingApp && !$reviewing): ?>
  <div class="error-msg"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card" style="padding:0; overflow:hidden; margin-bottom:28px;">
  
  <!-- FILTER BAR & LOG BUTTON -->
  <div style="display:flex; gap:10px; flex-wrap:nowrap; align-items:center; padding:14px 16px; border-bottom:1px solid var(--border); overflow-x:auto;">
    
    <div style="flex:1; min-width:220px; position:relative;">
      <i class="bi bi-search" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--ink-400); font-size:13px;"></i>
      <input id="app-search" type="search" placeholder="Search applicant, business, or property..."
             autocomplete="off"
             style="width:100%; box-sizing:border-box; padding:8px 12px 8px 34px; border:1px solid var(--border); border-radius:8px; font:inherit; font-size:13px; background:var(--bg); color:var(--ink-900);">
    </div>

    <a href="/dashboard.php?page=applications&log=1" class="btn btn-primary" style="width:auto; display:inline-flex; align-items:center; gap:6px; padding:6px 14px; font-size:12px; flex-shrink:0;">
      <i class="bi bi-plus-lg"></i> Log Application
    </a>

    <select id="app-status-filter"
            style="padding:8px 12px; border:1px solid var(--border); border-radius:8px; font:inherit; font-size:13px; background:#fff; color:var(--ink-900); min-width:140px; flex-shrink:0;">
      <option value="">All statuses</option>
      <option value="pending">Pending</option>
      <option value="approved">Approved</option>
      <option value="rejected">Rejected</option>
    </select>
    <span id="app-filter-count" style="font-size:12.5px; color:var(--ink-500); white-space:nowrap; flex-shrink:0;"></span>
    
  </div>
  <!-- END FILTER BAR -->

  <div class="table-scroll">
  <table>
    <thead><tr><th>Applicant</th><th>Property</th><th>Term</th><th>Rent</th><th>Documents</th><th>Submitted</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php if (!$applications): ?>
        <tr><td colspan="8" style="text-align:center; color:var(--ink-500);">No applications yet.</td></tr>
      <?php else: foreach ($applications as $a): 
          $appDocs = 0;
          if (!empty($a['valid_id_path'])) $appDocs++;
          if (!empty($a['sec_dti_path'])) $appDocs++;
          if (!empty($a['business_permit_path'])) $appDocs++;
          if (!empty($a['bir_cert_path'])) $appDocs++;
          
          // Generate hidden search text for this row
          $searchBlob = strtolower(trim(implode(' ', array_filter([
              $a['tenant_name'],
              $a['business_name'] ?? '',
              $a['property_name'] ?? '',
              $a['status']
          ]))));
      ?>
        <tr class="app-row" data-status="<?= htmlspecialchars($a['status']) ?>" data-search="<?= htmlspecialchars($searchBlob) ?>">
          <td style="font-weight:600;">
            <?= htmlspecialchars($a['tenant_name']) ?>
            <?php if ($a['business_name']): ?>
              <div style="font-size:11.5px; color:var(--ink-500); font-weight:400;"><?= htmlspecialchars($a['business_name']) ?></div>
            <?php endif; ?>
          </td>
          <td style="color:var(--ink-500);"><?= htmlspecialchars($a['property_name']) ?></td>
          <td style="color:var(--ink-500);"><?= (int) $a['term_months'] ?> mo</td>
          <td style="color:var(--ink-500);">₱<?= number_format($a['rent'], 0) ?></td>
          <td>
            <?php if ($appDocs > 0): ?>
              <span class="badge" style="background:#e6f4ea; color:#137333; font-weight:600;">
                <i class="bi bi-file-earmark-check"></i> <?= $appDocs ?>/4 Docs
              </span>
            <?php else: ?>
              <span style="font-size:11.5px; color:var(--ink-400);">None</span>
            <?php endif; ?>
          </td>
          <td style="color:var(--ink-500);"><?= (new DateTime($a['submitted_at']))->format('M j') ?></td>
          <td>
            <?php if ($a['status'] === 'pending'): ?>
              <span class="badge badge-pending">Pending</span>
            <?php elseif ($a['status'] === 'approved'): ?>
              <span class="badge badge-approved">Approved</span>
            <?php else: ?>
              <span class="badge badge-rejected">Rejected</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($a['status'] === 'pending'): ?>
              <a class="btn btn-primary" style="width:auto; padding:6px 14px; display:inline-flex; font-size:12px;"
                 href="/dashboard.php?page=applications&review=<?= $a['id'] ?>">Review</a>
            <?php elseif ($a['status'] === 'approved' && $a['tenant_id']): ?>
              <a class="btn" style="width:auto; background:var(--bg); color:var(--ink-900); padding:6px 14px; display:inline-flex; border:1px solid var(--border); font-size:12px;"
                 href="/dashboard.php?page=contract&tenant_id=<?= $a['tenant_id'] ?>">
                Contract
              </a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
        
        <tr id="app-empty-filter" style="display:none;">
          <td colspan="8" style="text-align:center; color:var(--ink-500);">No applications match your search or filter.</td>
        </tr>
    </tbody>
  </table>
  </div>

  <!-- CENTERED PAGINATION BAR (NO INFO TEXT) -->
  <?php if ($applications): ?>
  <div id="app-pagination-bar" style="display:flex; justify-content:center; align-items:center; padding:12px 16px; border-top:1px solid var(--border); background:var(--surface);">
    <div id="app-page-controls" style="display:flex; gap:6px; align-items:center; justify-content:center;"></div>
  </div>
  <?php endif; ?>
</div>

<?php if ($applications): ?>
<script>
(function () {
  var search = document.getElementById('app-search');
  var status = document.getElementById('app-status-filter');
  var count = document.getElementById('app-filter-count');
  var empty = document.getElementById('app-empty-filter');
  var pageControls = document.getElementById('app-page-controls');
  var paginationBar = document.getElementById('app-pagination-bar');
  var rows = Array.prototype.slice.call(document.querySelectorAll('.app-row'));
  var total = rows.length;

  var currentPage = 1;
  var perPage = 5; // Change this number to show more or fewer applications per page

  function renderPagination(totalMatching, totalPages) {
    if (!paginationBar) return;
    
    if (totalMatching === 0) {
      paginationBar.style.display = 'none';
      return;
    }
    paginationBar.style.display = 'flex';

    if (!pageControls) return;
    pageControls.innerHTML = '';

    // Previous Button
    var prevBtn = document.createElement('button');
    prevBtn.type = 'button';
    prevBtn.innerHTML = '<i class="bi bi-chevron-left"></i> Prev';
    prevBtn.disabled = currentPage === 1;
    prevBtn.style.cssText = 'padding:5px 10px; font-size:12px; border:1px solid var(--border); border-radius:6px; background:' + (currentPage === 1 ? 'var(--bg)' : '#fff') + '; color:' + (currentPage === 1 ? 'var(--ink-300)' : 'var(--ink-900)') + '; cursor:' + (currentPage === 1 ? 'not-allowed' : 'pointer') + '; font-family:inherit; font-weight:500;';
    prevBtn.addEventListener('click', function () {
      if (currentPage > 1) {
        currentPage--;
        updateTable();
      }
    });
    pageControls.appendChild(prevBtn);

    // Page Number Buttons
    for (var p = 1; p <= totalPages; p++) {
      (function (pageNum) {
        var pageBtn = document.createElement('button');
        pageBtn.type = 'button';
        pageBtn.textContent = pageNum;
        var isActive = pageNum === currentPage;
        pageBtn.style.cssText = 'padding:5px 10px; font-size:12px; border:1px solid ' + (isActive ? 'var(--primary)' : 'var(--border)') + '; border-radius:6px; background:' + (isActive ? 'var(--primary)' : '#fff') + '; color:' + (isActive ? '#fff' : 'var(--ink-900)') + '; cursor:pointer; font-weight:' + (isActive ? '700' : '500') + '; font-family:inherit; min-width:30px;';
        pageBtn.addEventListener('click', function () {
          currentPage = pageNum;
          updateTable();
        });
        pageControls.appendChild(pageBtn);
      })(p);
    }

    // Next Button
    var nextBtn = document.createElement('button');
    nextBtn.type = 'button';
    nextBtn.innerHTML = 'Next <i class="bi bi-chevron-right"></i>';
    nextBtn.disabled = currentPage === totalPages;
    nextBtn.style.cssText = 'padding:5px 10px; font-size:12px; border:1px solid var(--border); border-radius:6px; background:' + (currentPage === totalPages ? 'var(--bg)' : '#fff') + '; color:' + (currentPage === totalPages ? 'var(--ink-300)' : 'var(--ink-900)') + '; cursor:' + (currentPage === totalPages ? 'not-allowed' : 'pointer') + '; font-family:inherit; font-weight:500;';
    nextBtn.addEventListener('click', function () {
      if (currentPage < totalPages) {
        currentPage++;
        updateTable();
      }
    });
    pageControls.appendChild(nextBtn);
  }

  function updateTable() {
    var q = (search.value || '').trim().toLowerCase();
    var st = status.value || '';

    var matchingRows = rows.filter(function (row) {
      var matchQ = !q || (row.getAttribute('data-search') || '').indexOf(q) !== -1;
      var matchS = !st || row.getAttribute('data-status') === st;
      return matchQ && matchS;
    });

    var totalMatching = matchingRows.length;
    var totalPages = Math.max(1, Math.ceil(totalMatching / perPage));
    if (currentPage > totalPages) currentPage = totalPages;

    // Hide all rows first
    rows.forEach(function (row) {
      row.style.display = 'none';
    });

    // Show only rows for the current page
    var startIndex = (currentPage - 1) * perPage;
    var endIndex = startIndex + perPage;
    matchingRows.slice(startIndex, endIndex).forEach(function (row) {
      row.style.display = '';
    });

    if (empty) empty.style.display = totalMatching ? 'none' : '';
    if (count) {
      count.textContent = (q || st) ? (totalMatching + ' of ' + total) : (total + ' application' + (total === 1 ? '' : 's'));
    }

    renderPagination(totalMatching, totalPages);
  }

  search.addEventListener('input', function () {
    currentPage = 1;
    updateTable();
  });
  status.addEventListener('change', function () {
    currentPage = 1;
    updateTable();
  });

  updateTable(); // Run on load
})();
</script>
<?php endif; ?>

<!-- LOG APPLICATION POPUP DRAWER -->
<?php if ($loggingApp || ($error && !$reviewing)): ?>
<div style="position:fixed; inset:0; background:rgba(0,0,0,.4); display:flex; justify-content:flex-end; z-index:50;">
  <div style="background:#fff; width:440px; max-width:92vw; height:100%; padding:24px; overflow-y:auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <h3 style="margin:0;">Log an application</h3>
      <a href="/dashboard.php?page=applications" style="font-size:20px; color:var(--ink-500); text-decoration:none;">&times;</a>
    </div>
    
    <p style="font-size:12.5px; color:var(--ink-500); margin-bottom:16px;">
      Received an inquiry by phone or in person? Log it here so it goes through the same review flow.
    </p>

    <?php if ($error): ?>
      <div class="error-msg" style="margin-bottom:16px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$verifiedProperties): ?>
      <p style="font-size:13px; color:var(--ink-500);">You need at least one verified property before you can log applications.</p>
    <?php else: ?>
      <form method="POST" action="/dashboard.php?page=applications">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="log_application" value="1">
        <div class="field">
          <label>Property</label>
          <select name="property_id" required>
            <option value="">Select property…</option>
            <?php foreach ($verifiedProperties as $p): ?>
              <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Applicant Name</label>
          <input type="text" name="tenant_name" required>
        </div>
        <div class="field">
          <label>Business Name (optional)</label>
          <input type="text" name="business_name">
        </div>
        <div class="field">
          <label>Lease Term (months)</label>
          <input type="number" name="term_months" min="1" required>
        </div>
        <div class="field">
          <label>Monthly Rent (₱)</label>
          <input type="number" name="rent" min="1" step="0.01" required>
        </div>
        <button class="btn btn-primary" type="submit" style="width:100%; margin-top:8px;">Log Application</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- REVIEW APPLICATION POPUP DRAWER -->
<?php if ($reviewing): 
  $LEASE_CHECKLIST_ITEMS = \App\Services\LeaseChecklist::items();
  $negotiation = [];
  if (!empty($reviewing['checklist_negotiation'])) {
      $decoded = json_decode($reviewing['checklist_negotiation'], true);
      if (is_array($decoded)) {
          $negotiation = $decoded;
      }
  }

  $clauseKeys = array_filter(array_keys($negotiation), function($k) {
      return $k !== 'messages';
  });
  $totalClauses = !empty($clauseKeys) ? count($clauseKeys) : count($LEASE_CHECKLIST_ITEMS);
  $counterOffers = [];
  $agreedCount = 0;

  if (empty($clauseKeys)) {
      $agreedCount = $totalClauses;
  } else {
      foreach ($clauseKeys as $key) {
          $clauseStatus = $negotiation[$key];
          $isAgreed = true;
          $note = '';
          if (is_array($clauseStatus)) {
              $isAgreed = !isset($clauseStatus['agreed']) || $clauseStatus['agreed'] === true || $clauseStatus['agreed'] === 1 || $clauseStatus['agreed'] === 'true';
              $note = trim($clauseStatus['rebuttal_note'] ?? $clauseStatus['counter_note'] ?? $clauseStatus['note'] ?? '');
          }
          
          if (!$isAgreed || !empty($note)) {
              $label = $LEASE_CHECKLIST_ITEMS[$key]['label'] ?? ucwords(str_replace('_', ' ', $key));
              $counterOffers[] = [
                  'key'   => $key,
                  'label' => $label,
                  'note'  => !empty($note) ? $note : 'Counter-offer proposed.',
              ];
          } else {
              $agreedCount++;
          }
      }
  }
  $conversation = [];
  if (isset($negotiation['messages']) && is_array($negotiation['messages']) && !empty($negotiation['messages'])) {
      $conversation = $negotiation['messages'];
  } else {
      if (!empty($counterOffers)) {
          $initialText = [];
          foreach ($counterOffers as $co) {
              $initialText[] = $co['label'] . ': "' . $co['note'] . '"';
          }
          $conversation[] = [
              'sender'      => 'lessee',
              'sender_name' => $reviewing['tenant_name'],
              'message'     => implode("\n", $initialText),
              'sent_at'     => $reviewing['submitted_at'],
          ];
      }
      if (!empty($reviewing['lessor_rebuttal'])) {
          $conversation[] = [
              'sender'      => 'lessor',
              'sender_name' => 'Lessor',
              'message'     => $reviewing['lessor_rebuttal'],
              'sent_at'     => $reviewing['rebuttal_at'] ?? '',
          ];
      }
  }
?>
<div style="position:fixed; inset:0; background:rgba(0,0,0,.4); display:flex; justify-content:flex-end; z-index:50;">
  <div style="background:#fff; width:440px; max-width:92vw; height:100%; padding:24px; overflow-y:auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <h3 style="margin:0;">AP-<?= str_pad($reviewing['id'], 3, '0', STR_PAD_LEFT) ?></h3>
      <a href="/dashboard.php?page=applications" style="font-size:20px; color:var(--ink-500); text-decoration:none;">&times;</a>
    </div>

    <div class="card" style="margin-bottom:16px;">
      <p style="margin:4px 0; font-size:13px;"><strong>Applicant:</strong> <?= htmlspecialchars($reviewing['tenant_name']) ?></p>
      <?php if ($reviewing['business_name']): ?>
        <p style="margin:4px 0; font-size:13px;"><strong>Business:</strong> <?= htmlspecialchars($reviewing['business_name']) ?></p>
      <?php endif; ?>
      <p style="margin:4px 0; font-size:13px;"><strong>Property:</strong> <?= htmlspecialchars($reviewing['property_name']) ?></p>
      <p style="margin:4px 0; font-size:13px;"><strong>Lease term:</strong> <?= (int) $reviewing['term_months'] ?> months</p>
      <p style="margin:4px 0; font-size:13px;"><strong>Monthly rent:</strong> ₱<?= number_format($reviewing['rent'], 0) ?></p>
      <p style="margin:4px 0; font-size:13px;"><strong>Submitted:</strong> <?= (new DateTime($reviewing['submitted_at']))->format('M j, Y') ?></p>
      <p style="margin:4px 0; font-size:13px;"><strong>Checklist:</strong> 
        <?php if (empty($counterOffers)): ?>
          <span style="color:var(--success); font-weight:600;"><?= $agreedCount ?>/<?= $totalClauses ?> (All Agreed)</span>
        <?php else: ?>
          <span style="color:var(--ink-700); font-weight:600;"><?= $agreedCount ?>/<?= $totalClauses ?> (<?= count($counterOffers) ?> Counter-Offer<?= count($counterOffers) > 1 ? 's' : '' ?>)</span>
        <?php endif; ?>
      </p>
    </div>

    <?php
    $tenantDocLabels = [
        'valid_id_path'        => ['label' => 'Valid Government ID',         'icon' => 'bi-person-badge'],
        'sec_dti_path'         => ['label' => 'SEC / DTI Registration',      'icon' => 'bi-file-earmark-ruled'],
        'business_permit_path' => ['label' => "Mayor's / Business Permit",  'icon' => 'bi-file-earmark-check'],
        'bir_cert_path'        => ['label' => 'BIR Certificate / Registration', 'icon' => 'bi-file-earmark-text'],
    ];
    ?>
    <div class="card" style="margin-bottom:16px;">
      <p style="margin:0 0 10px; font-size:12.5px; font-weight:700; color:var(--ink-900);">
        <i class="bi bi-folder2-open" style="color:var(--primary); margin-right:4px;"></i> Tenant Verification Documents
      </p>
      <div style="display:flex; flex-direction:column; gap:8px;">
        <?php foreach ($tenantDocLabels as $key => $info): $path = $reviewing[$key] ?? null; ?>
          <div style="display:flex; justify-content:space-between; align-items:center; padding:9px 12px; border:1px solid var(--border); border-radius:8px; background:var(--bg);">
            <span style="font-size:12px; font-weight:500; color:var(--ink-800); display:flex; align-items:center; gap:6px;">
              <i class="bi <?= $info['icon'] ?>" style="color:var(--ink-500);"></i> <?= $info['label'] ?>
            </span>
            <?php if (!empty($path)): ?>
              <a href="/<?= htmlspecialchars($path) ?>" target="_blank" class="btn btn-primary" style="width:auto; padding:4px 10px; font-size:11.5px; display:inline-flex; align-items:center; gap:4px; text-decoration:none;">
                <i class="bi bi-box-arrow-up-right"></i> View
              </a>
            <?php else: ?>
              <span style="font-size:11px; color:var(--ink-400); font-style:italic;">Not uploaded</span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if (!empty($conversation)): ?>
      <div class="card" style="margin-bottom:16px;">
        <p style="margin:0 0 10px; font-size:12.5px; font-weight:700; color:var(--ink-900);">Negotiation Conversation</p>
        <div style="display:flex; flex-direction:column; gap:8px;">
          <?php foreach ($conversation as $msg): 
            $isLessor = ($msg['sender'] ?? '') === 'lessor';
          ?>
            <div style="padding:8px 10px; border-radius:6px; background:<?= $isLessor ? '#f0f7ff' : 'var(--bg)' ?>; border:1px solid <?= $isLessor ? '#cce3ff' : 'var(--border)' ?>;">
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:3px;">
                <span style="font-size:11.5px; font-weight:700; color:<?= $isLessor ? '#0b57d0' : 'var(--ink-800)' ?>;">
                  <?= htmlspecialchars($msg['sender_name'] ?? ($isLessor ? 'Lessor' : 'Applicant')) ?> (<?= $isLessor ? 'Lessor' : 'Applicant' ?>)
                </span>
                <span style="font-size:10.5px; color:var(--ink-500);">
                  <?= !empty($msg['sent_at']) ? (new DateTime($msg['sent_at']))->format('M j, g:i A') : '' ?>
                </span>
              </div>
              <div style="font-size:12px; color:<?= $isLessor ? '#1a3c6d' : 'var(--ink-700)' ?>; line-height:1.4;">
                <?= nl2br(htmlspecialchars($msg['message'] ?? '')) ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="card" style="margin-bottom:20px;">
      <p style="margin:0 0 6px; font-size:12.5px; font-weight:700;">Reply / Counter-Proposal to Applicant</p>
      <form method="POST" action="/dashboard.php?page=applications&review=<?= $reviewing['id'] ?>">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="application_id" value="<?= $reviewing['id'] ?>">
        <input type="hidden" name="action" value="rebuttal">
        <textarea name="lessor_rebuttal" rows="3" style="width:100%; box-sizing:border-box; font-family:inherit; font-size:12.5px; padding:8px; border:1px solid var(--border); border-radius:6px; margin-bottom:8px;" placeholder="Type your reply, counter-terms, or response..." required></textarea>
        <button class="btn btn-primary" style="width:100%; font-size:13px; padding:8px 14px;" type="submit">
          Send Reply
        </button>
      </form>
    </div>

    <div style="display:flex; gap:12px;">
      <form method="POST" action="/dashboard.php?page=applications" style="flex:1;">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="application_id" value="<?= $reviewing['id'] ?>">
        <input type="hidden" name="action" value="approve">
        <button class="btn" style="width:100%; background:var(--success); color:#fff; padding:8px 14px;" type="submit">
          Approve
        </button>
      </form>
      <form method="POST" action="/dashboard.php?page=applications" style="flex:1;">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="application_id" value="<?= $reviewing['id'] ?>">
        <input type="hidden" name="action" value="reject">
        <button class="btn" style="width:100%; background:var(--error); color:#fff; padding:8px 14px;" type="submit">
          Reject
        </button>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>