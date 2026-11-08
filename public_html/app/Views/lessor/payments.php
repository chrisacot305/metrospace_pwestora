<?php
/**
 * app/Views/lessor/payments.php — HTML for the "payments" screen (was the bottom half of modules/payments.php).
 * Data comes from app/Controllers/Lessor/PaymentsController.php
 */
?>

<!-- FLOATING TOAST NOTIFICATIONS -->
<?php if ($msg || ($error && !$loggingPayment)): ?>
  <div id="toast-notification" style="position:fixed; top:24px; right:24px; z-index:9999; transition: opacity 0.5s ease, transform 0.5s ease; transform: translateY(0); opacity: 1;">
    <?php if ($msg === 'approved'): ?>
      <div class="success-msg" style="margin:0; box-shadow:0 4px 12px rgba(0,0,0,0.1); border-radius:8px; display:flex; align-items:center; gap:8px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--success);"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
        <span><strong>Payment Confirmed!</strong> The payment has been verified and recorded.</span>
      </div>
    <?php elseif ($msg === 'declined'): ?>
      <div class="error-msg" style="margin:0; box-shadow:0 4px 12px rgba(0,0,0,0.1); border-radius:8px; display:flex; align-items:center; gap:8px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--error);"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
        <span><strong>Payment Declined.</strong> The submission was declined and marked accordingly.</span>
      </div>
    <?php elseif ($msg === 'logged'): ?>
      <div class="success-msg" style="margin:0; box-shadow:0 4px 12px rgba(0,0,0,0.1); border-radius:8px; display:flex; align-items:center; gap:8px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--primary);"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
        <span>Payment logged and is now <strong>Pending Review</strong>.</span>
      </div>
    <?php endif; ?>

    <?php if ($error && !$loggingPayment): ?>
      <div class="error-msg" style="margin:0; box-shadow:0 4px 12px rgba(0,0,0,0.1); border-radius:8px; display:flex; align-items:center; gap:8px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--error);"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
    <?php endif; ?>
  </div>

  <script>
    setTimeout(function() {
      var toast = document.getElementById('toast-notification');
      if (toast) {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-20px)';
        setTimeout(function() { toast.remove(); }, 500);
      }
    }, 3500);
  </script>
<?php endif; ?>

<!-- HEADER SECTION -->
<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
  <div>
    <h2 style="margin:0 0 4px; font-weight:800; color:var(--ink-900);">Rent Payments</h2>
  </div>
  <a href="/dashboard.php?page=payments&log=1" class="btn btn-primary" style="width:auto; height:fit-content; display:inline-flex; align-items:center; gap:6px; padding:10px 18px; font-size:13px; font-weight:600; white-space:nowrap; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.1);">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
    Log Payment
  </a>
</div>

<!-- Summary KPI Grid -->
<div class="kpi-grid" style="margin-bottom:28px;">
  <div class="card" style="border-left: 3px solid <?= $pendingCount > 0 ? 'var(--warning)' : 'var(--border)' ?>;">
    <div class="kpi-label">Pending Verification</div>
    <div class="kpi-value" style="color:<?= $pendingCount > 0 ? 'var(--primary)' : 'inherit' ?>;">
      <?= $pendingCount ?> <span style="font-size:14px; font-weight:600; color:var(--ink-500);">(₱<?= number_format($pendingTotal, 2) ?>)</span>
    </div>
  </div>
  <div class="card">
    <div class="kpi-label">Verified This Month</div>
    <div class="kpi-value">₱<?= number_format($monthTotal, 2) ?></div>
  </div>
  <div class="card">
    <div class="kpi-label">Total Verified Records</div>
    <div class="kpi-value"><?= $historyCount ?></div>
  </div>
</div>

<!-- =============================================================
     UNIFIED PAYMENT LEDGER TABLE
     ============================================================= -->
