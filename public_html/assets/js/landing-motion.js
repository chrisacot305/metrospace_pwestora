/* =====================================================================
   assets/js/landing-motion.js — animations for the public landing page.
   Plain JavaScript, no libraries. Loaded with "defer" at the end of
   app/Views/auth/landing.php. Works together with landing-motion.css.

   1. Preloader (counts to 100%, then slides away — once per visit)
   2. Hero entrance + mouse parallax + number count-up
   3. Nav: shrinks on scroll, hides when scrolling down, highlights the current section
   4. Scroll reveals (cards fade up one after another, headings word by word)
   5. Listings carousel: 3 visible, autoplay, arrows, dots, swipe/drag, works with the type chips
   6. Extras: infinite marquee, workflow progress line, CTA light, magnetic buttons
   ===================================================================== */
(function () {
  'use strict';

  var root = document.documentElement;
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var finePointer = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  /* ------------------------------------------------------------ 1. preloader */
  function startPage() {
    if (root.classList.contains('lm-ready')) return;
    root.classList.add('lm-ready');
    countUp();
  }

  (function preloader() {
    var loader = $('.lm-loader');
    var seen = root.classList.contains('lm-seen');
    if (!loader || seen || reduced) { requestAnimationFrame(startPage); return; }

    var countEl = $('.lm-loader-count', loader);
    var bar = $('.lm-loader-bar', loader);
    var loaded = false, shown = 0, t0 = performance.now(), MIN = 1300;
    window.addEventListener('load', function () { loaded = true; });

    (function tick(now) {
      // Move toward 90% while loading, then to 100% once the page has loaded (and the minimum time passed).
      var target = loaded && now - t0 > MIN ? 100 : Math.min(90, (now - t0) / MIN * 90);
      shown += (target - shown) * 0.12;
      if (target === 100 && shown > 99.5) shown = 100;
      countEl.textContent = Math.round(shown) + '%';
      bar.style.transform = 'scaleX(' + (shown / 100) + ')';
      if (shown < 100) { requestAnimationFrame(tick); return; }
      try { sessionStorage.setItem('lm-seen', '1'); } catch (e) {}
      loader.classList.add('lm-done');
      setTimeout(startPage, 250);
      setTimeout(function () { loader.style.display = 'none'; }, 1100);
    })(t0);
  })();

  /* --------------------------------------------------------- 2. hero extras */
  function countUp() {
    $$('[data-count]').forEach(function (el) {
      var end = parseFloat(el.getAttribute('data-count')) || 0;
      if (reduced) { el.textContent = end.toLocaleString('en-PH'); return; }
      var start = performance.now(), dur = 1800, delay = 900;
      setTimeout(function () {
        (function step(now) {
          var p = Math.min(1, (now - start - delay) / dur);
          if (p < 0) { requestAnimationFrame(step); return; }
          var eased = 1 - Math.pow(1 - p, 4);
          el.textContent = Math.round(end * eased).toLocaleString('en-PH');
          if (p < 1) requestAnimationFrame(step);
        })(performance.now());
      }, 0);
    });
  }

  var hero = $('.l-hero');
  if (hero && finePointer && !reduced) {
    hero.addEventListener('mousemove', function (e) {
      var r = hero.getBoundingClientRect();
      var x = (e.clientX - r.left) / r.width - 0.5, y = (e.clientY - r.top) / r.height - 0.5;
      hero.style.setProperty('--lm-mx', (x * -14).toFixed(1) + 'px');
      hero.style.setProperty('--lm-my', (y * -14).toFixed(1) + 'px');
    });
    hero.addEventListener('mouseleave', function () { hero.style.setProperty('--lm-mx', '0px'); hero.style.setProperty('--lm-my', '0px'); });
  }

  /* --------------------------------------------------------------- 3. nav */
  var nav = $('.l-nav');
  var progress = $('.lm-progress');
  var lastY = window.scrollY;
  var steps = $('.l-steps');
  var ticking = false;

  function onScroll() {
    var y = window.scrollY;
    var max = document.documentElement.scrollHeight - window.innerHeight;
    if (progress) progress.style.setProperty('--lm-progress', max > 0 ? (y / max).toFixed(4) : 0);
    if (nav) {
      nav.classList.toggle('lm-scrolled', y > 30);
      var menuOpen = $('#lMobileMenu') && !$('#lMobileMenu').hasAttribute('hidden');
      nav.classList.toggle('lm-hide', !menuOpen && y > lastY && y > 520);
    }
    lastY = y;

    if (!reduced) {
      if (steps) {
        var r = steps.getBoundingClientRect();
        var p = (window.innerHeight * 0.85 - r.top) / (r.height + window.innerHeight * 0.35);
        steps.style.setProperty('--lm-steps', Math.max(0, Math.min(1, p)).toFixed(3));
      }
    }
    ticking = false;
  }
  window.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(onScroll); } }, { passive: true });
  onScroll();

  // Highlight the nav link of the section on screen.
  if ('IntersectionObserver' in window) {
    var links = $$('.l-links a[href^="#"]');
    var spy = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        links.forEach(function (a) { a.classList.toggle('lm-active', a.getAttribute('href') === '#' + en.target.id); });
      });
    }, { rootMargin: '-45% 0px -50% 0px' });
    links.forEach(function (a) { var s = $(a.getAttribute('href')); if (s) spy.observe(s); });
  }

  /* ------------------------------------------------------ 4. scroll reveals */
  // Headings: wrap each word so it can rise into place. Only plain-text headings are split.
  $$('.landing main h2, .l-cta h2').forEach(function (h) {
    if (h.children.length) return;
    h.setAttribute('data-lm-text', h.textContent);
    var words = h.textContent.trim().split(/\s+/);
    h.textContent = '';
    words.forEach(function (w, i) {
      var outer = document.createElement('span'); outer.className = 'lm-w';
      var inner = document.createElement('span'); inner.textContent = w;
      inner.style.setProperty('--lm-w', i);
      outer.appendChild(inner); h.appendChild(outer);
      if (i < words.length - 1) h.appendChild(document.createTextNode(' '));
    });
    h.classList.add('lm-words');
  });

  // Groups: each child gets a stagger index so they appear one after another.
  var groups = [
    ['.l-intro > div', 0], ['.l-roles > .l-role', 0], ['.l-features > .l-feature', 0], ['.l-steps > .l-step', 0],
    ['.l-listings-sub', 0], ['.l-chips', 0], ['.lm-carousel', 0], ['.l-listings-empty', 0],
    ['.l-cta p', 0], ['.l-footer > div', 0]
  ];
  groups.forEach(function (g) {
    $$(g[0]).forEach(function (el, i) {
      el.classList.add('lm-reveal');
      el.style.setProperty('--lm-i', (i % 6) + g[1]);
    });
  });
  $$('.l-label').forEach(function (el) { el.classList.add('lm-reveal'); });

  // After a heading has finished rising in, turn it back into plain text so the normal
  // letter and word spacing of the font is used (the per-word boxes are only for the animation).
  function restoreText(el, now) {
    var txt = el.getAttribute('data-lm-text');
    if (txt === null) return;
    var n = el.querySelectorAll('.lm-w').length;
    setTimeout(function () {
      el.textContent = txt.trim();
      el.classList.remove('lm-words');
      el.removeAttribute('data-lm-text');
    }, now ? 0 : 1000 + n * 45 + 100);
  }

  if ('IntersectionObserver' in window && !reduced) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        var el = en.target;
        el.classList.add('lm-in'); io.unobserve(el);
        restoreText(el);
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });
    $$('.lm-reveal, .lm-words').forEach(function (el) { io.observe(el); });
  } else {
    $$('.lm-reveal, .lm-words').forEach(function (el) { el.classList.add('lm-in'); restoreText(el, true); });
  }

  /* -------------------------------------------------- 5. listings carousel */
  var Carousel = (function () {
    var wrap = $('.lm-carousel');
    if (!wrap) return { refresh: function () {} };
    var track = $('#lListings', wrap);
    var prev = $('.lm-prev', wrap), next = $('.lm-next', wrap);
    var dotsBox = $('.lm-dots', wrap), counter = $('.lm-counter', wrap), bar = $('.lm-car-bar', wrap);
    var AUTOPLAY = 4500;
    var index = 0, timer = null, hovering = false, visibleOnScreen = true;
    wrap.style.setProperty('--lm-autoplay', AUTOPLAY + 'ms');

    // Photos become an inner layer so they can zoom on hover without moving the card.
    $$('.l-listing-photo', track).forEach(function (ph) {
      var bg = ph.style.backgroundImage;
      if (bg && bg !== 'none') {
        var img = document.createElement('div'); img.className = 'lm-card-img'; img.style.backgroundImage = bg;
        ph.insertBefore(img, ph.firstChild);
      }
    });

    function cards() { return $$('.l-listing', track).filter(function (c) { return !c.classList.contains('l-hidden'); }); }
    function perView() { return window.innerWidth <= 640 ? 1 : (window.innerWidth <= 1000 ? 2 : 3); }
    function pages() { return Math.max(1, cards().length - perView() + 1); }

    function go(i, animateCards) {
      var list = cards(), total = pages();
      index = (i + total) % total;
      var first = list[index];
      var offset = first ? first.offsetLeft - list[0].offsetLeft : 0;
      track.style.transform = 'translate3d(' + (-offset) + 'px,0,0)';
      if (animateCards) {
        list.forEach(function (c, k) {
          c.classList.remove('lm-enter');
          if (k >= index && k < index + perView()) {
            void c.offsetWidth; c.style.setProperty('--lm-i', k - index); c.classList.add('lm-enter');
          }
        });
      }
      renderUi();
      restart();
    }

    function renderUi() {
      var total = pages(), n = cards().length;
      bar.classList.toggle('lm-single', total <= 1);
      counter.textContent = String(Math.min(index + 1, total)).padStart(2, '0') + ' / ' + String(total).padStart(2, '0');
      if (dotsBox.children.length !== total) {
        dotsBox.innerHTML = '';
        for (var d = 0; d < total; d++) {
          var b = document.createElement('button');
          b.type = 'button'; b.className = 'lm-dot'; b.setAttribute('aria-label', 'Show listings from ' + (d + 1));
          (function (k) { b.addEventListener('click', function () { go(k); }); })(d);
          dotsBox.appendChild(b);
        }
      }
      $$('.lm-dot', dotsBox).forEach(function (b, k) {
        b.classList.remove('active');
        if (k === index) { void b.offsetWidth; b.classList.add('active'); }
      });
      prev.disabled = next.disabled = n <= perView();
    }

    function restart() {
      clearTimeout(timer);
      if (reduced || pages() <= 1) return;
      timer = setTimeout(function () {
        if (hovering || !visibleOnScreen || document.hidden) { restart(); return; }
        go(index + 1);
      }, AUTOPLAY);
    }
    function setPaused(p) { wrap.classList.toggle('lm-paused', p); }

    prev.addEventListener('click', function () { go(index - 1); });
    next.addEventListener('click', function () { go(index + 1); });
    wrap.addEventListener('mouseenter', function () { hovering = true; setPaused(true); });
    wrap.addEventListener('mouseleave', function () { hovering = false; setPaused(false); restart(); });
    wrap.addEventListener('focusin', function () { hovering = true; setPaused(true); });
    wrap.addEventListener('focusout', function () { hovering = false; setPaused(false); });
    wrap.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') { go(index - 1); } else if (e.key === 'ArrowRight') { go(index + 1); }
    });
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (en) { visibleOnScreen = en[0].isIntersecting; }, { threshold: 0.2 }).observe(wrap);
    }

    // Drag / swipe. A real drag cancels the card click that would open the details.
    var startX = 0, dx = 0, dragging = false, moved = false, baseOffset = 0;
    track.addEventListener('pointerdown', function (e) {
      if (e.pointerType === 'mouse' && e.button !== 0) return;
      dragging = true; moved = false; startX = e.clientX; dx = 0;
      var m = /translate3d\((-?[\d.]+)px/.exec(track.style.transform || '');
      baseOffset = m ? parseFloat(m[1]) : 0;
    });
    window.addEventListener('pointermove', function (e) {
      if (!dragging) return;
      dx = e.clientX - startX;
      if (Math.abs(dx) > 6) { moved = true; track.classList.add('lm-dragging'); }
      if (moved) track.style.transform = 'translate3d(' + (baseOffset + dx) + 'px,0,0)';
    });
    window.addEventListener('pointerup', function () {
      if (!dragging) return;
      dragging = false; track.classList.remove('lm-dragging');
      if (moved) { go(index + (dx < -50 ? 1 : dx > 50 ? -1 : 0)); }
    });
    track.addEventListener('click', function (e) { if (moved) { e.stopPropagation(); e.preventDefault(); moved = false; } }, true);
    track.addEventListener('dragstart', function (e) { e.preventDefault(); });

    var rt;
    window.addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(function () { go(Math.min(index, pages() - 1)); }, 150); });
    document.addEventListener('visibilitychange', function () { if (!document.hidden) restart(); });

    go(0);
    return { refresh: function () { go(0, true); } };
  })();
  window.LMCarousel = Carousel;   // filterListings() in landing.php calls LMCarousel.refresh()

  /* ------------------------------------------------------------- 6. extras */
  // Infinite marquee: duplicate the words so the strip can loop without a gap.
  var marquee = $('.l-marquee');
  if (marquee && !$('.lm-marquee-track', marquee)) {
    var track2 = document.createElement('div'); track2.className = 'lm-marquee-track';
    var items = $$('span', marquee);
    for (var r = 0; r < 2; r++) {
      for (var k = 0; k < 2; k++) { items.forEach(function (s) { track2.appendChild(s.cloneNode(true)); }); }
    }
    marquee.innerHTML = ''; marquee.appendChild(track2);
    $$('span', track2).forEach(function (s, i) { if (i >= items.length) s.setAttribute('aria-hidden', 'true'); });
  }

  // CTA light follows the mouse.
  var cta = $('.l-cta');
  if (cta && finePointer && !reduced) {
    cta.addEventListener('mousemove', function (e) {
      var r = cta.getBoundingClientRect();
      cta.style.setProperty('--lm-cx', (e.clientX - r.left) + 'px');
      cta.style.setProperty('--lm-cy', (e.clientY - r.top) + 'px');
    });
  }

  // Magnetic buttons.
  if (finePointer && !reduced) {
    $$('.l-btn-primary, .l-btn-gold, .l-nav-cta, .lm-arrow').forEach(function (btn) {
      btn.classList.add('lm-magnetic');
      btn.addEventListener('mousemove', function (e) {
        var r = btn.getBoundingClientRect();
        var x = (e.clientX - r.left - r.width / 2) * 0.25, y = (e.clientY - r.top - r.height / 2) * 0.35;
        btn.style.transform = 'translate(' + x.toFixed(1) + 'px,' + y.toFixed(1) + 'px)';
      });
      btn.addEventListener('mouseleave', function () { btn.style.transform = ''; });
    });
  }
})();