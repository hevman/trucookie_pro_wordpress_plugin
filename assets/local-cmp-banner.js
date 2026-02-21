/* global gtag */
(function () {
  "use strict";

  var cfgNode = document.getElementById("sc-local-cmp-config");
  if (!cfgNode) return;

  var payload = {};
  try {
    payload = JSON.parse(cfgNode.textContent || "{}");
  } catch (e) {
    return;
  }

  var siteKey = String(payload.site || "wp");
  var cfg = payload.config || {};
  var consentKey = "sc_cmp_consent_v1:" + siteKey;
  var observer = null;

  function readConsent() {
    try {
      var raw = localStorage.getItem(consentKey);
      if (!raw) return null;
      var parsed = JSON.parse(raw);
      return parsed && typeof parsed === "object" ? parsed : null;
    } catch (e) {
      return null;
    }
  }

  function saveConsent(state) {
    try {
      localStorage.setItem(consentKey, JSON.stringify(state));
    } catch (e) {}
  }

  function localeBase(locale) {
    var l = String(locale || "en").toLowerCase().replace("-", "_");
    if (l.indexOf("pl") === 0) return "pl";
    if (l.indexOf("de") === 0) return "de";
    if (l.indexOf("es") === 0) return "es";
    if (l.indexOf("fr") === 0) return "fr";
    if (l.indexOf("it") === 0) return "it";
    if (l === "pt" || l.indexOf("pt_br") === 0 || l.indexOf("pt") === 0) return "pt_br";
    return "en";
  }

  function regionMode(mode) {
    var m = String(mode || "auto").toLowerCase();
    if (m === "eu" || m === "us") return m;
    var lang = String(navigator.language || "").toLowerCase();
    if (lang.indexOf("en-us") === 0) return "us";
    return "eu";
  }

  function copyFor() {
    var base = localeBase(cfg.locale);
    var region = regionMode(cfg.regionMode);
    var key = base + "_" + region;
    var copy = {
      en_us: {
        title: "Privacy choices",
        body: "We use essential cookies to make our site work. You can choose whether we can use optional cookies for analytics and ads.",
        accept: "Accept",
        reject: "Reject",
        prefs: "Privacy choices",
        save: "Save",
        close: "Close",
        necessary: "Necessary",
        analytics: "Analytics",
        marketing: "Ads",
        links: "Details in our policies."
      },
      en_eu: {
        title: "Cookies & privacy",
        body: "We use essential cookies to make our site work. With your consent, we may also use optional cookies for analytics and marketing.",
        accept: "Accept",
        reject: "Essential only",
        prefs: "Cookie settings",
        save: "Save",
        close: "Close",
        necessary: "Necessary",
        analytics: "Analytics",
        marketing: "Marketing",
        links: "Details in our policies."
      },
      pl_us: {
        title: "Twoje wybory prywatności",
        body: "Używamy niezbędnych cookies, aby serwis działał. Możesz zdecydować, czy pozwalasz nam na cookies opcjonalne do analityki i reklam.",
        accept: "Akceptuj",
        reject: "Odrzuć",
        prefs: "Ustawienia prywatności",
        save: "Zapisz",
        close: "Zamknij",
        necessary: "Niezbędne",
        analytics: "Analityka",
        marketing: "Reklamy",
        links: "Szczegóły znajdziesz w politykach."
      },
      pl_eu: {
        title: "Cookies i prywatność",
        body: "Używamy niezbędnych cookies, aby serwis działał. Za Twoją zgodą możemy też używać cookies opcjonalnych do analityki i marketingu.",
        accept: "Akceptuj",
        reject: "Tylko niezbędne",
        prefs: "Ustawienia cookies",
        save: "Zapisz",
        close: "Zamknij",
        necessary: "Niezbędne",
        analytics: "Analityka",
        marketing: "Marketing",
        links: "Szczegóły znajdziesz w politykach."
      },
      de_us: {
        title: "Ihre Datenschutzwahl",
        body: "Wir verwenden essenzielle Cookies, damit unsere Website funktioniert. Sie können entscheiden, ob wir optionale Cookies für Analysen und Werbung zulassen.",
        accept: "Akzeptieren",
        reject: "Ablehnen",
        prefs: "Datenschutz-Einstellungen",
        save: "Speichern",
        close: "Schließen",
        necessary: "Notwendig",
        analytics: "Analysen",
        marketing: "Werbung",
        links: "Details in unseren Richtlinien."
      },
      de_eu: {
        title: "Cookies und Datenschutz",
        body: "Wir verwenden essenzielle Cookies, damit unsere Website funktioniert. Mit Ihrer Einwilligung können wir auch optionale Cookies für Analysen und Marketing verwenden.",
        accept: "Akzeptieren",
        reject: "Nur essenzielle",
        prefs: "Cookie-Einstellungen",
        save: "Speichern",
        close: "Schließen",
        necessary: "Notwendig",
        analytics: "Analysen",
        marketing: "Marketing",
        links: "Details in unseren Richtlinien."
      },
      es_us: {
        title: "Tus opciones de privacidad",
        body: "Usamos cookies esenciales para que el sitio funcione. Puedes decidir si permites cookies opcionales para analítica y anuncios.",
        accept: "Aceptar",
        reject: "Rechazar",
        prefs: "Opciones de privacidad",
        save: "Guardar",
        close: "Cerrar",
        necessary: "Necesarias",
        analytics: "Analítica",
        marketing: "Anuncios",
        links: "Más detalles en nuestras políticas."
      },
      es_eu: {
        title: "Cookies y privacidad",
        body: "Usamos cookies esenciales para que el sitio funcione. Con tu consentimiento, también podemos usar cookies opcionales para analítica y marketing.",
        accept: "Aceptar",
        reject: "Solo esenciales",
        prefs: "Configuración de cookies",
        save: "Guardar",
        close: "Cerrar",
        necessary: "Necesarias",
        analytics: "Analítica",
        marketing: "Marketing",
        links: "Más detalles en nuestras políticas."
      },
      fr_us: {
        title: "Vos choix de confidentialité",
        body: "Nous utilisons des cookies essentiels pour faire fonctionner le site. Vous pouvez choisir si nous pouvons utiliser des cookies optionnels pour l'analyse et la publicité.",
        accept: "Accepter",
        reject: "Refuser",
        prefs: "Choix de confidentialité",
        save: "Enregistrer",
        close: "Fermer",
        necessary: "Essentiels",
        analytics: "Analytique",
        marketing: "Publicité",
        links: "Plus de détails dans nos politiques."
      },
      fr_eu: {
        title: "Cookies et confidentialité",
        body: "Nous utilisons des cookies essentiels pour faire fonctionner le site. Avec votre consentement, nous pouvons aussi utiliser des cookies optionnels pour l'analyse et le marketing.",
        accept: "Accepter",
        reject: "Essentiels uniquement",
        prefs: "Paramètres des cookies",
        save: "Enregistrer",
        close: "Fermer",
        necessary: "Essentiels",
        analytics: "Analytique",
        marketing: "Marketing",
        links: "Plus de détails dans nos politiques."
      },
      it_us: {
        title: "Le tue scelte sulla privacy",
        body: "Usiamo cookie essenziali per far funzionare il sito. Puoi scegliere se permettere cookie opzionali per analisi e annunci.",
        accept: "Accetta",
        reject: "Rifiuta",
        prefs: "Scelte sulla privacy",
        save: "Salva",
        close: "Chiudi",
        necessary: "Necessari",
        analytics: "Analisi",
        marketing: "Annunci",
        links: "Dettagli nelle nostre policy."
      },
      it_eu: {
        title: "Cookie e privacy",
        body: "Usiamo cookie essenziali per far funzionare il sito. Con il tuo consenso possiamo usare anche cookie opzionali per analisi e marketing.",
        accept: "Accetta",
        reject: "Solo essenziali",
        prefs: "Impostazioni cookie",
        save: "Salva",
        close: "Chiudi",
        necessary: "Necessari",
        analytics: "Analisi",
        marketing: "Marketing",
        links: "Dettagli nelle nostre policy."
      },
      pt_br_us: {
        title: "Suas escolhas de privacidade",
        body: "Usamos cookies essenciais para o site funcionar. Você pode escolher se permite cookies opcionais para análise e anúncios.",
        accept: "Aceitar",
        reject: "Rejeitar",
        prefs: "Escolhas de privacidade",
        save: "Salvar",
        close: "Fechar",
        necessary: "Essenciais",
        analytics: "Análise",
        marketing: "Anúncios",
        links: "Mais detalhes em nossas políticas."
      },
      pt_br_eu: {
        title: "Cookies e privacidade",
        body: "Usamos cookies essenciais para o site funcionar. Com seu consentimento, também podemos usar cookies opcionais para análise e marketing.",
        accept: "Aceitar",
        reject: "Somente essenciais",
        prefs: "Configurações de cookies",
        save: "Salvar",
        close: "Fechar",
        necessary: "Essenciais",
        analytics: "Análise",
        marketing: "Marketing",
        links: "Mais detalhes em nossas políticas."
      }
    };
    return copy[key] || copy.en_eu;
  }
function applyConsentMode(consent) {
    if (typeof gtag !== "function") return;
    var analytics = consent.analytics ? "granted" : "denied";
    var marketing = consent.marketing ? "granted" : "denied";
    gtag("consent", "update", {
      analytics_storage: analytics,
      ad_storage: marketing,
      ad_user_data: marketing,
      ad_personalization: marketing
    });
  }

  function emitConsentEvent(consent) {
    var detail = {
      consent: consent,
      source: "local-cmp-banner",
      site: siteKey
    };
    document.dispatchEvent(new CustomEvent("sc:consent", { detail: detail }));
    window.dispatchEvent(new CustomEvent("sc:consent", { detail: detail }));
  }

  function shouldUnlock(purpose, consent) {
    if (purpose === "analytics") return !!consent.analytics;
    if (purpose === "marketing" || purpose === "ads") return !!consent.marketing;
    if (purpose === "necessary") return true;
    return !!consent.analytics || !!consent.marketing;
  }

  function cloneAndExecute(script) {
    if (script.dataset.scUnlocked === "1") return;
    var replacement = document.createElement("script");
    for (var i = 0; i < script.attributes.length; i++) {
      var attr = script.attributes[i];
      if (!attr) continue;
      if (
        attr.name === "type" ||
        attr.name === "data-sc-purpose" ||
        attr.name === "data-sc-blocked" ||
        attr.name === "data-sc-type"
      ) {
        continue;
      }
      replacement.setAttribute(attr.name, attr.value);
    }
    replacement.type = script.getAttribute("data-sc-type") || "text/javascript";
    replacement.text = script.text || script.textContent || "";
    script.dataset.scUnlocked = "1";
    script.parentNode.insertBefore(replacement, script);
    script.parentNode.removeChild(script);
  }

  function unlockByPurpose(consent, root) {
    var scope = root || document;
    var nodes = scope.querySelectorAll(
      'script[data-sc-purpose],script[type="text/plain"][data-sc-purpose],script[data-sc-blocked="1"]'
    );
    for (var i = 0; i < nodes.length; i++) {
      var script = nodes[i];
      var purpose = String(script.getAttribute("data-sc-purpose") || "").toLowerCase();
      if (shouldUnlock(purpose, consent)) {
        cloneAndExecute(script);
      }
    }
  }

  function startObserver(consent) {
    if (observer) observer.disconnect();
    observer = new MutationObserver(function (mutations) {
      for (var i = 0; i < mutations.length; i++) {
        var m = mutations[i];
        for (var j = 0; j < m.addedNodes.length; j++) {
          var n = m.addedNodes[j];
          if (!n || n.nodeType !== 1) continue;
          if (n.matches && n.matches("script[data-sc-purpose],script[data-sc-blocked='1']")) {
            unlockByPurpose(consent, n.parentNode || document);
          } else if (n.querySelectorAll) {
            unlockByPurpose(consent, n);
          }
        }
      }
    });
    observer.observe(document.documentElement, { childList: true, subtree: true });
  }

  function setConsent(nextConsent) {
    var stored = {
      necessary: true,
      analytics: !!nextConsent.analytics,
      marketing: !!nextConsent.marketing,
      ts: Date.now()
    };
    saveConsent(stored);
    applyConsentMode(stored);
    unlockByPurpose(stored, document);
    startObserver(stored);
    emitConsentEvent(stored);
    removeBanner();
  }

  function removeBanner() {
    var wrap = document.getElementById("sc-cmp-wrap");
    if (wrap && wrap.parentNode) wrap.parentNode.removeChild(wrap);
  }

  function createToggleRow(label, checked) {
    var row = document.createElement("label");
    row.style.display = "flex";
    row.style.gap = "10px";
    row.style.alignItems = "center";
    row.style.marginBottom = "8px";
    var input = document.createElement("input");
    input.type = "checkbox";
    input.checked = !!checked;
    row.appendChild(input);
    var span = document.createElement("span");
    span.textContent = label;
    row.appendChild(span);
    return { row: row, input: input };
  }

  function renderBanner() {
    var t = copyFor();
    var wrap = document.createElement("div");
    wrap.id = "sc-cmp-wrap";
    wrap.style.position = "fixed";
    wrap.style.left = "16px";
    wrap.style.right = "16px";
    wrap.style.bottom = "16px";
    wrap.style.zIndex = "999999";
    wrap.style.background = cfg.backgroundColor || "#ffffff";
    wrap.style.color = "#111827";
    wrap.style.border = "1px solid rgba(17,24,39,.15)";
    wrap.style.borderRadius = "12px";
    wrap.style.boxShadow = "0 14px 36px rgba(0,0,0,.16)";
    wrap.style.padding = "14px";
    wrap.style.maxWidth = "820px";
    if (String(cfg.position || "bottom").indexOf("top") === 0) {
      wrap.style.top = "16px";
      wrap.style.bottom = "auto";
    }
    if (String(cfg.position || "").indexOf("right") > -1) {
      wrap.style.left = "auto";
      wrap.style.width = "min(420px, calc(100vw - 32px))";
    } else if (String(cfg.position || "").indexOf("left") > -1) {
      wrap.style.right = "auto";
      wrap.style.width = "min(420px, calc(100vw - 32px))";
    }

    var title = document.createElement("strong");
    title.textContent = t.title;
    title.style.display = "block";
    title.style.marginBottom = "6px";
    wrap.appendChild(title);

    var body = document.createElement("p");
    body.textContent = t.body;
    body.style.margin = "0 0 10px";
    body.style.fontSize = "13px";
    wrap.appendChild(body);

    var actions = document.createElement("div");
    actions.style.display = "flex";
    actions.style.gap = "8px";
    actions.style.flexWrap = "wrap";

    function mkBtn(label, primary) {
      var btn = document.createElement("button");
      btn.type = "button";
      btn.textContent = label;
      btn.style.cursor = "pointer";
      btn.style.borderRadius = "9px";
      btn.style.padding = "8px 11px";
      btn.style.fontWeight = "600";
      btn.style.border = primary ? "0" : "1px solid rgba(17,24,39,.2)";
      btn.style.background = primary ? (cfg.primaryColor || "#059669") : "transparent";
      btn.style.color = primary ? "#fff" : "#111827";
      return btn;
    }

    var acceptBtn = mkBtn(t.accept, true);
    acceptBtn.addEventListener("click", function () {
      setConsent({ analytics: true, marketing: true });
    });
    actions.appendChild(acceptBtn);

    if (cfg.showDeclineButton !== false) {
      var rejectBtn = mkBtn(t.reject, false);
      rejectBtn.addEventListener("click", function () {
        setConsent({ analytics: false, marketing: false });
      });
      actions.appendChild(rejectBtn);
    }

    if (cfg.showPreferencesButton !== false) {
      var prefBtn = mkBtn(t.prefs, false);
      prefBtn.addEventListener("click", function () {
        openPrefs(wrap, t);
      });
      actions.appendChild(prefBtn);
    }

    wrap.appendChild(actions);

    var links = document.createElement("div");
    links.style.fontSize = "12px";
    links.style.marginTop = "8px";
    links.textContent = t.links + " ";

    var cookiesUrl = cfg.cookiesUrl || "/cookies";
    var privacyUrl = cfg.privacyUrl || "/privacy-policy";
    var cLink = document.createElement("a");
    cLink.href = cookiesUrl;
    cLink.textContent = "Cookies";
    cLink.style.marginRight = "8px";
    var pLink = document.createElement("a");
    pLink.href = privacyUrl;
    pLink.textContent = "Privacy";
    links.appendChild(cLink);
    links.appendChild(pLink);
    wrap.appendChild(links);

    document.body.appendChild(wrap);
  }

  function openPrefs(wrap, t) {
    var modal = document.createElement("div");
    modal.style.position = "fixed";
    modal.style.inset = "0";
    modal.style.background = "rgba(0,0,0,.42)";
    modal.style.display = "flex";
    modal.style.alignItems = "center";
    modal.style.justifyContent = "center";
    modal.style.zIndex = "1000000";

    var card = document.createElement("div");
    card.style.background = "#fff";
    card.style.borderRadius = "12px";
    card.style.padding = "16px";
    card.style.width = "min(520px, calc(100vw - 24px))";

    var title = document.createElement("strong");
    title.textContent = t.prefs;
    title.style.display = "block";
    title.style.marginBottom = "10px";
    card.appendChild(title);

    var necessary = createToggleRow(t.necessary, true);
    necessary.input.disabled = true;
    card.appendChild(necessary.row);
    var analytics = createToggleRow(t.analytics, false);
    card.appendChild(analytics.row);
    var marketing = createToggleRow(t.marketing, false);
    card.appendChild(marketing.row);

    var actions = document.createElement("div");
    actions.style.display = "flex";
    actions.style.gap = "8px";
    actions.style.marginTop = "10px";

    var saveBtn = document.createElement("button");
    saveBtn.type = "button";
    saveBtn.textContent = t.save;
    saveBtn.style.padding = "8px 11px";
    saveBtn.style.borderRadius = "8px";
    saveBtn.style.border = "0";
    saveBtn.style.background = cfg.primaryColor || "#059669";
    saveBtn.style.color = "#fff";
    saveBtn.addEventListener("click", function () {
      setConsent({ analytics: analytics.input.checked, marketing: marketing.input.checked });
      if (modal.parentNode) modal.parentNode.removeChild(modal);
    });
    actions.appendChild(saveBtn);

    var closeBtn = document.createElement("button");
    closeBtn.type = "button";
    closeBtn.textContent = t.close;
    closeBtn.style.padding = "8px 11px";
    closeBtn.style.borderRadius = "8px";
    closeBtn.style.border = "1px solid rgba(17,24,39,.2)";
    closeBtn.style.background = "transparent";
    closeBtn.addEventListener("click", function () {
      if (modal.parentNode) modal.parentNode.removeChild(modal);
    });
    actions.appendChild(closeBtn);

    card.appendChild(actions);
    modal.appendChild(card);
    document.body.appendChild(modal);
  }

  var existing = readConsent();
  if (existing) {
    applyConsentMode(existing);
    unlockByPurpose(existing, document);
    startObserver(existing);
    emitConsentEvent(existing);
    return;
  }

  document.addEventListener("DOMContentLoaded", function () {
    renderBanner();
  });

  window.scCmp = {
    getConsent: readConsent,
    openPreferences: function () {
      var wrap = document.getElementById("sc-cmp-wrap");
      if (wrap) openPrefs(wrap, copyFor());
    },
    acceptAll: function () {
      setConsent({ analytics: true, marketing: true });
    },
    rejectAll: function () {
      setConsent({ analytics: false, marketing: false });
    }
  };
})();
