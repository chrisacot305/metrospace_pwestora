<?php
/**
 * views/superadmin/users.php
 * Presentation layer for User Management.
 */
?>

<!-- FLOATING TOAST NOTIFICATIONS -->
<link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/style.css') ?>">
<div id="toast-notification" class="toast-container hidden-toast">
  <div id="toast-content"></div>
</div>

<script>
  var globalCsrfToken = "<?= csrf_token() ?>";

  function showToast(msg, isError) {
    var container = document.getElementById('toast-notification');
    var content = document.getElementById('toast-content');
    
    if (isError) {
        content.innerHTML = '<div class="toast-box toast-error"><i class="bi bi-slash-circle"></i><span><strong>Status Updated!</strong> ' + msg + '</span></div>';
    } else {
        content.innerHTML = '<div class="toast-box toast-success"><i class="bi bi-check-circle-fill"></i><span><strong>Status Updated!</strong> ' + msg + '</span></div>';
    }
      
    container.classList.remove('hidden-toast');
    
    setTimeout(function() {
      container.classList.add('hidden-toast');
    }, 3500);
  }

  <?php if ($msg): ?>
    document.addEventListener("DOMContentLoaded", function() { showToast(<?= json_encode($msg) ?>, <?= strpos($msg, 'suspended') !== false ? 'true' : 'false' ?>); });
  <?php endif; ?>
  <?php if ($error): ?>
    document.addEventListener("DOMContentLoaded", function() { showToast(<?= json_encode($error) ?>, true); });
  <?php endif; ?>
</script>

<h2 class="page-title">User Management</h2>

<div class="card card-table">
  
  <!-- SEARCH & FILTER BAR -->
  <div class="filter-bar">
    <div class="search-wrapper">
      <i class="bi bi-search search-icon"></i>
      <input id="user-search" class="search-input" type="search" placeholder="Search name or email..." autocomplete="off">
    </div>

    <select id="user-role-filter" class="filter-select">
      <option value="">All Roles</option>
      <option value="lessor">Lessors</option>
      <option value="lessee">Lessees</option>
    </select>

    <select id="user-status-filter" class="filter-select">
      <option value="">All Statuses</option>
      <option value="active">Active</option>
      <option value="pending">Pending</option>
      <option value="suspended">Suspended</option>
    </select>
  </div>

  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Role</th>
          <th>Email</th>
          <th>Status</th>
          <th class="action-cell">Action</th>
        </tr>
      </thead>
      <tbody id="user-table-body">
        <?php if (!$usersList): ?>
          <tr><td colspan="5" class="empty-state-text text-center p-24">No users yet.</td></tr>
        <?php else: foreach ($usersList as $u): 
            $displayName = $u['role'] === 'lessor' && !empty($u['company_name']) ? $u['company_name'] : $u['full_name'];
            $searchBlob = strtolower(trim($displayName . ' ' . $u['email']));
        ?>
          <tr class="user-row" id="user-row-<?= $u['id'] ?>" data-search="<?= htmlspecialchars($searchBlob) ?>" data-role="<?= htmlspecialchars($u['role']) ?>" data-status="<?= htmlspecialchars($u['status']) ?>" data-name="<?= htmlspecialchars($displayName) ?>">
            <td><strong><?= htmlspecialchars($displayName) ?></strong></td>
            <td>
              <?php if ($u['role'] === 'lessor'): ?>
                <span class="badge badge-lessor">Lessor</span>
              <?php else: ?>
                <span class="badge badge-pending">Lessee</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            
            <td class="status-cell">
              <?php if ($u['status'] === 'active'): ?>
                <span class="badge badge-verified"><i class="bi bi-check-circle-fill"></i> Active</span>
              <?php elseif ($u['status'] === 'pending'): ?>
                <span class="badge badge-pending">Pending</span>
              <?php else: ?>
                <span class="badge badge-suspended"><i class="bi bi-slash-circle"></i> Suspended</span>
              <?php endif; ?>
            </td>
            
            <td class="action-cell">
              <?php if ($u['status'] === 'suspended'): ?>
                <form method="POST" class="ajax-toggle-form action-inline">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="toggle_user_id" value="<?= $u['id'] ?>">
                  <button class="btn btn-primary btn-sm" type="submit">
                    <i class="bi bi-play-fill"></i> Reinstate
                  </button>
                </form>
              <?php else: ?>
                <button type="button" class="btn btn-sm btn-danger-soft" onclick="openSuspendModal(<?= $u['id'] ?>)">
                  <i class="bi bi-slash-circle"></i> Suspend
                </button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        <tr id="user-empty-filter" class="hidden-row">
          <td colspan="5" class="empty-state-text text-center p-24">No users match your search or filter criteria.</td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- PAGINATION UI -->
  <?php if ($usersList): ?>
  <div id="user-pagination-bar" class="pagination-bar">
    <div id="user-page-controls" class="page-controls"></div>
  </div>
  <?php endif; ?>
