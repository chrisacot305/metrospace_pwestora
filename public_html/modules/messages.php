<?php
/**
 * modules/messages.php  (Lessor → "Messages")
 * Direct threads per tenant, filtered by building/property, plus formal notifications.
 */
$lessorId = $user['id'];
$error = '';

$NOTICE_TYPES = ['General Notice', 'Payment Reminder', 'Maintenance Update'];

// Fetch all properties owned by this lessor
$propertiesStmt = $pdo->prepare('SELECT id, name FROM properties WHERE lessor_id = ? ORDER BY name ASC');
$propertiesStmt->execute([$lessorId]);
$properties = $propertiesStmt->fetchAll();

// Fetch all tenants with their property info and unit
$tenantsStmt = $pdo->prepare('
    SELECT t.id, t.tenant_name, t.business_name, t.unit_label, t.property_id, p.name AS property_name 
    FROM tenants t 
    JOIN properties p ON p.id = t.property_id 
    WHERE t.lessor_id = ? 
    ORDER BY p.name ASC, t.tenant_name ASC
');
$tenantsStmt->execute([$lessorId]);
$allTenants = $tenantsStmt->fetchAll();

// Filter by selected building/property if requested
$selectedPropertyId = (isset($_GET['property_id']) && $_GET['property_id'] !== '') ? (int) $_GET['property_id'] : null;

$tenants = $allTenants;
if ($selectedPropertyId !== null) {
    $tenants = array_values(array_filter($allTenants, function ($t) use ($selectedPropertyId) {
        return (int) $t['property_id'] === $selectedPropertyId;
    }));
}

/** Get (or lazily create) the thread between this lessor and a tenant. */
function get_or_create_thread(PDO $pdo, int $lessorId, int $tenantId): int {
    $stmt = $pdo->prepare('SELECT id FROM message_threads WHERE lessor_id = ? AND tenant_id = ?');
    $stmt->execute([$lessorId, $tenantId]);
    $row = $stmt->fetch();
    if ($row) return (int) $row['id'];

    $pdo->prepare('INSERT INTO message_threads (lessor_id, tenant_id) VALUES (?, ?)')->execute([$lessorId, $tenantId]);
    return (int) $pdo->lastInsertId();
}

$composing = isset($_GET['compose']);

// ---- Send a direct message ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    csrf_check();
    $tenantId = (int) $_POST['tenant_id'];
    $body = trim($_POST['body'] ?? '');

    $check = $pdo->prepare('SELECT id FROM tenants WHERE id = ? AND lessor_id = ?');
    $check->execute([$tenantId, $lessorId]);

    try {
        if ($check->fetch() && $body !== '') {
            $threadId = get_or_create_thread($pdo, $lessorId, $tenantId);
            $pdo->prepare('INSERT INTO messages (thread_id, sender, body) VALUES (?, "lessor", ?)')->execute([$threadId, $body]);
        }
        $propUrl = $selectedPropertyId ? '&property_id=' . $selectedPropertyId : '';
        echo '<script>if(window.history.replaceState){window.history.replaceState(null,null,"/dashboard.php?page=messages' . $propUrl . '&tenant=' . $tenantId . '");}</script>';
        $_GET['tenant'] = $tenantId; // Ensure the active tenant stays selected
    } catch (Throwable $e) {
        error_log('Send message failed: ' . $e->getMessage());
        $error = 'Could not send this message — the error has been logged. Please try again.';
    }
}

