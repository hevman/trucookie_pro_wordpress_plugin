(function($){
  var SC_I18N = __SC_ADMIN_I18N__;
  function base64UrlEncode(obj){
    try {
      var json = JSON.stringify(obj || {});
      var bytes = (typeof TextEncoder !== "undefined") ? new TextEncoder().encode(json) : null;
      var bin = "";
      if(bytes){
        for(var i=0;i<bytes.length;i++){ bin += String.fromCharCode(bytes[i]); }
      } else {
        bin = unescape(encodeURIComponent(json));
      }
      var b64 = btoa(bin);
      return b64.replace(/\\+/g,"-").replace(/\\//g,"_").replace(/=+$/g,"");
    } catch(e){ return ""; }
  }

  function readConfig(){
    return {
      locale: $("#locale").val() || "pl",
      regionMode: $("#regionMode").val() || "auto",
      position: $("#position").val() || "bottom",
      bannerSize: $("#bannerSize").val() || "standard",
      style: $("#style").val() || "bar",
      primaryColor: $("#primaryColor").val() || "#059669",
      backgroundColor: $("#backgroundColor").val() || "#ffffff",
      autoTheme: $("input[type=checkbox][name=autoTheme]").is(":checked"),
      showDeclineButton: $("input[type=checkbox][name=showDeclineButton]").is(":checked"),
      showPreferencesButton: $("input[type=checkbox][name=showPreferencesButton]").is(":checked")
    };
  }

  function effectiveTheme(cfg, theme){
    var t = String(theme || "auto").toLowerCase();
    if(t === "dark" || t === "light"){ return t; }
    try {
      if(window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches){
        return "dark";
      }
    } catch(e){}
    return "light";
  }

  function esc(s){
    return String(s || "").replace(/[&<>\"']/g, function(ch){
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch] || ch);
    });
  }

  function normalizeLocale(locale){
    var l = String(locale || "pl").toLowerCase().replace(/-/g, "_");
    if(l.indexOf("pl") === 0) return "pl";
    if(l.indexOf("en") === 0) return "en";
    if(l.indexOf("de") === 0) return "de";
    if(l.indexOf("es") === 0) return "es";
    if(l.indexOf("fr") === 0) return "fr";
    if(l.indexOf("it") === 0) return "it";
    if(l === "pt" || l.indexOf("pt_br") === 0 || l.indexOf("pt") === 0) return "pt_br";
    return "en";
  }

  function copyFor(cfg){
    var locale = normalizeLocale(cfg && cfg.locale);
    var isUs = (cfg && cfg.regionMode === "us");
    var key = locale + (isUs ? "_us" : "_eu");
    if(SC_I18N && SC_I18N[key]) return SC_I18N[key];
    return {
      title: "Cookies & privacy",
      body: "We use essential cookies to make our site work.",
      accept: "Accept",
      reject: "Reject",
      settings: "Preferences",
      prefs: "Privacy choices",
      analytics: "Analytics",
      analyticsDesc: "Traffic measurement.",
      marketing: "Marketing",
      marketingDesc: "Ads / remarketing.",
      disclaimer: "Details in the Cookie Policy.",
      learnMore: "Cookie Policy",
      privacy: "Privacy Policy",
      save: "Save",
      close: "Close"
    };
  }

  function renderLocal(cfg, theme){
    var $root = $("#sc-local-preview");
    if(!$root.length){ return; }

    function hexToRgb(hex){
      if(typeof hex !== 'string') return null;
      var h = (hex || '').trim().toLowerCase();
      if(!h) return null;
      if(h[0] === '#') h = h.slice(1);
      if(h.length === 3){ h = h[0]+h[0]+h[1]+h[1]+h[2]+h[2]; }
      if(h.length !== 6) return null;
      var r = parseInt(h.slice(0,2), 16);
      var g = parseInt(h.slice(2,4), 16);
      var b = parseInt(h.slice(4,6), 16);
      if(!isFinite(r) || !isFinite(g) || !isFinite(b)) return null;
      return { r:r, g:g, b:b };
    }
    function isDarkHex(hex){
      var rgb = hexToRgb(hex);
      if(!rgb) return null;
      var lum = (0.2126*rgb.r + 0.7152*rgb.g + 0.0722*rgb.b) / 255;
      return lum < 0.52;
    }
    function bannerTheme(){
      var wantsDark = effectiveTheme(cfg, theme) === 'dark';
      var autoTheme = !(cfg && cfg.autoTheme === false);
      var bg = (cfg && cfg.backgroundColor) ? String(cfg.backgroundColor) : '#ffffff';
      var bgNorm = bg.trim().toLowerCase();
      var bgIsDark = isDarkHex(bgNorm);
      if(bgIsDark === null) bgIsDark = wantsDark;
      if(autoTheme && wantsDark && (!bgNorm || bgNorm === '#ffffff' || bgNorm === '#fff')) {
        bg = '#18181b';
        bgIsDark = true;
      }
      var textColor = bgIsDark ? 'rgb(244,244,245)' : 'rgb(17,24,39)';
      var border = bgIsDark ? 'rgba(255,255,255,0.16)' : 'rgba(17,24,39,0.15)';
      var divider = bgIsDark ? 'rgba(255,255,255,0.12)' : 'rgba(17,24,39,0.10)';
      var btnBorder = bgIsDark ? 'rgba(255,255,255,0.22)' : 'rgba(17,24,39,0.18)';
      return { bg:bg, isDark:!!bgIsDark, text:textColor, border:border, divider:divider, btnBorder:btnBorder };
    }
    function applyBannerLayout(wrap){
      var sp = '12px';
      wrap.style.bottom = sp;
      wrap.style.top = '';
      wrap.style.margin = '0';
      wrap.style.width = 'calc(100% - 24px)';
      if(cfg.style === 'rectangle-right'){
        wrap.style.left = 'auto';
        wrap.style.right = sp;
        wrap.style.maxWidth = '360px';
        return;
      }
      if(cfg.style === 'rectangle-left'){
        wrap.style.left = sp;
        wrap.style.right = 'auto';
        wrap.style.maxWidth = '360px';
        return;
      }
      wrap.style.left = sp;
      wrap.style.right = sp;
      wrap.style.maxWidth = '960px';
      wrap.style.margin = '0 auto';
    }
    function el(tag, textContent){
      var e = document.createElement(tag);
      if(typeof textContent === 'string') e.textContent = textContent;
      return e;
    }
    function btn(label, primary){
      var th = bannerTheme();
      var b = el('button', label);
      b.type = 'button';
      b.style.cursor = 'pointer';
      b.style.borderRadius = '10px';
      b.style.padding = (cfg.bannerSize === 'compact') ? '8px 10px' : '10px 12px';
      b.style.fontSize = '13px';
      b.style.fontWeight = '600';
      b.style.border = '1px solid ' + th.btnBorder;
      b.style.background = primary ? (cfg.primaryColor || '#059669') : 'transparent';
      b.style.color = primary ? '#ffffff' : th.text;
      return b;
    }

    $root.empty();

    // Fake page viewport background (like backend preview page).
    var pageDark = effectiveTheme(cfg, theme) === 'dark';
    var pageBg = pageDark ? 'rgb(9, 9, 11)' : 'rgb(244, 244, 245)';
    var pageText = pageDark ? 'rgb(244, 244, 245)' : 'rgb(17, 24, 39)';

    var outer = document.createElement('div');
    outer.style.position = 'absolute';
    outer.style.inset = '0';
    outer.style.display = 'flex';
    outer.style.alignItems = 'stretch';
    outer.style.justifyContent = 'stretch';
    outer.style.padding = '16px';

    var frame = document.createElement('div');
    frame.style.position = 'relative';
    frame.style.width = '100%';
    frame.style.height = '100%';
    frame.style.borderRadius = '10px';
    frame.style.overflow = 'hidden';
    frame.style.border = pageDark ? '1px solid rgba(255,255,255,0.10)' : '1px solid rgba(0,0,0,0.08)';
    frame.style.background = pageBg;
    frame.style.color = pageText;

    var hint = document.createElement('div');
    hint.textContent = (SC_I18N && SC_I18N.previewLabel) ? SC_I18N.previewLabel : 'Preview';
    hint.style.position = 'absolute';
    hint.style.inset = '0';
    hint.style.display = 'flex';
    hint.style.alignItems = 'center';
    hint.style.justifyContent = 'center';
    hint.style.pointerEvents = 'none';
    hint.style.opacity = '0.35';
    hint.style.fontSize = '12px';
    hint.style.letterSpacing = '0.02em';

    function openModal(){
      var existing = frame.querySelector('[data-sc-prev-modal]');
      if(existing && existing.parentNode) existing.parentNode.removeChild(existing);

      var text = copyFor(cfg);
      var th = bannerTheme();

      var overlay = document.createElement('div');
      overlay.setAttribute('data-sc-prev-modal','1');
      overlay.style.position = 'absolute';
      overlay.style.inset = '0';
      overlay.style.background = 'rgba(0,0,0,0.55)';
      overlay.style.display = 'flex';
      overlay.style.alignItems = 'center';
      overlay.style.justifyContent = 'center';
      overlay.style.padding = '16px';

      var modal = document.createElement('div');
      modal.style.width = '100%';
      modal.style.maxWidth = '560px';
      modal.style.borderRadius = '16px';
      modal.style.border = '1px solid ' + th.btnBorder;
      modal.style.background = th.bg;
      modal.style.padding = '16px';
      modal.style.boxShadow = '0 20px 60px rgba(0,0,0,0.25)';
      modal.style.color = th.text;

      var head = document.createElement('div');
      head.style.display = 'flex';
      head.style.alignItems = 'center';
      head.style.justifyContent = 'space-between';
      head.style.gap = '12px';

      var h = el('div', text.prefs);
      h.style.fontWeight = '800';
      h.style.fontSize = '16px';
      var close = btn(text.close, false);
      close.onclick = function(){ overlay.parentNode && overlay.parentNode.removeChild(overlay); };
      head.appendChild(h);
      head.appendChild(close);

      var d = el('div', text.disclaimer);
      d.style.marginTop = '8px';
      d.style.fontSize = '12px';
      d.style.opacity = '0.9';

      function makeLink(href, label){
        var a = document.createElement('a');
        a.href = href;
        a.textContent = label;
        a.target = '_blank';
        a.rel = 'noopener';
        a.style.textDecoration = 'underline';
        a.style.color = (cfg.primaryColor || '#059669');
        return a;
      }
      var links = el('div');
      links.style.marginTop = '8px';
      links.style.fontSize = '12px';
      links.style.opacity = '0.9';
      links.appendChild(makeLink('#', text.learnMore));
      links.appendChild(document.createTextNode(' \u00b7 '));
      links.appendChild(makeLink('#', text.privacy));

      function toggleRow(label, desc, initial){
        var row = el('div');
        row.style.display = 'flex';
        row.style.alignItems = 'center';
        row.style.justifyContent = 'space-between';
        row.style.gap = '12px';
        row.style.padding = '10px 0';
        row.style.borderBottom = '1px solid ' + th.divider;
        var left = el('div');
        var l1 = el('div', label);
        l1.style.fontWeight = '700';
        l1.style.fontSize = '13px';
        var l2 = el('div', desc);
        l2.style.fontSize = '12px';
        l2.style.opacity = '0.85';
        l2.style.marginTop = '2px';
        left.appendChild(l1);
        left.appendChild(l2);
        var input = document.createElement('input');
        input.type = 'checkbox';
        input.checked = !!initial;
        input.style.width = '18px';
        input.style.height = '18px';
        input.style.accentColor = (cfg.primaryColor || '#059669');
        row.appendChild(left);
        row.appendChild(input);
        return row;
      }

      var rA = toggleRow(text.analytics, text.analyticsDesc, true);
      var rM = toggleRow(text.marketing, text.marketingDesc, false);
      rM.style.borderBottom = '0';

      var actions = el('div');
      actions.style.display = 'flex';
      actions.style.flexWrap = 'wrap';
      actions.style.gap = '8px';
      actions.style.justifyContent = 'flex-end';
      actions.style.marginTop = '12px';
      var save = btn(text.save, true);
      save.onclick = function(){ overlay.parentNode && overlay.parentNode.removeChild(overlay); };
      actions.appendChild(save);

      modal.appendChild(head);
      modal.appendChild(d);
      modal.appendChild(links);
      modal.appendChild(rA);
      modal.appendChild(rM);
      modal.appendChild(actions);
      overlay.appendChild(modal);
      overlay.onclick = function(e){ if(e && e.target === overlay){ overlay.parentNode && overlay.parentNode.removeChild(overlay);} };
      frame.appendChild(overlay);
    }

    // Banner (same vertical layout as the real renderer).
    var th = bannerTheme();
    var text = copyFor(cfg);
    var banner = document.createElement('div');
    banner.style.position = 'absolute';
    banner.style.zIndex = '10';
    banner.style.boxSizing = 'border-box';
    banner.style.boxShadow = '0 10px 30px rgba(0,0,0,0.15)';
    banner.style.border = '1px solid ' + th.border;
    banner.style.borderRadius = '16px';
    banner.style.padding = (cfg.bannerSize === 'compact') ? '12px' : '16px';
    banner.style.background = th.bg;
    banner.style.color = th.text;
    applyBannerLayout(banner);

    var title = el('div', text.title);
    title.style.fontWeight = '700';
    title.style.fontSize = '14px';
    var body = el('div', text.body);
    body.style.marginTop = '6px';
    body.style.fontSize = '13px';
    body.style.opacity = '0.9';

    var disc = el('div');
    disc.style.marginTop = '8px';
    disc.style.fontSize = '12px';
    disc.style.opacity = '0.85';
    disc.appendChild(document.createTextNode(text.disclaimer));
    var a = document.createElement('a');
    a.href = '#';
    a.textContent = text.learnMore;
    a.style.marginLeft = '8px';
    a.style.textDecoration = 'underline';
    a.style.color = (cfg.primaryColor || '#059669');
    disc.appendChild(document.createTextNode(' \u00b7 '));
    disc.appendChild(a);

    var actions = el('div');
    actions.style.display = 'flex';
    actions.style.flexWrap = 'wrap';
    actions.style.gap = '8px';
    actions.style.marginTop = '12px';

    var accept = btn(text.accept, true);
    accept.onclick = function(){};
    actions.appendChild(accept);

    if(cfg.showDeclineButton){
      var reject = btn(text.reject, false);
      reject.onclick = function(){};
      actions.appendChild(reject);
    }
    if(cfg.showPreferencesButton){
      var settings = btn(text.settings, false);
      settings.onclick = function(){ openModal(); };
      actions.appendChild(settings);
    }

    var btnList = [accept];
    if(cfg.showDeclineButton && reject) btnList.push(reject);
    if(cfg.showPreferencesButton && settings) btnList.push(settings);

    function layoutActions(){
      try {
        for(var i=0;i<btnList.length;i++){
          var b = btnList[i];
          if(!b) continue;
          try { b.style.width = ''; } catch(e){}
          try { b.style.minWidth = ''; } catch(e){}
          try { b.style.gridColumn = ''; } catch(e){}
        }

        actions.style.display = 'flex';
        actions.style.flexWrap = 'wrap';
        actions.style.alignItems = 'center';
        actions.style.justifyContent = 'flex-start';

        var wrapped = false;
        try {
          var firstTop = null;
          for(var k=0;k<btnList.length;k++){
            var bk = btnList[k];
            if(!bk) continue;
            var top = bk.offsetTop;
            if(firstTop === null) firstTop = top;
            else if(top > firstTop + 1){ wrapped = true; break; }
          }
        } catch(e){}

        if(wrapped){
          actions.style.display = 'grid';
          actions.style.gridAutoFlow = 'row';
          actions.style.gridTemplateColumns = (btnList.length >= 3) ? '1fr 1fr' : '1fr';
          actions.style.alignItems = 'stretch';
          actions.style.justifyContent = 'stretch';
          for(var j=0;j<btnList.length;j++){
            var bb = btnList[j];
            if(!bb) continue;
            bb.style.width = '100%';
            bb.style.minWidth = '0';
          }
          if(btnList.length >= 3 && btnList[0]) btnList[0].style.gridColumn = '1 / -1';
        }
      } catch(e){}
    }

    banner.appendChild(title);
    banner.appendChild(body);
    banner.appendChild(disc);
    banner.appendChild(actions);

    frame.appendChild(hint);
    frame.appendChild(banner);
    outer.appendChild(frame);
    $root.append(outer);
    try { layoutActions(); } catch(e){}
    try { if(window.requestAnimationFrame) window.requestAnimationFrame(function(){ try { layoutActions(); } catch(e){} }); } catch(e){}
  }

  function updatePreview(){
    var $wrap = $("#sc-banner-preview");
    var theme = String($wrap.data("theme") || "auto");

    var $f = $("#sc-banner-preview-iframe");
    var baseUrl = $f.length ? String($f.data("previewBase") || "") : "";
    if($f.length && baseUrl){
      var site = String($f.data("site") || "preview");
      var cfg = readConfig();
      var enc = base64UrlEncode(cfg);
      var src = baseUrl + "?site=" + encodeURIComponent(site) + "&theme=" + encodeURIComponent(theme);
      if(enc){ src += "&config=" + enc; }
      $f.attr("src", src);
      return;
    }

    renderLocal(readConfig(), theme);
  }

  var timer = null;
  function schedule(){
    if(timer){ clearTimeout(timer); }
    timer = setTimeout(updatePreview, 80);
    try { updateUnsaved(); } catch(e){}
  }

  var initialSnapshot = null;
  function updateUnsaved(){
    var $u = $("#sc-banner-unsaved");
    if(!$u.length){ return; }
    var connected = String($("#sc-banner-preview").data("connected") || "0") === "1";
    if(initialSnapshot === null){
      initialSnapshot = JSON.stringify(readConfig() || {});
    }
    var cur = JSON.stringify(readConfig() || {});
    if(cur !== initialSnapshot){
      var dash = (SC_I18N && SC_I18N.unsavedDashboard) ? SC_I18N.unsavedDashboard : "Unsaved changes - click \"Save to dashboard\".";
      var loc = (SC_I18N && SC_I18N.unsavedLocal) ? SC_I18N.unsavedLocal : "Unsaved changes - click \"Save locally\".";
      $u.text(connected ? dash : loc);
    } else {
      $u.text("");
    }
  }

  function initPickers(){
    if(!$.fn.wpColorPicker){ return; }
    ["#primaryColor","#backgroundColor"].forEach(function(sel){
      var $el = $(sel);
      if(!$el.length || $el.data("scPicker")){ return; }
      $el.wpColorPicker({change:function(){schedule();},clear:function(){schedule();}});
      $el.data("scPicker", true);
    });
  }

  $(function(){
    initPickers();
    $(document).on("change input", "#sc-banner-config-form select, #sc-banner-config-form input", function(){ schedule(); });
    $(document).on("click", "[data-sc-preview-theme]", function(e){
      e.preventDefault();
      var t = String($(this).attr("data-sc-preview-theme") || "auto");
      $("#sc-banner-preview").data("theme", t);
      updatePreview();
    });
    updatePreview();
    updateUnsaved();
  });
})(jQuery);