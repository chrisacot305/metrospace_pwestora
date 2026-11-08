<?php
/**
 * app/Views/lessor/maintenance.php — HTML for the "maintenance" screen (was the bottom half of modules/maintenance.php).
 * Data comes from app/Controllers/Lessor/MaintenanceController.php
 */
?>

<style>
  .maint-photo-thumb {
    display: block;
    position: relative;
    aspect-ratio: 1 / 1;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid var(--border);
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    background: #f8fafc;
  }
  .maint-photo-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.2s ease;
  }
  .maint-photo-thumb:hover img {
    transform: scale(1.05);
  }
</style>

<!-- FLOATING TOAST NOTIFICATIONS -->
<?php if ($msg || ($error && !$loggingTicket && !$reviewing)): ?>
  <div id="toast-notification" style="position:fixed; top:24px; right:24px; z-index:9999; transition: opacity 0.5s ease, transform 0.5s ease; transform: translateY(0); opacity: 1;">
    <?php if ($msg === 'advanced'): ?>
      <div class="success-msg" style="margin:0; box-shadow:0 4px 12px rgba(0,0,0,0.1); border-radius:8px; display:flex; align-items:center; gap:8px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--success);"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
        <span><strong>Ticket Updated!</strong> Successfully advanced to the next stage.</span>
      </div>
    <?php elseif ($msg === 'logged'): ?>
      <div class="success-msg" style="margin:0; box-shadow:0 4px 12px rgba(0,0,0,0.1); border-radius:8px; display:flex; align-items:center; gap:8px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--success);"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
        <span><strong>Ticket Logged!</strong> New maintenance ticket recorded successfully.</span>
      </div>
    <?php elseif ($msg === 'rejected'): ?>
      <div class="success-msg" style="margin:0; box-shadow:0 4px 12px rgba(0,0,0,0.1); border-radius:8px; display:flex; align-items:center; gap:8px; background:var(--error-soft); color:var(--error); border:1px solid rgba(220,38,38,0.2);">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--error);"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
        <span><strong>Ticket Rejected.</strong> The maintenance request has been removed.</span>
      </div>
    <?php endif; ?>

    <?php if ($error && !$loggingTicket && !$reviewing): ?>
      <div class="error-msg" style="margin:0; box-shadow:0 4px 12px rgba(0,0,0,0.1); border-radius:8px; display:flex; align-items:center; gap:8px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--error);"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div style="margin-bottom:20px;">
  <h2 style="margin:0 0 4px;">Maintenance</h2>
</div>

<!-- SUMMARY KPI CARDS -->
<div class="kpi-grid" style="margin-bottom:24px;">
  <div class="card">
    <div class="kpi-label">Open Tickets</div>
    <div class="kpi-value"><?= $openTicketsCount ?></div>
  </div>
  <div class="card" style="border-left: 3px solid <?= $urgentHighCount > 0 ? '#D97706' : 'var(--border)' ?>;">
    <div class="kpi-label">Urgent / High Priority</div>
    <div class="kpi-value" style="color:<?= $urgentHighCount > 0 ? '#D97706' : 'inherit' ?>;"><?= $urgentHighCount ?></div>
  </div>
  <div class="card" style="border-left: 3px solid <?= $overdueCount > 0 ? 'var(--error)' : 'var(--border)' ?>;">
    <div class="kpi-label">Overdue SLA</div>
    <div class="kpi-value" style="color:<?= $overdueCount > 0 ? 'var(--error)' : 'inherit' ?>;"><?= $overdueCount ?></div>
  </div>
  <div class="card">
    <div class="kpi-label">Completed</div>
    <div class="kpi-value" style="color:var(--success);"><?= $completedCount ?></div>
  </div>
</div>

