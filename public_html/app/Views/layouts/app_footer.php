<?php /* app/Views/layouts/app_footer.php — closes the dashboard shell (was includes/footer.php) */ ?>
    </div><!-- /.content -->
  </div><!-- /.main -->
</div><!-- /.app-shell -->

<!--
  In-page document viewer.
  Any link inside the dashboard that would open in a NEW TAB (target="_blank") and points to this
  website (contracts, eviction notices, PDFs, verification documents, photos) opens in this pop-up
  instead. Images are shown directly; everything else is shown in a frame.
  To force a normal new tab for a link, add  data-no-viewer  to it.
-->
<style>
  .doc-viewer { position:fixed; inset:0; z-index:200; background:rgba(16,24,40,.6); display:none; align-items:center; justify-content:center; padding:2.5vh 2vw; }
  .doc-viewer.open { display:flex; }
  .doc-viewer-panel { background:#fff; width:min(1000px, 96vw); height:95vh; border-radius:12px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,.3); }
  .doc-viewer-panel.is-image { width:auto; max-width:96vw; height:auto; max-height:95vh; }
  .doc-viewer-bar { display:flex; align-items:center; gap:8px; padding:10px 14px; border-bottom:1px solid var(--border, #E4E7EC); background:#fff; }
  .doc-viewer-title { flex:1; font-weight:600; font-size:14px; color:var(--ink-900, #16181D); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .doc-viewer-bar button, .doc-viewer-bar a { border:1px solid var(--border, #E4E7EC); background:var(--bg, #F4F6F9); color:var(--ink-900, #16181D); border-radius:7px;
      padding:6px 12px; font:inherit; font-size:12.5px; cursor:pointer; text-decoration:none; }
  .doc-viewer-bar .doc-viewer-x { font-size:20px; line-height:1; padding:4px 10px; background:#fff; }
  .doc-viewer-body { flex:1; background:#E9EDF2; display:flex; align-items:center; justify-content:center; min-height:0; }
  .doc-viewer-body iframe { width:100%; height:100%; border:0; background:#fff; }
  .doc-viewer-body img { max-width:94vw; max-height:calc(95vh - 56px); display:block; object-fit:contain; }
  .doc-viewer-loading { position:absolute; font-size:13px; color:var(--ink-500, #6B7280); }
  body.doc-viewer-lock { overflow:hidden; }
</style>

<div class="doc-viewer" id="docViewer" aria-hidden="true">
  <div class="doc-viewer-panel" role="dialog" aria-modal="true" aria-labelledby="docViewerTitle">
    <div class="doc-viewer-bar">
      <span class="doc-viewer-title" id="docViewerTitle">Document</span>
      <button type="button" id="docViewerPrint">Print</button>
      <a id="docViewerDownload" href="#" download>Download</a>
      <button type="button" class="doc-viewer-x" id="docViewerClose" aria-label="Close" title="Close (Esc)">&times;</button>
    </div>
    <div class="doc-viewer-body" id="docViewerBody"></div>
  </div>
</div>

<script>
(function () {
  var viewer = document.getElementById('docViewer');
  if (!viewer) return;
  var panel = viewer.querySelector('.doc-viewer-panel');
  var body = document.getElementById('docViewerBody');
  var title = document.getElementById('docViewerTitle');
  var printBtn = document.getElementById('docViewerPrint');
  var download = document.getElementById('docViewerDownload');
  var IMAGE = /\.(png|jpe?g|gif|webp|bmp|svg)(\?.*)?$/i;
  var FILE  = /\.(pdf|png|jpe?g|gif|webp|bmp|svg|docx?|xlsx?)(\?.*)?$/i;
  var frame = null;

  function nameFor(link, url) {
    var text = (link.getAttribute('title') || link.textContent || '').replace(/\s+/g, ' ').trim();
    if (text && text.length > 2 && !/^view$/i.test(text)) return text;
    var path = url.pathname.split('/').pop() || 'Document';
    return decodeURIComponent(path);
  }

  function open(url, label) {
    body.innerHTML = '';
    frame = null;
    title.textContent = label;
    var isImage = IMAGE.test(url.pathname);
    panel.classList.toggle('is-image', isImage);

    if (isImage) {
      var img = document.createElement('img');
      img.src = url.href; img.alt = label;
      body.appendChild(img);
      printBtn.style.display = 'none';
    } else {
      var loading = document.createElement('div');
      loading.className = 'doc-viewer-loading'; loading.textContent = 'Loading...';
      body.style.position = 'relative';
      body.appendChild(loading);
      frame = document.createElement('iframe');
      frame.src = url.href; frame.title = label;
      frame.addEventListener('load', function () { loading.remove(); });
      body.appendChild(frame);
      printBtn.style.display = '';
    }
    // Download only makes sense for real files (PDF, images); pages like the contract use Print.
    download.style.display = FILE.test(url.pathname) ? '' : 'none';
    download.href = url.href;

    viewer.classList.add('open');
    viewer.setAttribute('aria-hidden', 'false');
    document.body.classList.add('doc-viewer-lock');
  }

  function close() {
    viewer.classList.remove('open');
    viewer.setAttribute('aria-hidden', 'true');
    body.innerHTML = '';
    frame = null;
    document.body.classList.remove('doc-viewer-lock');   // a contract editor underneath keeps its own lock (cm-open)
  }

  document.addEventListener('click', function (e) {
    var link = e.target.closest('a[target="_blank"]');
    if (!link || link.hasAttribute('data-no-viewer') || e.ctrlKey || e.metaKey || e.shiftKey) return;
    var url;
    try { url = new URL(link.getAttribute('href'), window.location.href); } catch (err) { return; }
    if (url.origin !== window.location.origin) return;   // outside websites still open normally
    e.preventDefault();
    e.stopPropagation();
    open(url, nameFor(link, url));
  }, true);

  printBtn.addEventListener('click', function () {
    if (frame && frame.contentWindow) {
      try { frame.contentWindow.focus(); frame.contentWindow.print(); return; } catch (err) {}
    }
    window.print();
  });
  document.getElementById('docViewerClose').addEventListener('click', close);
  viewer.addEventListener('click', function (e) { if (e.target === viewer) close(); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && viewer.classList.contains('open')) { e.stopImmediatePropagation(); close(); }
  }, true);
})();
</script>
</body>
</html>
