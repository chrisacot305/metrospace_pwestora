<?php
/**
 * app/Views/lessor/deposits.php — HTML for the "deposits" screen (was the bottom half of modules/deposits.php).
 * Data comes from app/Controllers/Lessor/DepositsController.php
 */
?>

<div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; margin-bottom:24px;">
  <div>
    <h2 style="margin:0 0 4px; font-weight:800; color:var(--ink-900);">Security Deposits</h2>
  </div>
  <a href="/dashboard.php?page=deposits&log=1" class="btn btn-primary" style="width:auto; height:fit-content; display:inline-flex; align-items:center; gap:6px; padding:10px 18px; font-size:13px; font-weight:600; white-space:nowrap; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.1);">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> 
    Log Deposit
  </a>
</div>

<?php if ($error && !$loggingDeposit && !$releasing): ?>
  <div class="error-msg" style="margin-bottom:20px; border-radius:8px;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- DASHBOARD KPI SUMMARY -->
<div class="kpi-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:20px; margin-bottom:32px;">
  <!-- Total Amount -->
  <div class="card" style="padding:24px; border-radius:16px; border:none; box-shadow:0 2px 12px rgba(0,0,0,0.03); display:flex; align-items:center; gap:20px;">
    <div style="background:#e6f4ea; color:#1e8e3e; width:56px; height:56px; border-radius:14px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"></rect><circle cx="12" cy="12" r="2"></circle><path d="M6 12h.01M18 12h.01"></path></svg>
    </div>
    <div>
      <div style="font-size:28px; font-weight:800; color:var(--ink-900); line-height:1.2;">₱<?= number_format($totalLockedAmount, 2) ?></div>
      <div style="font-size:12px; font-weight:600; color:var(--ink-500); text-transform:uppercase; letter-spacing:0.5px; margin-top:4px;">Total Locked Assets</div>
    </div>
  </div>
  
  <!-- Locked Count -->
  <div class="card" style="padding:24px; border-radius:16px; border:none; box-shadow:0 2px 12px rgba(0,0,0,0.03); display:flex; align-items:center; gap:20px;">
    <div style="background:#f0f7ff; color:#0b57d0; width:56px; height:56px; border-radius:14px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
    </div>
    <div>
      <div style="font-size:28px; font-weight:800; color:var(--ink-900); line-height:1.2;"><?= $lockedCount ?></div>
      <div style="font-size:12px; font-weight:600; color:var(--ink-500); text-transform:uppercase; letter-spacing:0.5px; margin-top:4px;">Active / Locked Deposits</div>
    </div>
  </div>

  <!-- Released Count -->
  <div class="card" style="padding:24px; border-radius:16px; border:none; box-shadow:0 2px 12px rgba(0,0,0,0.03); display:flex; align-items:center; gap:20px;">
    <div style="background:#fff5f5; color:#d93025; width:56px; height:56px; border-radius:14px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 9.9-1"></path></svg>
    </div>
    <div>
      <div style="font-size:28px; font-weight:800; color:var(--ink-900); line-height:1.2;"><?= $releasedCount ?></div>
      <div style="font-size:12px; font-weight:600; color:var(--ink-500); text-transform:uppercase; letter-spacing:0.5px; margin-top:4px;">Deposits Released</div>
    </div>
  </div>
</div>

