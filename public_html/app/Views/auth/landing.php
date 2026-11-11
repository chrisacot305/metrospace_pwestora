<?php /* app/Views/auth/landing.php — page HTML (was the bottom half of index.php) */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pwestora - Commercial Leasing Platform</title>
<meta name="description" content="Pwestora is the leasing management platform built for commercial property lessors documented agreements, payments, maintenance, and tenant communication in one workspace.">
<link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(APP_ROOT . '/assets/css/style.css') ?>">
<link rel="icon" type="image/png" href="/assets/img/icon.png">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png?v=2">
<link rel="manifest" href="/manifest.json">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Pwestora">
<meta name="theme-color" content="#102340">
<link rel="stylesheet" href="/assets/css/landing-motion.css?v=<?= filemtime(APP_ROOT . '/assets/css/landing-motion.css') ?>">
<script>
  // Turn on animations (content stays visible if JavaScript is off). Skip the preloader after the first visit.
  document.documentElement.classList.add('lm-js');
  try { if (sessionStorage.getItem('lm-seen')) document.documentElement.classList.add('lm-seen'); } catch (e) {}
</script>
</head>
<body class="landing">

<!-- Motion layers (see assets/css/landing-motion.css + assets/js/landing-motion.js) -->
<div class="lm-loader" aria-hidden="true">
  <div class="lm-loader-lines"><span></span><span></span><span></span><span></span></div>
  <div class="lm-loader-brand"><span>Pwestora</span></div>
  <div class="lm-loader-meta">Commercial leasing<span class="lm-loader-count">0%</span></div>
  <div class="lm-loader-bar"></div>
</div>
<div class="lm-grid-lines" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
<div class="lm-progress" aria-hidden="true"></div>

<header class="l-nav">
  <a class="brand-mark" href="/"><img src="/assets/img/logo-web.png?v=2" alt="Pwestora"></a>
  <nav class="l-links" aria-label="Main">
    <a href="#platform">Platform</a><a href="#portfolio">Listings</a><a href="#capabilities">Capabilities</a><a href="#process">How it works</a>
  </nav>
  <div class="l-nav-actions">
    <a class="l-nav-cta" href="#" onclick="openAuthModal('login'); return false;">Sign in</a>
  </div>
  <button class="l-menu" id="lMenuBtn" type="button" aria-label="Open menu" aria-expanded="false" onclick="toggleMobileMenu()">☰</button>
</header>
<div class="l-mobile-menu" id="lMobileMenu" hidden>
  <a href="#platform" onclick="toggleMobileMenu(false)">Platform</a>
  <a href="#portfolio" onclick="toggleMobileMenu(false)">Listings</a>
  <a href="#capabilities" onclick="toggleMobileMenu(false)">Capabilities</a>
  <a href="#process" onclick="toggleMobileMenu(false)">How it works</a>
  <a class="l-nav-cta" href="#" onclick="toggleMobileMenu(false); openAuthModal('login'); return false;">Sign in</a>
</div>

<main>
<section class="l-hero">
  <div>
    <div class="l-kicker">Built for commercial property lessors</div>
    <h1><span class="lm-line"><span>List the space.</span></span><span class="lm-line"><span>Run the <span class="lm-accent">business.</span></span></span></h1>
    <p class="l-lead">Pwestora gives lessors one workspace to manage leases, tenants, payments, maintenance, and disputes replacing scattered messages and verbal agreements with a documented, accountable system.</p>
    <div class="l-buttons">
      <a class="l-btn l-btn-primary" href="#" onclick="openAuthModal('register'); return false;">Manage your portfolio →</a>
      <a class="l-btn l-btn-secondary" href="#portfolio">Browse listings</a>
    </div>
    <a class="lm-scroll-cue" href="#platform"><i></i>Scroll to explore</a>
  </div>
  <div class="l-hero-visual">
    <div class="l-photo"><div class="lm-photo-img"></div></div>
    <div class="l-floating l-float-top"><div class="l-small">Your lease income</div><div class="l-big">₱<span data-count="28500">28,500</span> / month</div><div class="l-verified">✓ Agreement documented</div></div>
    <div class="l-floating l-float-bottom"><div class="l-small">Portfolio status</div><div class="l-big"><span data-count="92">92</span>% Occupied</div><div class="l-verified"><span class="lm-pulse"></span><span data-count="12">12</span> active leases</div></div>
  </div>
</section>

