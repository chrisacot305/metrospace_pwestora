<?php
/**
 * app/Views/superadmin/audit.php
 * Presentation layer for the Audit Logs.
 */
?>

<!-- HEADER & ACTIONS IN ONE ROW -->
<div class="rev-top-bar">
  <div class="rev-header-titles">
    <h2 class="page-title">Audit Logs</h2>
  </div>
  
  <a href="/export_audit_log.php" class="btn btn-outline">
    <i class="bi bi-download"></i> Export Full Log
  </a>
</div>

<div class="card card-table">

  <!-- SEARCH & FILTER BAR -->
  <div class="filter-bar">
    <div class="search-wrapper">
      <i class="bi bi-search search-icon"></i>
      <input id="audit-search" class="search-input" type="search" placeholder="Search actors or targets..." autocomplete="off">
    </div>

    <select id="audit-action-filter" class="filter-select">
      <option value="">All Actions</option>
      <?php foreach ($uniqueActions as $action): ?>
        <option value="<?= htmlspecialchars($action) ?>"><?= htmlspecialchars($action) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <!-- DATA TABLE -->
  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>Actor</th>
          <th>Action</th>
          <th>Target</th>
          <th>Time</th>
        </tr>
      </thead>
      <tbody id="audit-table-body">
        <?php if (!$logs): ?>
          <tr>
            <td colspan="4" class="empty-state-text text-center p-24">Nothing logged yet.</td>
          </tr>
        <?php else: foreach ($logs as $l): 
            $searchBlob = strtolower(trim($l['actor_display'] . ' ' . $l['details']));
        ?>
          <tr class="audit-row" data-search="<?= htmlspecialchars($searchBlob) ?>" data-action="<?= htmlspecialchars($l['action']) ?>">
            <td class="font-semibold"><?= htmlspecialchars($l['actor_display']) ?></td>
            <td class="text-muted"><?= htmlspecialchars($l['action']) ?></td>
            <td class="text-muted"><?= htmlspecialchars($l['details'] ?: '—') ?></td>
            <td class="text-muted"><?= (new DateTime($l['created_at']))->format('M j, g:i A') ?></td>
          </tr>
        <?php endforeach; endif; ?>
        <tr id="audit-empty-filter" class="hidden-row">
          <td colspan="4" class="empty-state-text text-center p-24">No audit logs match your search or filter.</td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- PAGINATION UI -->
  <?php if ($logs): ?>
  <div id="audit-pagination-bar" class="pagination-bar">
    <div id="audit-page-controls" class="page-controls"></div>
  </div>
  <?php endif; ?>
</div>

<!-- PAGINATION & FILTER LOGIC -->
<script>
(function () {
  var search = document.getElementById('audit-search');
  var actionFilter = document.getElementById('audit-action-filter');
  var rows = Array.prototype.slice.call(document.querySelectorAll('.audit-row'));
  var emptyFilter = document.getElementById('audit-empty-filter');
  var total = rows.length;

  var paginationBar = document.getElementById('audit-pagination-bar');
  var pageControls = document.getElementById('audit-page-controls');
  var currentPage = 1;
  var perPage = 15; // Showing 15 logs per page since they are compact

  if (!search || !actionFilter) return;

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

    // Limit visible page buttons so it doesn't break the UI if there are 30 pages
    var startPage = Math.max(1, currentPage - 2);
    var endPage = Math.min(totalPages, currentPage + 2);
    
    if (startPage > 1) {
        var firstBtn = document.createElement('button');
        firstBtn.type = 'button';
        firstBtn.className = 'page-btn';
        firstBtn.textContent = '1';
        firstBtn.addEventListener('click', function () { currentPage = 1; filterTable(); });
        pageControls.appendChild(firstBtn);
        if (startPage > 2) {
            var dots = document.createElement('span');
            dots.innerHTML = '&hellip;';
            dots.style.padding = '0 8px';
            pageControls.appendChild(dots);
        }
    }

    for (var p = startPage; p <= endPage; p++) {
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

    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            var dots = document.createElement('span');
            dots.innerHTML = '&hellip;';
            dots.style.padding = '0 8px';
            pageControls.appendChild(dots);
        }
        var lastBtn = document.createElement('button');
        lastBtn.type = 'button';
        lastBtn.className = 'page-btn';
        lastBtn.textContent = totalPages;
        lastBtn.addEventListener('click', function () { currentPage = totalPages; filterTable(); });
        pageControls.appendChild(lastBtn);
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
    var a = actionFilter.value;
    
    var matchingRows = rows.filter(function (row) {
      var rowSearch = (row.getAttribute('data-search') || '').toLowerCase();
      var rowAction = row.getAttribute('data-action') || '';
      var matchQ = !q || rowSearch.indexOf(q) !== -1;
      var matchA = !a || rowAction === a;
      return matchQ && matchA;
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
  actionFilter.addEventListener('change', function() { currentPage = 1; filterTable(); });
  
  filterTable();
})();
</script>