/**
 * assets/js/photo-uploader.js — drag & drop photo uploader for Pwestora (no libraries).
 *
 * Markup (see app/Views/lessor/partials/photo_uploader.php):
 *   <div class="pm-uploader" data-input="photos[]" data-max-files="15" data-max-mb="5"
 *        data-accept="jpg,jpeg,png,webp" data-categories='{"exterior":"Exterior",...}' data-single="0">
 *     <input type="file" class="pm-file-input" name="photos[]" multiple hidden>
 *     <div class="pm-dropzone">...</div>
 *     <div class="pm-error" hidden></div>
 *     <div class="pm-queue"></div>
 *   </div>
 *
 * What it does:
 *   - click or drag files onto the drop zone; paste also works
 *   - checks type, size and the photo limit BEFORE anything is uploaded
 *   - shows a thumbnail card per file with category + caption (gallery) and a remove button
 *   - keeps the real <input type="file"> in sync (DataTransfer) so the normal form post works
 *   - forms marked data-pm-progress submit through XHR with an upload progress bar
 */
(function () {
  'use strict';

  function fmtSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  }

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }

  var canSync = (function () {
    try { return typeof DataTransfer !== 'undefined' && !!new DataTransfer().items; } catch (e) { return false; }
  })();

  function Uploader(root) {
    this.root = root;
    this.input = root.querySelector('.pm-file-input');
    this.zone = root.querySelector('.pm-dropzone');
    this.queueBox = root.querySelector('.pm-queue');
    this.errorBox = root.querySelector('.pm-error');
    this.counter = root.querySelector('.pm-counter');
    this.single = root.getAttribute('data-single') === '1';
    this.maxFiles = this.single ? 1 : parseInt(root.getAttribute('data-max-files') || '10', 10);
    this.maxBytes = parseFloat(root.getAttribute('data-max-mb') || '5') * 1024 * 1024;
    this.accept = (root.getAttribute('data-accept') || 'jpg,jpeg,png,webp').split(',');
    this.required = root.getAttribute('data-required') === '1';
    try { this.categories = JSON.parse(root.getAttribute('data-categories') || '{}'); } catch (e) { this.categories = {}; }
    this.defaultCategory = root.getAttribute('data-default-category') || Object.keys(this.categories)[0] || '';
    this.items = []; // { file, url, category, caption }
    this.bind();
    this.render();
  }

  Uploader.prototype.bind = function () {
    var self = this;
    this.zone.addEventListener('click', function () { self.input.click(); });
    this.zone.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); self.input.click(); }
    });
    ['dragenter', 'dragover'].forEach(function (ev) {
      self.zone.addEventListener(ev, function (e) { e.preventDefault(); self.zone.classList.add('is-drag'); });
    });
    ['dragleave', 'dragend', 'drop'].forEach(function (ev) {
      self.zone.addEventListener(ev, function (e) { e.preventDefault(); self.zone.classList.remove('is-drag'); });
    });
    this.zone.addEventListener('drop', function (e) {
      if (e.dataTransfer && e.dataTransfer.files) self.add(e.dataTransfer.files);
    });
    this.input.addEventListener('change', function () {
      // Without DataTransfer support the browser's own selection is used as-is.
      if (!canSync) { self.items = []; }
      self.add(self.input.files, true);
    });
    var form = this.root.closest('form');
    if (form) {
      form.addEventListener('submit', function (e) {
        if (self.required && self.items.length === 0) {
          e.preventDefault();
          e.stopImmediatePropagation();
          self.showError('Please add at least one photo.');
          self.zone.scrollIntoView({ behavior: 'smooth', block: 'center' });
          return;
        }
        self.writeMeta(form);
      }, true);
    }
  };

  Uploader.prototype.showError = function (msgs) {
    if (!this.errorBox) return;
    msgs = [].concat(msgs || []);
    if (!msgs.length) { this.errorBox.hidden = true; this.errorBox.textContent = ''; return; }
    this.errorBox.hidden = false;
    this.errorBox.innerHTML = '';
    var self = this;
    msgs.forEach(function (m) { self.errorBox.appendChild(el('div', null, m)); });
  };

  Uploader.prototype.add = function (fileList, fromInput) {
    var errors = [];
    var files = Array.prototype.slice.call(fileList || []);
    if (this.single) { this.clear(); files = files.slice(0, 1); }
    for (var i = 0; i < files.length; i++) {
      var f = files[i];
      var ext = (f.name.split('.').pop() || '').toLowerCase();
      if (this.accept.indexOf(ext) === -1) { errors.push('"' + f.name + '" is not a supported image (' + this.accept.join(', ').toUpperCase() + ').'); continue; }
      if (f.size > this.maxBytes) { errors.push('"' + f.name + '" is ' + fmtSize(f.size) + ' — the limit is ' + fmtSize(this.maxBytes) + '.'); continue; }
      if (this.items.length >= this.maxFiles) { errors.push('Photo limit reached (' + this.maxFiles + '). "' + f.name + '" was not added.'); continue; }
      var dupe = this.items.some(function (it) { return it.file.name === f.name && it.file.size === f.size; });
      if (dupe) continue;
      this.items.push({ file: f, url: URL.createObjectURL(f), category: this.defaultCategory, caption: '' });
    }
    this.showError(errors);
    this.sync();
    this.render();
    if (!canSync && fromInput && errors.length) {
      // Can't drop just the bad files without DataTransfer: make the user pick again.
      this.input.value = ''; this.items = []; this.render();
    }
  };

  Uploader.prototype.remove = function (idx) {
    var it = this.items[idx];
    if (it) URL.revokeObjectURL(it.url);
    this.items.splice(idx, 1);
    this.sync();
    this.render();
  };

  Uploader.prototype.clear = function () {
    this.items.forEach(function (it) { URL.revokeObjectURL(it.url); });
    this.items = [];
  };

  Uploader.prototype.sync = function () {
    if (!canSync) return;
    var dt = new DataTransfer();
    this.items.forEach(function (it) { dt.items.add(it.file); });
    this.input.files = dt.files;
  };

  /** Hidden photo_categories[] / photo_captions[] in the same order as the files. */
  Uploader.prototype.writeMeta = function (form) {
    if (this.single || !Object.keys(this.categories).length) return;
    form.querySelectorAll('input.pm-meta').forEach(function (n) { n.remove(); });
    this.items.forEach(function (it) {
      ['photo_categories[]', 'photo_captions[]'].forEach(function (name, k) {
        var h = document.createElement('input');
        h.type = 'hidden'; h.className = 'pm-meta'; h.name = name;
        h.value = k === 0 ? it.category : it.caption;
        form.appendChild(h);
      });
    });
  };

  Uploader.prototype.render = function () {
    var self = this;
    this.queueBox.innerHTML = '';
    this.root.classList.toggle('has-files', this.items.length > 0);
    if (this.counter) {
      this.counter.textContent = this.single
        ? (this.items.length ? '1 photo selected' : 'No photo selected')
        : this.items.length + ' / ' + this.maxFiles + ' selected';
    }
    this.items.forEach(function (it, idx) {
      var card = el('div', 'pm-qcard');
      var thumb = el('div', 'pm-qthumb');
      var img = el('img'); img.src = it.url; img.alt = it.file.name;
      thumb.appendChild(img);
      if (!self.single && idx === 0 && self.root.getAttribute('data-first-is-cover') === '1') {
        thumb.appendChild(el('span', 'pm-chip pm-chip-cover', 'Cover'));
      }
      card.appendChild(thumb);

      var body = el('div', 'pm-qbody');
      var top = el('div', 'pm-qtop');
      top.appendChild(el('span', 'pm-qname', it.file.name));
      top.appendChild(el('span', 'pm-qsize', fmtSize(it.file.size)));
      body.appendChild(top);

      if (!self.single && Object.keys(self.categories).length) {
        var row = el('div', 'pm-qfields');
        var sel = el('select', 'pm-input pm-input-sm');
        sel.setAttribute('aria-label', 'Photo category');
        Object.keys(self.categories).forEach(function (k) {
          var o = el('option', null, self.categories[k]); o.value = k;
          if (k === it.category) o.selected = true;
          sel.appendChild(o);
        });
        sel.addEventListener('change', function () { it.category = sel.value; });
        var cap = el('input', 'pm-input pm-input-sm');
        cap.type = 'text'; cap.maxLength = 150; cap.placeholder = 'Caption (optional)'; cap.value = it.caption;
        cap.addEventListener('input', function () { it.caption = cap.value; });
        row.appendChild(sel); row.appendChild(cap);
        body.appendChild(row);
      }
      card.appendChild(body);

      var rm = el('button', 'pm-icon-btn pm-icon-btn-danger');
      rm.type = 'button'; rm.title = 'Remove'; rm.setAttribute('aria-label', 'Remove ' + it.file.name);
      rm.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>';
      rm.addEventListener('click', function () { self.remove(idx); });
      card.appendChild(rm);
      self.queueBox.appendChild(card);
    });
  };

  // ---------------------------------------------------------------- XHR submit with progress
  function bindProgress(form) {
    form.addEventListener('submit', function (e) {
      if (e.defaultPrevented || !window.FormData || !window.XMLHttpRequest) return;
      var hasFiles = Array.prototype.some.call(form.querySelectorAll('input[type=file]'), function (i) { return i.files && i.files.length; });
      if (!hasFiles) return; // nothing to upload: normal submit
      e.preventDefault();

      // The server rejects one save larger than post_max_size (30 MB on Hostinger, see .user.ini).
      var maxTotal = parseFloat(form.getAttribute('data-max-total-mb') || '0') * 1024 * 1024;
      if (maxTotal) {
        var total = 0;
        form.querySelectorAll('input[type=file]').forEach(function (i) {
          Array.prototype.forEach.call(i.files || [], function (f) { total += f.size; });
        });
        if (total > maxTotal) {
          var eb = form.querySelector('.pm-form-error');
          var m = 'These files add up to ' + fmtSize(total) + '. One save can upload up to ' + fmtSize(maxTotal) +
                  ' — remove a few photos, save, then add the rest.';
          if (eb) { eb.textContent = m; eb.hidden = false; eb.scrollIntoView({ behavior: 'smooth', block: 'center' }); } else { alert(m); }
          return;
        }
      }

      var overlay = document.getElementById('pmProgress');
      var bar = overlay && overlay.querySelector('.pm-progress-fill');
      var label = overlay && overlay.querySelector('.pm-progress-label');
      if (overlay) overlay.hidden = false;
      var buttons = form.querySelectorAll('button[type=submit]');
      buttons.forEach(function (b) { b.disabled = true; });

      var reqUrl = new URL(form.getAttribute('action') || window.location.href, window.location.href).href;
      var xhr = new XMLHttpRequest();
      xhr.open(form.method || 'POST', reqUrl, true);
      xhr.upload.addEventListener('progress', function (ev) {
        if (!ev.lengthComputable || !bar) return;
        var pct = Math.round((ev.loaded / ev.total) * 100);
        bar.style.width = pct + '%';
        if (label) label.textContent = pct < 100 ? 'Uploading… ' + pct + '%' : 'Processing…';
      });
      xhr.onload = function () {
        // Success = the server redirected (Post/Redirect/Get). Otherwise show the page it returned (with the error).
        if (xhr.status < 400 && xhr.responseURL && xhr.responseURL !== reqUrl) {
          window.location.href = xhr.responseURL;
          return;
        }
        // The server answered with the same page + an error message: show it here and keep the
        // selected files, so the user can fix the problem without choosing them again.
        var msg = '';
        try {
          var doc = new DOMParser().parseFromString(xhr.responseText, 'text/html');
          var node = doc.querySelector('[data-pm-error]') || doc.querySelector('.error-msg');
          msg = node ? node.textContent.trim() : '';
        } catch (err) { msg = ''; }
        if (overlay) overlay.hidden = true;
        buttons.forEach(function (b) { b.disabled = false; });
        var box = form.querySelector('.pm-form-error');
        if (box) {
          box.textContent = msg || 'Something went wrong while saving. Please try again.';
          box.hidden = false;
          box.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
          alert(msg || 'Something went wrong while saving. Please try again.');
        }
      };
      xhr.onerror = function () {
        if (overlay) overlay.hidden = true;
        buttons.forEach(function (b) { b.disabled = false; });
        alert('Upload failed — please check your internet connection and try again.');
      };
      xhr.send(new FormData(form));
    });
  }

  // ---------------------------------------------------------------- lightbox
  function bindLightbox() {
    var box = document.getElementById('pmLightbox');
    if (!box) return;
    var img = box.querySelector('img');
    var cap = box.querySelector('.pm-lightbox-cap');
    document.addEventListener('click', function (e) {
      var t = e.target.closest('[data-pm-zoom]');
      if (t) {
        e.preventDefault();
        img.src = t.getAttribute('data-pm-zoom');
        cap.textContent = t.getAttribute('data-pm-caption') || '';
        box.hidden = false;
        return;
      }
      if (!box.hidden && (e.target === box || e.target.closest('.pm-lightbox-close'))) {
        box.hidden = true; img.src = '';
      }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !box.hidden) { box.hidden = true; img.src = ''; }
    });
  }

  function init() {
    document.querySelectorAll('.pm-uploader').forEach(function (root) {
      if (!root.__pm) root.__pm = new Uploader(root);
    });
    document.querySelectorAll('form[data-pm-progress]').forEach(function (f) {
      if (!f.__pmp) { f.__pmp = true; bindProgress(f); }
    });
    bindLightbox();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
