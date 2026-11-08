/**
 * Pwestora PWA Installation & Service Worker Registrar
 */
(function () {
  'use strict';

  // Inject keyframe animation if not already injected
  if (!document.getElementById('pwa-styles')) {
    var style = document.createElement('style');
    style.id = 'pwa-styles';
    style.textContent =
      '@keyframes pwaSlideUp {' +
      '  from { opacity: 0; transform: translate(-50%, 25px); }' +
      '  to { opacity: 1; transform: translate(-50%, 0); }' +
      '}';
    document.head.appendChild(style);
  }

  // 1. Register Service Worker
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('/sw.js', { scope: '/' })
        .then(function (reg) {
          // Check for updates
          reg.onupdatefound = function () {
            var installingWorker = reg.installing;
            installingWorker.onstatechange = function () {
              if (installingWorker.state === 'installed' && navigator.serviceWorker.controller) {
                console.log('[PWA] New version available.');
              }
            };
          };
        })
        .catch(function (err) {
          console.warn('[PWA] SW registration failed:', err);
        });
    });
  }

  // 2. Add-to-Home-Screen Prompt Handling
  var deferredPrompt = null;
  var isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

  if (isStandalone) {
    // Already running as installed PWA — don't show prompt
    return;
  }

  // Check if user dismissed recently (wait 7 days)
  var dismissedTime = localStorage.getItem('pwa_prompt_dismissed');
  if (dismissedTime && (Date.now() - parseInt(dismissedTime, 10) < 7 * 24 * 60 * 60 * 1000)) {
    // Dismissed recently
  } else {
    // Listen for Chrome / Android beforeinstallprompt
    window.addEventListener('beforeinstallprompt', function (e) {
      e.preventDefault();
      deferredPrompt = e;
      showInstallBanner();
    });

    // Detect iOS Safari
    var isIos = /iphone|ipad|ipod/.test(window.navigator.userAgent.toLowerCase());
    var isSafari = /safari/.test(window.navigator.userAgent.toLowerCase()) && !/chrome|crios|fxios/.test(window.navigator.userAgent.toLowerCase());

    if (isIos && isSafari && !isStandalone) {
      // Delay showing iOS hint slightly
      setTimeout(function () {
        if (!localStorage.getItem('pwa_ios_hint_seen')) {
          showIosHint();
        }
      }, 3000);
    }
  }

  function showInstallBanner() {
    if (document.getElementById('pwa-install-banner')) return;

    var banner = document.createElement('div');
    banner.id = 'pwa-install-banner';
    banner.style.cssText =
      'position:fixed; bottom:20px; left:50%; transform:translateX(-50%); z-index:99999;' +
      'width:calc(100% - 32px); max-width:420px; background:#102340; color:#fff; padding:12px 16px;' +
      'border-radius:14px; box-shadow:0 10px 25px rgba(0,0,0,0.3); display:flex; align-items:center;' +
      'justify-content:space-between; gap:12px; font-family:inherit; animation:pwaSlideUp 0.35s ease-out;';

    banner.innerHTML =
      '<div style="display:flex; align-items:center; gap:10px; min-width:0;">' +
      '  <img src="/assets/img/icon.png" width="34" height="34" style="border-radius:8px; flex-shrink:0;">' +
      '  <div style="min-width:0;">' +
      '    <div style="font-weight:700; font-size:13px; line-height:1.2;">Install Pwestora</div>' +
      '    <div style="font-size:11px; color:#cbd5e1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Add to Home Screen for quick access</div>' +
      '  </div>' +
      '</div>' +
      '<div style="display:flex; align-items:center; gap:6px; flex-shrink:0;">' +
      '  <button id="pwa-install-btn" style="background:#1E6BFF; color:#fff; border:none; border-radius:8px; padding:6px 12px; font-size:12px; font-weight:700; cursor:pointer;">Install</button>' +
      '  <button id="pwa-close-btn" style="background:transparent; color:#94a3b8; border:none; font-size:18px; line-height:1; padding:4px 6px; cursor:pointer;" aria-label="Close">&times;</button>' +
      '</div>';

    document.body.appendChild(banner);

    document.getElementById('pwa-install-btn').addEventListener('click', function () {
      if (deferredPrompt) {
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then(function (choice) {
          if (choice.outcome === 'accepted') {
            console.log('[PWA] User accepted installation');
          }
          deferredPrompt = null;
          banner.remove();
        });
      }
    });

    document.getElementById('pwa-close-btn').addEventListener('click', function () {
      localStorage.setItem('pwa_prompt_dismissed', Date.now().toString());
      banner.remove();
    });
  }

  function showIosHint() {
    if (document.getElementById('pwa-ios-hint')) return;

    var hint = document.createElement('div');
    hint.id = 'pwa-ios-hint';
    hint.style.cssText =
      'position:fixed; bottom:20px; left:50%; transform:translateX(-50%); z-index:99999;' +
      'width:calc(100% - 32px); max-width:400px; background:#102340; color:#fff; padding:14px 16px;' +
      'border-radius:14px; box-shadow:0 10px 25px rgba(0,0,0,0.3); font-family:inherit; font-size:12px; line-height:1.4;' +
      'animation:pwaSlideUp 0.35s ease-out;';

    hint.innerHTML =
      '<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:6px;">' +
      '  <strong style="font-size:13px; color:#fff; display:flex; align-items:center; gap:6px;">' +
      '    <img src="/assets/img/icon.png" width="18" height="18" style="border-radius:4px;"> Add to Home Screen' +
      '  </strong>' +
      '  <button id="pwa-ios-close" style="background:transparent; color:#94a3b8; border:none; font-size:18px; line-height:1; cursor:pointer;" aria-label="Close">&times;</button>' +
      '</div>' +
      '<p style="margin:0 0 4px; color:#cbd5e1;">Install Pwestora as an app on your iPhone:</p>' +
      '<div style="color:#93c5fd; font-weight:600;">Tap the Share button <span style="font-size:14px;">⎋</span> below and select <strong>"Add to Home Screen" ⊞</strong>.</div>';

    document.body.appendChild(hint);

    document.getElementById('pwa-ios-close').addEventListener('click', function () {
      localStorage.setItem('pwa_ios_hint_seen', 'true');
      hint.remove();
    });
  }
})();