<div class="l-marquee"><span>Property Owners</span><span>Commercial Spaces</span><span>Documented Agreements</span><span>Tenant Screening</span><span>Payment Tracking</span></div>

<section class="l-section" id="platform">
  <div class="l-intro">
    <div><div class="l-label">Why Pwestora</div><h2>Less uncertainty. More professional leasing.</h2></div>
    <div>
      <p>Managing commercial tenants well means clear boundaries, reliable communication, and a shared record of what was agreed. Pwestora is built around those principles for you, the lessor.</p>
      <p>Instead of relying on scattered messages and informal handshake agreements, every lease, payment, and maintenance request lives in one accountable system.</p>
    </div>
  </div>
  <div class="l-roles">
    <article class="l-role"><div class="l-icon-block">01</div><h3>Manage every space with confidence.</h3><p>Keep your commercial units, occupancy, agreements, and asking rates organized in one portfolio view.</p>
      <ul><li>Property and unit overview</li><li>Lease and agreement records</li><li>Payment monitoring</li><li>Maintenance request tracking</li></ul>
    </article>
    <article class="l-role"><div class="l-icon-block">02</div><h3>Build accountable partnerships.</h3><p>Screen prospective tenants, document lease conditions, and manage communication through a structured, traceable channel.</p>
      <ul><li>Tenant screening and verification</li><li>Digital lease agreements</li><li>Negotiated payment terms</li><li>Complaint and dispute logs</li></ul>
    </article>
  </div>
</section>

<section class="l-section" id="portfolio">
  <div class="l-label">Live listings</div>
  <h2>Commercial spaces on Pwestora right now.</h2>
  <p class="l-listings-sub">Every listing below is a real, document-verified property managed through Pwestora.</p>

  <?php if (!empty($listings)): ?>
    <?php $types = array_values(array_unique(array_column($listings, 'type'))); ?>
    <?php if (count($types) > 1): ?>
      <div class="l-chips" role="tablist" aria-label="Filter by type">
        <button type="button" class="l-chip active" data-type="all" onclick="filterListings('all', this)">All (<?= count($listings) ?>)</button>
        <?php foreach ($types as $t): ?>
          <button type="button" class="l-chip" data-type="<?= e($t) ?>" onclick="filterListings(<?= e(js($t)) ?>, this)"><?= e($t) ?></button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="lm-carousel" aria-roledescription="carousel" aria-label="Commercial spaces">
    <div class="lm-viewport">
    <div class="l-listings" id="lListings">
      <?php foreach ($listings as $i => $L): ?>
        <article class="l-listing" data-type="<?= e($L['type']) ?>" tabindex="0" role="button"
                 aria-label="View <?= e($L['name']) ?>" onclick="openListing(<?= $i ?>)" onkeydown="if(event.key==='Enter'){openListing(<?= $i ?>)}">
          <div class="l-listing-photo"<?php if ($L['photos']): ?> style="background-image:url('<?= e($L['photos'][0]) ?>');"<?php endif; ?>>
            <?php if (!$L['photos']): ?><span class="l-listing-nophoto">No photo yet</span><?php endif; ?>
            <span class="l-listing-type"><?= e($L['type']) ?></span>
            <?php if (count($L['photos']) > 1): ?><span class="l-listing-count">📷 <?= count($L['photos']) ?></span><?php endif; ?>
          </div>
          <div class="l-listing-body">
            <span class="l-status l-status-<?= e($L['tone']) ?>"><?= e($L['status']) ?></span>
            <h3><?= e($L['name']) ?></h3>
            <p class="l-listing-addr"><?= e($L['address']) ?></p>
            <div class="l-listing-foot">
              <div class="l-listing-rent"><?= $L['rent'] !== null ? '₱' . number_format($L['rent']) . ' <small>/ month</small>' : '<small>Rate on request</small>' ?></div>
              <span class="l-listing-more">View details →</span>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    </div>
    <div class="lm-car-bar">
      <div class="lm-dots" aria-label="Choose slide"></div>
      <div class="lm-arrows">
        <span class="lm-counter" aria-live="polite"></span>
        <button type="button" class="lm-arrow lm-prev" aria-label="Previous listings">&larr;</button>
        <button type="button" class="lm-arrow lm-next" aria-label="Next listings">&rarr;</button>
      </div>
    </div>
    </div>
  <?php else: ?>
    <div class="l-listings-empty">
      <h3>New spaces are being verified.</h3>
      <p>Our team checks every property's documents before it goes live. Own a commercial space? Be one of the first listed.</p>
      <a class="l-btn l-btn-primary" href="#" onclick="openAuthModal('register'); return false;">List your property →</a>
    </div>
  <?php endif; ?>
