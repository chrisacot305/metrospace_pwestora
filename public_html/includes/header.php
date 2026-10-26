<?php
/**
 * includes/header.php
 * Expects: $user (from current_user()), $page (current ?page= id)
 */
require_once __DIR__ . '/sidebar_nav.php';
$nav = $NAV_BY_ROLE[$user['role']] ?? [];
$roleLabel = $user['role'] === 'superadmin' ? 'Super Admin' : 'Admin (Lessor)';

// Notification bell — currently only lessors receive admin notices.
$notices = [];
$allNotices = [];
$unreadCount = 0;

if ($user['role'] === 'lessor') {
    
    // --- HANDLE MARKING AS READ GLOBALLY ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
        csrf_check();
        $pdo->prepare('UPDATE notification_recipients SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL')->execute([$user['id']]);
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read_id'])) {
        csrf_check();
        $pdo->prepare('UPDATE notification_recipients SET read_at = NOW() WHERE notification_id = ? AND user_id = ?')->execute([(int)$_POST['mark_read_id'], $user['id']]);
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    // ---------------------------------------

    // Fetch up to 50 for the full modal view
    $stmt = $pdo->prepare(
        "SELECT nl.*, nr.read_at FROM notification_recipients nr
         JOIN notifications_log nl ON nl.id = nr.notification_id
         WHERE nr.user_id = ? ORDER BY nl.sent_at DESC LIMIT 50"
    );
    $stmt->execute([$user['id']]);
    $allNotices = $stmt->fetchAll();
    
    // Slice the first 5 for the quick dropdown
    $notices = array_slice($allNotices, 0, 5);

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM notification_recipients WHERE user_id = ? AND read_at IS NULL');
    $countStmt->execute([$user['id']]);
    $unreadCount = (int) $countStmt->fetchColumn();

    $pendingPaymentsStmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE lessor_id = ? AND (logged_by IS NULL OR note LIKE '[PENDING]%') AND (note NOT LIKE '[DECLINED%')");
    $pendingPaymentsStmt->execute([$user['id']]);
    $pendingPaymentsCount = (int) $pendingPaymentsStmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= APP_NAME ?> · <?= htmlspecialchars(ucfirst($page)) ?></title>
<link rel="stylesheet" href="/assets/css/style.css?v=13">
<link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/style.css') ?>">
<link rel="icon" type="image/png" href="/assets/img/icon.png">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png?v=2">
<meta name="theme-color" content="#102340">
</head>
<body>
<div class="app-shell">
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <aside class="sidebar" id="sidebar">
    <div class="brand"><img src="/assets/img/logo-web.png?v=2" alt="Pwestora"></div>
    <nav>
      <?php foreach ($nav as $item): ?>
        <a class="nav-item <?= $page === $item['id'] ? 'active' : '' ?>"
           href="/dashboard.php?page=<?= $item['id'] ?>">
          <i class="bi <?= $item['icon'] ?>"></i> <span><?= $item['label'] ?></span>
          <?php if ($item['id'] === 'payments' && !empty($pendingPaymentsCount)): ?>
            <span class="badge badge-pending" style="margin-left:auto; font-size:11px; padding:2px 7px;"><?= $pendingPaymentsCount ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </aside>

  <div class="main">
    <div class="topbar">
      <div class="who">
        <button class="menu-toggle" id="menuToggle" aria-label="Open menu"><i class="bi bi-list"></i></button>
        <div style="min-width:0;">
          <strong><?= htmlspecialchars($user['company'] ?: $user['name']) ?></strong>
          <span class="role-pill" style="margin-left:8px;"><?= $roleLabel ?></span>
        </div>
      </div>
      

      <div class="right">
        <?php if ($user['role'] === 'lessor'): ?>
        <div class="notif-wrap" id="notifWrap">
          <button class="notif-bell" id="notifBell" aria-label="Notifications" type="button">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                  <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
            <?php if ($unreadCount > 0): ?>
              <span class="notif-badge"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span>
            <?php endif; ?>
          </button>
          
          <div class="notif-dropdown" id="notifDropdown" style="display:none; flex-direction:column; max-height:420px;">
            <div class="notif-dropdown-header" style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center;">
              <div style="display:flex; align-items:center; gap:8px;">
                <strong>Notices from Pwestora</strong>
                <?php if ($unreadCount > 0): ?>
                  <!-- Added onsubmit to close the dropdown instantly -->
                  <form method="POST" style="margin:0; display:inline;" onsubmit="document.getElementById('notifDropdown').style.display='none';">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="mark_all_read" value="1">
                    <button type="submit" style="background:none; border:none; color:var(--primary); font-size:11px; font-weight:normal; cursor:pointer; padding:0; text-decoration:underline;">Mark all read</button>
                  </form>
                <?php endif; ?>
              </div>
              <button id="notifCloseBtn" type="button" style="background:none; border:none; font-size:22px; color:var(--ink-500); cursor:pointer; line-height:1; padding:0 4px;">&times;</button>
            </div>
            
            <div style="overflow-y:auto; flex:1;">
              <?php if (!$notices): ?>
                <div class="notif-empty">No notices yet.</div>
              <?php else: foreach ($notices as $n): ?>
                <div class="notif-item" <?= !$n['read_at'] ? 'style="background:var(--primary-soft); border-left:3px solid var(--primary);"' : '' ?>>
                  <div class="notif-meta">
                    <span class="badge" style="background:var(--accent-soft); color:var(--primary);"><?= htmlspecialchars($n['title']) ?></span>
                    <span style="font-size:10.5px; color:var(--ink-300);"><?= (new DateTime($n['sent_at']))->format('M j') ?></span>
                  </div>
                  <p style="<?= !$n['read_at'] ? 'font-weight:600; color:var(--ink-900);' : '' ?>"><?= nl2br(htmlspecialchars($n['body'])) ?></p>
                </div>
              <?php endforeach; endif; ?>
            </div>

            <!-- View All Notifications Button -->
            <div style="padding:10px; border-top:1px solid var(--border); background:#fff; text-align:center; flex-shrink:0;">
              <button type="button" id="openAllNotifsModalBtn" style="display:block; width:100%; padding:10px; background:var(--bg); border:none; color:var(--ink-900); text-decoration:none; font-size:12.5px; font-weight:700; border-radius:8px; cursor:pointer; transition:background 0.2s;" onmouseover="this.style.background='var(--border)'" onmouseout="this.style.background='var(--bg)'">
                View All Notifications
              </button>
            </div>
          </div>
        </div>
        <?php endif; ?>
        <span class="email-full"><?= htmlspecialchars($user['email']) ?></span>
        <a href="/logout.php" class="btn btn-primary" style="padding:8px 14px;">
          <i class="bi bi-box-arrow-right"></i> <span class="d-none-mobile">Log out</span>
        </a>
      </div>
    </div>
    
    <div class="content">

<?php if ($user['role'] === 'lessor'): ?>
<!-- FULL NOTIFICATIONS MODAL / DRAWER -->
<div id="allNotifsModal" style="position:fixed; inset:0; background:rgba(0,0,0,.45); display:none; justify-content:flex-end; z-index:1050; backdrop-filter:blur(2px);">
  <div style="background:#fff; width:480px; max-width:95vw; height:100%; padding:24px; overflow-y:auto; box-shadow:-4px 0 24px rgba(0,0,0,0.15); display:flex; flex-direction:column;">
    
    <!-- Modal Header -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-shrink:0;">
      <div>
        <h3 style="margin:0 0 4px; font-size:18px;">All Notifications</h3>
        <p style="font-size:12.5px; color:var(--ink-500); margin:0;">You have <strong style="color:var(--primary);"><?= $unreadCount ?></strong> unread alerts.</p>
      </div>
      <div style="display:flex; align-items:center; gap:12px;">
        <?php if ($unreadCount > 0): ?>
          <!-- Added onsubmit to close the modal instantly -->
          <form method="POST" style="margin:0;" onsubmit="document.getElementById('allNotifsModal').style.display='none';">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="mark_all_read" value="1">
            <button type="submit" class="btn" style="background:var(--bg); border:1px solid var(--border); color:var(--ink-800); font-weight:600; padding:6px 12px; font-size:12px; border-radius:8px;">
              <i class="bi bi-check2-all" style="margin-right:4px;"></i> Mark all read
            </button>
          </form>
        <?php endif; ?>
        <button id="closeAllNotifsModalBtn" type="button" style="background:none; border:none; font-size:24px; color:var(--ink-500); cursor:pointer; line-height:1; padding:0 4px;">&times;</button>
      </div>
    </div>

    <!-- Modal Content List -->
    <div style="flex:1; overflow-y:auto; border-top:1px solid var(--border); padding-top:8px;">
      <?php if (!$allNotices): ?>
        <div style="padding:60px 20px; text-align:center; color:var(--ink-500);">
          <i class="bi bi-bell-slash" style="font-size:32px; color:var(--border); display:block; margin-bottom:12px;"></i>
          You're all caught up! No notifications to show.
        </div>
      <?php else: ?>
        <div style="display:flex; flex-direction:column; gap:8px;">
          <?php foreach ($allNotices as $n): 
              $isUnread = $n['read_at'] === null;
              $bgColor = $isUnread ? 'var(--primary-soft)' : '#fff';
          ?>
            <div style="display:flex; align-items:flex-start; justify-content:space-between; padding:16px; border-radius:12px; border:1px solid var(--border); background:<?= $bgColor ?>; transition:background 0.2s;" onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background='<?= $bgColor ?>'">
              
              <div style="display:flex; gap:12px; align-items:flex-start; flex:1;">
                <div style="width:10px; height:10px; border-radius:50%; background:<?= $isUnread ? 'var(--primary)' : 'transparent' ?>; margin-top:5px; flex-shrink:0;"></div>
                <div>
                  <h4 style="margin:0 0 4px; font-size:13.5px; font-weight:<?= $isUnread ? '800' : '600' ?>; color:var(--ink-900);">
                    <?= htmlspecialchars($n['title']) ?>
                  </h4>
                  <p style="margin:0 0 8px; font-size:12.5px; color:var(--ink-700); line-height:1.5; white-space:pre-wrap;"><?= htmlspecialchars($n['body']) ?></p>
                  <div style="font-size:11px; color:var(--ink-400); font-weight:500;">
                    <i class="bi bi-clock" style="margin-right:4px;"></i> <?= (new DateTime($n['sent_at']))->format('M j, Y g:i A') ?>
                  </div>
                </div>
              </div>
              
              <div style="display:flex; flex-direction:column; align-items:flex-end; gap:10px; flex-shrink:0; margin-left:12px;">
                <?php if ($isUnread): ?>
                  <!-- Added onsubmit to close the modal instantly -->
                  <form method="POST" style="margin:0;" onsubmit="document.getElementById('allNotifsModal').style.display='none';">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="mark_read_id" value="<?= $n['id'] ?>">
                    <button type="submit" style="font-size:11.5px; color:var(--primary); font-weight:600; padding:6px 12px; background:var(--bg); border-radius:6px; border:1px solid var(--border); cursor:pointer;">
                      Mark as read
                    </button>
                  </form>
                <?php endif; ?>
              </div>

            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
(function () {
  // Sidebar logic
  var sidebar = document.getElementById('sidebar');
  var overlay = document.getElementById('sidebarOverlay');
  var toggle = document.getElementById('menuToggle');
  function open() { sidebar.classList.add('open'); overlay.classList.add('open'); }
  function close() { sidebar.classList.remove('open'); overlay.classList.remove('open'); }
  if (toggle) toggle.addEventListener('click', open);
  if (overlay) overlay.addEventListener('click', close);

  // Notification Bell Logic
  var bell = document.getElementById('notifBell');
  var dropdown = document.getElementById('notifDropdown');
  var closeBtn = document.getElementById('notifCloseBtn');
  var notifWrap = document.getElementById('notifWrap');

  // Full View Modal Logic
  var allNotifsModal = document.getElementById('allNotifsModal');
  var openAllNotifsBtn = document.getElementById('openAllNotifsModalBtn');
  var closeAllNotifsModalBtn = document.getElementById('closeAllNotifsModalBtn');

  if (bell && dropdown) {
    // Toggle dropdown when clicking the bell
    bell.addEventListener('click', function (e) {
      e.stopPropagation();
      if (dropdown.classList.contains('open') || dropdown.style.display === 'flex') {
          dropdown.classList.remove('open');
          dropdown.style.display = 'none';
      } else {
          dropdown.classList.add('open');
          dropdown.style.display = 'flex';
      }
    });

    // Close when clicking the "X" button
    if (closeBtn) {
      closeBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdown.classList.remove('open');
        dropdown.style.display = 'none';
      });
    }

    // Close when clicking anywhere outside the dropdown
    document.addEventListener('click', function (e) {
      if (dropdown.classList.contains('open') || dropdown.style.display === 'flex') {
        if (notifWrap && !notifWrap.contains(e.target)) {
          dropdown.classList.remove('open');
          dropdown.style.display = 'none';
        }
      }
    });

    // Prevent clicks inside the dropdown from closing it
    dropdown.addEventListener('click', function(e) {
      e.stopPropagation();
    });
  }

  // Handle Full Modal Open/Close
  if (openAllNotifsBtn && allNotifsModal) {
    openAllNotifsBtn.addEventListener('click', function(e) {
      e.preventDefault();
      // Hide the dropdown
      if(dropdown) {
          dropdown.classList.remove('open');
          dropdown.style.display = 'none';
      }
      // Show the modal
      allNotifsModal.style.display = 'flex';
    });
    
    closeAllNotifsModalBtn.addEventListener('click', function() {
      allNotifsModal.style.display = 'none';
    });

    // Close modal on outside click
    allNotifsModal.addEventListener('click', function(e) {
      if (e.target === allNotifsModal) {
        allNotifsModal.style.display = 'none';
      }
    });
  }
})();
</script>