// ---- Broadcast a formal notification ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    csrf_check();
    $targetBuilding = $_POST['target_building'] ?? 'all';
    $isAllTenants = isset($_POST['all_tenants']);
    $type = in_array($_POST['notice_type'] ?? '', $NOTICE_TYPES, true) ? $_POST['notice_type'] : 'General Notice';
    $body = trim($_POST['notice_body'] ?? '');

    if ($isAllTenants) {
        if ($targetBuilding !== 'all') {
            $targetPropId = (int) $targetBuilding;
            $recipientIds = array_column(array_filter($allTenants, fn($t) => (int) $t['property_id'] === $targetPropId), 'id');
            $bName = 'Building';
            foreach ($properties as $p) {
                if ((int) $p['id'] === $targetPropId) { $bName = $p['name']; break; }
            }
            $audience = "All in $bName";
        } else {
            $recipientIds = array_column($allTenants, 'id');
            $audience = 'all_tenants';
        }
    } else {
        $recipientIds = array_map('intval', $_POST['recipient_ids'] ?? []);
        $valid = array_column($allTenants, 'id');
        $recipientIds = array_values(array_intersect($recipientIds, $valid));
        $audience = count($recipientIds) . ' selected';
    }

    if (!$recipientIds || $body === '') {
        $error = 'Select at least one recipient and write a message.';
    } else {
        try {
            foreach ($recipientIds as $tid) {
                $threadId = get_or_create_thread($pdo, $lessorId, $tid);
                $pdo->prepare('INSERT INTO messages (thread_id, sender, notice_type, body) VALUES (?, "lessor", ?, ?)')
                    ->execute([$threadId, $type, $body]);
            }
            $pdo->prepare('INSERT INTO notifications_log (sender_id, audience, title, body) VALUES (?, ?, ?, ?)')
                ->execute([$lessorId, $audience, $type, $body]);
            audit_log($pdo, $lessorId, 'Sent notification', "$type to " . count($recipientIds) . ' tenant(s)');

            $propUrl = $selectedPropertyId ? '&property_id=' . $selectedPropertyId : '';
            $activeTenantUrl = isset($_GET['tenant']) ? '&tenant=' . (int)$_GET['tenant'] : '';
            echo '<script>if(window.history.replaceState){window.history.replaceState(null,null,"/dashboard.php?page=messages' . $propUrl . $activeTenantUrl . '");}</script>';
            $composing = false;
        } catch (Throwable $e) {
            error_log('Send notification failed: ' . $e->getMessage());
            $error = 'Could not send this notification — the error has been logged. Please try again.';
        }
    }
}

// Determine active tenant
$activeTenantId = isset($_GET['tenant']) ? (int) $_GET['tenant'] : null;
$activeTenant = null;

if ($activeTenantId) {
    foreach ($tenants as $t) {
        if ((int) $t['id'] === $activeTenantId) {
            $activeTenant = $t;
            break;
        }
    }
}

// Fallback to first tenant of the selected filter if not found
if (!$activeTenant && !empty($tenants)) {
    $activeTenant = $tenants[0];
    $activeTenantId = (int) $activeTenant['id'];
}

$thread = [];
if ($activeTenant) {
    $threadStmt = $pdo->prepare(
        'SELECT m.* FROM messages m
         JOIN message_threads mt ON mt.id = m.thread_id
         WHERE mt.lessor_id = ? AND mt.tenant_id = ?
         ORDER BY m.sent_at ASC'
    );
    $threadStmt->execute([$lessorId, $activeTenantId]);
    $thread = $threadStmt->fetchAll();
}

$sentLog = $pdo->prepare('SELECT * FROM notifications_log WHERE sender_id = ? ORDER BY sent_at DESC LIMIT 20');
$sentLog->execute([$lessorId]);
$sentLog = $sentLog->fetchAll();
?>

<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
  <div>
    <h2 style="margin:0 0 4px;">Messages</h2>
    <p style="color:var(--ink-500); margin:0;">Direct threads per tenant, filtered by building, plus formal broadcast notifications.</p>
  </div>
  <a href="/dashboard.php?page=messages&compose=1<?= $selectedPropertyId ? '&property_id=' . $selectedPropertyId : '' ?><?= $activeTenantId ? '&tenant=' . $activeTenantId : '' ?>"
     class="btn btn-primary" style="width:auto; height:fit-content; display:inline-flex; align-items:center; gap:6px; padding:8px 14px; font-size:13px; white-space:nowrap; flex-shrink:0;">
    <i class="bi bi-megaphone"></i> New Notification
  </a>
</div>

<?php if ($error): ?>
  <div class="error-msg"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (!$allTenants): ?>
  <div class="card"><p style="color:var(--ink-500); font-size:13px; margin:0;">You don't have any active tenants yet — messaging opens up once an application is approved.</p></div>