</section>

<section class="l-section" id="capabilities">
  <div class="l-label">Platform capabilities</div><h2>Built around the real friction points of leasing.</h2>
  <div class="l-features">
    <article class="l-feature"><div class="l-fico">01</div><h3>Lease &amp; Documentation</h3><p>Centralize lease terms, responsibilities, penalties, and amendments for every unit you manage.</p></article>
    <article class="l-feature"><div class="l-fico">02</div><h3>Payment Oversight</h3><p>Track due dates, document grace periods, and manage restructured payment arrangements with tenants.</p></article>
    <article class="l-feature"><div class="l-fico">03</div><h3>Facility Maintenance</h3><p>Log and track maintenance requests, repairs, and responsibilities so nothing falls through the cracks.</p></article>
    <article class="l-feature"><div class="l-fico">04</div><h3>Conflict Resolution</h3><p>Keep a structured record of complaints, response times, and step-by-step dispute resolution.</p></article>
    <article class="l-feature"><div class="l-fico">05</div><h3>Tenant Communication</h3><p>Keep decisions and conversations with tenants organized instead of relying on undocumented calls and texts.</p></article>
    <article class="l-feature"><div class="l-fico">06</div><h3>Portfolio Management</h3><p>Get a centralized operational view across every commercial space and tenant relationship you manage.</p></article>
  </div>

</section>

<section class="l-section l-workflow" id="process">
  <div class="l-label">The Pwestora process</div><h2>From listing a space to managing the relationship.</h2>
  <div class="l-steps">
    <article class="l-step"><div class="l-stepnum">01 — LIST</div><h3>List</h3><p>Add your commercial space with photos, terms, and asking rate.</p></article>
    <article class="l-step"><div class="l-stepnum">02 — SCREEN</div><h3>Screen</h3><p>Review applicants, verify tenants, and formalize lease conditions.</p></article>
    <article class="l-step"><div class="l-stepnum">03 — MANAGE</div><h3>Manage</h3><p>Track payments, maintenance, and communications in one place.</p></article>
    <article class="l-step"><div class="l-stepnum">04 — RESOLVE</div><h3>Resolve</h3><p>Handle disputes through documented, structured communication.</p></article>
  </div>
</section>


<section class="l-cta" id="get-started">
  <h2>Professionalize your leasing business.</h2>
  <p>Pwestora gives lessors clearer records, structured negotiations, and a more accountable way to manage every property and tenant relationship.</p>
  <a class="l-btn-gold" href="#" onclick="openAuthModal('register'); return false;">Start managing your properties →</a>
</section>
</main>

<footer class="l-footer">
  <div><div class="l-footbrand"><div class="l-brand-badge"><img src="/assets/img/logo-web.png?v=2" alt="Pwestora" style="height:20px;"></div></div><p>Commercial leasing, professionalized for lessors.</p></div>
  <div class="l-foot"><h4>Platform</h4><a href="#platform">Why Pwestora</a><a href="#portfolio">Live listings</a><a href="#capabilities">Capabilities</a><a href="#process">How it works</a></div>
  <div class="l-foot"><h4>Account</h4><a href="#" onclick="openAuthModal('login'); return false;">Sign in</a><a href="#" onclick="openAuthModal('register'); return false;">Create a lessor account</a></div>
  <div class="l-copyright">© <?= date('Y') ?> Pwestora. All rights reserved.</div>
</footer>