</div>

<!-- CUSTOM SUSPEND CONFIRMATION MODAL -->
<div id="suspendModal" class="modal-overlay" style="display:none;">
  <div class="card modal-card">
    <div class="modal-icon">
      <i class="bi bi-exclamation-triangle-fill"></i>
    </div>
    <h3 class="modal-title">Confirm Suspension</h3>
    <p class="modal-text">
      Are you sure you want to suspend <strong id="suspendUserName"></strong>? They will instantly lose access to the platform.
    </p>
    
    <form id="suspend-modal-form" method="POST" class="modal-actions">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="toggle_user_id" id="suspendUserIdInput" value="">
      
      <button type="button" class="btn btn-outline flex-1" onclick="closeSuspendModal()">Cancel</button>
      <button type="submit" class="btn btn-reject flex-1">Yes, Suspend</button>
    </form>
  </div>
</div>

<script>
// --- AJAX SUBMISSION LOGIC ---
function bindAjaxForms() {
    document.querySelectorAll('.ajax-toggle-form').forEach(function(form) {
        form.onsubmit = function(e) {
            e.preventDefault();
            submitToggleAjax(this);
        };
    });
}

document.getElementById('suspend-modal-form').onsubmit = function(e) {
    e.preventDefault();
    submitToggleAjax(this);
};

function submitToggleAjax(form) {
    var formData = new FormData(form);
    formData.append('ajax', '1'); 
    
    var btn = form.querySelector('button[type=submit]');
    var origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass"></i>...';

    fetch('/dashboard.php?page=users', {
        method: 'POST',
        body: formData
    })
    .then(r => r.text())
    .then(text => {
        // Highly resilient parser: extracts safely from HTML comments
        var match = text.match(/<!--AJAX_RES_START-->([\s\S]*?)<!--AJAX_RES_END-->/);
        
        if (match && match[1]) {
            var data = JSON.parse(match[1]);
            if(data.success) {
                updateUserRow(data.userId, data.newStatus);
                showToast(data.msg, data.newStatus === 'suspended');
                closeSuspendModal();
            } else {
                alert(data.error || 'Something went wrong processing your request.');
            }
        } else {
            console.error("Server output:", text);
            alert('Server output structure error. Please check your PHP logs.');
        }
    })
    .catch(e => {
        console.error(e);
        alert('Network or server error. Please try again.');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = origHtml;
    });
}

function updateUserRow(userId, newStatus) {
    var row = document.getElementById('user-row-' + userId);
    if (!row) return;
    
    row.setAttribute('data-status', newStatus);
    var statusCell = row.querySelector('.status-cell');
    var actionCell = row.querySelector('.action-cell');
    
    if (newStatus === 'active') {
        statusCell.innerHTML = '<span class="badge badge-verified"><i class="bi bi-check-circle-fill"></i> Active</span>';
        actionCell.innerHTML = '<button type="button" class="btn btn-sm btn-danger-soft" onclick="openSuspendModal(' + userId + ')"><i class="bi bi-slash-circle"></i> Suspend</button>';
    } else if (newStatus === 'suspended') {
        statusCell.innerHTML = '<span class="badge badge-suspended"><i class="bi bi-slash-circle"></i> Suspended</span>';
        actionCell.innerHTML = '<form method="POST" class="ajax-toggle-form action-inline"><input type="hidden" name="csrf_token" value="' + globalCsrfToken + '"><input type="hidden" name="toggle_user_id" value="' + userId + '"><button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-play-fill"></i> Reinstate</button></form>';
    }
    
    bindAjaxForms(); 
    if (typeof window.filterTable === 'function') window.filterTable(); 
}