<!-- TABLE CARD -->
<div class="card" style="padding:0; overflow:hidden; margin-bottom:28px;">

  <!-- FILTER BAR & LOG BUTTON -->
  <div style="display:flex; gap:10px; flex-wrap:nowrap; align-items:center; padding:14px 16px; border-bottom:1px solid var(--border); overflow-x:auto;">

    <div style="flex:1; min-width:220px; position:relative;">
      <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--ink-400);"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
      <input id="ticket-search" type="search" placeholder="Search ticket ID, title, property, tenant, or contractor..."
             autocomplete="off"
             style="width:100%; box-sizing:border-box; padding:8px 12px 8px 34px; border:1px solid var(--border); border-radius:8px; font:inherit; font-size:13px; background:var(--bg); color:var(--ink-900);">
    </div>

    <a href="/dashboard.php?page=maintenance&log=1" class="btn btn-primary" style="width:auto; display:inline-flex; align-items:center; gap:6px; padding:6px 14px; font-size:12px; flex-shrink:0;">
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> 
      Log Ticket
    </a>

    <!-- Priority Filter -->
    <select id="ticket-priority-filter"
            style="padding:8px 12px; border:1px solid var(--border); border-radius:8px; font:inherit; font-size:13px; background:#fff; color:var(--ink-900); min-width:140px; flex-shrink:0;">
      <option value="">All priorities</option>
      <option value="normal">Normal (Blue)</option>
      <option value="high">High (Orange)</option>
      <option value="urgent">Urgent (Red)</option>
    </select>

    <!-- Stage Filter -->
    <select id="ticket-status-filter"
            style="padding:8px 12px; border:1px solid var(--border); border-radius:8px; font:inherit; font-size:13px; background:#fff; color:var(--ink-900); min-width:150px; flex-shrink:0;">
      <option value="">All stages</option>
      <?php foreach($STAGES as $i => $stage): ?>
          <option value="<?= $i ?>"><?= $stage ?></option>
      <?php endforeach; ?>
    </select>
    <span id="ticket-filter-count" style="font-size:12.5px; color:var(--ink-500); white-space:nowrap; flex-shrink:0;"></span>

  </div>
  <!-- END FILTER BAR -->

  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>Ticket & Issue</th>
          <th>Property & Tenant</th>
          <th>Priority</th>
          <th>Stage & Progress</th>
          <th>Contractor</th>
          <th>SLA Status</th>
          <th style="text-align:right;"></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$tickets): ?>
          <tr><td colspan="7" style="text-align:center; padding:28px; color:var(--ink-500);">No maintenance tickets yet.</td></tr>
        <?php else: foreach ($tickets as $t):
            [$catLabel, $catIcon] = $CATEGORIES[$t['category']] ?? ['Other', '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"></circle><circle cx="19" cy="12" r="1"></circle><circle cx="5" cy="12" r="1"></circle></svg>'];
            [$slaText, $slaGood] = sla_label($t, $LAST_STAGE);
            $prioMeta = get_priority_meta($t['priority'] ?? 'medium');
            $pct = round(($t['stage'] / $LAST_STAGE) * 100);
            $ticketCode = 'MT-' . str_pad($t['id'], 4, '0', STR_PAD_LEFT);

            $photoCount = 0;
            if (!empty($t['photos'])) {
                $decodedPhotos = json_decode($t['photos'], true);
                $photoCount = is_array($decodedPhotos) ? count($decodedPhotos) : 1;
            }

            $searchBlob = strtolower(trim(implode(' ', array_filter([
                $ticketCode,
                $t['title'],
                $t['tenant_name'],
                $t['property_name'] ?? '',
                $t['contractor'] ?? '',
                $catLabel,
                $prioMeta['label'],
                $STAGES[$t['stage']]
            ]))));
        ?>
          <tr class="ticket-row" data-status="<?= $t['stage'] ?>" data-priority="<?= strtolower($prioMeta['label']) ?>" data-search="<?= htmlspecialchars($searchBlob) ?>">
            <!-- Ticket & Issue -->
            <td style="min-width:230px; max-width:300px;">
              <div style="display:flex; align-items:flex-start; gap:10px;">
                <div style="width:34px; height:34px; border-radius:8px; background:var(--bg); display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:2px; border:1px solid var(--border); color:var(--primary);" title="<?= htmlspecialchars($catLabel) ?>">
                  <?= $catIcon ?>
                </div>
                <div style="min-width:0;">
                  <div style="font-weight:700; color:var(--ink-900); font-size:13px; line-height:1.35; word-break:break-word;">
                    <?= htmlspecialchars($t['title']) ?>
                  </div>
                  <div style="display:flex; align-items:center; gap:6px; margin-top:4px; font-size:11.5px; color:var(--ink-500); flex-wrap:wrap;">
                    <span style="font-family:ui-monospace,SFMono-Regular,Consolas,monospace; font-weight:600; color:var(--ink-700);"><?= $ticketCode ?></span>
                    <span>·</span>
                    <span><?= htmlspecialchars($catLabel) ?></span>
                    <?php if ($photoCount > 0): ?>
                      <span>·</span>
                      <span style="color:var(--primary); font-weight:600; display:inline-flex; align-items:center; gap:3px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg> <?= $photoCount ?>
                      </span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </td>

            <!-- Property & Tenant -->
            <td style="min-width:150px;">
              <div style="font-weight:600; color:var(--ink-900); font-size:13px;"><?= htmlspecialchars($t['property_name'] ?? 'Unknown Property') ?></div>
              <div style="font-size:11.5px; color:var(--ink-500); margin-top:2px;"><?= htmlspecialchars($t['tenant_name']) ?></div>
            </td>

            <!-- Priority -->
            <td>
              <span style="display:inline-block; font-size:11px; font-weight:700; color:<?= $prioMeta['color'] ?>; background:<?= $prioMeta['bg'] ?>; border:1px solid <?= $prioMeta['border'] ?>; padding:3px 9px; border-radius:6px; letter-spacing:0.3px; text-transform:uppercase;">
                <?= $prioMeta['label'] ?>
              </span>
            </td>

            <!-- Stage & Progress -->
            <td style="min-width:165px;">
              <div style="display:flex; justify-content:space-between; align-items:center; font-size:12px; margin-bottom:5px;">
                <span style="font-weight:600; color:<?= (int)$t['stage'] === $LAST_STAGE ? 'var(--success)' : 'var(--ink-900)' ?>;">
                  <?= htmlspecialchars($STAGES[$t['stage']]) ?>
                </span>
                <span style="font-size:11px; color:var(--ink-400); font-weight:600;"><?= $pct ?>%</span>
              </div>
              <div style="width:100%; height:6px; border-radius:999px; background:var(--border); overflow:hidden;">
                <div style="height:6px; border-radius:999px; width:<?= $pct ?>%; background:<?= (int)$t['stage'] === $LAST_STAGE ? 'var(--success)' : 'var(--accent)' ?>; transition:width 0.3s ease;"></div>
              </div>
            </td>

            <!-- Contractor -->
            <td style="color:var(--ink-500); font-size:12.5px;">
              <?php if (!empty($t['contractor'])): ?>
                <span style="color:var(--ink-800); font-weight:500; display:inline-flex; align-items:center;">
                  <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--ink-400); margin-right:4px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle><circle cx="19" cy="11" r="2"></circle></svg>
                  <?= htmlspecialchars($t['contractor']) ?>
                </span>
              <?php else: ?>
                <span style="color:var(--ink-300); font-style:italic;">Unassigned</span>
              <?php endif; ?>
            </td>

            <!-- SLA Status -->
            <td>
              <?php if ($slaText === 'Overdue'): ?>
                <span class="badge badge-rejected"><?= $slaText ?></span>
              <?php elseif ($slaText === 'Met'): ?>
                <span class="badge badge-approved" style="display:inline-flex; align-items:center;"><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="margin-right:2px;"><polyline points="20 6 9 17 4 12"></polyline></svg> <?= $slaText ?></span>
              <?php elseif ($slaText === '—'): ?>
                <span style="color:var(--ink-300); font-size:12px;">No SLA</span>
              <?php else: ?>
                <span class="badge badge-pending" style="display:inline-flex; align-items:center;"><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:3px;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> <?= $slaText ?></span>
              <?php endif; ?>
            </td>

            <!-- Action -->
            <td style="text-align:right;">
              <a href="/dashboard.php?page=maintenance&review=<?= $t['id'] ?>"
                 class="btn <?= (int)$t['stage'] === $LAST_STAGE ? '' : 'btn-primary' ?>"
                 style="width:auto; padding:6px 14px; display:inline-flex; align-items:center; gap:5px; font-size:12px; <?= (int)$t['stage'] === $LAST_STAGE ? 'background:var(--bg); color:var(--ink-900); border:1px solid var(--border);' : '' ?>">
                <?php if ((int)$t['stage'] === $LAST_STAGE): ?>
                  <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg> View
                <?php else: ?>
                  Manage
                <?php endif; ?>
              </a>
            </td>
          </tr>
        <?php endforeach; endif; ?>

        <tr id="ticket-empty-filter" style="display:none;">
          <td colspan="7" style="text-align:center; padding:24px; color:var(--ink-500);">No tickets match your search or filter.</td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- CENTERED PAGINATION BAR -->
  <?php if ($tickets): ?>
  <div id="ticket-pagination-bar" style="display:flex; justify-content:center; align-items:center; padding:12px 16px; border-top:1px solid var(--border); background:var(--surface);">
    <div id="ticket-page-controls" style="display:flex; gap:6px; align-items:center; justify-content:center;"></div>
  </div>
  <?php endif; ?>