<!-- ===================== Listing details ===================== -->
<div class="l-modal-overlay" id="listingOverlay" onclick="if(event.target===this) closeListing()">
  <div class="l-modal l-lmodal" role="dialog" aria-modal="true" aria-labelledby="lmName">
    <button class="l-modal-close" type="button" onclick="closeListing()" aria-label="Close">&times;</button>
    <div class="l-lgallery" id="lmGallery">
      <button type="button" class="l-lnav l-lprev" id="lmPrev" onclick="stepPhoto(-1)" aria-label="Previous photo">‹</button>
      <button type="button" class="l-lnav l-lnext" id="lmNext" onclick="stepPhoto(1)" aria-label="Next photo">›</button>
      <span class="l-lcounter" id="lmCounter"></span>
    </div>
    <div class="l-lcontent">
      <div class="l-lhead">
        <span class="l-listing-type l-static" id="lmType"></span>
        <span class="l-status" id="lmStatus"></span>
      </div>
      <h3 id="lmName"></h3>
      <p class="l-listing-addr" id="lmAddr"></p>
      <div class="l-lfacts" id="lmFacts"></div>
      <p class="l-ldesc" id="lmDesc"></p>
      <a class="l-lmap" id="lmMap" href="#" target="_blank" rel="noopener">View on map ↗</a>
      <div class="l-lcta">
        <p><strong>Looking to rent?</strong> Apply for this space through the Pwestora mobile app.</p>
        <p><strong>Own a space like this?</strong> <a href="#" onclick="closeListing(); openAuthModal('register'); return false;">List it on Pwestora →</a></p>
      </div>
    </div>
  </div>
</div>

<!-- ===================== Auth Modal ===================== -->
<div class="l-modal-overlay" id="authOverlay" onclick="if(event.target===this) closeAuthModal()">
  <div class="l-modal">
    <button class="l-modal-close" onclick="closeAuthModal()">&times;</button>

    <div style="text-align:center; margin-bottom:18px;">
      <img src="/assets/img/logo-web.png?v=2" alt="Pwestora" style="height:28px;">
    </div>

    <div class="l-modal-tabs">
      <button type="button" class="l-modal-tab" id="tabLogin" onclick="switchTab('login')">Log In</button>
      <button type="button" class="l-modal-tab" id="tabRegister" onclick="switchTab('register')">Sign Up</button>
    </div>

    <!-- LOGIN PANE -->
    <div class="l-modal-pane" id="paneLogin">
      <p class="l-modal-hint">Log in as Admin (Lessor) or Super Admin.</p>
      <form method="POST" action="/login.php">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="l-field">
          <label>Email</label>
          <input type="email" name="email" required autocomplete="email">
        </div>
        <div class="l-field">
          <label>Password</label>
          <input type="password" name="password" required autocomplete="current-password">
        </div>
        <button class="l-modal-submit" type="submit">Log In</button>
      </form>
    </div>

    <!-- REGISTER PANE -->
    <div class="l-modal-pane" id="paneRegister">
      <p class="l-modal-hint">Register your business as a Lessor. Our team verifies your documents before you can list properties.</p>

      <div class="l-wizard-steps">
        <div class="l-wizard-dot-wrap"><div class="l-wizard-step-dot" data-step="1">1</div><div class="l-wizard-label">Company</div></div>
        <div class="l-wizard-line"></div>
        <div class="l-wizard-dot-wrap"><div class="l-wizard-step-dot" data-step="2">2</div><div class="l-wizard-label">Contact</div></div>
        <div class="l-wizard-line"></div>
        <div class="l-wizard-dot-wrap"><div class="l-wizard-step-dot" data-step="3">3</div><div class="l-wizard-label">Documents</div></div>
      </div>

      <form method="POST" action="/register.php" enctype="multipart/form-data" id="registerForm">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="l-wizard-pane active" data-step="1">
          <div class="l-field"><label>Company / Business Name</label><input type="text" name="company_name" required></div>
          <div class="l-field"><label>Full Name</label><input type="text" name="full_name" required></div>
        </div>

        <div class="l-wizard-pane" data-step="2">
          <div class="l-field"><label>Email</label><input type="email" name="email" required></div>
          <div class="l-field"><label>Phone</label><input type="tel" name="phone"></div>
          <div class="l-field"><label>Password (min. 8 characters)</label><input type="password" name="password" required minlength="8"></div>
        </div>

        <div class="l-wizard-pane" data-step="3">
          <p class="l-modal-hint">PDF, JPG, or PNG · max 5MB each.</p>
          <?php foreach ($REQUIRED_DOCS as $field => $label): ?>
            <label class="l-upload-box" for="upload_<?= $field ?>">
              <div class="l-upload-icon">⬆</div>
              <div class="l-upload-text"><?= htmlspecialchars($label) ?></div>
              <div class="l-upload-filename"></div>
              <input type="file" id="upload_<?= $field ?>" name="<?= $field ?>" accept=".pdf,.jpg,.jpeg,.png" required>
            </label>
          <?php endforeach; ?>
        </div>

        <div class="l-wizard-nav">
          <button type="button" class="l-wizard-btn" id="wizPrev" onclick="prevStep()" style="visibility:hidden;">Previous</button>
          <button type="button" class="l-wizard-btn primary" id="wizNext" onclick="nextStep()">Next</button>
          <button type="submit" class="l-wizard-btn primary" id="wizSubmit" style="display:none;">Create Account</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