<div class="card" style="padding:0; overflow:hidden;">
  <!-- REAL-TIME FILTER BAR -->
  <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; padding:16px 20px; border-bottom:1px solid var(--border); background:var(--surface);">
    <div style="flex:1; min-width:220px; position:relative;">
      <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--ink-400);"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
      <input id="pay-search" type="search" placeholder="Search property, tenant, target, or reference..." autocomplete="off" style="width:100%; box-sizing:border-box; padding:8px 12px 8px 34px; border:1px solid var(--border); border-radius:8px; font:inherit; font-size:13px; background:#fff; color:var(--ink-900);">
    </div>
    <select id="pay-status-filter" style="padding:8px 12px; border:1px solid var(--border); border-radius:8px; font:inherit; font-size:13px; background:#fff; color:var(--ink-900); min-width:140px;">
      <option value="">All statuses</option>
      <option value="pending">Pending Review</option>
      <option value="verified">Verified</option>
      <option value="declined">Declined</option>
    </select>
  </div>

  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>Property & Tenant</th>
          <th>Amount</th>
          <th>Target & Method</th>
          <th>Date</th>
          <th>Status</th>
          <th style="text-align:right;">Action</th>
        </tr>
      </thead>
      <tbody id="payments-table-body">
        <?php if (!$allPayments): ?>
          <tr>
            <td colspan="6" style="text-align:center; padding:40px 16px; color:var(--ink-500);">
              <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--ink-300); display:block; margin:0 auto 12px auto;"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1Z"></path><line x1="16" y1="8" x2="8" y2="8"></line><line x1="16" y1="12" x2="8" y2="12"></line><line x1="10" y1="16" x2="8" y2="16"></line></svg>
              No payment records found.
            </td>
          </tr>
        <?php else: foreach ($allPayments as $p):
          $parsed = parse_payment_note($p['note']);
          $isDeclined = $parsed['status'] === 'declined';
          $isPending = ($p['logged_by'] === null && !$isDeclined);
          $statusVal = $isDeclined ? 'declined' : ($isPending ? 'pending' : 'verified');
          
          $searchBlob = strtolower(trim(implode(' ', array_filter([
              $p['property_name'] ?? '',
              $p['tenant_name'],
              $parsed['target'],
              $parsed['ref'],
              $p['method'],
              $p['note']
          ]))));
        ?>
          <tr class="pay-row" data-status="<?= $statusVal ?>" data-search="<?= htmlspecialchars($searchBlob) ?>" style="background: <?= $isPending ? 'rgba(11, 87, 208, 0.03)' : 'transparent' ?>;">
            
            <!-- Property & Tenant -->
            <td>
              <div style="font-weight:700; color:var(--ink-900); font-size:14px;"><?= htmlspecialchars($p['tenant_name']) ?></div>
              <div style="color:var(--ink-500); font-size:12px; margin-top:2px; display:flex; align-items:center;">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect><path d="M9 22v-4h6v4"></path><path d="M8 6h.01"></path><path d="M16 6h.01"></path><path d="M12 6h.01"></path><path d="M12 10h.01"></path><path d="M12 14h.01"></path><path d="M16 10h.01"></path><path d="M16 14h.01"></path><path d="M8 10h.01"></path><path d="M8 14h.01"></path></svg>
                <?= htmlspecialchars($p['property_name'] ?? 'Unknown Property') ?>
              </div>
            </td>
            
            <!-- Amount -->
            <td>
              <span style="font-size:15px; font-weight:800; color:<?= $isDeclined ? 'var(--ink-400)' : 'var(--primary)' ?>;">
                ₱<?= number_format($p['amount'], 2) ?>
              </span>
            </td>
            
            <!-- Target & Method -->
            <td>
              <div style="font-size:12.5px;">
                <span style="font-weight:600; color:var(--ink-900);"><?= htmlspecialchars($parsed['target']) ?></span>
                <div style="color:var(--ink-500); margin-top:2px;">
                  <?= htmlspecialchars($METHODS[$p['method']] ?? $p['method']) ?>
                  <?php if ($parsed['ref'] !== '—'): ?>
                      · <span style="font-family:monospace; color:var(--ink-600);">Ref: <?= htmlspecialchars($parsed['ref']) ?></span>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            
            <!-- Date -->
            <td style="color:var(--ink-500); font-size:13px;">
              <?= (new DateTime($p['paid_at']))->format('M j, Y') ?>
            </td>
            
            <!-- Status -->
            <td>
              <?php if ($isDeclined): ?>
                <span class="badge badge-rejected" style="font-size:11px;">Declined</span>
              <?php elseif (!$isPending): ?>
                <span class="badge badge-approved" style="font-size:11px; display:inline-flex; align-items:center;">
                  <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:2px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><polyline points="9 12 11 14 15 10"></polyline></svg>
                  Verified
                </span>
              <?php else: ?>
                <span class="badge badge-pending" style="font-size:11px;">Pending Review</span>
              <?php endif; ?>
            </td>
            
            <!-- Action -->
            <td style="text-align:right;">
              <?php if ($isPending): ?>
                <div style="display:inline-flex; gap:8px; justify-content:flex-end;">
                  <form method="POST" style="margin:0;" onsubmit="var btn = this.querySelector('button[type=submit]'); btn.disabled = true; btn.innerHTML = '<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'12\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\' style=\'margin-right:4px;\'><path d=\'M18 3H6v4l4 4-4 4v4h12v-4l-4-4 4-4V3z\'></path></svg>...';">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="approve_payment">
                    <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                    <button type="submit" class="btn" style="padding:6px 12px; font-size:12px; background:var(--success); color:#fff; border-radius:6px; display:inline-flex; align-items:center;" title="Confirm Received">
                      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><polyline points="20 6 9 17 4 12"></polyline></svg> 
                      Confirm
                    </button>
                  </form>
                  <form method="POST" style="margin:0;" onsubmit="var reason = prompt('Reason for declining this payment?'); if(reason === null || reason.trim() === '') return false; this.querySelector('.decline-reason').value = reason; var btn = this.querySelector('button[type=submit]'); btn.disabled = true; btn.innerHTML = '<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'12\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'><path d=\'M18 3H6v4l4 4-4 4v4h12v-4l-4-4 4-4V3z\'></path></svg>';">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="decline_payment">
                    <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                    <input type="hidden" name="decline_reason" class="decline-reason" value="">
                    <button type="submit" class="btn" style="padding:6px 10px; font-size:12px; background:var(--error-soft); color:var(--error); border-radius:6px; display:inline-flex; align-items:center; justify-content:center;" title="Decline">
                      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                  </form>
                </div>
              <?php else: ?>
                <span style="font-size:12.5px; color:var(--ink-300); font-weight:500;">—</span>
              <?php endif; ?>
            </td>

          </tr>
        <?php endforeach; endif; ?>
          <tr id="payments-empty-filter" style="display:none;">
            <td colspan="6" style="text-align:center; padding:32px 16px; color:var(--ink-500);">No payments match your search.</td>
          </tr>
      </tbody>
    </table>
  </div>
  
  <!-- Pagination UI -->
  <?php if ($allPayments): ?>
  <div id="payment-pagination-bar" style="display:flex; justify-content:center; align-items:center; padding:12px 16px; border-top:1px solid var(--border); background:var(--surface);">
    <div id="payment-page-controls" style="display:flex; gap:6px; align-items:center; justify-content:center;"></div>
  </div>
  <?php endif; ?>