<!-- DEPOSITS FEED -->
<div class="card" style="padding:0; border-radius:16px; border:none; box-shadow:0 2px 12px rgba(0,0,0,0.03); overflow:hidden;">
  <div style="display:flex; gap:16px; flex-wrap:wrap; align-items:center; padding:20px 24px; border-bottom:1px solid var(--border); background:#fff;">
    <div style="font-weight:800; font-size:16px; color:var(--ink-900); margin-right:auto;">Deposits Feed</div>
    
    <div style="flex:1; max-width:320px; position:relative;">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--ink-400);"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
      <input id="deposit-search" type="search" placeholder="Search tenant or property..."
             autocomplete="off"
             style="width:100%; box-sizing:border-box; padding:10px 14px 10px 38px; border:1px solid var(--border); border-radius:10px; font:inherit; font-size:13px; background:#f8f9fa; color:var(--ink-900); transition:border-color 0.2s;">
    </div>
    
    <select id="deposit-status-filter"
            style="padding:10px 14px; border:1px solid var(--border); border-radius:10px; font:inherit; font-size:13px; background:#f8f9fa; color:var(--ink-900); min-width:140px; cursor:pointer; outline:none;">
      <option value="">All statuses</option>
      <option value="locked">Locked</option>
      <option value="released">Released</option>
    </select>
  </div>

  <div>
    <?php if (!$deposits): ?>
      <div style="padding:60px 40px; text-align:center; color:var(--ink-500); font-size:14px;" id="deposit-no-records">
        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--ink-300); display:block; margin:0 auto 12px auto;"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"></polyline><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"></path></svg>
        No deposits on record yet.
      </div>
    <?php else: foreach ($deposits as $d): 
      $searchBlob = strtolower(trim(implode(' ', array_filter([
          $d['tenant_name'],
          $d['property_name'] ?? '',
          $d['status'],
          (string)$d['amount'],
          $d['method'] ?? ''
      ]))));
    ?>
      <div class="deposit-row" data-status="<?= htmlspecialchars($d['status']) ?>" data-search="<?= htmlspecialchars($searchBlob) ?>" 
           style="display:flex; align-items:center; justify-content:space-between; padding:20px 24px; border-bottom:1px solid var(--border); transition:background 0.2s;">
        
        <!-- Left: Tenant Info -->
        <div style="display:flex; align-items:center; gap:16px; flex:1; min-width:220px;">
          <div style="width:44px; height:44px; border-radius:50%; background:#f3f4f6; display:flex; align-items:center; justify-content:center; color:var(--ink-500); flex-shrink:0;">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
          </div>
          <div>
            <div style="font-weight:700; color:var(--ink-900); font-size:14.5px; margin-bottom:3px;"><?= htmlspecialchars($d['tenant_name']) ?></div>
            <div style="font-size:12.5px; color:var(--ink-500); display:flex; align-items:center;">
              <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px; color:var(--ink-400);"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
              <?= htmlspecialchars($d['property_name'] ?? 'Unassigned Property') ?>
            </div>
          </div>
        </div>
        
        <!-- Right: Money, Status, Action Group -->
        <div style="display:flex; align-items:center; justify-content:flex-end; gap:32px; flex:2; min-width:400px;">
          
          <!-- Amount & Details -->
          <div style="text-align:right;">
            <div style="font-weight:800; font-size:17px; color:var(--ink-900); letter-spacing:-0.5px;">₱<?= number_format($d['amount'], 2) ?></div>
            <div style="font-size:12px; color:var(--ink-500); margin-top:4px; display:flex; align-items:center; justify-content:flex-end;">
              <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px; color:var(--ink-400);"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg> 
              <?= !empty($d['created_at']) ? date('M j, Y', strtotime($d['created_at'])) : 'Unknown date' ?> 
              <span style="margin:0 6px; color:var(--border);">|</span> 
              <?= htmlspecialchars($d['method'] ?? 'Cash') ?>
            </div>
            <?php if ($d['note']): ?>
              <div style="font-size:11.5px; color:var(--ink-400); margin-top:6px; max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars($d['note']) ?>">
                <?= htmlspecialchars($d['note']) ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Status Badge -->
          <div style="min-width:100px; text-align:center;">
            <?php if ($d['status'] === 'locked'): ?>
              <span style="background:#e6f4ea; color:#1e8e3e; padding:6px 12px; border-radius:20px; font-size:12px; font-weight:700; display:inline-flex; align-items:center;">
                <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg> 
                Locked
              </span>
            <?php else: ?>
              <span style="background:#fff5f5; color:#d93025; padding:6px 12px; border-radius:20px; font-size:12px; font-weight:700; display:inline-flex; align-items:center;">
                <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 9.9-1"></path></svg> 
                Released
              </span>
            <?php endif; ?>
          </div>
          
          <!-- Action Button -->
          <div style="width:120px; text-align:right;">
            <?php if ($d['status'] === 'locked'): ?>
              <a href="/dashboard.php?page=deposits&release=<?= $d['id'] ?>" 
                 style="font-size:12px; font-weight:700; color:var(--error); padding:8px 14px; border:1px solid rgba(217, 48, 37, 0.2); border-radius:8px; display:inline-block; text-decoration:none; background:#fff; transition:all 0.2s; white-space:nowrap;">
                Process Release
              </a>
            <?php else: ?>
              <span style="font-size:13px; color:var(--ink-300); font-weight:600;">Closed</span>
            <?php endif; ?>
          </div>

        </div>
      </div>
    <?php endforeach; endif; ?>
    
    <div id="deposit-empty-filter" style="display:none; padding:60px 40px; text-align:center; color:var(--ink-500); font-size:14px;">
      <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--ink-300); display:block; margin:0 auto 12px auto;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
      No security deposits match your search or filter.
    </div>
  </div>

  <!-- Pagination UI -->
  <?php if ($deposits): ?>
  <div id="deposit-pagination-bar" style="display:flex; justify-content:center; align-items:center; padding:16px; background:#fff;">
    <div id="deposit-page-controls" style="display:flex; gap:6px; align-items:center; justify-content:center;"></div>
  </div>
  <?php endif; ?>