/* ---------------- Live listings ---------------- */
var LISTINGS = <?= js($listings ?? []) ?>;
var lmIndex = 0, lmPhoto = 0;

function filterListings(type, btn) {
  document.querySelectorAll('.l-chip').forEach(function (c) { c.classList.toggle('active', c === btn); });
  document.querySelectorAll('.l-listing').forEach(function (card) {
    var match = type === 'all' || card.getAttribute('data-type') === type;
    card.classList.toggle('l-hidden', !match);
  });
  // The carousel shows 3 at a time and slides through the rest (assets/js/landing-motion.js).
  if (window.LMCarousel) window.LMCarousel.refresh();
}

function openListing(i) {
  var L = LISTINGS[i]; if (!L) return;
  lmIndex = i; lmPhoto = 0;
  document.getElementById('lmName').textContent = L.name;
  document.getElementById('lmAddr').textContent = L.address;
  document.getElementById('lmType').textContent = L.type;
  var st = document.getElementById('lmStatus'); st.textContent = L.status; st.className = 'l-status l-status-' + L.tone;
  var facts = [];
  facts.push(['Monthly rent', L.rent !== null ? '₱' + Number(L.rent).toLocaleString('en-PH') : 'On request']);
  if (L.floor_area) facts.push(['Floor area', /^\d+(\.\d+)?$/.test(L.floor_area) ? L.floor_area + ' sqm' : L.floor_area]);
  if (L.lease_term) facts.push(['Lease term', /^\d+$/.test(L.lease_term) ? L.lease_term + (L.lease_term === '1' ? ' month' : ' months') : L.lease_term]);
  if (L.escalation) facts.push(['Escalation', /^\d+(\.\d+)?$/.test(L.escalation) ? L.escalation + '% / year' : L.escalation]);
  if (L.floor_level) facts.push(['Floor / level', L.floor_level]);
  if (L.frontage) facts.push(['Frontage', L.frontage]);
  if (L.parking !== null && L.parking !== undefined) facts.push(['Parking', L.parking + (L.parking === 1 ? ' slot' : ' slots')]);
  if (L.hours) facts.push(['Hours', L.hours]);
  if (L.deposit) facts.push(['Deposit & advance', L.deposit]);
  if (L.landmark) facts.push(['Near', L.landmark]);
  if (L.permitted) facts.push(['Permitted use', L.permitted]);
  if (L.utilities) facts.push(['Utilities', L.utilities]);
  if (L.amenities) facts.push(['Amenities', L.amenities]);
  if (L.listed) facts.push(['Listed', L.listed]);
  var box = document.getElementById('lmFacts'); box.innerHTML = '';
  facts.forEach(function (f) {
    var d = document.createElement('div'); var k = document.createElement('span'); var v = document.createElement('strong');
    k.textContent = f[0]; v.textContent = f[1]; d.appendChild(k); d.appendChild(v); box.appendChild(d);
  });
  var desc = document.getElementById('lmDesc'); desc.textContent = L.description || ''; desc.style.display = L.description ? '' : 'none';
  var map = document.getElementById('lmMap'); map.style.display = L.map ? '' : 'none'; if (L.map) map.href = L.map;
  renderPhoto();
  document.getElementById('listingOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function renderPhoto() {
  var L = LISTINGS[lmIndex], g = document.getElementById('lmGallery'), n = L.photos.length;
  g.style.backgroundImage = n ? "url('" + L.photos[lmPhoto].replace(/'/g, '%27') + "')" : 'none';
  g.classList.toggle('l-nophoto', !n);
  document.getElementById('lmPrev').style.display = n > 1 ? '' : 'none';
  document.getElementById('lmNext').style.display = n > 1 ? '' : 'none';
  var c = document.getElementById('lmCounter');
  c.textContent = n > 1 ? (lmPhoto + 1) + ' / ' + n : (n ? '' : 'No photos yet');
  c.style.display = c.textContent ? '' : 'none';
}

function stepPhoto(d) {
  var n = LISTINGS[lmIndex].photos.length; if (n < 2) return;
  lmPhoto = (lmPhoto + d + n) % n; renderPhoto();
}

function closeListing() {
  document.getElementById('listingOverlay').classList.remove('open');
  document.body.style.overflow = '';
}

/* ---------------- Mobile menu ---------------- */
function toggleMobileMenu(force) {
  var m = document.getElementById('lMobileMenu'), b = document.getElementById('lMenuBtn');
  var open = typeof force === 'boolean' ? force : m.hasAttribute('hidden');
  if (open) { m.removeAttribute('hidden'); } else { m.setAttribute('hidden', ''); }
  b.setAttribute('aria-expanded', open ? 'true' : 'false');
  b.textContent = open ? '✕' : '☰';
}

function openAuthModal(tab) {
  document.getElementById('authOverlay').classList.add('open');
  switchTab(tab);
  document.body.style.overflow = 'hidden';
}
function closeAuthModal() {
  document.getElementById('authOverlay').classList.remove('open');
  document.body.style.overflow = '';
}
function switchTab(tab) {
  const isLogin = tab === 'login';
  document.getElementById('tabLogin').classList.toggle('active', isLogin);
  document.getElementById('tabRegister').classList.toggle('active', !isLogin);
  document.getElementById('paneLogin').classList.toggle('active', isLogin);
  document.getElementById('paneRegister').classList.toggle('active', !isLogin);
  if (!isLogin) showStep(1);
}
// Close on Escape key
document.addEventListener('keydown', function (e) {
  if (document.getElementById('listingOverlay').classList.contains('open')) {
    if (e.key === 'Escape') { closeListing(); return; }
    if (e.key === 'ArrowLeft') { stepPhoto(-1); return; }
    if (e.key === 'ArrowRight') { stepPhoto(1); return; }
  }
  if (e.key === 'Escape') closeAuthModal();
});

// ---------------- Registration wizard ----------------
let currentStep = 1;
const totalSteps = 3;

function showStep(n) {
  document.querySelectorAll('.l-wizard-pane').forEach(function (p) {
    p.classList.toggle('active', +p.dataset.step === n);
  });
  document.querySelectorAll('.l-wizard-step-dot').forEach(function (d) {
    const s = +d.dataset.step;
    d.classList.toggle('active', s === n);
    d.classList.toggle('done', s < n);
    d.textContent = s < n ? '✓' : s;
  });
  document.querySelectorAll('.l-wizard-line').forEach(function (l, i) {
    l.classList.toggle('done', (i + 1) < n);
  });
  document.getElementById('wizPrev').style.visibility = n === 1 ? 'hidden' : 'visible';
  document.getElementById('wizNext').style.display = n === totalSteps ? 'none' : 'block';
  document.getElementById('wizSubmit').style.display = n === totalSteps ? 'block' : 'none';
  currentStep = n;
}

function nextStep() {
  const pane = document.querySelector('.l-wizard-pane[data-step="' + currentStep + '"]');
  const inputs = pane.querySelectorAll('input[required]');
  for (const inp of inputs) {
    if (!inp.reportValidity()) return;
  }
  if (currentStep < totalSteps) showStep(currentStep + 1);
}

function prevStep() {
  if (currentStep > 1) showStep(currentStep - 1);
}

// Professional file-upload boxes: show the chosen filename, mark as filled.
document.querySelectorAll('.l-upload-box input[type="file"]').forEach(function (inp) {
  inp.addEventListener('change', function () {
    const box = this.closest('.l-upload-box');
    const nameEl = box.querySelector('.l-upload-filename');
    if (this.files.length) {
      box.classList.add('has-file');
      nameEl.textContent = this.files[0].name;
    } else {
      box.classList.remove('has-file');
      nameEl.textContent = '';
    }
  });
});

/* ---------------- Landing refresh behavior ----------------
   If a user refreshes while the URL contains #platform, #process,
   #portfolio, etc., remove the hash and return to the hero section.
------------------------------------------------------------ */
(function () {
  if (window.location.hash) {
    if ('scrollRestoration' in history) {
      history.scrollRestoration = 'manual';
    }

    window.addEventListener('load', function () {
      history.replaceState(
        null,
        document.title,
        window.location.pathname + window.location.search
      );

      window.scrollTo(0, 0);
    }, { once: true });
  }
})();

</script>
<script src="/assets/js/landing-motion.js?v=<?= filemtime(APP_ROOT . '/assets/js/landing-motion.js') ?>" defer></script>
<script src="/assets/js/pwa.js" defer></script>

</body>
</html>