<?php else: ?>
<div class="chat-shell" style="height:600px;">
  <!-- SIDEBAR WITH BUILDING SELECTOR -->
  <div class="chat-sidebar" style="width:280px; display:flex; flex-direction:column; background:var(--surface);">
    
    <!-- PROPERTY FILTER DROPDOWN -->
    <div style="padding:12px 14px; border-bottom:1px solid var(--border); background:#fbfcfe;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
        <label for="buildingFilter" style="font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--ink-500); margin:0;">
          <i class="bi bi-building"></i> Filter Building
        </label>
        <span style="font-size:11px; font-weight:600; color:var(--primary); background:rgba(16,35,64,0.06); padding:1px 6px; border-radius:10px;">
          <?= count($tenants) ?> / <?= count($allTenants) ?>
        </span>
      </div>
      <select id="buildingFilter" onchange="switchBuilding(this.value)" style="width:100%; padding:8px 10px; border-radius:8px; border:1px solid var(--border); font-size:12.5px; font-weight:600; background:#fff; color:var(--ink-900); cursor:pointer;">
        <option value="" <?= ($selectedPropertyId === null) ? 'selected' : '' ?>>All Buildings (<?= count($allTenants) ?>)</option>
        <?php foreach ($properties as $prop): 
          $countInProp = count(array_filter($allTenants, fn($t) => (int)$t['property_id'] === (int)$prop['id']));
        ?>
          <option value="<?= $prop['id'] ?>" <?= ($selectedPropertyId === (int)$prop['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($prop['name']) ?> (<?= $countInProp ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- TENANT LIST -->
    <div style="flex:1; overflow-y:auto;">
      <?php if (!$tenants): ?>
        <div style="padding:32px 16px; text-align:center; color:var(--ink-500); font-size:12.5px;">
          <i class="bi bi-people" style="font-size:26px; display:block; margin-bottom:6px; color:var(--ink-300);"></i>
          No tenants in this building yet.
        </div>
      <?php else: ?>
        <?php foreach ($tenants as $t): ?>
          <a href="/dashboard.php?page=messages<?= $selectedPropertyId ? '&property_id=' . $selectedPropertyId : '' ?>&tenant=<?= $t['id'] ?>" 
             class="chat-tenant-btn <?= $t['id'] == $activeTenantId ? 'active' : '' ?>"
             style="display:block; text-decoration:none; padding:12px 14px; border-bottom:1px solid var(--border); transition:background 0.15s ease;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
              <span style="font-size:13px; font-weight:600; color:var(--ink-900);"><?= htmlspecialchars($t['tenant_name']) ?></span>
              <?php if (!empty($t['unit_label'])): ?>
                <span style="font-size:10px; font-weight:600; color:var(--ink-500); background:var(--bg); padding:1px 5px; border-radius:4px; border:1px solid var(--border);">
                  <?= htmlspecialchars($t['unit_label']) ?>
                </span>
              <?php endif; ?>
            </div>
            
            <?php if (!empty($t['business_name'])): ?>
              <div style="font-size:11.5px; color:var(--ink-600); font-weight:500; margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                <i class="bi bi-shop" style="font-size:11px; margin-right:2px; color:var(--ink-400);"></i><?= htmlspecialchars($t['business_name']) ?>
              </div>
            <?php endif; ?>

            <div style="margin-top:4px;">
              <span style="display:inline-flex; align-items:center; gap:4px; font-size:10px; font-weight:600; color:var(--primary); background:rgba(16,35,64,0.06); padding:2px 6px; border-radius:4px;">
                <i class="bi bi-geo-alt" style="font-size:9.5px;"></i> <?= htmlspecialchars($t['property_name']) ?>
              </span>
            </div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- CHAT MAIN CONVERSATION -->
  <div class="chat-main" style="display:flex; flex-direction:column; flex:1; min-width:0;">
    <?php if ($activeTenant): ?>
      <!-- ACTIVE CHAT HEADER -->
      <div style="padding:12px 20px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; background:#fff; flex-shrink:0;">
        <div>
          <div style="font-weight:700; font-size:14.5px; color:var(--ink-900); display:flex; align-items:center; gap:8px;">
            <span><?= htmlspecialchars($activeTenant['tenant_name']) ?></span>
            <?php if (!empty($activeTenant['business_name'])): ?>
              <span style="font-size:12px; font-weight:500; color:var(--ink-600); background:var(--bg); padding:2px 8px; border-radius:12px; border:1px solid var(--border);">
                <?= htmlspecialchars($activeTenant['business_name']) ?>
              </span>
            <?php endif; ?>
          </div>
          <div style="font-size:11.5px; color:var(--ink-500); margin-top:3px; display:flex; align-items:center; gap:8px;">
            <span style="color:var(--primary); font-weight:600;">
              <i class="bi bi-building"></i> <?= htmlspecialchars($activeTenant['property_name']) ?>
            </span>
            <?php if (!empty($activeTenant['unit_label'])): ?>
              <span>• Unit <?= htmlspecialchars($activeTenant['unit_label']) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <div>
          <a href="/dashboard.php?page=tenants" class="btn" style="font-size:11.5px; padding:5px 12px; height:auto; color:var(--ink-700); border:1px solid var(--border); background:#fff; display:inline-flex; align-items:center; gap:5px;" title="View in Tenants Roster">
            <i class="bi bi-person-badge"></i> Tenant Info
          </a>
        </div>
      </div>

      <!-- MESSAGES THREAD -->
      <div class="chat-messages" id="chat-messages-container" style="flex:1; overflow-y:auto; padding:20px;">
        <?php if (!$thread): ?>
          <div style="text-align:center; padding:40px 20px; color:var(--ink-500);">
            <i class="bi bi-chat-dots" style="font-size:32px; color:var(--ink-300); display:block; margin-bottom:8px;"></i>
            <p style="font-size:13px; margin:0 0 4px; font-weight:600; color:var(--ink-700);">No messages yet with <?= htmlspecialchars($activeTenant['tenant_name']) ?></p>
            <p style="font-size:11.5px; margin:0;">Send a direct message below to start the conversation.</p>
          </div>
        <?php else: foreach ($thread as $m): $isMe = $m['sender'] === 'lessor'; ?>
          <div class="chat-bubble-row <?= $isMe ? 'me' : '' ?>">
            <div class="chat-bubble <?= $isMe ? 'me' : 'tenant' ?>">
              <?php if (!empty($m['notice_type'])): ?>
                <p class="notice-label"><i class="bi bi-megaphone-fill" style="margin-right:3px;"></i><?= htmlspecialchars($m['notice_type']) ?></p>
              <?php endif; ?>
              <div class="bubble-text"><?= nl2br(htmlspecialchars($m['body'])) ?></div>
              <p class="bubble-time"><?= (new DateTime($m['sent_at']))->format('M j, g:i A') ?></p>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <!-- INPUT BAR -->
      <form method="POST" class="chat-input-row" action="/dashboard.php?page=messages<?= $selectedPropertyId ? '&property_id=' . $selectedPropertyId : '' ?>&tenant=<?= $activeTenantId ?>">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="send_message" value="1">
        <input type="hidden" name="tenant_id" value="<?= $activeTenantId ?>">
        <input type="text" name="body" placeholder="Write a message to <?= htmlspecialchars($activeTenant['tenant_name']) ?>…" required autocomplete="off" autofocus>
        <button type="submit" class="chat-send-btn" title="Send message"><i class="bi bi-send-fill"></i></button>
      </form>

    <?php else: ?>
      <!-- EMPTY SELECTION STATE -->
      <div style="flex:1; display:flex; align-items:center; justify-content:center; flex-direction:column; color:var(--ink-500); padding:20px;">
        <i class="bi bi-chat-square-text" style="font-size:36px; color:var(--ink-300); margin-bottom:12px;"></i>
        <h4 style="margin:0 0 6px; color:var(--ink-900);">No Tenant Selected</h4>
        <p style="font-size:13px; margin:0;">Select a tenant from the sidebar to view their message history.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
  // Switch building filter and reset active tenant selection
  function switchBuilding(propertyId) {
    const url = new URL(window.location.href);
    if (propertyId) {
      url.searchParams.set('property_id', propertyId);
    } else {
      url.searchParams.delete('property_id');
    }
    url.searchParams.delete('tenant');
    window.location.href = url.toString();
  }

  // Scroll chat messages to bottom
  var chatContainer = document.getElementById('chat-messages-container');
  if (chatContainer) {
    chatContainer.scrollTop = chatContainer.scrollHeight;
  }
</script>
<?php endif; ?>

<!-- SENT NOTIFICATIONS LOG -->
<p style="font-weight:700; font-size:14px; margin:28px 0 12px;">Sent notifications history</p>
<div class="card" style="padding:0; overflow:hidden;">
  <div class="table-scroll">
  <table>
    <thead><tr><th>Type</th><th>Audience / Building</th><th>Message</th><th>Sent</th></tr></thead>
    <tbody>
      <?php if (!$sentLog): ?>
        <tr><td colspan="4" style="text-align:center; color:var(--ink-500);">Nothing sent yet.</td></tr>
      <?php else: foreach ($sentLog as $n): ?>
        <tr>
          <td><span class="badge" style="background:var(--accent-soft); color:var(--primary); font-weight:600;"><?= htmlspecialchars($n['title']) ?></span></td>
          <td style="color:var(--ink-700); font-weight:500;">
            <i class="bi bi-building" style="margin-right:4px; font-size:11px; color:var(--ink-400);"></i><?= htmlspecialchars($n['audience']) ?>
          </td>
          <td style="color:var(--ink-600);"><?= htmlspecialchars($n['body']) ?></td>
          <td style="color:var(--ink-500); font-size:12px; white-space:nowrap;"><?= (new DateTime($n['sent_at']))->format('M j, g:i A') ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
  </div>
</div>

<!-- COMPOSE BROADCAST NOTIFICATION DRAWER -->
<?php if ($composing && $allTenants): ?>
<div style="position:fixed; inset:0; background:rgba(0,0,0,.45); display:flex; justify-content:flex-end; z-index:100; backdrop-filter:blur(2px);">
  <div style="background:#fff; width:460px; max-width:92vw; height:100%; padding:24px; overflow-y:auto; box-shadow:-4px 0 24px rgba(0,0,0,0.12);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
      <div>
        <h3 style="margin:0 0 2px;">New Notification</h3>
        <p style="font-size:12px; color:var(--ink-500); margin:0;">Send formal notices or announcements to tenants.</p>
      </div>
      <a href="/dashboard.php?page=messages<?= $selectedPropertyId ? '&property_id=' . $selectedPropertyId : '' ?><?= $activeTenantId ? '&tenant=' . $activeTenantId : '' ?>" style="font-size:22px; color:var(--ink-500); text-decoration:none; padding:4px 8px; line-height:1;">&times;</a>
    </div>

    <form method="POST" id="notifyForm">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="send_notification" value="1">

      <!-- TARGET BUILDING DROPDOWN -->
      <div style="margin-bottom:14px;">
        <label for="modalBuildingSelect" style="font-size:12px; font-weight:700; margin-bottom:6px; display:block; color:var(--ink-800);">
          <i class="bi bi-building" style="margin-right:4px;"></i> Select Building
        </label>
        <select name="target_building" id="modalBuildingSelect" onchange="updateModalBuilding(this.value)" style="width:100%; padding:9px 12px; border-radius:8px; border:1px solid var(--border); font-size:13px; font-weight:600; color:var(--ink-900); background:#fff; cursor:pointer;">
          <option value="all" <?= ($selectedPropertyId === null) ? 'selected' : '' ?>>🏢 All Buildings (Broadcast to Everyone)</option>
          <?php foreach ($properties as $prop): ?>
            <option value="<?= $prop['id'] ?>" <?= ($selectedPropertyId === (int)$prop['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($prop['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <p style="font-size:12px; font-weight:700; margin:16px 0 8px; color:var(--ink-800);">Recipients</p>
      
      <!-- ALL TENANTS TOGGLE -->
      <label style="display:flex; align-items:center; justify-content:space-between; padding:12px; border:1px solid var(--border); border-radius:8px; margin-bottom:10px; cursor:pointer; background:#fbfcfe;">
        <span style="font-size:12.5px; font-weight:600; color:var(--ink-900);">
          <i class="bi bi-people" style="margin-right:6px; color:var(--primary);"></i>
          <span id="allTenantsLabel">
            <?= $selectedPropertyId ? 'All tenants in this building' : 'All tenants (across all buildings)' ?>
          </span>
        </span>
        <input type="checkbox" name="all_tenants" id="allTenantsBox" style="width:16px; height:16px;">
      </label>

      <!-- INDIVIDUAL TENANT CHECKBOXES FILTERABLE BY BUILDING -->
      <div id="tenantCheckboxes" style="max-height:190px; overflow-y:auto; border:1px solid var(--border); border-radius:8px; padding:8px; margin-bottom:14px; background:#fff;">
        <?php foreach ($allTenants as $t): ?>
          <label class="modal-tenant-item" data-property="<?= $t['property_id'] ?>" 
                 style="display:flex; align-items:center; justify-content:space-between; padding:8px 10px; border-radius:6px; margin-bottom:4px; cursor:pointer; transition:background 0.1s ease;">
            <div style="min-width:0; padding-right:10px;">
              <div style="font-size:12.5px; font-weight:600; color:var(--ink-900);"><?= htmlspecialchars($t['tenant_name']) ?></div>
              <div style="font-size:11px; color:var(--ink-500); display:flex; align-items:center; gap:4px; margin-top:1px;">
                <span><?= htmlspecialchars($t['property_name']) ?></span>
                <?php if (!empty($t['unit_label'])): ?>
                  <span>• Unit <?= htmlspecialchars($t['unit_label']) ?></span>
                <?php endif; ?>
              </div>
            </div>
            <input type="checkbox" name="recipient_ids[]" value="<?= $t['id'] ?>"
                   <?= ($activeTenantId == $t['id']) ? 'checked' : '' ?>
                   style="width:15px; height:15px; flex-shrink:0;">
          </label>
        <?php endforeach; ?>
      </div>

      <!-- NOTICE TYPE -->
      <p style="font-size:12px; font-weight:700; margin:16px 0 8px; color:var(--ink-800);">Notice Type</p>
      <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px;">
        <?php foreach ($NOTICE_TYPES as $i => $type): ?>
          <label style="font-size:11.5px; font-weight:600; padding:6px 12px; border-radius:999px; border:1px solid var(--border); cursor:pointer; background:var(--bg);">
            <input type="radio" name="notice_type" value="<?= $type ?>" <?= $i === 0 ? 'checked' : '' ?> style="margin-right:4px;"><?= $type ?>
          </label>
        <?php endforeach; ?>
      </div>

      <!-- MESSAGE BODY -->
      <div class="field">
        <label style="font-size:12px; font-weight:700; margin-bottom:6px; display:block; color:var(--ink-800);">Message</label>
        <textarea name="notice_body" rows="4" required placeholder="Write the notice or announcement here…" style="width:100%; padding:11px 13px; border:1px solid var(--border); border-radius:8px; font-size:13.5px; font-family:inherit; resize:vertical;"></textarea>
      </div>

      <button class="btn btn-primary" style="width:100%; margin-top:16px; display:flex; justify-content:center; align-items:center; gap:8px; padding:11px 16px; font-weight:600;" type="submit">
        <i class="bi bi-megaphone"></i> Send Notification
      </button>
    </form>
  </div>
</div>

<script>
  // Filter individual checkboxes in modal based on selected building
  function updateModalBuilding(propId) {
    const items = document.querySelectorAll('.modal-tenant-item');
    const allLabel = document.getElementById('allTenantsLabel');
    const buildingSelect = document.getElementById('modalBuildingSelect');
    const selectedText = buildingSelect.options[buildingSelect.selectedIndex].text;
    
    items.forEach(function(item) {
      const itemProp = item.getAttribute('data-property');
      if (propId === 'all' || itemProp === propId) {
        item.style.display = 'flex';
      } else {
        item.style.display = 'none';
        const cb = item.querySelector('input[type="checkbox"]');
        if (cb) cb.checked = false;
      }
    });

    if (allLabel) {
      if (propId === 'all') {
        allLabel.textContent = 'All tenants (across all buildings)';
      } else {
        allLabel.textContent = 'All tenants in ' + selectedText;
      }
    }
  }

  // Initialize modal building filter on open
  (function() {
    const bSelect = document.getElementById('modalBuildingSelect');
    if (bSelect) {
      updateModalBuilding(bSelect.value);
    }
  })();

  // Toggle disabling individual checkboxes when "All tenants" is checked
  document.getElementById('allTenantsBox').addEventListener('change', function () {
    const isChecked = this.checked;
    document.querySelectorAll('#tenantCheckboxes input[type="checkbox"]').forEach(function (cb) {
      cb.disabled = isChecked;
    });
  });
</script>
<?php endif; ?>