// --- MODAL LOGIC ---
function openSuspendModal(userId) {
  var row = document.getElementById('user-row-' + userId);
  var userName = row ? row.getAttribute('data-name') : 'this user';
  
  document.getElementById('suspendUserIdInput').value = userId;
  document.getElementById('suspendUserName').textContent = userName;
  document.getElementById('suspendModal').style.display = 'flex'; // Force display
}

function closeSuspendModal() {
  document.getElementById('suspendModal').style.display = 'none'; // Force hide
}

document.getElementById('suspendModal').addEventListener('click', function(e) {
  if (e.target === this) closeSuspendModal();
});

// --- FILTER & PAGINATION LOGIC ---
(function () {
  var search = document.getElementById('user-search');
  var roleFilter = document.getElementById('user-role-filter');
  var statusFilter = document.getElementById('user-status-filter');
  var rows = Array.prototype.slice.call(document.querySelectorAll('.user-row'));
  var emptyFilter = document.getElementById('user-empty-filter');
  var total = rows.length;

  var paginationBar = document.getElementById('user-pagination-bar');
  var pageControls = document.getElementById('user-page-controls');
  var currentPage = 1;
  var perPage = 10;

  if (!search) return;

  function renderPagination(totalMatching, totalPages) {
    if (!paginationBar || !pageControls) return;
    if (totalMatching === 0) {
      paginationBar.classList.add('hidden-row');
      return;
    }
    
    paginationBar.classList.remove('hidden-row');
    pageControls.innerHTML = '';

    var prevBtn = document.createElement('button');
    prevBtn.type = 'button';
    prevBtn.className = 'page-btn';
    prevBtn.innerHTML = '<i class="bi bi-chevron-left"></i> Prev';
    prevBtn.disabled = currentPage === 1;
    prevBtn.addEventListener('click', function () {
      if (currentPage > 1) { currentPage--; window.filterTable(); }
    });
    pageControls.appendChild(prevBtn);

    for (var p = 1; p <= totalPages; p++) {
      (function (pageNum) {
        var pageBtn = document.createElement('button');
        pageBtn.type = 'button';
        pageBtn.className = 'page-btn';
        if (pageNum === currentPage) pageBtn.classList.add('active');
        pageBtn.textContent = pageNum;
        pageBtn.addEventListener('click', function () {
          currentPage = pageNum; window.filterTable();
        });
        pageControls.appendChild(pageBtn);
      })(p);
    }

    var nextBtn = document.createElement('button');
    nextBtn.type = 'button';
    nextBtn.className = 'page-btn';
    nextBtn.innerHTML = 'Next <i class="bi bi-chevron-right"></i>';
    nextBtn.disabled = currentPage === totalPages;
    nextBtn.addEventListener('click', function () {
      if (currentPage < totalPages) { currentPage++; window.filterTable(); }
    });
    pageControls.appendChild(nextBtn);
  }

  window.filterTable = function() {
    var q = search.value.trim().toLowerCase();
    var r = roleFilter.value;
    var s = statusFilter.value;
    
    var matchingRows = rows.filter(function (row) {
      var matchQ = !q || (row.getAttribute('data-search') || '').indexOf(q) !== -1;
      var matchR = !r || row.getAttribute('data-role') === r;
      var matchS = !s || row.getAttribute('data-status') === s;
      return matchQ && matchR && matchS;
    });

    var totalMatching = matchingRows.length;
    var totalPages = Math.max(1, Math.ceil(totalMatching / perPage));
    if (currentPage > totalPages) currentPage = totalPages;

    rows.forEach(function (row) { row.classList.add('hidden-row'); });

    var startIndex = (currentPage - 1) * perPage;
    var endIndex = startIndex + perPage;
    matchingRows.slice(startIndex, endIndex).forEach(function (row) {
      row.classList.remove('hidden-row');
    });

    if (emptyFilter) {
      if (totalMatching === 0 && total > 0) {
        emptyFilter.classList.remove('hidden-row');
      } else {
        emptyFilter.classList.add('hidden-row');
      }
    }

    renderPagination(totalMatching, totalPages);
  };

  search.addEventListener('input', function() { currentPage = 1; window.filterTable(); });
  roleFilter.addEventListener('change', function() { currentPage = 1; window.filterTable(); });
  statusFilter.addEventListener('change', function() { currentPage = 1; window.filterTable(); });
  
  window.filterTable();
  bindAjaxForms();
})();
</script>