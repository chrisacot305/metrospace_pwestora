<?php
/**
 * app/Views/superadmin/lessors.php
 * Presentation layer for Lessor Verification.
 */
?>
<link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(APP_ROOT . '/assets/css/style.css') ?>">
<h2 class="page-title">Lessor Verification</h2>

<?php if ($error): ?>
  <div class="error-msg"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card card-table">
  
  <!-- SEARCH & FILTER BAR -->
  <div class="filter-bar">
    <div class="search-wrapper">
      <i class="bi bi-search search-icon"></i>
      <input id="lessor-search" class="search-input" type="search" placeholder="Search lessor name or email..." autocomplete="off">
    </div>

    <select id="lessor-status-filter" class="filter-select">
      <option value="">All Statuses</option>
      <option value="pending">Pending</option>
      <option value="active">Verified (Active)</option>
      <option value="suspended">Suspended / Rejected</option>
    </select>
  </div>

  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>Lessor</th>
          <th>Contact</th>
          <th>Info</th>
          <th>Joined</th>
          <th>Status</th>
          <th class="action-cell">Action</th>
        </tr>
      </thead>
      <tbody id="lessor-table-body">
        <?php if (!$allLessors): ?>
          <tr><td colspan="6" class="empty-state-text text-center p-24">No lessors found.</td></tr>
        <?php else: foreach ($allLessors as $l): 
            $searchBlob = strtolower(trim($l['company_name'] . ' ' . $l['email']));
            
            $isPending = $l['status'] === 'pending';
            $isActive = $l['status'] === 'active';

            $docCount = 0;
            if ($isPending) {
                foreach ($docLabels as $key => $label) { if (!empty($l[$key])) $docCount++; }
            }
        ?>
          <tr class="lessor-row" data-search="<?= htmlspecialchars($searchBlob) ?>" data-status="<?= htmlspecialchars($l['status']) ?>">
            <td><strong><?= htmlspecialchars($l['company_name']) ?></strong></td>
            <td><?= htmlspecialchars($l['email']) ?></td>
            <td>
              <?php if ($isPending): ?>
                <i class="bi bi-folder2-open"></i> <?= $docCount ?> / <?= count($docLabels) ?> files
              <?php else: ?>
                <i class="bi bi-building"></i> <?= $l['property_count'] ?> listed
              <?php endif; ?>
            </td>
            <td><?= (new DateTime($l['created_at']))->format('M j, Y') ?></td>
            <td>
              <?php if ($isPending): ?>
                <span class="badge badge-pending">Pending Review</span>
              <?php elseif ($isActive): ?>
                <span class="badge badge-verified"><i class="bi bi-check-circle-fill"></i> Verified</span>
              <?php else: ?>
                <span class="badge badge-suspended">Suspended</span>
              <?php endif; ?>
            </td>
            <td class="action-cell">
              <a class="btn btn-primary btn-sm" href="/dashboard.php?page=lessors&review=<?= $l['id'] ?>">
                <?= $isPending ? 'Review' : 'View' ?>
              </a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        <tr id="lessor-empty-filter" class="hidden-row">
          <td colspan="6" class="empty-state-text text-center p-24">No lessors match your search or filter.</td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- PAGINATION UI -->
  <?php if ($allLessors): ?>
  <div id="lessor-pagination-bar" class="pagination-bar">
    <div id="lessor-page-controls" class="page-controls"></div>
  </div>
  <?php endif; ?>
</div>

<!-- Review drawer -->
<?php if ($reviewing): ?>
<div class="drawer-overlay">
  <div class="drawer-content">
    <div class="drawer-header">
      <h3 class="drawer-title">LS-<?= str_pad($reviewing['id'], 3, '0', STR_PAD_LEFT) ?></h3>
      <a href="/dashboard.php?page=lessors" class="drawer-close">&times;</a>
    </div>

    <div class="card drawer-card">
      <p class="info-text"><strong>Lessor:</strong> <?= htmlspecialchars($reviewing['company_name']) ?></p>
      <p class="info-text"><strong>Contact:</strong> <?= htmlspecialchars($reviewing['email']) ?></p>
      <p class="info-text"><strong>Submitted:</strong> <?= (new DateTime($reviewing['created_at']))->format('M j, Y') ?></p>
    </div>

    <p class="drawer-section-title">Documents</p>
    <div class="doc-list-wrap">
      <?php foreach ($docLabels as $key => $label): $path = $reviewing[$key] ?? null; ?>
        <div class="doc-list-item">
          <span class="doc-label"><i class="bi bi-file-earmark-text"></i> <?= $label ?></span>
          <?php if ($path): ?>
            <a href="<?= htmlspecialchars(upload_url($path)) ?>" target="_blank" class="doc-link">View</a>
          <?php else: ?>
            <span class="doc-missing">Not uploaded</span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($reviewing['status'] === 'pending'): ?>
      <div class="action-group">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="lessor_id" value="<?= $reviewing['id'] ?>">
          <input type="hidden" name="action" value="approve">
          <button class="btn btn-approve" type="submit">
            <i class="bi bi-check-lg"></i> Approve
          </button>
        </form>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="lessor_id" value="<?= $reviewing['id'] ?>">
          <input type="hidden" name="action" value="reject">
          <button class="btn btn-reject" type="submit">
            <i class="bi bi-x-lg"></i> Reject
          </button>
        </form>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<script>
(function () {
  var search = document.getElementById('lessor-search');
  var statusFilter = document.getElementById('lessor-status-filter');
  var rows = Array.prototype.slice.call(document.querySelectorAll('.lessor-row'));
  var emptyFilter = document.getElementById('lessor-empty-filter');
  var total = rows.length;

  var paginationBar = document.getElementById('lessor-pagination-bar');
  var pageControls = document.getElementById('lessor-page-controls');
  var currentPage = 1;
  var perPage = 10;

  if (!search || !statusFilter) return;

  function renderPagination(totalMatching, totalPages) {
    if (!paginationBar || !pageControls) return;
    if (totalMatching === 0) {
      paginationBar.classList.add('hidden');
      return;
    }
    
    paginationBar.classList.remove('hidden');
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
    var s = statusFilter.value.toLowerCase();
    
    var matchingRows = rows.filter(function (row) {
      var rowSearch = (row.getAttribute('data-search') || '').toLowerCase();
      var rowStatus = (row.getAttribute('data-status') || '').toLowerCase();
      var matchQ = !q || rowSearch.indexOf(q) !== -1;
      var matchS = !s || rowStatus === s;
      return matchQ && matchS;
    });

    var totalMatching = matchingRows.length;
    var totalPages = Math.max(1, Math.ceil(totalMatching / perPage));
    if (currentPage > totalPages) currentPage = totalPages;

    rows.forEach(function (row) {
      row.classList.add('hidden-row');
    });

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
  statusFilter.addEventListener('change', function() { currentPage = 1; filterTable(); });
  
  filterTable();
})();
</script>