</div>

<!-- LOG TICKET POPUP DRAWER -->
<?php if ($loggingTicket || ($error && !$reviewing)): ?>
<div style="position:fixed; inset:0; background:rgba(0,0,0,.4); display:flex; justify-content:flex-end; z-index:50;">
  <div style="background:#fff; width:440px; max-width:92vw; height:100%; padding:24px; overflow-y:auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <h3 style="margin:0;">Log a ticket</h3>
      <a href="/dashboard.php?page=maintenance" style="font-size:20px; color:var(--ink-500); text-decoration:none;">&times;</a>
    </div>

    <p style="font-size:12.5px; color:var(--ink-500); margin-bottom:16px;">
      Received a maintenance issue by phone or in person? Log it so it's tracked the same way.
    </p>

    <?php if ($error): ?>
      <div class="error-msg" style="margin-bottom:16px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$tenants): ?>
      <p style="font-size:13px; color:var(--ink-500);">You don't have any active tenants yet.</p>
    <?php else: ?>
      <form id="maint-log-form" method="POST" action="/dashboard.php?page=maintenance">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="log_ticket" value="1">

        <div class="field">
          <label>Property</label>
          <select id="maint_property_select" name="property_id" required>
            <option value="">Select property…</option>
            <?php foreach ($properties as $p): ?>
              <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label>Tenant</label>
          <select id="maint_tenant_select" name="tenant_id" required disabled>
            <option value="">Select a property first…</option>
            <?php foreach ($tenants as $t): ?>
              <option value="<?= $t['id'] ?>" data-property="<?= $t['property_id'] ?>"><?= htmlspecialchars($t['tenant_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label>Category</label>
          <select name="category" required>
            <option value="">Select category…</option>
            <?php foreach ($CATEGORIES as $id => [$label, $icon]): ?>
              <option value="<?= $id ?>"><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label>Priority Level</label>
          <select name="priority">
            <option value="medium">Normal (Blue)</option>
            <option value="high">High (Orange)</option>
            <option value="urgent">Urgent (Red)</option>
          </select>
        </div>

        <div class="field">
          <label>Issue Title</label>
          <input type="text" name="title" placeholder="e.g. Flickering lights — dining area" required>
        </div>

        <div class="field">
          <label>Contractor (optional)</label>
          <input type="text" name="contractor">
        </div>

        <div class="field">
          <label>Service Level Agreement (optional)</label>
          <input type="number" name="sla_hours" min="1" placeholder="e.g. 24">
        </div>

        <button class="btn btn-primary" type="submit" style="width:100%; margin-top:8px;">Log Ticket</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- REVIEW TICKET POPUP DRAWER -->
<?php if ($reviewing):
    [$slaText, $slaGood] = sla_label($reviewing, $LAST_STAGE);
    $revPrio = get_priority_meta($reviewing['priority'] ?? 'medium');
?>
<div style="position:fixed; inset:0; background:rgba(0,0,0,.4); display:flex; justify-content:flex-end; z-index:50;">
  <div style="background:#fff; width:420px; max-width:92vw; height:100%; padding:24px; overflow-y:auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <h3 style="margin:0;">MT-<?= str_pad($reviewing['id'], 4, '0', STR_PAD_LEFT) ?></h3>
      <a href="/dashboard.php?page=maintenance" style="font-size:20px; color:var(--ink-500); text-decoration:none;">&times;</a>
    </div>

    <div class="card" style="margin-bottom:16px;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; padding-bottom:8px; border-bottom:1px solid var(--border);">
        <span style="font-size:11px; font-weight:700; color:<?= $revPrio['color'] ?>; background:<?= $revPrio['bg'] ?>; border:1px solid <?= $revPrio['border'] ?>; padding:3px 10px; border-radius:6px; letter-spacing:0.3px; text-transform:uppercase;">
          Priority: <?= $revPrio['label'] ?>
        </span>
        <span class="badge <?= $slaGood ? 'badge-approved' : 'badge-pending' ?>"><?= $slaText ?></span>
      </div>
      <p style="margin:4px 0; font-size:13px;"><strong>Property:</strong> <?= htmlspecialchars($reviewing['property_name'] ?? 'Unknown') ?></p>
      <p style="margin:4px 0; font-size:13px;"><strong>Tenant:</strong> <?= htmlspecialchars($reviewing['tenant_name']) ?></p>
      <p style="margin:4px 0; font-size:13px;"><strong>Issue:</strong> <?= htmlspecialchars($reviewing['title']) ?></p>
      <p style="margin:4px 0; font-size:13px;"><strong>Contractor:</strong> <?= htmlspecialchars($reviewing['contractor'] ?: '—') ?></p>
      <p style="margin:4px 0; font-size:13px;"><strong>SLA:</strong> <?= $slaText ?></p>
    </div>

    <!-- PHOTOS SECTION -->
    <?php
      $reviewPhotos = [];
      if (!empty($reviewing['photos'])) {
          $decoded = json_decode($reviewing['photos'], true);
          if (is_array($decoded)) {
              $reviewPhotos = $decoded;
          } else {
              $reviewPhotos = [$reviewing['photos']];
          }
      }
    ?>
    <?php if (!empty($reviewPhotos)): ?>
      <div class="card" style="margin-bottom:16px; padding:14px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
          <p style="margin:0; font-size:12.5px; font-weight:700; color:var(--ink-900); display:inline-flex; align-items:center;">
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--primary); margin-right:4px;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg> Ticket Photos (<?= count($reviewPhotos) ?>)
          </p>
          <span style="font-size:11px; color:var(--ink-400);">Click to enlarge</span>
        </div>
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(80px, 1fr)); gap:8px;">
          <?php foreach ($reviewPhotos as $photoPath): 
              $cleanPath = '/' . ltrim($photoPath, '/');
          ?>
            <a href="<?= htmlspecialchars($cleanPath) ?>" target="_blank" rel="noopener" title="Click to view full image" class="maint-photo-thumb">
              <img src="<?= htmlspecialchars($cleanPath) ?>" alt="Issue Photo">
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <p style="font-size:12px; font-weight:700; margin-bottom:12px;">Timeline</p>
    <div style="position:relative; padding-left:24px; margin-bottom:20px;">
      <div style="position:absolute; left:7px; top:4px; bottom:4px; width:2px; background:var(--border);"></div>
      <?php foreach ($STAGES as $i => $label): $done = $i <= $reviewing['stage']; ?>
        <div style="position:relative; padding-bottom:16px;">
          <div style="position:absolute; left:-24px; width:16px; height:16px; border-radius:50%; display:flex; align-items:center; justify-content:center;
                      background:<?= $done ? 'var(--accent)' : '#fff' ?>; border:2px solid <?= $done ? 'var(--accent)' : 'var(--border)' ?>;">
            <?php if ($done): ?><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="color:#fff;"><polyline points="20 6 9 17 4 12"></polyline></svg><?php endif; ?>
          </div>
          <p style="font-size:12.5px; font-weight:500; margin:0; color:<?= $done ? 'var(--ink-900)' : 'var(--ink-300)' ?>;"><?= $label ?></p>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($reviewing['stage'] < $LAST_STAGE): ?>
      <div style="display:flex; flex-direction:column; gap:12px;">
        <form id="maint-advance-form" method="POST" action="/dashboard.php?page=maintenance&review=<?= $reviewing['id'] ?>">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="advance_ticket_id" value="<?= $reviewing['id'] ?>">

          <?php if ($reviewing['stage'] == 2): ?>
            <div style="margin-bottom:12px;">
              <label style="display:block; font-size:12px; font-weight:700; margin-bottom:6px; color:var(--ink-900);">Assign Contractor</label>
              <input type="text" name="assign_contractor" placeholder="Enter contractor name or company..." required style="width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; box-sizing:border-box; font-family:inherit; font-size:13px;">
            </div>
          <?php endif; ?>

          <div style="display:flex; gap:10px;">
            <button class="btn btn-primary" style="flex:2; display:flex; align-items:center; justify-content:center; padding:10px; border-radius:8px; font-weight:600;" type="submit">
              <?= $reviewing['stage'] == 2 ? 'Assign & Advance' : 'Advance to Next Stage' ?> <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left:6px;"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
            </button>
            <button type="button" onclick="if(confirm('Are you sure you want to reject this ticket? It will be permanently removed.')){ document.getElementById('reject-ticket-form-<?= $reviewing['id'] ?>').submit(); }" class="btn" style="flex:1; display:flex; align-items:center; justify-content:center; padding:10px; border-radius:8px; font-weight:600; background:var(--error-soft); color:var(--error); border:none;">
              <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg> Reject
            </button>
          </div>
        </form>
        
        <!-- Hidden Rejection Form -->
        <form id="reject-ticket-form-<?= $reviewing['id'] ?>" method="POST" action="/dashboard.php?page=maintenance" style="display:none;">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="reject_ticket_id" value="<?= $reviewing['id'] ?>">
        </form>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<script>