</div>

<script>
(function () {
  var search = document.getElementById('deposit-search');
  var status = document.getElementById('deposit-status-filter');
  var rows = Array.prototype.slice.call(document.querySelectorAll('.deposit-row'));
  var empty = document.getElementById('deposit-empty-filter');
  var total = rows.length;

  var paginationBar = document.getElementById('deposit-pagination-bar');
  var pageControls = document.getElementById('deposit-page-controls');
  var currentPage = 1;
  var perPage = 10;

  if (!search || !status) return;

  function renderPagination(totalMatching, totalPages) {
    if (!paginationBar || !pageControls) return;
    if (totalMatching === 0) {
      paginationBar.style.display = 'none';
      return;
    }
    
    paginationBar.style.display = 'flex';
    pageControls.innerHTML = '';

    var prevBtn = document.createElement('button');
    prevBtn.type = 'button';
    prevBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><polyline points="15 18 9 12 15 6"></polyline></svg> Prev';
    prevBtn.disabled = currentPage === 1;
    prevBtn.style.cssText = 'padding:6px 12px; font-size:13px; border:1px solid var(--border); border-radius:8px; background:' + (currentPage === 1 ? 'var(--bg)' : '#fff') + '; color:' + (currentPage === 1 ? 'var(--ink-300)' : 'var(--ink-900)') + '; cursor:' + (currentPage === 1 ? 'not-allowed' : 'pointer') + '; font-weight:600; transition:all 0.2s; display:inline-flex; align-items:center;';
    prevBtn.addEventListener('click', function () {
      if (currentPage > 1) { currentPage--; filterFeed(); }
    });
    pageControls.appendChild(prevBtn);

    for (var p = 1; p <= totalPages; p++) {
      (function (pageNum) {
        var pageBtn = document.createElement('button');
        pageBtn.type = 'button';
        pageBtn.textContent = pageNum;
        var isActive = pageNum === currentPage;
        pageBtn.style.cssText = 'padding:6px 12px; font-size:13px; border:1px solid ' + (isActive ? 'var(--primary)' : 'var(--border)') + '; border-radius:8px; background:' + (isActive ? 'var(--primary)' : '#fff') + '; color:' + (isActive ? '#fff' : 'var(--ink-900)') + '; cursor:pointer; font-weight:' + (isActive ? '700' : '600') + '; min-width:36px; transition:all 0.2s;';
        pageBtn.addEventListener('click', function () {
          currentPage = pageNum; filterFeed();
        });
        pageControls.appendChild(pageBtn);
      })(p);
    }

    var nextBtn = document.createElement('button');
    nextBtn.type = 'button';
    nextBtn.innerHTML = 'Next <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left:4px;"><polyline points="9 18 15 12 9 6"></polyline></svg>';
    nextBtn.disabled = currentPage === totalPages;
    nextBtn.style.cssText = 'padding:6px 12px; font-size:13px; border:1px solid var(--border); border-radius:8px; background:' + (currentPage === totalPages ? 'var(--bg)' : '#fff') + '; color:' + (currentPage === totalPages ? 'var(--ink-300)' : 'var(--ink-900)') + '; cursor:' + (currentPage === totalPages ? 'not-allowed' : 'pointer') + '; font-weight:600; transition:all 0.2s; display:inline-flex; align-items:center;';
    nextBtn.addEventListener('click', function () {
      if (currentPage < totalPages) { currentPage++; filterFeed(); }
    });
    pageControls.appendChild(nextBtn);
  }

  function filterFeed() {
    var q = (search.value || '').trim().toLowerCase();
    var st = status.value || '';
    
    // Filter matching rows
    var matchingRows = rows.filter(function (row) {
      var matchQ = !q || (row.getAttribute('data-search') || '').indexOf(q) !== -1;
      var matchS = !st || row.getAttribute('data-status') === st;
      return matchQ && matchS;
    });

    var totalMatching = matchingRows.length;
    var totalPages = Math.max(1, Math.ceil(totalMatching / perPage));
    if (currentPage > totalPages) currentPage = totalPages;

    // Hide everything
    rows.forEach(function (row) { row.style.display = 'none'; });

    // Show only the slice for the current page
    var startIndex = (currentPage - 1) * perPage;
    var endIndex = startIndex + perPage;
    matchingRows.slice(startIndex, endIndex).forEach(function (row) {
      row.style.display = 'flex';
    });

    if (empty) {
      empty.style.display = (totalMatching === 0 && total > 0) ? 'block' : 'none';
    }

    renderPagination(totalMatching, totalPages);
  }

  search.addEventListener('input', function() { currentPage = 1; filterFeed(); });
  status.addEventListener('change', function() { currentPage = 1; filterFeed(); });
  filterFeed();
})();
</script>

