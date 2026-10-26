<?php
/**
 * views/superadmin/notify.php
 * Presentation layer for Notify Lessors.
 */
?>
<link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/style.css') ?>">
<h2 class="page-title">Notify Lessors</h2>

<?php if ($error): ?>
  <div class="error-msg"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($successMsg): ?>
  <div id="temp-success-msg" class="success-msg">
    <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($successMsg) ?>
  </div>
  <script>
    // Clean up the URL so refreshing doesn't resubmit the form
    if (window.history.replaceState) {
        window.history.replaceState(null, null, "/dashboard.php?page=notify");
    }
    
    // Smoothly fade out and remove the message after 3.5 seconds
    setTimeout(function() {
        var msgDiv = document.getElementById('temp-success-msg');
        if (msgDiv) {
            msgDiv.style.transition = 'opacity 0.5s ease';
            msgDiv.style.opacity = '0';
            setTimeout(function() { msgDiv.style.display = 'none'; }, 500);
        }
    }, 3500);
  </script>
<?php endif; ?>

<div class="action-bar-top">
  <a href="/dashboard.php?page=notify&compose=1" class="btn btn-accent">
    <i class="bi bi-megaphone"></i> New Notice
  </a>
</div>

<div class="card card-table">

  <!-- SEARCH & FILTER BAR -->
  <div class="filter-bar">
    <div class="search-wrapper">
      <i class="bi bi-search search-icon"></i>
      <input id="notice-search" class="search-input" type="search" placeholder="Search message or recipients..." autocomplete="off">
    </div>

    <select id="notice-type-filter" class="filter-select">
      <option value="">All Notice Types</option>
      <?php foreach ($NOTICE_TYPES as $type): ?>
        <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>Type</th>
          <th>Recipients</th>
          <th>Message</th>
          <th>Sent</th>
        </tr>
      </thead>
      <tbody id="notice-table-body">
        <?php if (!$sentLog): ?>
          <tr><td colspan="4" class="empty-state-text text-center p-24">Nothing sent yet.</td></tr>
        <?php else: foreach ($sentLog as $n): 
            $searchBlob = strtolower(trim($n['title'] . ' ' . $n['audience'] . ' ' . $n['body']));
        ?>
          <tr class="notice-row" data-search="<?= htmlspecialchars($searchBlob) ?>" data-type="<?= htmlspecialchars($n['title']) ?>">
            <td><span class="badge badge-accent"><?= htmlspecialchars($n['title']) ?></span></td>
            <td><?= htmlspecialchars($n['audience']) ?></td>
            <td><?= htmlspecialchars($n['body']) ?></td>
            <td><?= (new DateTime($n['sent_at']))->format('M j, g:i A') ?></td>
          </tr>
        <?php endforeach; endif; ?>
        <tr id="notice-empty-filter" class="hidden-row">
          <td colspan="4" class="empty-state-text text-center p-24">No notices match your search or filter.</td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- PAGINATION UI -->
  <?php if ($sentLog): ?>
  <div id="notice-pagination-bar" class="pagination-bar">
    <div id="notice-page-controls" class="page-controls"></div>
  </div>
  <?php endif; ?>
</div>

<?php if ($composing): ?>
<div class="drawer-overlay">
  <div class="drawer-content">
    <div class="drawer-header">
      <h3 class="drawer-title">Compose Notice</h3>
      <a href="/dashboard.php?page=notify" class="drawer-close">&times;</a>
    </div>

    <?php if (!$lessors): ?>
      <p class="drawer-empty-text">No active lessors on the platform yet.</p>
    <?php else: ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="send_notice" value="1">

        <p class="drawer-section-title">Send to</p>
        
        <label class="checkbox-card">
          <span class="checkbox-label"><i class="bi bi-people"></i> All lessors</span>
          <input type="checkbox" name="all_lessors" id="allLessorsBox">
        </label>
        
        <div id="lessorCheckboxes">
          <?php foreach ($lessors as $l): ?>
            <label class="checkbox-card">
              <span class="checkbox-label"><?= htmlspecialchars($l['company_name']) ?></span>
              <input type="checkbox" name="recipient_ids[]" value="<?= $l['id'] ?>">
            </label>
          <?php endforeach; ?>
        </div>

        <p class="drawer-section-title mt-16">Notice Type</p>
        <div class="radio-group">
          <?php foreach ($NOTICE_TYPES as $i => $type): ?>
            <label class="radio-pill">
              <input type="radio" name="notice_type" value="<?= $type ?>" <?= $i === 0 ? 'checked' : '' ?>> 
              <?= $type ?>
            </label>
          <?php endforeach; ?>
        </div>

        <div class="field">
          <label>Message</label>
          <textarea name="notice_body" rows="6" required></textarea>
        </div>

        <button class="btn btn-accent w-100 mt-16" type="submit">
          <i class="bi bi-megaphone"></i> Send Notice
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<script>
  // Disable individual checkboxes if "All Lessors" is selected
  var allLessorsBox = document.getElementById('allLessorsBox');
  if (allLessorsBox) {
      allLessorsBox.addEventListener('change', function () {
          document.querySelectorAll('#lessorCheckboxes input[type="checkbox"]').forEach(function (cb) {
              cb.disabled = allLessorsBox.checked;
          });
      });
  }
</script>
<?php endif; ?>

<!-- PAGINATION & FILTER LOGIC -->
<script>
(function () {
  var search = document.getElementById('notice-search');
  var typeFilter = document.getElementById('notice-type-filter');
  var rows = Array.prototype.slice.call(document.querySelectorAll('.notice-row'));
  var emptyFilter = document.getElementById('notice-empty-filter');
  var total = rows.length;

  var paginationBar = document.getElementById('notice-pagination-bar');
  var pageControls = document.getElementById('notice-page-controls');
  var currentPage = 1;
  var perPage = 10;

  if (!search || !typeFilter) return;

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
      if (currentPage > 1) { currentPage--; filterTable(); }
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
          currentPage = pageNum; filterTable();
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
      if (currentPage < totalPages) { currentPage++; filterTable(); }
    });
    pageControls.appendChild(nextBtn);
  }

  function filterTable() {
    var q = search.value.trim().toLowerCase();
    var t = typeFilter.value.toLowerCase();
    
    var matchingRows = rows.filter(function (row) {
      var rowSearch = (row.getAttribute('data-search') || '').toLowerCase();
      var rowType = (row.getAttribute('data-type') || '').toLowerCase();
      var matchQ = !q || rowSearch.indexOf(q) !== -1;
      var matchT = !t || rowType === t;
      return matchQ && matchT;
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
  }

  search.addEventListener('input', function() { currentPage = 1; filterTable(); });
  typeFilter.addEventListener('change', function() { currentPage = 1; filterTable(); });
  
  filterTable();
})();
</script>