(function () {
  // Clean URL history state after POST actions
  <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error): ?>
  if (window.history && window.history.replaceState) {
    var cleanUrl = '/dashboard.php?page=maintenance<?= isset($_GET['review']) && $msg !== 'rejected' ? '&review=' . (int)$_GET['review'] : '' ?><?= $msg ? '&msg=' . htmlspecialchars($msg) : '' ?>';
    window.history.replaceState(null, null, cleanUrl);
  }
  <?php endif; ?>

  // Auto-dismiss toast notification
  var toast = document.getElementById('toast-notification');
  if (toast) {
    setTimeout(function() {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(-20px)';
      setTimeout(function() { toast.remove(); }, 500);
    }, 3500);
  }

  // Prevent double-submission on forms
  var logForm = document.getElementById('maint-log-form');
  if (logForm) {
    logForm.addEventListener('submit', function() {
      var btn = logForm.querySelector('button[type="submit"]');
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Logging...';
      }
    });
  }

  var advForm = document.getElementById('maint-advance-form');
  if (advForm) {
    advForm.addEventListener('submit', function() {
      var btn = advForm.querySelector('button[type="submit"]');
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Advancing...';
      }
    });
  }

  // Cascading Property -> Tenant dropdown in Log Ticket drawer
  var propSel = document.getElementById('maint_property_select');
  var tenSel = document.getElementById('maint_tenant_select');
  if (propSel && tenSel) {
    var allOptions = Array.prototype.slice.call(tenSel.querySelectorAll('option[data-property]'));
    propSel.addEventListener('change', function() {
      var pid = this.value;
      tenSel.textContent = '';
      var defaultOpt = document.createElement('option');
      defaultOpt.value = '';
      defaultOpt.textContent = 'Select tenant...';
      tenSel.appendChild(defaultOpt);

      var hasTenants = false;
      allOptions.forEach(function(opt) {
        if (opt.getAttribute('data-property') === pid) {
          tenSel.appendChild(opt.cloneNode(true));
          hasTenants = true;
        }
      });

      if (!pid) {
        defaultOpt.textContent = 'Select a property first...';
        tenSel.disabled = true;
      } else if (!hasTenants) {
        defaultOpt.textContent = 'No tenants found here';
        tenSel.disabled = true;
      } else {
        tenSel.disabled = false;
      }
    });
    tenSel.textContent = '';
    var initOpt = document.createElement('option');
    initOpt.value = '';
    initOpt.textContent = 'Select a property first...';
    tenSel.appendChild(initOpt);
    tenSel.disabled = true;
  }

  // Table Filtering & Pagination
  var search = document.getElementById('ticket-search');
  var status = document.getElementById('ticket-status-filter');
  var priority = document.getElementById('ticket-priority-filter');
  var count = document.getElementById('ticket-filter-count');
  var empty = document.getElementById('ticket-empty-filter');
  var pageControls = document.getElementById('ticket-page-controls');
  var paginationBar = document.getElementById('ticket-pagination-bar');
  var rows = Array.prototype.slice.call(document.querySelectorAll('.ticket-row'));
  var total = rows.length;

  var currentPage = 1;
  var perPage = 5;

  function renderPagination(totalMatching, totalPages) {
    if (!paginationBar) return;
    if (totalMatching === 0) {
      paginationBar.style.display = 'none';
      return;
    }
    paginationBar.style.display = 'flex';
    if (!pageControls) return;
    pageControls.textContent = '';

    var prevBtn = document.createElement('button');
    prevBtn.type = 'button';
    prevBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><polyline points="15 18 9 12 15 6"></polyline></svg> Prev';
    prevBtn.disabled = currentPage === 1;
    prevBtn.style.cssText = 'padding:5px 10px; font-size:12px; border:1px solid var(--border); border-radius:6px; background:' + (currentPage === 1 ? 'var(--bg)' : '#fff') + '; color:' + (currentPage === 1 ? 'var(--ink-300)' : 'var(--ink-900)') + '; cursor:' + (currentPage === 1 ? 'not-allowed' : 'pointer') + '; font-family:inherit; font-weight:500; display:inline-flex; align-items:center;';
    prevBtn.addEventListener('click', function () {
      if (currentPage > 1) {
        currentPage--;
        updateTable();
      }
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
          currentPage = pageNum;
          updateTable();
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
      if (currentPage < totalPages) {
        currentPage++;
        updateTable();
      }
    });
    pageControls.appendChild(nextBtn);
  }

  function updateTable() {
    if (!search || !status) return;
    var q = (search.value || '').trim().toLowerCase();
    var st = status.value || '';
    var pr = (priority ? priority.value : '') || '';

    var matchingRows = rows.filter(function (row) {
      var matchQ = !q || (row.getAttribute('data-search') || '').indexOf(q) !== -1;
      var matchS = !st || row.getAttribute('data-status') === st;
      var matchP = !pr || row.getAttribute('data-priority') === pr;
      return matchQ && matchS && matchP;
    });

    var totalMatching = matchingRows.length;
    var totalPages = Math.max(1, Math.ceil(totalMatching / perPage));
    if (currentPage > totalPages) currentPage = totalPages;

    rows.forEach(function (row) {
      row.style.display = 'none';
    });

    var startIndex = (currentPage - 1) * perPage;
    var endIndex = startIndex + perPage;
    matchingRows.slice(startIndex, endIndex).forEach(function (row) {
      row.style.display = '';
    });

    if (empty && total > 0) empty.style.display = totalMatching ? 'none' : '';
    if (count) {
      count.textContent = (q || st || pr) ? (totalMatching + ' of ' + total) : (total + ' ticket' + (total === 1 ? '' : 's'));
    }

    renderPagination(totalMatching, totalPages);
  }

  if (search && status) {
    search.addEventListener('input', function () {
      currentPage = 1;
      updateTable();
    });
    status.addEventListener('change', function () {
      currentPage = 1;
      updateTable();
    });
    if (priority) {
      priority.addEventListener('change', function () {
        currentPage = 1;
        updateTable();
      });
    }
    updateTable();
  }
})();
</script>