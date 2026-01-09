/* /public/igbo-calendar/install/install.js */
(() => {
  "use strict";

  const $ = (sel, root) => (root || document).querySelector(sel);

  const btnInstall   = $("#btnInstall");
  const btnCheck     = $("#btnCheck");
  const statusEl     = $("#installStatus");
  const diagBox      = $("#diagBox");
  const btnCopyDebug = $("#btnCopyDebug");

  if (!statusEl) return;

  const APP_SCOPE     = "/igbo-calendar/";
  const MANIFEST_URL  = "/igbo-calendar/manifest.json";
  const SW_URL        = "/igbo-calendar/service-worker.js";
  const INSTALL_PAGE  = "/igbo-calendar/install/";
  const APP_URL       = "/igbo-calendar/";

  const uaRaw = (navigator.userAgent || "");
  const ua = uaRaw.toLowerCase();

  const isIOS = /iphone|ipad|ipod/.test(ua);
  const isAndroid = /android/.test(ua);
  const isOpera = /\bopr\//.test(ua) || /\bopera\b/.test(ua);
  const isEdge = /\bedg\//.test(ua);
  const isChromium = /chrome|crios|chromium|edg|opr/.test(ua) && !/firefox/.test(ua);

  const isStandaloneIOS = (() => {
    try { return !!navigator.standalone; } catch { return false; }
  })();

  const isInstalledByDisplayMode = () => {
    try { return !!(window.matchMedia && window.matchMedia("(display-mode: standalone)").matches); }
    catch { return false; }
  };

  let deferredPrompt = null;
  let lastDiag = null;
  let bipFired = false;          // did beforeinstallprompt actually fire on THIS page load?
  let reloadedOnce = false;      // used for controllerchange reload safety
  let warnedOpera = false;

  const setStatus = (html, kind) => {
    statusEl.classList.remove("mk-alert--danger", "mk-alert--ok");
    if (kind === "danger") statusEl.classList.add("mk-alert--danger");
    if (kind === "ok") statusEl.classList.add("mk-alert--ok");
    statusEl.innerHTML = html;
  };

  const setInstallEnabled = (enabled) => {
    if (!btnInstall) return;
    btnInstall.disabled = !enabled;
    btnInstall.setAttribute("aria-disabled", enabled ? "false" : "true");
    btnInstall.title = enabled
      ? "Install Igbo Calendar as an app"
      : "Install will be enabled when your browser allows it.";
  };

  const safeJson = (obj) => {
    try { return JSON.stringify(obj, null, 2); } catch { return String(obj); }
  };

  const pathOk = (() => {
    try { return location.pathname.startsWith("/igbo-calendar/install"); } catch { return true; }
  })();

  const fetchHead = async (url) => {
    try {
      const res = await fetch(url, { method: "HEAD", cache: "no-store" });
      return { ok: res.ok, status: res.status, type: "HEAD" };
    } catch (e) {
      try {
        const res = await fetch(url + (url.includes("?") ? "&" : "?") + "t=" + Date.now(), { cache: "no-store" });
        return { ok: res.ok, status: res.status, type: "GET" };
      } catch (e2) {
        return { ok: false, status: 0, type: "ERR", error: String(e2?.message || e2) };
      }
    }
  };

  // One-time reload when SW takes control, so this page becomes "controlled".
  function armControllerReload() {
    if (!("serviceWorker" in navigator)) return;
    navigator.serviceWorker.addEventListener("controllerchange", () => {
      if (reloadedOnce) return;
      reloadedOnce = true;
      // Mark so we do not loop if the browser triggers multiple controllerchange events
      try { sessionStorage.setItem("mk_sw_reloaded", "1"); } catch {}
      location.reload();
    });
  }

  function alreadyReloadedThisSession() {
    try { return sessionStorage.getItem("mk_sw_reloaded") === "1"; } catch { return false; }
  }

  async function ensureServiceWorker() {
    if (!("serviceWorker" in navigator)) return { ok: false, note: "serviceWorker not supported" };

    // Avoid infinite reload loops across sessions
    if (!alreadyReloadedThisSession()) armControllerReload();

    try {
      const reg = await navigator.serviceWorker.register(SW_URL, {
        scope: APP_SCOPE,
        updateViaCache: "none",
      });

      // If an update is waiting, request immediate activation (requires SW message handler)
      if (reg.waiting) {
        try { reg.waiting.postMessage({ type: "SKIP_WAITING" }); } catch {}
      }

      reg.addEventListener("updatefound", () => {
        const nw = reg.installing;
        if (!nw) return;
        nw.addEventListener("statechange", () => {
          if (nw.state === "installed" && reg.waiting) {
            try { reg.waiting.postMessage({ type: "SKIP_WAITING" }); } catch {}
          }
        });
      });

      try { await reg.update(); } catch {}

      return { ok: true, reg };
    } catch (e) {
      return { ok: false, error: String(e?.message || e) };
    }
  }

  // Capture install prompt (Chromium).
  window.addEventListener("beforeinstallprompt", (e) => {
    bipFired = true;
    e.preventDefault();
    deferredPrompt = e;
    setInstallEnabled(true);
    setStatus("Install is available. Click <strong>Install App</strong>.", "ok");
  });

  window.addEventListener("appinstalled", () => {
    deferredPrompt = null;
    setInstallEnabled(false);
    setStatus("App installed successfully. You can now open it from your home screen/app list.", "ok");
  });

  const runDiagnostics = async () => {
    const diag = {
      ts: new Date().toISOString(),
      location: (() => { try { return { href: location.href, pathname: location.pathname }; } catch { return {}; } })(),
      ua: uaRaw,
      platformHints: {
        isIOS,
        isAndroid,
        isChromium,
        isOpera,
        isEdge,
        isStandaloneIOS,
        standaloneDisplayMode: isInstalledByDisplayMode(),
        controller: !!(navigator.serviceWorker && navigator.serviceWorker.controller),
        beforeinstallpromptFired: bipFired
      },
      supports: {
        serviceWorker: "serviceWorker" in navigator,
        fetch: "fetch" in window,
        beforeinstallpromptProperty: ("onbeforeinstallprompt" in window) // informational only
      },
      endpoints: {
        app: APP_URL,
        manifest: MANIFEST_URL,
        serviceWorker: SW_URL,
        scope: APP_SCOPE,
        installPage: INSTALL_PAGE
      },
      reachability: {},
      serviceWorker: {}
    };

    diag.reachability.manifest = await fetchHead(MANIFEST_URL);
    diag.reachability.serviceWorker = await fetchHead(SW_URL);

    const sw = await ensureServiceWorker();

    if (!sw.ok) {
      diag.serviceWorker = { ok: false, error: sw.error || sw.note || "unknown" };
    } else {
      const reg = sw.reg;
      diag.serviceWorker = {
        ok: true,
        scope: reg.scope || null,
        active: !!reg.active,
        waiting: !!reg.waiting,
        installing: !!reg.installing,
        controller: !!(navigator.serviceWorker && navigator.serviceWorker.controller)
      };
    }

    lastDiag = diag;
    return diag;
  };

  const showDiagnostics = async () => {
    if (!diagBox) return;

    diagBox.style.display = "block";
    diagBox.textContent = "Running diagnostics…";

    const diag = await runDiagnostics();

    const manifestOk = !!diag.reachability?.manifest?.ok;
    const swOk = !!diag.reachability?.serviceWorker?.ok;
    const controllerOk = !!diag.serviceWorker?.controller;

    // User-facing logic
    if (!pathOk) {
      setStatus(
        "This install page may be served from an unexpected route. Confirm the URL is <strong>/igbo-calendar/install/</strong>.",
        "danger"
      );
      setInstallEnabled(false);
    } else if (!manifestOk || !swOk) {
      setStatus(
        "Install prerequisites failed: manifest or service worker is not reachable (must be 200). See diagnostics.",
        "danger"
      );
      setInstallEnabled(false);
    } else if (isInstalledByDisplayMode() || isStandaloneIOS) {
      setStatus("This app appears to be installed already. Open it from your home screen/app list.", "ok");
      setInstallEnabled(false);
    } else if (isIOS && !isStandaloneIOS) {
      setStatus("On iPhone/iPad, install via Safari: <strong>Share → Add to Home Screen</strong>.", "ok");
      setInstallEnabled(false);
    } else if (deferredPrompt) {
      setStatus("Install is available. Click <strong>Install App</strong>.", "ok");
      setInstallEnabled(true);
    } else if (isChromium && !controllerOk) {
      // The most common remaining blocker once SW is active
      setStatus(
        "Service worker is active but not controlling this page yet. Open <strong>/igbo-calendar/</strong>, refresh once, then return here and refresh.",
        "danger"
      );
      setInstallEnabled(false);
    } else if (isOpera && !warnedOpera) {
      warnedOpera = true;
      setStatus(
        "Opera sometimes hides the install prompt. Use the browser menu or address-bar install icon. If you want the visible button, test once in Chrome/Edge.",
        "ok"
      );
      setInstallEnabled(false);
    } else if (isChromium) {
      // Controlled but no event fired yet
      setStatus(
        "Install prompt has not fired on this page load. Open <strong>/igbo-calendar/</strong>, refresh, then return here. Also check the browser menu for <strong>Install app</strong>.",
        "ok"
      );
      setInstallEnabled(false);
    } else {
      setStatus(
        "This browser may not expose a direct install prompt. Try Chrome/Edge for desktop installs or Safari on iOS.",
        "ok"
      );
      setInstallEnabled(false);
    }

    diagBox.textContent = safeJson(diag);
  };

  const installNow = async () => {
    if (isInstalledByDisplayMode() || isStandaloneIOS) {
      setStatus("This app appears to be installed already.", "ok");
      setInstallEnabled(false);
      return;
    }

    if (!deferredPrompt) {
      if (isIOS) {
        setStatus("iPhone/iPad: use <strong>Share → Add to Home Screen</strong> in Safari.", "ok");
      } else if (isOpera) {
        setStatus(
          "Opera may not expose the install prompt event. Use the browser menu/address-bar install option, or test in Chrome/Edge.",
          "danger"
        );
      } else {
        setStatus(
          "Install prompt is not available yet. Open <strong>/igbo-calendar/</strong>, refresh, then come back here and refresh.",
          "danger"
        );
      }
      return;
    }

    try {
      setInstallEnabled(false);
      setStatus("Opening install prompt…", "ok");

      deferredPrompt.prompt();
      const choice = await deferredPrompt.userChoice;

      if (choice?.outcome === "accepted") {
        setStatus("Install accepted. Completing setup…", "ok");
      } else {
        setStatus("Install dismissed. You can try again later.", "danger");
        setInstallEnabled(true);
      }
    } catch (e) {
      setStatus("Install failed to start. See diagnostics and try again.", "danger");
      setInstallEnabled(true);
    } finally {
      deferredPrompt = null;
    }
  };

  const copyDebug = async () => {
    const text = lastDiag ? safeJson(lastDiag) : "No diagnostics captured yet.";
    try {
      await navigator.clipboard.writeText(text);
      setStatus("Debug info copied.", "ok");
    } catch {
      try { window.prompt("Copy debug info:", text); } catch {}
      setStatus("Copy may be blocked; manual copy prompt attempted.", "danger");
    }
  };

  if (btnInstall) btnInstall.addEventListener("click", installNow);
  if (btnCheck) btnCheck.addEventListener("click", showDiagnostics);
  if (btnCopyDebug) btnCopyDebug.addEventListener("click", copyDebug);

  setInstallEnabled(false);

  // Initial message (before the diagnostics run)
  if (isInstalledByDisplayMode() || isStandaloneIOS) {
    setStatus("This app appears to be installed already.", "ok");
  } else if (isIOS) {
    setStatus("iPhone/iPad: install via Safari → <strong>Share → Add to Home Screen</strong>.", "ok");
  } else {
    setStatus("Checking install support…", "ok");
  }

  window.addEventListener("load", () => {
    // Reset the one-session reload guard after the page has fully loaded once
    // (so the next real session can reload again if needed).
    try { sessionStorage.removeItem("mk_sw_reloaded"); } catch {}
    showDiagnostics().catch(() => {});
  });
})();