<!-- LOG DEPOSIT POPUP DRAWER -->
<?php if ($loggingDeposit || ($error && !$releasing)): ?>
<div style="position:fixed; inset:0; background:rgba(0,0,0,.4); display:flex; justify-content:flex-end; z-index:50;">
  <div style="background:#fff; width:440px; max-width:92vw; height:100%; padding:24px; overflow-y:auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <h3 style="margin:0;">Log a deposit</h3>
      <a href="/dashboard.php?page=deposits" style="font-size:20px; color:var(--ink-500); text-decoration:none;">&times;</a>
    </div>

    <p style="font-size:13px; color:var(--ink-500); margin-bottom:20px;">
      Record the security deposit amount paid by the tenant.
    </p>

    <?php if ($error): ?>
      <div class="error-msg" style="margin-bottom:16px; border-radius:8px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$tenantsList): ?>
      <p style="font-size:13px; color:var(--ink-500);">You don't have any active tenants yet.</p>
    <?php else: ?>
      <form method="POST" action="/dashboard.php?page=deposits">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="log_deposit" value="1">
        
        <div class="field">
          <label style="font-weight:600;">Property</label>
          <select id="property_select" name="property_id" required style="width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; font-family:inherit;">
            <option value="">Select property…</option>
            <?php foreach ($properties as $p): ?>
              <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field" style="margin-top:16px;">
          <label style="font-weight:600;">Tenant</label>
          <select id="tenant_select" name="tenant_id" required disabled style="width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; font-family:inherit;">
            <option value="">Select a property first…</option>
            <?php foreach ($tenantsList as $t): ?>
              <option value="<?= $t['id'] ?>" data-property="<?= $t['property_id'] ?>" data-deposit="<?= $t['security_deposit'] ?>">
                <?= htmlspecialchars($t['tenant_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="display:flex; gap:16px; margin-top:16px; margin-bottom:24px;">
          <div class="field" style="flex:1;">
            <label style="font-weight:600;">Deposit Amount (₱)</label>
            <input type="text" id="deposit_amount" name="amount" required style="width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; box-sizing:border-box; font-family:inherit;" inputmode="numeric">
            <div id="contract_hint" style="font-size:11.5px; color:var(--ink-400); margin-top:6px; display:none;">
              Contracted Deposit: ₱<span id="hint_amount" style="font-weight:600; color:var(--ink-600);">0</span>
            </div>
          </div>
          <div class="field" style="flex:1;">
            <label style="font-weight:600;">Payment Method</label>
            <select name="method" required style="width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; font-family:inherit;">
              <option value="Cash">Cash</option>
              <option value="Bank Transfer">Bank Transfer</option>
              <option value="GCash">GCash</option>
            </select>
          </div>
        </div>

        <button class="btn btn-primary" type="submit" style="width:100%; padding:12px; font-weight:600; border-radius:8px;">Log Deposit</button>
      </form>

      <!-- JAVASCRIPT FOR CASCADING DROPDOWN & NUMBER FORMATTING -->
      <script>
        (function() {
           var propSel = document.getElementById('property_select');
           var tenSel = document.getElementById('tenant_select');
           var amtInput = document.getElementById('deposit_amount');
           var hintDiv = document.getElementById('contract_hint');
           var hintSpan = document.getElementById('hint_amount');
           
           function formatNumber(val) {
               var parts = val.replace(/,/g, '').split('.');
               parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
               return parts.join('.');
           }

           if (amtInput) {
               amtInput.addEventListener('input', function(e) {
                   var value = this.value.replace(/[^0-9.]/g, ''); 
                   var parts = value.split('.');
                   if (parts.length > 2) {
                       value = parts[0] + '.' + parts.slice(1).join(''); 
                   }
                   this.value = formatNumber(value);
               });
           }

           if (propSel && tenSel) {
               var allOptions = Array.from(tenSel.querySelectorAll('option[data-property]'));
               
               propSel.addEventListener('change', function() {
                   var pid = this.value;
                   tenSel.innerHTML = '<option value="">Select tenant...</option>';
                   
                   amtInput.value = ''; 
                   hintDiv.style.display = 'none';

                   var hasTenants = false;
                   
                   allOptions.forEach(function(opt) {
                       if(opt.getAttribute('data-property') === pid) {
                           tenSel.appendChild(opt.cloneNode(true));
                           hasTenants = true;
                       }
                   });
                   
                   if (!pid) {
                      tenSel.innerHTML = '<option value="">Select a property first...</option>';
                      tenSel.disabled = true;
                   } else if (!hasTenants) {
                      tenSel.innerHTML = '<option value="">No tenants found here</option>';
                      tenSel.disabled = true;
                   } else {
                      tenSel.disabled = false;
                   }
               });

               tenSel.addEventListener('change', function() {
                   var selectedOpt = this.options[this.selectedIndex];

                   if (selectedOpt && selectedOpt.value) {
                       var contractAmount = parseFloat(selectedOpt.getAttribute('data-deposit')) || 0;
                       
                       if (contractAmount > 0) {
                           var formattedAmount = contractAmount.toLocaleString('en-US', {maximumFractionDigits: 2});
                           amtInput.value = formattedAmount;
                           hintSpan.textContent = formattedAmount;
                           hintDiv.style.display = 'block';
                       } else {
                           amtInput.value = '';
                           hintDiv.style.display = 'none';
                       }
                   } else {
                       amtInput.value = '';
                       hintDiv.style.display = 'none';
                   }
               });
               
               tenSel.innerHTML = '<option value="">Select a property first...</option>';
               tenSel.disabled = true;
           }
        })();
      </script>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- PROCESS RELEASE POPUP DRAWER -->
<?php if ($releasing && !$confirming): ?>
<div style="position:fixed; inset:0; background:rgba(0,0,0,.4); display:flex; justify-content:flex-end; z-index:50;">
  <div style="background:#fff; width:440px; max-width:92vw; height:100%; padding:24px; overflow-y:auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <h3 style="margin:0;">Process Deposit Release</h3>
      <a href="/dashboard.php?page=deposits" style="font-size:20px; color:var(--ink-500); text-decoration:none;">&times;</a>
    </div>

    <div class="card" style="margin-bottom:24px; padding:16px; border-radius:12px; background:#f8f9fa; border:1px solid var(--border);">
      <p style="margin:0 0 6px; font-size:13px; color:var(--ink-500);">Releasing deposit for</p>
      <p style="margin:0 0 12px; font-size:16px; font-weight:700; color:var(--ink-900);"><?= htmlspecialchars($releasing['tenant_name']) ?></p>
      
      <p style="margin:0 0 6px; font-size:13px; color:var(--ink-500);">Amount to release</p>
      <p style="margin:0; font-size:20px; font-weight:800; color:var(--primary);">₱<?= number_format($releasing['amount'], 2) ?></p>
    </div>

    <form method="POST" action="/dashboard.php?page=deposits">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="deposit_id" value="<?= $releasing['id'] ?>">
      <input type="hidden" name="confirm_release" value="1">

      <div style="margin-bottom:24px;">
        <label style="display:block; font-size:13.5px; font-weight:700; margin-bottom:8px; color:var(--ink-900);">Reason for Request / Release</label>
        <textarea name="release_reason" rows="4" placeholder="e.g. End of lease contract, requested emergency medical funds..." required style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; box-sizing:border-box; font-family:inherit; font-size:13.5px; line-height:1.5;"></textarea>
      </div>

      <p style="font-size:12.5px; color:var(--ink-500); margin-bottom:24px; line-height:1.5; display:flex; align-items:flex-start;">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px; flex-shrink:0; margin-top:2px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg> 
        <span>Processing this release will permanently log the reason in the tenant's transaction history.</span>
      </p>

      <div style="display:flex; gap:12px;">
        <a href="/dashboard.php?page=deposits" class="btn" style="flex:1; background:var(--bg); color:var(--ink-900); display:flex; justify-content:center; padding:12px; border-radius:8px; font-weight:600;">Cancel</a>
        <button class="btn" style="flex:1.5; background:var(--error); color:#fff; display:flex; align-items:center; justify-content:center; padding:12px; border-radius:8px; font-weight:600;" type="submit">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 9.9-1"></path></svg> 
          Confirm Release
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>