</div>

<!-- =============================================================
     POPUP DRAWER: LOG OFFLINE PAYMENT
     ============================================================= -->
<?php if ($loggingPayment || ($error && isset($_POST['log_payment']))): ?>
<div style="position:fixed; inset:0; background:rgba(0,0,0,.4); display:flex; justify-content:flex-end; z-index:50;">
  <div style="background:#fff; width:440px; max-width:92vw; height:100%; padding:24px; overflow-y:auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <h3 style="margin:0;">Log Offline Payment</h3>
      <a href="/dashboard.php?page=payments" style="font-size:20px; color:var(--ink-500); text-decoration:none;">&times;</a>
    </div>

    <p style="font-size:13px; color:var(--ink-500); margin-bottom:20px;">
      Directly record payments received. They will be added to the queue for pending verification.
    </p>

    <?php if ($error): ?>
      <div class="error-msg" style="margin-bottom:16px; border-radius:8px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$tenants): ?>
      <p style="font-size:13px; color:var(--ink-500);">You don't have any active tenants yet.</p>
    <?php else: ?>
      <form method="POST" action="/dashboard.php?page=payments" onsubmit="var btn = this.querySelector('button[type=submit]'); btn.disabled = true; btn.innerHTML = '<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'12\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\' style=\'margin-right:6px;\'><path d=\'M18 3H6v4l4 4-4 4v4h12v-4l-4-4 4-4V3z\'></path></svg> Logging...';">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="log_payment" value="1">
        
        <div class="field">
          <label style="font-weight:600;">Property</label>
          <select id="payment_property_select" name="property_id" required style="width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; font-family:inherit;">
            <option value="">Select property…</option>
            <?php foreach ($properties as $p): ?>
              <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field" style="margin-top:16px;">
          <label style="font-weight:600;">Tenant</label>
          <select id="payment_tenant_select" name="tenant_id" required disabled style="width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; font-family:inherit;">
            <option value="">Select a property first…</option>
            <?php foreach ($tenants as $t): ?>
              <!-- FIXED: The tenant option now securely carries their contract limit! -->
              <option value="<?= $t['id'] ?>" data-property="<?= $t['property_id'] ?>" data-rent="<?= $t['rent'] ?? 0 ?>"><?= htmlspecialchars($t['tenant_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div style="display:flex; gap:16px; margin-top:16px;">
          <div class="field" style="flex:1;">
            <label style="font-weight:600;">Amount (₱)</label>
            <input type="text" id="manual_payment_amount" name="amount" placeholder="e.g. 2,500" required style="width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; box-sizing:border-box; font-family:inherit;" inputmode="numeric">
            <!-- FIXED: Hint to show the contract limit -->
            <div id="payment_contract_hint" style="font-size:11.5px; color:var(--ink-400); margin-top:6px; display:none;">
              Contracted Rent: ₱<span id="payment_hint_amount" style="font-weight:600; color:var(--ink-600);">0</span>
            </div>
          </div>
          
          <div class="field" style="flex:1;">
            <label style="font-weight:600;">Payment Method</label>
            <select name="method" id="payment_method_select" required style="width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; font-family:inherit;">
              <?php foreach ($METHODS as $key => $label): ?>
                <option value="<?= $key ?>"><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="field" id="method_other_container" style="display:none; margin-top:16px;">
          <label style="font-weight:600;">Specify Method</label>
          <input type="text" name="method_other" id="method_other_input" placeholder="e.g. Western Union, Remitly" style="width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; box-sizing:border-box; font-family:inherit;">
        </div>
        
        <div class="field" style="margin-top:16px;">
          <label style="font-weight:600;">Date Received</label>
          <input type="date" name="paid_at" value="<?= date('Y-m-d') ?>" required style="width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; box-sizing:border-box; font-family:inherit;">
        </div>
        
        <div class="field" style="margin-top:16px; margin-bottom:24px;">
          <label style="font-weight:600;">Note / Remarks (optional)</label>
          <input type="text" name="note" placeholder="e.g. Official Receipt #10492" style="width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; box-sizing:border-box; font-family:inherit;">
        </div>
        
        <button class="btn btn-primary" type="submit" style="width:100%; padding:12px; font-weight:600; border-radius:8px; display:inline-flex; align-items:center; justify-content:center;">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg> 
          Log Payment
        </button>
      </form>

      <script>
        document.addEventListener('DOMContentLoaded', function() {
           var propSel = document.getElementById('payment_property_select');
           var tenSel = document.getElementById('payment_tenant_select');
           var amtInput = document.getElementById('manual_payment_amount');
           var methodSel = document.getElementById('payment_method_select');
           var methodOtherContainer = document.getElementById('method_other_container');
           var methodOtherInput = document.getElementById('method_other_input');
           var hintDiv = document.getElementById('payment_contract_hint');
           var hintSpan = document.getElementById('payment_hint_amount');
           
           // State to track the tenant's maximum contracted rent
           var currentMaxRent = 99999999.99;
           
           if (methodSel) {
               methodSel.addEventListener('change', function() {
                   if (this.value === 'other') {
                       methodOtherContainer.style.display = 'block';
                       methodOtherInput.required = true;
                   } else {
                       methodOtherContainer.style.display = 'none';
                       methodOtherInput.required = false;
                       methodOtherInput.value = '';
                   }
               });
           }

           function formatNumber(val) {
               var parts = val.replace(/,/g, '').split('.');
               parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
               return parts.join('.');
           }

           // FIXED: Strict limit enforcement based on currentMaxRent
           if (amtInput) {
               amtInput.addEventListener('input', function(e) {
                   var value = this.value.replace(/[^0-9.]/g, ''); 
                   var parts = value.split('.');
                   
                   // Prevent more than one decimal point
                   if (parts.length > 2) {
                       value = parts[0] + '.' + parts.slice(1).join(''); 
                       parts = value.split('.');
                   }
                   
                   // Check numeric value against the contracted max limit
                   var numVal = parseFloat(value);
                   if (!isNaN(numVal) && currentMaxRent > 0 && numVal > currentMaxRent) {
                       value = currentMaxRent.toString();
                       parts = value.split('.');
                   }
                   
                   // Fallback visual limits
                   if (parts[0].length > 8) parts[0] = parts[0].substring(0, 8);
                   if (parts.length > 1 && parts[1].length > 2) parts[1] = parts[1].substring(0, 2);
                   
                   value = parts.join('.');
                   this.value = formatNumber(value);
               });
           }

           if (propSel && tenSel) {
               var allOptions = Array.from(tenSel.querySelectorAll('option[data-property]'));
               
               propSel.addEventListener('change', function() {
                   var pid = this.value;
                   tenSel.innerHTML = '<option value="">Select tenant...</option>';
                   var hasTenants = false;
                   
                   // Reset amounts when property changes
                   amtInput.value = ''; 
                   if (hintDiv) hintDiv.style.display = 'none';
                   currentMaxRent = 99999999.99;
                   
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
               
               // FIXED: When a tenant is selected, fetch their max rent and auto-fill!
               tenSel.addEventListener('change', function() {
                   var selectedOpt = this.options[this.selectedIndex];

                   if (selectedOpt && selectedOpt.value) {
                       var rent = parseFloat(selectedOpt.getAttribute('data-rent')) || 0;
                       if (rent > 0) {
                           currentMaxRent = rent;
                           var formattedAmount = formatNumber(rent.toFixed(2));
                           amtInput.value = formattedAmount; // Auto-fill their exact rent
                           
                           if (hintDiv && hintSpan) {
                               hintSpan.textContent = formattedAmount;
                               hintDiv.style.display = 'block';
                           }
                       } else {
                           currentMaxRent = 99999999.99;
                           amtInput.value = '';
                           if (hintDiv) hintDiv.style.display = 'none';
                       }
                   } else {
                       currentMaxRent = 99999999.99;
                       amtInput.value = '';
                       if (hintDiv) hintDiv.style.display = 'none';
                   }
               });
               
               tenSel.innerHTML = '<option value="">Select a property first...</option>';
               tenSel.disabled = true;
           }
        });
      </script>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- Unified Real-Time Javascript Filtering & Pagination Script -->
<script>
(function () {
  var search = document.getElementById('pay-search');
  var status = document.getElementById('pay-status-filter');
  var rows = Array.prototype.slice.call(document.querySelectorAll('#payments-table-body .pay-row'));
  var emptyFilter = document.getElementById('payments-empty-filter');
  
  // Pagination vars
  var paginationBar = document.getElementById('payment-pagination-bar');
  var pageControls = document.getElementById('payment-page-controls');
  var currentPage = 1;
  var perPage = 10;
  var total = rows.length;

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
    prevBtn.style.cssText = 'padding:5px 10px; font-size:12px; border:1px solid var(--border); border-radius:6px; background:' + (currentPage === 1 ? 'var(--bg)' : '#fff') + '; color:' + (currentPage === 1 ? 'var(--ink-300)' : 'var(--ink-900)') + '; cursor:' + (currentPage === 1 ? 'not-allowed' : 'pointer') + '; font-family:inherit; font-weight:500; display:inline-flex; align-items:center;';
    prevBtn.addEventListener('click', function () {
      if (currentPage > 1) { currentPage--; applyFilter(); }
    });
    pageControls.appendChild(prevBtn);

    for (var p = 1; p <= totalPages; p++) {
      (function (pageNum) {
        var pageBtn = document.createElement('button');
        pageBtn.type = 'button';
        pageBtn.textContent = pageNum;
        var isActive = pageNum === currentPage;
        pageBtn.style.cssText = 'padding:5px 10px; font-size:12px; border:1px solid ' + (isActive ? 'var(--primary)' : 'var(--border)') + '; border-radius:6px; background:' + (isActive ? 'var(--primary)' : '#fff') + '; color:' + (isActive ? '#fff' : 'var(--ink-900)') + '; cursor:pointer; font-weight:' + (isActive ? '700' : '500') + '; font-family:inherit; min-width:30px;';
        pageBtn.addEventListener('click', function () {
          currentPage = pageNum; applyFilter();
        });
        pageControls.appendChild(pageBtn);
      })(p);
    }

    var nextBtn = document.createElement('button');
    nextBtn.type = 'button';
    nextBtn.innerHTML = 'Next <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left:4px;"><polyline points="9 18 15 12 9 6"></polyline></svg>';
    nextBtn.disabled = currentPage === totalPages;
    nextBtn.style.cssText = 'padding:5px 10px; font-size:12px; border:1px solid var(--border); border-radius:6px; background:' + (currentPage === totalPages ? 'var(--bg)' : '#fff') + '; color:' + (currentPage === totalPages ? 'var(--ink-300)' : 'var(--ink-900)') + '; cursor:' + (currentPage === totalPages ? 'not-allowed' : 'pointer') + '; font-family:inherit; font-weight:500; display:inline-flex; align-items:center;';
    nextBtn.addEventListener('click', function () {
      if (currentPage < totalPages) { currentPage++; applyFilter(); }
    });
    pageControls.appendChild(nextBtn);
  }

  function applyFilter() {
    var q = (search.value || '').trim().toLowerCase();
    var st = status.value || '';
    
    // First, find all matching rows based on search & filter
    var matchingRows = rows.filter(function (row) {
      var matchQ = !q || (row.getAttribute('data-search') || '').indexOf(q) !== -1;
      var matchS = !st || row.getAttribute('data-status') === st;
      return matchQ && matchS;
    });

    var totalMatching = matchingRows.length;
    var totalPages = Math.max(1, Math.ceil(totalMatching / perPage));
    if (currentPage > totalPages) currentPage = totalPages;

    // Hide everything initially
    rows.forEach(function (row) { row.style.display = 'none'; });

    // Show only the slice for the current page
    var startIndex = (currentPage - 1) * perPage;
    var endIndex = startIndex + perPage;
    matchingRows.slice(startIndex, endIndex).forEach(function (row) { 
        row.style.display = ''; 
    });

    if (emptyFilter) {
        emptyFilter.style.display = (totalMatching === 0 && total > 0) ? '' : 'none';
    }

    // Re-render pagination buttons
    renderPagination(totalMatching, totalPages);
  }

  if (search && status) {
    search.addEventListener('input', function() { currentPage = 1; applyFilter(); });
    status.addEventListener('change', function() { currentPage = 1; applyFilter(); });
    applyFilter(); // Run immediately on load
  }
})();
</script>