<?php
/**
 * app/Views/superadmin/properties.php
 * Presentation layer for Property Verification.
 */
?>
<link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(APP_ROOT . '/assets/css/style.css') ?>">
<h2 class="page-title">Property Verification</h2>

<?php if (!empty($error)): ?>
  <div class="error-msg"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card card-table">
  
  <!-- SEARCH & FILTER BAR -->
  <div class="filter-bar">
    <div class="search-wrapper">
      <i class="bi bi-search search-icon"></i>
      <input id="prop-search" class="search-input" type="search" placeholder="Search property name or lessor..." autocomplete="off">
    </div>

    <select id="prop-status-filter" class="filter-select">
      <option value="">All Statuses</option>
      <option value="pending">Pending</option>
      <option value="verified">Verified</option>
      <option value="rejected">Rejected</option>
    </select>
  </div>

  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>Property</th>
          <th>Lessor</th>
          <th>Type</th>
          <th>Documents</th>
          <th>Status</th>
          <th class="action-cell">Action</th>
        </tr>
      </thead>
      <tbody id="prop-table-body">
        <?php if (!$allProperties): ?>
          <tr><td colspan="6" class="empty-state-text text-center p-24">No properties found.</td></tr>
        <?php else: foreach ($allProperties as $p): 
            $searchBlob = strtolower(trim($p['name'] . ' ' . $p['lessor_name'] . ' ' . $p['type']));
            
            $isPending = $p['status'] === 'pending';
            $isVerified = $p['status'] === 'verified';

            $docCount = 0;
            foreach ($docLabels as $key => $label) { if (!empty($p[$key])) $docCount++; }
        ?>
          <tr class="prop-row" data-search="<?= htmlspecialchars($searchBlob) ?>" data-status="<?= htmlspecialchars($p['status']) ?>">
            <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
            <td><?= htmlspecialchars($p['lessor_name']) ?></td>
            <td><?= htmlspecialchars($p['type']) ?></td>
            <td><i class="bi bi-file-earmark-text"></i> <?= $docCount ?> / <?= count($docLabels) ?> files</td>
            <td>
              <?php if ($isPending): ?>
                <span class="badge badge-pending">Pending Review</span>
              <?php elseif ($isVerified): ?>
                <span class="badge badge-verified"><i class="bi bi-check-circle-fill"></i> Verified</span>
              <?php else: ?>
                <span class="badge badge-suspended">Rejected</span>
              <?php endif; ?>
            </td>
            <td class="action-cell">
              <a class="btn btn-primary btn-sm" href="/dashboard.php?page=properties&review=<?= $p['id'] ?>">
                <?= $isPending ? 'Review' : 'View' ?>
              </a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        <tr id="prop-empty-filter" class="hidden-row">
          <td colspan="6" class="empty-state-text text-center p-24">No properties match your search or filter.</td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- PAGINATION UI -->
  <?php if ($allProperties): ?>
  <div id="prop-pagination-bar" class="pagination-bar">
    <div id="prop-page-controls" class="page-controls"></div>
  </div>
  <?php endif; ?>
</div>

<!-- Review drawer -->
<?php if ($reviewing): ?>
<div class="drawer-overlay">
  <div class="drawer-content">
    <div class="drawer-header">
      <h3 class="drawer-title">PR-<?= str_pad($reviewing['id'], 3, '0', STR_PAD_LEFT) ?></h3>
      <a href="/dashboard.php?page=properties" class="drawer-close">&times;</a>
    </div>

    <div class="card drawer-card">
      <p class="info-text"><strong>Property:</strong> <?= htmlspecialchars($reviewing['name']) ?></p>
      <p class="info-text"><strong>Lessor:</strong> <?= htmlspecialchars($reviewing['lessor_name']) ?></p>
      <p class="info-text"><strong>Type:</strong> <?= htmlspecialchars($reviewing['type']) ?></p>
      <p class="info-text"><strong>Address:</strong> <?= htmlspecialchars($reviewing['address']) ?></p>
    </div>

    <p class="drawer-section-title">Documents</p>
    <div class="doc-list-wrap">
      <?php foreach ($docLabels as $key => $label): $path = $reviewing[$key] ?? null; ?>
        <div class="doc-list-item">
          <span class="doc-label"><i class="bi bi-file-earmark-text"></i> <?= htmlspecialchars($label) ?></span>
          <?php if ($path): ?>
            <a href="/<?= htmlspecialchars(ltrim($path, '/')) ?>" target="_blank" class="doc-link">View</a>
          <?php else: ?>
            <span class="doc-missing">Not uploaded</span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <p class="drawer-section-title">Photos (<?= count($reviewingPhotos) ?>)</p>
    <div class="photo-grid">
      <?php if (!$reviewingPhotos): ?>
        <p class="doc-missing col-span-full">No photos uploaded.</p>
      <?php else: foreach ($reviewingPhotos as $photo): ?>
        <a href="/<?= htmlspecialchars(ltrim($photo, '/')) ?>" target="_blank">
          <img src="/<?= htmlspecialchars(ltrim($photo, '/')) ?>" alt="Property photo" class="photo-thumb">
        </a>
      <?php endforeach; endif; ?>
    </div>

    <?php if ($reviewing['status'] === 'pending'): ?>
      <div class="action-group">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="property_id" value="<?= $reviewing['id'] ?>">
          <input type="hidden" name="action" value="approve">
          <button class="btn btn-approve" type="submit">
            <i class="bi bi-check-lg"></i> Approve
          </button>
        </form>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="property_id" value="<?= $reviewing['id'] ?>">
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
  var search = document.getElementById('prop-search');
  var statusFilter = document.getElementById('prop-status-filter');
  var rows = Array.prototype.slice.call(document.querySelectorAll('.prop-row'));
  var emptyFilter = document.getElementById('prop-empty-filter');
  var total = rows.length;

  var paginationBar = document.getElementById('prop-pagination-bar');
  var pageControls = document.getElementById('prop-page-controls');
  var currentPage = 1;
  var perPage = 10;

  if (!search || !statusFilter) return;

  function renderPagination(totalMatching, totalPages) {
    if (!paginationBar || !pageControls) return;
    if (totalMatching === 0) {
      paginationBar.style.display = 'none';
      return;
    }
    
    paginationBar.style.display = 'flex';
    pageControls.textContent = '';

    var prevBtn = document.createElement('button');
    prevBtn.type = 'button';
    prevBtn.className = 'page-btn';
    prevBtn.textContent = '‹ Prev';
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
    nextBtn.textContent = 'Next ›';
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
      row.style.display = 'none';
    });

    var startIndex = (currentPage - 1) * perPage;
    var endIndex = startIndex + perPage;
    matchingRows.slice(startIndex, endIndex).forEach(function (row) {
      row.style.display = '';
    });

    if (emptyFilter) {
      emptyFilter.style.display = (totalMatching === 0 && total > 0) ? 'table-row' : 'none';
    }

    renderPagination(totalMatching, totalPages);
  }

  search.addEventListener('input', function() { currentPage = 1; filterTable(); });
  statusFilter.addEventListener('change', function() { currentPage = 1; filterTable(); });
  
  filterTable();
})();
</script>