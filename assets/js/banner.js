/* global fetch */
(function () {
  "use strict";

  var cfg = window._tcsConfig || {};
  if (!cfg || !cfg.bannerEnabled) {
    return;
  }

  var doc = document;
  var bannerId = "sc-cmp-banner";
  var modalId = "sc-cmp-modal";
  var storageKey = String(cfg.localStorageKey || "tcs_cmp_consent_v1");
  var cookieKey = String(cfg.cookieStorageKey || storageKey);
  var forceParams = Array.isArray(cfg.forceParams) ? cfg.forceParams : ["tcs_force_banner", "sc_force_banner"];
  var resetParams = Array.isArray(cfg.resetParams) ? cfg.resetParams : ["tcs_reset_consent", "sc_reset_consent"];
  var labels = cfg.labels || {};
  var labelsByLocale = cfg.labelsByLocale || null;
  var consentExpiryDays = parseInt(cfg.consentExpiryDays, 10);
  var showRevisitButton = cfg.showRevisitButton !== false;
  var revisitButtonId = "sc-cmp-revisit-button";
  var gcmConfig = cfg.gcm || {};
  var gcmEnabled = !!gcmConfig.enabled;
  var gcmWaitForUpdate = parseInt(gcmConfig.waitForUpdate, 10);
  var scriptBlockerEnabled = cfg.enableScriptBlocker !== false;

  var legacyStorageKeys = Array.isArray(cfg.legacyStorageKeys) ? cfg.legacyStorageKeys : [];
  if (legacyStorageKeys.indexOf("sc_cmp_gcm_v2") < 0) {
    legacyStorageKeys.push("sc_cmp_gcm_v2");
  }
  if (!consentExpiryDays || consentExpiryDays < 1) {
    consentExpiryDays = 180;
  }
  if (!gcmWaitForUpdate || gcmWaitForUpdate < 0) {
    gcmWaitForUpdate = 500;
  }

  var scriptBlockerStats = {
    scanned: 0,
    unlocked: 0
  };

  function ensureGtagStub() {
    window.dataLayer = window.dataLayer || [];
    if (typeof window.gtag !== "function") {
      window.gtag = function gtag() {
        window.dataLayer.push(arguments);
      };
    }
  }

  function setupGcmDefault() {
    if (!gcmEnabled) {
      return;
    }
    ensureGtagStub();
    window.gtag("consent", "default", {
      analytics_storage: "denied",
      ad_storage: "denied",
      ad_user_data: "denied",
      ad_personalization: "denied",
      wait_for_update: gcmWaitForUpdate
    });
  }

  function resolveLocale() {
    var locale = String(cfg.locale || "").toLowerCase();
    var htmlLang = "";

    if (locale.indexOf("pl") === 0) {
      return "pl";
    }
    if (locale.indexOf("en") === 0) {
      return "en";
    }

    try {
      htmlLang = String((doc.documentElement && doc.documentElement.lang) || "").toLowerCase();
    } catch (e) {
      htmlLang = "";
    }

    if (htmlLang.indexOf("pl") === 0) {
      return "pl";
    }
    return "en";
  }

  function mergeObjects(a, b) {
    var out = {};
    var key;
    a = a || {};
    b = b || {};
    for (key in a) {
      if (Object.prototype.hasOwnProperty.call(a, key)) {
        out[key] = a[key];
      }
    }
    for (key in b) {
      if (Object.prototype.hasOwnProperty.call(b, key)) {
        out[key] = b[key];
      }
    }
    return out;
  }

  function resolveLocalizedLabels() {
    var localeKey = resolveLocale();
    var localeLabels = {};
    if (labelsByLocale && typeof labelsByLocale === "object") {
      localeLabels = labelsByLocale[localeKey] || labelsByLocale.en || {};
    }
    return mergeObjects(localeLabels, labels);
  }

  labels = resolveLocalizedLabels();

  function debugLog() {
    if (!cfg.debug || !window.console || !console.log) {
      return;
    }
    var args = Array.prototype.slice.call(arguments);
    args.unshift("[TruCookieCMP]");
    console.log.apply(console, args);
  }

  function safeJsonParse(value) {
    try {
      return JSON.parse(value);
    } catch (e) {
      return null;
    }
  }

  function getCookie(name) {
    try {
      var escaped = name.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
      var match = doc.cookie.match(new RegExp("(?:^|; )" + escaped + "=([^;]*)"));
      return match ? decodeURIComponent(match[1]) : null;
    } catch (e) {
      return null;
    }
  }

  function setCookie(name, value, days) {
    var ttl = days ? "; Max-Age=" + String(days * 24 * 60 * 60) : "";
    var secure = window.location.protocol === "https:" ? "; Secure" : "";
    doc.cookie = name + "=" + encodeURIComponent(value) + "; Path=/" + ttl + "; SameSite=Lax" + secure;
  }

  function clearCookie(name) {
    var secure = window.location.protocol === "https:" ? "; Secure" : "";
    doc.cookie = name + "=; Path=/; Max-Age=0; SameSite=Lax" + secure;
  }

  function readSearchParam(name) {
    try {
      var params = new URLSearchParams(window.location.search || "");
      return params.get(name);
    } catch (e) {
      return null;
    }
  }

  function hasQueryFlag(names) {
    var i;
    for (i = 0; i < names.length; i += 1) {
      if (readSearchParam(names[i]) === "1") {
        return true;
      }
    }
    return false;
  }

  function forceBanner() {
    return hasQueryFlag(forceParams) || window.TCS_FORCE_BANNER === true || window.SC_FORCE_BANNER === true;
  }

  function resetRequested() {
    return hasQueryFlag(resetParams);
  }

  function normalizeConsent(input) {
    var raw = input;
    if (!raw || typeof raw !== "object") {
      return null;
    }

    if (raw.consent && typeof raw.consent === "object") {
      raw = raw.consent;
    }

    var analytics = !!(raw.analytics || (raw.decision && raw.decision.analytics));
    var marketing = !!(raw.marketing || raw.ads || (raw.decision && (raw.decision.marketing || raw.decision.ads)));

    return {
      necessary: true,
      analytics: analytics,
      marketing: marketing,
      ts: typeof raw.ts === "number" ? raw.ts : Date.now(),
      schema_version: typeof raw.schema_version === "number" ? raw.schema_version : 1
    };
  }

  function isConsentExpired(consent) {
    var ts;
    var ttlMs;

    if (!consent || typeof consent !== "object") {
      return true;
    }
    ts = parseInt(consent.ts, 10);
    if (!ts || ts < 1) {
      return false;
    }
    ttlMs = consentExpiryDays * 24 * 60 * 60 * 1000;
    return Date.now() - ts > ttlMs;
  }

  function getStoredConsent() {
    var parsed;
    var i;
    var raw = null;

    try {
      raw = window.localStorage ? window.localStorage.getItem(storageKey) : null;
    } catch (e) {
      raw = null;
    }
    if (raw) {
      parsed = normalizeConsent(safeJsonParse(raw));
      if (parsed) {
        if (isConsentExpired(parsed)) {
          clearStoredConsent();
          return null;
        }
        return parsed;
      }
    }

    for (i = 0; i < legacyStorageKeys.length; i += 1) {
      try {
        raw = window.localStorage ? window.localStorage.getItem(legacyStorageKeys[i]) : null;
      } catch (e2) {
        raw = null;
      }
      if (!raw) {
        continue;
      }
      parsed = normalizeConsent(safeJsonParse(raw));
      if (parsed) {
        if (isConsentExpired(parsed)) {
          clearStoredConsent();
          return null;
        }
        return parsed;
      }
    }

    raw = getCookie(cookieKey);
    if (raw) {
      parsed = normalizeConsent(safeJsonParse(raw));
      if (parsed) {
        if (isConsentExpired(parsed)) {
          clearStoredConsent();
          return null;
        }
        return parsed;
      }
    }

    raw = getCookie("sc_cmp_gcm_v2");
    if (raw) {
      parsed = normalizeConsent(safeJsonParse(raw));
      if (parsed) {
        if (isConsentExpired(parsed)) {
          clearStoredConsent();
          return null;
        }
        return parsed;
      }
    }

    return null;
  }

  function setStoredConsent(consent) {
    var payload = {
      v: 1,
      necessary: true,
      analytics: !!consent.analytics,
      marketing: !!consent.marketing,
      ts: consent.ts || Date.now(),
      schema_version: 1
    };
    var json = JSON.stringify(payload);

    try {
      if (window.localStorage) {
        window.localStorage.setItem(storageKey, json);
      }
    } catch (e) {}

    setCookie(cookieKey, json, consentExpiryDays);
  }

  function clearStoredConsent() {
    var i;
    try {
      if (window.localStorage) {
        window.localStorage.removeItem(storageKey);
      }
    } catch (e) {}

    for (i = 0; i < legacyStorageKeys.length; i += 1) {
      try {
        if (window.localStorage) {
          window.localStorage.removeItem(legacyStorageKeys[i]);
        }
      } catch (e2) {}
    }

    clearCookie(cookieKey);
    clearCookie("sc_cmp_gcm_v2");
  }

  function buildConsent(analytics, marketing, meta) {
    return {
      necessary: true,
      analytics: !!analytics,
      marketing: !!marketing,
      ts: Date.now(),
      schema_version: 1,
      meta: meta || {}
    };
  }

  function dispatchConsentEvent(consent, source) {
    var detail = {
      source: source || "local-banner",
      consent: consent
    };

    try {
      doc.dispatchEvent(new CustomEvent("trucookie:consent", { detail: detail }));
      window.dispatchEvent(new CustomEvent("trucookie:consent", { detail: detail }));
      doc.dispatchEvent(new CustomEvent("sc:consent", { detail: detail }));
      window.dispatchEvent(new CustomEvent("sc:consent", { detail: detail }));
    } catch (e) {}
  }

  function sendConsentLog(consent) {
    if (!cfg.restUrl || typeof fetch !== "function") {
      return;
    }

    var payload = {
      consent: consent,
      url: window.location.href,
      referrer: doc.referrer || "",
      mode: cfg.mode || "local"
    };

    fetch(cfg.restUrl, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
      keepalive: true,
      credentials: "omit"
    }).catch(function () {
      debugLog("Consent log request failed");
    });
  }

  function applyGcmConsent(consent) {
    if (!gcmEnabled) {
      return;
    }
    ensureGtagStub();
    window.gtag("consent", "update", {
      analytics_storage: consent.analytics ? "granted" : "denied",
      ad_storage: consent.marketing ? "granted" : "denied",
      ad_user_data: consent.marketing ? "granted" : "denied",
      ad_personalization: consent.marketing ? "granted" : "denied"
    });
  }

  function applyWpConsentBridge(consent) {
    if (typeof window.wp_set_consent !== "function") {
      return;
    }

    try {
      window.wp_consent_type = "optin";
    } catch (e) {}

    try {
      window.wp_set_consent("functional", "allow");
      window.wp_set_consent("preferences", "allow");
      window.wp_set_consent("statistics", consent.analytics ? "allow" : "deny");
      window.wp_set_consent("statistics-anonymous", consent.analytics ? "allow" : "deny");
      window.wp_set_consent("marketing", consent.marketing ? "allow" : "deny");
    } catch (e2) {}
  }

  function canUnlockCategory(category, consent) {
    var key = String(category || "").toLowerCase();
    if (key === "necessary" || key === "functional") {
      return true;
    }
    if (key === "analytics" || key === "statistics") {
      return !!consent.analytics;
    }
    if (key === "marketing" || key === "ads" || key === "advertisement") {
      return !!consent.marketing;
    }
    return false;
  }

  function unlockBlockedScripts(consent) {
    var nodes;
    var i;
    var original;
    var category;
    var cloned;
    var source;
    var inlineCode;
    var attrs = ["src", "async", "defer", "crossorigin", "integrity", "referrerpolicy", "nonce"];

    if (!scriptBlockerEnabled) {
      return;
    }

    nodes = doc.querySelectorAll("script[type='text/plain'][data-tc-category]:not([data-tc-unlocked='1'])");
    scriptBlockerStats.scanned += nodes.length;
    for (i = 0; i < nodes.length; i += 1) {
      original = nodes[i];
      category = original.getAttribute("data-tc-category");
      if (!canUnlockCategory(category, consent)) {
        continue;
      }

      cloned = doc.createElement("script");
      source = original.getAttribute("data-tc-src") || original.getAttribute("src");

      attrs.forEach(function (name) {
        var value = original.getAttribute(name);
        if (!value || name === "src") {
          return;
        }
        cloned.setAttribute(name, value);
      });

      if (source) {
        cloned.src = source;
      } else {
        inlineCode = original.text || original.textContent || "";
        if (inlineCode) {
          cloned.text = inlineCode;
        }
      }

      original.setAttribute("data-tc-unlocked", "1");
      original.type = "text/blocked-unlocked";
      scriptBlockerStats.unlocked += 1;

      if (original.parentNode) {
        original.parentNode.insertBefore(cloned, original.nextSibling);
      } else if (doc.head) {
        doc.head.appendChild(cloned);
      } else if (doc.body) {
        doc.body.appendChild(cloned);
      }
    }
  }

  function mountRevisitButton() {
    var button;
    var th = theme();
    if (!showRevisitButton || doc.getElementById(revisitButtonId)) {
      return;
    }
    if (!doc.body) {
      return;
    }

    button = doc.createElement("button");
    button.id = revisitButtonId;
    button.type = "button";
    button.className = "tcs-revisit-btn";
    button.textContent = textValue("revisitButton", cfg.revisitButtonText || "Privacy settings");
    button.style.setProperty("--tcs-primary", th.primary);
    button.onclick = function () {
      openModal();
    };

    doc.body.appendChild(button);
  }

  function unmountRevisitButton() {
    var node = doc.getElementById(revisitButtonId);
    if (node && node.parentNode) {
      node.parentNode.removeChild(node);
    }
  }

  function applyConsentRuntime(consent) {
    applyGcmConsent(consent);
    applyWpConsentBridge(consent);
    unlockBlockedScripts(consent);
    mountRevisitButton();
  }

  function getScriptBlockerDiagnostics() {
    var pending = 0;
    if (scriptBlockerEnabled) {
      pending = doc.querySelectorAll("script[type='text/plain'][data-tc-category]:not([data-tc-unlocked='1'])").length;
    }
    return {
      enabled: scriptBlockerEnabled,
      pending: pending,
      scanned: scriptBlockerStats.scanned,
      unlocked: scriptBlockerStats.unlocked
    };
  }

  function saveConsentAndNotify(consent, source) {
    setStoredConsent(consent);
    dispatchConsentEvent(consent, source);
    applyConsentRuntime(consent);
    sendConsentLog(consent);
  }

  function shouldApplyDntDefault(existing) {
    if (!cfg.respectDnt || existing) {
      return false;
    }

    var dnt = String(navigator.doNotTrack || window.doNotTrack || navigator.msDoNotTrack || "");
    return dnt === "1" || dnt.toLowerCase() === "yes";
  }

  function isDarkMode() {
    var t = null;
    var dt = "";

    try {
      dt = doc.documentElement && doc.documentElement.dataset ? (doc.documentElement.dataset.theme || "") : "";
      if (dt === "dark") {
        return true;
      }
      if (dt === "light") {
        return false;
      }
    } catch (e) {}

    try {
      t = window.localStorage ? window.localStorage.getItem("theme") : null;
      if (t === "dark") {
        return true;
      }
      if (t === "light") {
        return false;
      }
    } catch (e2) {}

    try {
      if (doc.documentElement && doc.documentElement.classList && doc.documentElement.classList.contains("dark")) {
        return true;
      }
    } catch (e3) {}

    try {
      return !!(window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches);
    } catch (e4) {
      return false;
    }
  }

  function hexToRgb(hex) {
    var h = typeof hex === "string" ? hex.trim().toLowerCase() : "";
    var r;
    var g;
    var b;

    if (!h) {
      return null;
    }
    if (h.charAt(0) === "#") {
      h = h.slice(1);
    }
    if (h.length === 3) {
      h = h.charAt(0) + h.charAt(0) + h.charAt(1) + h.charAt(1) + h.charAt(2) + h.charAt(2);
    }
    if (h.length !== 6) {
      return null;
    }

    r = parseInt(h.slice(0, 2), 16);
    g = parseInt(h.slice(2, 4), 16);
    b = parseInt(h.slice(4, 6), 16);

    if (!isFinite(r) || !isFinite(g) || !isFinite(b)) {
      return null;
    }

    return { r: r, g: g, b: b };
  }

  function isDarkHex(hex) {
    var rgb = hexToRgb(hex);
    var lum;
    if (!rgb) {
      return null;
    }
    lum = (0.2126 * rgb.r + 0.7152 * rgb.g + 0.0722 * rgb.b) / 255;
    return lum < 0.52;
  }

  function theme() {
    var scheme = String(cfg.colorScheme || "auto").toLowerCase();
    var wantsDark = isDarkMode();
    var autoTheme = !(cfg && cfg.autoTheme === false);
    var themeCfg = cfg.theme || {};
    var primary = themeCfg.primary || cfg.primaryColor || "#059669";
    var bg = themeCfg.background || cfg.backgroundColor || "#ffffff";
    var bgNorm = String(bg || "").trim().toLowerCase();
    var bgIsDark = isDarkHex(bgNorm);

    if (scheme === "dark") {
      wantsDark = true;
    } else if (scheme === "light") {
      wantsDark = false;
    }

    if (bgIsDark === null) {
      bgIsDark = wantsDark;
    }

    if (wantsDark && (bgNorm === "" || bgNorm === "#ffffff" || bgNorm === "#fff")) {
      bg = "#18181b";
      bgIsDark = true;
    }
    if (!wantsDark && (bgNorm === "" || bgNorm === "#18181b" || bgNorm === "#111111")) {
      bg = "#ffffff";
      bgIsDark = false;
    }

    if (!autoTheme && scheme === "auto") {
      // Preserve explicit background provided by config when auto theme is disabled.
      bgIsDark = isDarkHex(bg) === true;
    }

    return {
      primary: primary,
      bg: bg,
      isDark: !!bgIsDark,
      text: bgIsDark ? "rgb(244,244,245)" : "rgb(17,24,39)",
      border: bgIsDark ? "rgba(255,255,255,0.16)" : "rgba(17,24,39,0.15)",
      divider: bgIsDark ? "rgba(255,255,255,0.12)" : "rgba(17,24,39,0.10)",
      btnBorder: bgIsDark ? "rgba(255,255,255,0.22)" : "rgba(17,24,39,0.18)"
    };
  }

  function textValue(key, fallback) {
    if (labels && typeof labels[key] === "string" && labels[key] !== "") {
      return labels[key];
    }
    return fallback;
  }

  function el(tag, textContent) {
    var node = doc.createElement(tag);
    if (typeof textContent === "string") {
      node.textContent = textContent;
    }
    return node;
  }

  function btn(label, primary) {
    var node = el("button", label);

    node.type = "button";
    node.setAttribute("data-sc-btn", primary ? "primary" : "secondary");
    node.className = "tcs-btn " + (primary ? "tcs-btn--primary" : "tcs-btn--secondary");

    return node;
  }

  function resolveUrl(explicitValue) {
    if (typeof explicitValue === "string" && explicitValue !== "") {
      return explicitValue;
    }
    return "";
  }

  function createPoweredByNode() {
    var enabled = cfg.showPoweredBy !== false;
    var row;
    var link;
    var logo;
    var label;

    if (!enabled) {
      return null;
    }

    row = el("div");
    row.className = "tcs-powered";
    row.style.marginTop = "10px";
    row.style.fontSize = "11px";
    row.style.opacity = "0.72";

    link = doc.createElement("a");
    link.href = cfg.poweredByUrl || "https://trucookie.pro";
    link.target = "_blank";
    link.rel = "noopener noreferrer";
    link.style.display = "inline-flex";
    link.style.alignItems = "center";
    link.style.gap = "6px";
    link.style.textDecoration = "none";
    link.style.color = "inherit";

    logo = doc.createElement("img");
    logo.src = cfg.poweredByLogoUrl || "https://trucookie.pro/favicon.svg";
    logo.alt = "TruCookie";
    logo.loading = "lazy";
    logo.decoding = "async";
    logo.style.width = "16px";
    logo.style.height = "16px";
    logo.style.borderRadius = "4px";
    logo.style.objectFit = "contain";
    logo.style.display = "inline-block";

    label = el("span", "Powered by TruCookie");
    label.style.whiteSpace = "nowrap";

    link.appendChild(logo);
    link.appendChild(label);
    row.appendChild(link);

    return row;
  }

  function layoutActionButtons(actions, wrap, btnList) {
    var wrapped = false;
    var firstTop = null;
    var i;

    if (!actions || !wrap) {
      return;
    }

    for (i = 0; i < btnList.length; i += 1) {
      if (!btnList[i]) {
        continue;
      }
      btnList[i].style.width = "";
      btnList[i].style.minWidth = "";
      btnList[i].style.gridColumn = "";
    }

    actions.style.display = "flex";
    actions.style.flexWrap = "wrap";
    actions.style.alignItems = "center";
    actions.style.justifyContent = "flex-start";

    for (i = 0; i < btnList.length; i += 1) {
      if (!btnList[i]) {
        continue;
      }
      if (firstTop === null) {
        firstTop = btnList[i].offsetTop;
      } else if (btnList[i].offsetTop > firstTop + 1) {
        wrapped = true;
        break;
      }
    }

    if (!wrapped) {
      return;
    }

    actions.style.display = "grid";
    actions.style.gridAutoFlow = "row";
    actions.style.gridTemplateColumns = btnList.length >= 3 ? "1fr 1fr" : "1fr";
    actions.style.alignItems = "stretch";
    actions.style.justifyContent = "stretch";

    for (i = 0; i < btnList.length; i += 1) {
      if (!btnList[i]) {
        continue;
      }
      btnList[i].style.width = "100%";
      btnList[i].style.minWidth = "0";
    }

    if (btnList.length >= 3 && btnList[0]) {
      btnList[0].style.gridColumn = "1 / -1";
    }
  }

  function applyBannerLayout(wrap) {
    wrap.classList.remove("tcs-banner--bar", "tcs-banner--rectangle-left", "tcs-banner--rectangle-right");

    if (cfg.style === "rectangle-right") {
      wrap.classList.add("tcs-banner--rectangle-right");
      return;
    }

    if (cfg.style === "rectangle-left") {
      wrap.classList.add("tcs-banner--rectangle-left");
      return;
    }

    wrap.classList.add("tcs-banner--bar");
  }

  function openModal(onCloseBanner) {
    var existing = doc.getElementById(modalId);
    var overlay;
    var modal;
    var title;
    var description;
    var disclaimer;
    var links;
    var analyticsRow;
    var marketingRow;
    var actions;
    var closeButton;
    var saveButton;
    var essentialButton;
    var consent = getStoredConsent() || { analytics: false, marketing: false };

    function makeToggleRow(titleText, descriptionText, checked, disabled) {
      var row = el("div");
      var left = el("div");
      var l1 = el("div", titleText);
      var l2 = el("div", descriptionText);
      var input = doc.createElement("input");
      var th = theme();

      row.className = "tcs-modal-row";
      row.setAttribute("data-sc-row", "1");

      l1.style.fontWeight = "700";
      l1.style.fontSize = "13px";

      l2.style.fontSize = "12px";
      l2.style.opacity = "0.85";
      l2.style.marginTop = "2px";

      input.type = "checkbox";
      input.checked = !!checked;
      input.disabled = !!disabled;
      input.style.width = "18px";
      input.style.height = "18px";
      input.style.accentColor = th.primary;

      left.appendChild(l1);
      left.appendChild(l2);
      row.appendChild(left);
      row.appendChild(input);

      if (disabled) {
        row.style.opacity = "0.7";
      }

      return { row: row, input: input };
    }

    function closeModal() {
      if (overlay && overlay.parentNode) {
        overlay.parentNode.removeChild(overlay);
      }
    }

    function addLink(href, label) {
      var anchor;
      if (!href) {
        return;
      }
      if (links.childNodes.length > 0) {
        links.appendChild(doc.createTextNode(" | "));
      }
      anchor = doc.createElement("a");
      anchor.href = href;
      anchor.target = "_blank";
      anchor.rel = "noopener";
      anchor.textContent = label;
      links.appendChild(anchor);
    }

    if (existing && existing.parentNode) {
      existing.parentNode.removeChild(existing);
    }

    overlay = el("div");
    overlay.id = modalId;
    overlay.className = "tcs-modal-overlay";

    modal = el("div");
    modal.setAttribute("data-sc-modal", "1");
    modal.className = "tcs-modal-card";
    modal.style.setProperty("--tcs-primary", theme().primary);
    modal.style.setProperty("--tcs-bg", theme().bg);
    modal.style.setProperty("--tcs-text", theme().text);
    modal.style.setProperty("--tcs-divider", theme().divider);
    modal.style.setProperty("--tcs-btn-border", theme().btnBorder);

    title = el("div", textValue("preferencesTitle", "Privacy preferences"));
    title.className = "tcs-modal-title";

    description = el("div", textValue("modalBody", textValue("body", "Manage your cookie preferences.")));
    description.className = "tcs-modal-desc";

    disclaimer = el("div", textValue("disclaimer", ""));
    disclaimer.className = "tcs-modal-disclaimer";

    links = el("div");
    links.className = "tcs-modal-links";

    addLink(resolveUrl(cfg.cookiesUrl), textValue("cookiesLinkLabel", "Cookie Policy"));
    addLink(resolveUrl(cfg.privacyUrl), textValue("privacyLinkLabel", "Privacy Policy"));
    addLink(
      resolveUrl(cfg.googleDataResponsibilityUrl || "https://business.safety.google/privacy/"),
      textValue("googleDataResponsibilityLabel", "Google data responsibility")
    );

    analyticsRow = makeToggleRow(
      textValue("analyticsLabel", "Analytics"),
      textValue("analyticsDescription", "Traffic measurement and performance analysis."),
      !!consent.analytics,
      false
    );

    marketingRow = makeToggleRow(
      textValue("marketingLabel", "Marketing"),
      textValue("marketingDescription", "Ads and remarketing integrations."),
      !!consent.marketing,
      false
    );
    marketingRow.row.style.borderBottom = "none";

    actions = el("div");
    actions.className = "tcs-modal-actions";

    if (cfg.showDeclineButton !== false) {
      essentialButton = btn(textValue("reject", "Essential only"), false);
      essentialButton.onclick = function () {
        closeModal();
        if (typeof onCloseBanner === "function") {
          onCloseBanner();
        }
        saveConsentAndNotify(buildConsent(false, false), "essential-only");
      };
      actions.appendChild(essentialButton);
    }

    closeButton = btn(textValue("close", "Close"), false);
    closeButton.onclick = function () {
      closeModal();
    };

    saveButton = btn(textValue("save", "Save"), true);
    saveButton.onclick = function () {
      closeModal();
      if (typeof onCloseBanner === "function") {
        onCloseBanner();
      }
      saveConsentAndNotify(buildConsent(!!analyticsRow.input.checked, !!marketingRow.input.checked), "save-preferences");
    };

    actions.appendChild(closeButton);
    actions.appendChild(saveButton);

    modal.appendChild(title);
    modal.appendChild(description);
    if (disclaimer.textContent) {
      modal.appendChild(disclaimer);
    }
    if (links.childNodes.length > 0) {
      modal.appendChild(links);
    }
    modal.appendChild(analyticsRow.row);
    modal.appendChild(marketingRow.row);
    modal.appendChild(actions);

    overlay.appendChild(modal);

    overlay.onclick = function (event) {
      if (event && event.target === overlay) {
        closeModal();
      }
    };

    if (doc.body) {
      doc.body.appendChild(overlay);
    }
  }

  function mountBanner() {
    var wrap;
    var title;
    var body;
    var disclaimer;
    var links;
    var actions;
    var accept;
    var reject;
    var settings;
    var powered;
    var btnList = [];
    var cleanupLayout = null;
    var th = theme();

    function closeBanner() {
      if (typeof cleanupLayout === "function") {
        cleanupLayout();
      }
      if (wrap && wrap.parentNode) {
        wrap.parentNode.removeChild(wrap);
      }
      mountRevisitButton();
    }

    function appendLink(href, label) {
      var anchor;
      if (!href) {
        return;
      }
      if (links.childNodes.length > 0) {
        links.appendChild(doc.createTextNode(" | "));
      }
      anchor = doc.createElement("a");
      anchor.href = href;
      anchor.target = "_blank";
      anchor.rel = "noopener";
      anchor.textContent = label;
      links.appendChild(anchor);
    }

    function layout() {
      layoutActionButtons(actions, wrap, btnList);
    }

    function mount() {
      if (!doc.body) {
        return;
      }
      if (doc.getElementById(bannerId)) {
        return;
      }

      doc.body.appendChild(wrap);
      layout();
      if (window.requestAnimationFrame) {
        window.requestAnimationFrame(function () {
          layout();
        });
      }

      var onResize = function () {
        if (doc.getElementById(bannerId)) {
          layout();
        }
      };
      window.addEventListener("resize", onResize);
      cleanupLayout = function () {
        window.removeEventListener("resize", onResize);
      };
    }

    if (doc.getElementById(bannerId)) {
      return;
    }
    unmountRevisitButton();

    wrap = el("div");
    wrap.id = bannerId;
    wrap.className = "tcs-banner " + (th.isDark ? "tcs-theme-dark" : "tcs-theme-light");
    wrap.setAttribute("data-sc-banner", "1");
    wrap.style.setProperty("--tcs-primary", th.primary);
    wrap.style.setProperty("--tcs-bg", th.bg);
    wrap.style.setProperty("--tcs-text", th.text);
    wrap.style.setProperty("--tcs-border", th.border);
    wrap.style.setProperty("--tcs-divider", th.divider);
    wrap.style.setProperty("--tcs-btn-border", th.btnBorder);

    applyBannerLayout(wrap);

    title = el("div", textValue("title", "Cookies and privacy"));
    title.className = "tcs-banner__title";

    body = el("div", textValue("body", "We use cookies to enhance your browsing experience, serve personalised ads or content, and analyse our traffic. By clicking \"Accept All\", you consent to our use of cookies."));
    body.className = "tcs-banner__body";

    disclaimer = el("div", textValue("disclaimer", ""));
    disclaimer.className = "tcs-banner__disclaimer";

    links = el("div");
    links.className = "tcs-banner__links";

    appendLink(resolveUrl(cfg.privacyUrl), textValue("privacyLinkLabel", "Privacy Policy"));
    appendLink(resolveUrl(cfg.cookiesUrl), textValue("cookiesLinkLabel", "Cookie Policy"));
    appendLink(
      resolveUrl(cfg.googleDataResponsibilityUrl || "https://business.safety.google/privacy/"),
      textValue("googleDataResponsibilityLabel", "Google data responsibility")
    );

    actions = el("div");
    actions.className = "tcs-actions";

    accept = btn(textValue("accept", "Accept"), true);
    accept.onclick = function () {
      closeBanner();
      saveConsentAndNotify(buildConsent(true, true), "accept-all");
    };
    btnList.push(accept);

    actions.appendChild(accept);

    if (cfg.showDeclineButton !== false) {
      reject = btn(textValue("reject", "Essential only"), false);
      reject.onclick = function () {
        closeBanner();
        saveConsentAndNotify(buildConsent(false, false), "essential-only");
      };
      actions.appendChild(reject);
      btnList.push(reject);
    }

    if (cfg.showPreferencesButton !== false) {
      settings = btn(textValue("preferences", "Preferences"), false);
      settings.onclick = function () {
        openModal(closeBanner);
      };
      actions.appendChild(settings);
      btnList.push(settings);
    }

    powered = createPoweredByNode();

    wrap.appendChild(title);
    wrap.appendChild(body);
    if (disclaimer.textContent) {
      wrap.appendChild(disclaimer);
    }
    if (links.childNodes.length > 0) {
      wrap.appendChild(links);
    }
    if (powered) {
      wrap.appendChild(powered);
    }
    wrap.appendChild(actions);

    if (doc.readyState === "loading") {
      doc.addEventListener("DOMContentLoaded", mount);
    } else {
      mount();
    }
  }

  function exposeApi() {
    var localApi = {
      __tcsLocalApi: true,
      getConsent: getStoredConsent,
      reset: function () {
        clearStoredConsent();
        unmountRevisitButton();
        mountBanner();
      },
      open: function () {
        mountBanner();
      },
      openSettings: function () {
        openModal();
      },
      acceptAll: function () {
        saveConsentAndNotify(buildConsent(true, true, { api: true }), "api-accept-all");
        var banner = doc.getElementById(bannerId);
        if (banner && banner.parentNode) {
          banner.parentNode.removeChild(banner);
        }
      },
      rejectAll: function () {
        saveConsentAndNotify(buildConsent(false, false, { api: true }), "api-reject-all");
        var banner = doc.getElementById(bannerId);
        if (banner && banner.parentNode) {
          banner.parentNode.removeChild(banner);
        }
      },
      diagnostics: function () {
        return {
          mode: cfg.mode || "local",
          regulation: cfg.regulation || "gdpr",
          geoTarget: cfg.geoTarget || "worldwide",
          colorScheme: cfg.colorScheme || "auto",
          storageKey: storageKey,
          hasStoredConsent: !!getStoredConsent(),
          hasBannerNode: !!doc.getElementById(bannerId),
          hasModalNode: !!doc.getElementById(modalId),
          hasRevisitNode: !!doc.getElementById(revisitButtonId),
          gcmEnabled: gcmEnabled,
          gcmWaitForUpdate: gcmWaitForUpdate,
          scriptBlocker: getScriptBlockerDiagnostics(),
          remoteScriptId: cfg.remoteScriptId || null
        };
      }
    };

    window.trucookieCmp = localApi;

    if (!window.scCmp || window.scCmp.__tcsLocalApi === true) {
      window.scCmp = localApi;
    }
  }

  function hasRemoteSignal() {
    if (window.trucookieCmpRemote || window.TruCookieCMP) {
      return true;
    }

    if (window.scCmp && window.scCmp.__tcsLocalApi !== true) {
      return true;
    }

    return !!doc.querySelector("[data-trucookie-banner], [data-sc-cmp-banner], #sc-cmp-wrap, #sc-cmp-banner[data-remote='1']");
  }

  function runConnectedMode() {
    var timeoutMs = parseInt(cfg.remoteTimeoutMs, 10);
    var remoteScript = cfg.remoteScriptId ? doc.getElementById(cfg.remoteScriptId) : null;
    var done = false;
    var start = Date.now();

    function fallback(reason) {
      if (done) {
        return;
      }
      done = true;
      debugLog("Connected fallback to local banner:", reason);
      mountBanner();
    }

    function finishRemote(reason) {
      if (done) {
        return;
      }
      done = true;
      debugLog("Remote renderer detected:", reason);
    }

    if (!timeoutMs || timeoutMs < 1000) {
      timeoutMs = 3500;
    }

    if (remoteScript) {
      remoteScript.addEventListener("error", function () {
        fallback("remote-script-error");
      });
      remoteScript.addEventListener("load", function () {
        setTimeout(function () {
          if (hasRemoteSignal()) {
            finishRemote("remote-script-load");
          } else {
            fallback("remote-script-loaded-no-signal");
          }
        }, 250);
      });
    }

    var timer = setInterval(function () {
      if (done) {
        clearInterval(timer);
        return;
      }
      if (hasRemoteSignal()) {
        clearInterval(timer);
        finishRemote("poll-signal");
        return;
      }
      if (Date.now() - start >= timeoutMs) {
        clearInterval(timer);
        fallback("timeout");
      }
    }, 120);
  }

  function boot() {
    var existing;
    var dntConsent;
    var force;

    setupGcmDefault();

    if (resetRequested()) {
      clearStoredConsent();
    }

    existing = getStoredConsent();
    if (existing) {
      dispatchConsentEvent(existing, "existing");
      applyConsentRuntime(existing);
    } else if (shouldApplyDntDefault(existing)) {
      dntConsent = buildConsent(false, false, { dnt: true });
      saveConsentAndNotify(dntConsent, "dnt-default");
      existing = dntConsent;
    }

    force = forceBanner();

    if (!existing || force) {
      if (cfg.mode === "connected" && cfg.remoteScriptUrl) {
        runConnectedMode();
      } else {
        mountBanner();
      }
    } else {
      mountRevisitButton();
    }
  }

  exposeApi();

  if (doc.readyState === "loading") {
    doc.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
