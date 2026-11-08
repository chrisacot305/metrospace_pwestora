<?php
/**
 * app/Views/superadmin/users.php
 * Presentation layer for User Management.
 */

// Filter the users list so we ONLY display Lessors
$lessorList = [];
if (!empty($usersList)) {
    foreach ($usersList as $u) {
        if (isset($u['role']) && $u['role'] === 'lessor') {
            $lessorList[] = $u;
        }
    }
}
?>

<!-- FLOATING TOAST NOTIFICATIONS -->
<link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(APP_ROOT . '/assets/css/style.css') ?>">
<div id="toast-notification" class="toast-container hidden-toast">
  <div id="toast-content"></div>
</div>

<script>
  var globalCsrfToken = "<?= csrf_token() ?>";

  function showToast(msg, isError) {
    var container = document.getElementById('toast-notification');
    var content = document.getElementById('toast-content');
    
    if (isError) {
        content.innerHTML = '<div class="toast-box toast-error"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px;"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg><span><strong>Status Updated!</strong> ' + msg + '</span></div>';
    } else {
        content.innerHTML = '<div class="toast-box toast-success"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg><span><strong>Status Updated!</strong> ' + msg + '</span></div>';
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
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="search-icon"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
      <input id="user-search" class="search-input" type="search" placeholder="Search name or email..." autocomplete="off">
    </div>

    <!-- Removed the Role Filter Dropdown -->

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
        <?php if (!$lessorList): ?>
          <tr><td colspan="5" class="empty-state-text text-center p-24">No lessors yet.</td></tr>
        <?php else: foreach ($lessorList as $u): 
            $displayName = !empty($u['company_name']) ? $u['company_name'] : $u['full_name'];
            $searchBlob = strtolower(trim($displayName . ' ' . $u['email']));
        ?>
          <tr class="user-row" id="user-row-<?= $u['id'] ?>" data-search="<?= htmlspecialchars($searchBlob) ?>" data-status="<?= htmlspecialchars($u['status']) ?>" data-name="<?= htmlspecialchars($displayName) ?>">
            <td><strong><?= htmlspecialchars($displayName) ?></strong></td>
            <td>
              <span class="badge badge-lessor">Lessor</span>
            </td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            
            <td class="status-cell">
              <?php if ($u['status'] === 'active'): ?>
                <span class="badge badge-verified"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg> Active</span>
              <?php elseif ($u['status'] === 'pending'): ?>
                <span class="badge badge-pending">Pending</span>
              <?php else: ?>
                <span class="badge badge-suspended"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg> Suspended</span>
              <?php endif; ?>
            </td>
            
            <td class="action-cell">
              <?php if ($u['status'] === 'suspended'): ?>
                <form method="POST" class="ajax-toggle-form action-inline">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="toggle_user_id" value="<?= $u['id'] ?>">
                  <button class="btn btn-primary btn-sm" type="submit" style="display:inline-flex; align-items:center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg> Reinstate
                  </button>
                </form>
              <?php else: ?>
                <button type="button" class="btn btn-sm btn-danger-soft" onclick="openSuspendModal(<?= $u['id'] ?>)" style="display:inline-flex; align-items:center;">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg> Suspend
                </button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        <tr id="user-empty-filter" class="hidden-row">
          <td colspan="5" class="empty-state-text text-center p-24">No lessors match your search or filter criteria.</td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- PAGINATION UI -->
  <?php if ($lessorList): ?>
  <div id="user-pagination-bar" class="pagination-bar">
    <div id="user-page-controls" class="page-controls"></div>
  </div>
  <?php endif; ?>
</div>

<!-- CUSTOM SUSPEND CONFIRMATION MODAL -->
<div id="suspendModal" class="modal-overlay" style="display:none;">
  <div class="card modal-card">
    <div class="modal-icon" style="color:var(--error);">
      <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
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
    btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><path d="M18 3H6v4l4 4-4 4v4h12v-4l-4-4 4-4V3z"></path></svg>...';

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
        statusCell.innerHTML = '<span class="badge badge-verified"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg> Active</span>';
        actionCell.innerHTML = '<button type="button" class="btn btn-sm btn-danger-soft" onclick="openSuspendModal(' + userId + ')" style="display:inline-flex; align-items:center;"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg> Suspend</button>';
    } else if (newStatus === 'suspended') {
        statusCell.innerHTML = '<span class="badge badge-suspended"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg> Suspended</span>';
        actionCell.innerHTML = '<form method="POST" class="ajax-toggle-form action-inline"><input type="hidden" name="csrf_token" value="' + globalCsrfToken + '"><input type="hidden" name="toggle_user_id" value="' + userId + '"><button class="btn btn-primary btn-sm" type="submit" style="display:inline-flex; align-items:center;"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg> Reinstate</button></form>';
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
    prevBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><polyline points="15 18 9 12 15 6"></polyline></svg> Prev';
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
    nextBtn.innerHTML = 'Next <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left:4px;"><polyline points="9 18 15 12 9 6"></polyline></svg>';
    nextBtn.disabled = currentPage === totalPages;
    nextBtn.addEventListener('click', function () {
      if (currentPage < totalPages) { currentPage++; window.filterTable(); }
    });
    pageControls.appendChild(nextBtn);
  }

  window.filterTable = function() {
    var q = search.value.trim().toLowerCase();
    var s = statusFilter.value;
    
    var matchingRows = rows.filter(function (row) {
      var matchQ = !q || (row.getAttribute('data-search') || '').indexOf(q) !== -1;
      var matchS = !s || row.getAttribute('data-status') === s;
      return matchQ && matchS;
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
  statusFilter.addEventListener('change', function() { currentPage = 1; window.filterTable(); });
  
  window.filterTable();
  bindAjaxForms();
})();
</script>