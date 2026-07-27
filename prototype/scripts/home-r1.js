/* =========================================================================
   ENKO — Home R1 interactions: lang pills, lead popup (15s), quick contact,
   consult form. Isolated; safe to remove with its <script>/<link>.
   ========================================================================= */
(function () {
  "use strict";
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  function esc(s){ return String(s == null ? "" : s).replace(/[&<>"]/g, function (c){ return ({ "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;" })[c]; }); }

  /* Затримки авто-появи попапів/барів (керуються з адмін-кабінету, у секундах).
     Менеджер міняє значення → зберігаються в localStorage → діють при наступному
     завантаженні сторінки. Редагувати код не треба. */
  var BAR_DELAY_DEFAULTS = { lead: 30, callbar: 60, cookie: 0 };
  function barDelaySec(k){
    try { var v = parseFloat(localStorage.getItem("enko_delay_" + k)); return (isFinite(v) && v >= 0) ? v : BAR_DELAY_DEFAULTS[k]; }
    catch (e) { return BAR_DELAY_DEFAULTS[k]; }
  }
  window.ENKO_BARS = { defaults: BAR_DELAY_DEFAULTS, getSec: barDelaySec };

  /* inject quick-contact widget (lead popup + green chat/call launcher + quick-pop)
     on EVERY page if not already present in the markup (index has it inline) */
  (function injectQuickContact(){
    if (document.getElementById("quick-launch")) return;
    var x = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
    var wrap = document.createElement("div");
    wrap.innerHTML =
      '<div class="pop lead-pop" id="lead-pop" role="dialog" aria-label="Підбір техніки">'
      + '<button class="pop__close" data-pop-close="lead-pop" aria-label="Закрити">' + x + '</button>'
      + '<h3>Для індивідуального підбору вже сьогодні</h3>'
      + '<p>Залиште свій контакт — підкажемо оптимальне рішення під ваш об\u2019єкт.</p>'
      + '<form id="lead-form" onsubmit="return false"><input type="tel" placeholder="+380 __ ___ __ __" aria-label="Телефон" required><button class="btn btn--primary" type="submit">Залишити контакт</button><p class="ok" id="lead-ok">Дякуємо! Ми зв\u2019яжемося з вами найближчим часом.</p></form>'
      + '</div>'
      + '<div class="pop quick-pop" id="quick-pop" role="dialog" aria-label="Швидкий зв\u2019язок">'
      + '<button class="pop__close" data-pop-close="quick-pop" aria-label="Закрити">' + x + '</button>'
      + '<div class="quick-pop__inner" id="quick-pop-inner"></div>'
      + '</div>'
      + '<div class="quick-launch" id="quick-launch">'
      + '<button class="ql-call" id="ql-call" aria-label="Передзвоніть мені"><svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg></button>'
      + '<button class="ql-chat" id="ql-chat" aria-label="Швидкий зв\u2019язок"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></button>'
      + '</div>';
    while (wrap.firstChild) document.body.appendChild(wrap.firstChild);
  })();

  /* Admin-cabinet entry button — top-bar right cluster */
  (function injectAdminBtn(){
    var right = document.querySelector(".topbar__right"); var bar = document.querySelector(".topbar__in");
    if ((!right && !bar) || document.querySelector(".admin-entry")) return;
    var a = document.createElement("a");
    a.className = "admin-entry"; a.href = "admin.html";
    a.innerHTML = '<svg viewBox="0 0 24 24" width="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/></svg>Адмін-кабінет';
    if (right) right.insertBefore(a, right.firstChild); else bar.appendChild(a);
  })();

  /* Тестові тригери попапів/барів — більше НЕ в шапці сайту.
     Запускаються лише з адмін-кабінету (admin.html, після авторизації):
     адмін-кнопка відкриває публічну сторінку з параметром ?poptest=<k>,
     і попап показується у реальному контексті. */
  window.__enkoRunTest = function (k){
    if (k === "lead") show("lead-pop");
    else if (k === "chat" && window.__enkoShowChat) window.__enkoShowChat(false);
    else if (k === "chat2" && window.__enkoShowChat) window.__enkoShowChat(true);
    else if (k === "bar" && window.__enkoShowCallbar) window.__enkoShowCallbar(false);
    else if (k === "bar2" && window.__enkoShowCallbar) window.__enkoShowCallbar(true);
    else if (k === "cookie" && window.__enkoShowCookie) window.__enkoShowCookie();
  };
  (function readPopTest(){
    var k; try { k = new URLSearchParams(location.search).get("poptest"); } catch (e) {}
    if (!k) return;
    // дати віджетам ін'єктуватись, тоді показати потрібний стан
    setTimeout(function (){ if (window.__enkoRunTest) window.__enkoRunTest(k); }, 650);
  })();

  /* mobile: move burger (three lines) to the far LEFT of the header */
  (function () {
    var hin = document.querySelector(".header__in");
    var burger = hin && hin.querySelector(".burger");
    if (hin && burger) hin.insertBefore(burger, hin.firstChild);
  })();

  /* mobile header: search circle (before cart) + language toggle (after cart) + search row */
  (function () {
    var hr = document.querySelector(".header__right"); if (!hr) return;
    var cart = hr.querySelector(".cart-link");
    var loupe = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>';
    if (!hr.querySelector(".m-search")) {
      var sb = document.createElement("button");
      sb.type = "button"; sb.className = "icon-btn m-search"; sb.setAttribute("aria-label", "Пошук"); sb.innerHTML = loupe;
      if (cart) hr.insertBefore(sb, cart); else hr.insertBefore(sb, hr.firstChild);
    }
    var header = document.querySelector(".site-header");
    var row = document.querySelector(".m-search-row");
    if (!row && header) {
      row = document.createElement("div"); row.className = "m-search-row";
      row.innerHTML = '<div class="container"><form class="header-search" role="search" onsubmit="return false"><input type="search" placeholder="Пошук по каталогу…" aria-label="Пошук"><button type="submit" aria-label="Знайти">' + loupe + '</button></form></div>';
      header.insertAdjacentElement("afterend", row);
    }
    var sBtn = hr.querySelector(".m-search");
    if (sBtn && row) sBtn.addEventListener("click", function () {
      row.classList.toggle("open");
      if (row.classList.contains("open")) { var inp = row.querySelector("input"); if (inp) setTimeout(function () { inp.focus(); }, 60); }
    });
    // language UA|RU pills inside the mobile drawer top (between logo and ×)
    var mtop = document.querySelector(".mobile-nav__top");
    if (mtop && !mtop.querySelector(".lang-pills")) {
      var lp = document.createElement("div"); lp.className = "lang-pills";
      lp.innerHTML = '<button type="button" class="active" data-lang="UA">UA</button><button type="button" data-lang="RU">RU</button>';
      var closeBtn = mtop.querySelector(".mobile-nav__close");
      if (closeBtn) mtop.insertBefore(lp, closeBtn); else mtop.appendChild(lp);
      var sv2 = null; try { sv2 = localStorage.getItem("enko_sel_lang"); } catch (e) {}
      lp.querySelectorAll("button").forEach(function (b) {
        if (sv2 && b.getAttribute("data-lang") === sv2) { lp.querySelectorAll("button").forEach(function (x){ x.classList.remove("active"); }); b.classList.add("active"); }
        b.addEventListener("click", function () {
          lp.querySelectorAll("button").forEach(function (x){ x.classList.remove("active"); }); b.classList.add("active");
          try { localStorage.setItem("enko_sel_lang", b.getAttribute("data-lang")); } catch (e) {}
        });
      });
    }
  })();

  /* mobile nav drawer: backdrop close + browser back-button close */
  /* футер: кредит розробника (між ЄДРПОУ та юр-посиланнями), на всіх сторінках */
  (function(){
    var fb = document.querySelector(".footer-bottom"); if (!fb || fb.querySelector(".dev-credit")) return;
    var span = $$("span", fb).filter(function (s){ return /ЄДРПОУ/.test(s.textContent); })[0];
    if (!span) return;
    var a = document.createElement("a");
    a.className = "dev-credit"; a.href = "https://www.linkedin.com/in/oleh-martynenko-ua"; a.target = "_blank"; a.rel = "noopener";
    a.textContent = "Developed by IT Guy.";
    span.insertAdjacentElement("afterend", a);
  })();

  var mnavEl = document.getElementById("mobile-nav");
  /* підсвітити активний розділ у мобільному меню (легка фіолетова обводка) */
  (function(){
    if (!mnavEl) return;
    var path = (location.pathname.split("/").pop() || "index.html").toLowerCase();
    if (/^(catalog|cat-)/.test(path)){ var g = mnavEl.querySelector(".mnav-group"); if (g) g.classList.add("mnav-group--active"); }
    var link = mnavEl.querySelector('a[href="' + path + '"]'); if (link) link.classList.add("mnav-active");
  })();
  function mnavClose(){ if (mnavEl){ mnavEl.classList.remove("open"); document.body.style.overflow = ""; } }
  $$("[data-mnav-close]").forEach(function (b){
    b.addEventListener("click", function (){
      if (history.state && history.state.mnav) { history.back(); } else { mnavClose(); }
    });
  });
  var burgerBtn = document.querySelector(".burger");
  if (burgerBtn) burgerBtn.addEventListener("click", function (){ try { history.pushState({ mnav: 1 }, ""); } catch (e) {} });
  window.addEventListener("popstate", function (){ if (mnavEl && mnavEl.classList.contains("open")) mnavClose(); });

  /* search field: shows loupe + 'Пошук'; on focus expands to overlay the right buttons */
  $$(".footer-contacts").forEach(function (ul) {
    if (ul.parentNode.querySelector(".footer-callback")) return;
    var btn = document.createElement("button");
    btn.className = "btn footer-callback";
    btn.textContent = "Передзвоніть мені";
    btn.type = "button";
    btn.addEventListener("click", function () { if (window.enkoOpenModal) window.enkoOpenModal(""); });
    ul.parentNode.appendChild(btn);
  });

  $$(".header-search input").forEach(function (inp) {
    var form = inp.closest(".header-search");
    inp.placeholder = "Пошук";
    inp.addEventListener("focus", function () {
      inp.placeholder = "Пошук по каталогу…";
      if (form) {
        var hin = form.closest(".header__in");
        if (hin) form.style.left = (form.getBoundingClientRect().left - hin.getBoundingClientRect().left) + "px";
        form.classList.add("is-open");
      }
    });
    inp.addEventListener("blur", function () { if (!inp.value) { inp.placeholder = "Пошук"; if (form) { form.classList.remove("is-open"); form.style.left = ""; } } });
  });

  /* lang pills (UA|RU) — persisted */
  try { var savedLang = localStorage.getItem("enko_sel_lang"); } catch (e) {}
  $$(".lang-pills button").forEach(function (b) {
    if (savedLang && b.getAttribute("data-lang") === savedLang) {
      $$(".lang-pills button").forEach(function (x){ x.classList.remove("active"); });
      b.classList.add("active");
    }
    b.addEventListener("click", function () {
      $$(".lang-pills button").forEach(function (x){ x.classList.remove("active"); });
      b.classList.add("active");
      try { localStorage.setItem("enko_sel_lang", b.getAttribute("data-lang")); } catch (e) {}
    });
  });

  /* generic popup show/hide */
  function show(id){ var p = document.getElementById(id); if (p) p.classList.add("show"); }
  function hide(id){ var p = document.getElementById(id); if (p) p.classList.remove("show"); }
  $$("[data-pop-close]").forEach(function (b) {
    b.addEventListener("click", function () { hide(b.getAttribute("data-pop-close")); });
  });

  /* lead popup after 15s. Re-show on every page RELOAD (fresh 15s countdown),
     even if previously closed; but on link-navigation (no reload) don't re-show
     if it was already shown/closed this session. */
  var ACTED = "enko_lead_seen";
  (function(){
    try {
      var nav = (performance.getEntriesByType && performance.getEntriesByType("navigation")[0]) || {};
      var navType = nav.type || (performance.navigation && performance.navigation.type === 1 ? "reload" : "navigate");
      if (navType === "reload") sessionStorage.removeItem(ACTED);
    } catch (e) {}
  })();
  function alreadyHandled(){ try { return sessionStorage.getItem(ACTED) === "1"; } catch (e) { return false; } }
  function markHandled(){ try { sessionStorage.setItem(ACTED, "1"); } catch (e) {} }
  if (!alreadyHandled()) {
    var t = setTimeout(function () {
      if (!alreadyHandled()) { show("lead-pop"); markHandled(); }
    }, barDelaySec("lead") * 1000); /* затримка керується з адмін-кабінету */
    // cancel if user opens request modal / adds to cart earlier
    document.addEventListener("click", function (e) {
      if (e.target.closest("[data-modal-open],[data-add-request]")) { clearTimeout(t); markHandled(); }
    });
  }
  var leadForm = document.getElementById("lead-form");
  if (leadForm) leadForm.addEventListener("submit", function () {
    leadForm.querySelector("input").style.display = "none";
    leadForm.querySelector(".btn").style.display = "none";
    var ok = document.getElementById("lead-ok"); if (ok) ok.style.display = "block";
    setTimeout(function () { hide("lead-pop"); }, 2200);
  });

  /* quick-contact launcher — working-hours aware (діапазон керується з адмін-кабінету) */
  function isWorking(){
    function p(v, def){ var m = /^(\d{1,2}):(\d{2})$/.exec(String(v || "").trim()); if (!m) return def; var h = +m[1], mi = +m[2]; return (h >= 0 && h <= 24 && mi >= 0 && mi < 60) ? h * 60 + mi : def; }
    var s, e; try { s = localStorage.getItem("enko_work_start"); e = localStorage.getItem("enko_work_end"); } catch (x) {}
    var d = new Date(), day = d.getDay(), cur = d.getHours() * 60 + d.getMinutes();
    return day >= 1 && day <= 5 && cur >= p(s, 540) && cur < p(e, 1080);
  }
  var TG_LINK = "#"; // placeholder — реальний лінк Telegram-бота підставимо
  function openCallback(){ if (window.enkoOpenModal) window.enkoOpenModal(""); }
  function renderQuickPop(force){
    var inner = document.getElementById("quick-pop-inner"); if (!inner) return;
    if ((typeof force === "boolean") ? force : isWorking()){
      inner.innerHTML =
        '<div class="quick-pop__head"><span class="quick-pop__ava quick-pop__ava--online"><img class="qp-ava-logo" src="assets/logo-enko-white.png" alt="ENKO"></span><span><b>Михайло</b><span>Онлайн</span></span></div>'
        + '<div class="chat-log" id="qp-log"></div>'
        + '<form class="chat-input qp-chat-input" id="qp-form"><div class="qp-input-wrap"><textarea id="qp-msg" maxlength="200" rows="1" placeholder="Ваше повідомлення" autocomplete="off"></textarea><span class="qp-counter" id="qp-counter">0 / 200</span></div><button class="chat-send" type="submit" aria-label="Відправити"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></svg></button></form>';
      var log = document.getElementById("qp-log");
      var pop = document.getElementById("quick-pop");
      var CHAT = window.ENKO_CHAT;
      function qpRender(){ if (CHAT) CHAT.render(log); }
      if (CHAT) CHAT.ensureGreeting();
      qpRender();
      var qpMsg = document.getElementById("qp-msg"), qpCounter = document.getElementById("qp-counter"), qpForm = document.getElementById("qp-form");
      function qpUpd(){ if (qpCounter) qpCounter.textContent = qpMsg.value.length + " / 200"; }
      qpMsg.addEventListener("input", qpUpd);
      qpMsg.addEventListener("focus", function(){ if (qpForm) qpForm.classList.add("is-typing"); });
      qpMsg.addEventListener("blur", function(){ if (qpForm && !qpMsg.value.trim()) qpForm.classList.remove("is-typing"); });
      qpMsg.addEventListener("keydown", function(e){ if (e.key === "Enter" && !e.shiftKey){ e.preventDefault(); if (qpForm.requestSubmit) qpForm.requestSubmit(); else qpForm.dispatchEvent(new Event("submit", { cancelable: true })); } });
      qpForm.addEventListener("submit", function(e){ e.preventDefault(); var t=qpMsg.value.trim(); if(!t || !CHAT) return; CHAT.add("user", t); qpMsg.value=""; qpUpd(); qpRender(); setTimeout(function(){ CHAT.add("support", CHAT.reply); qpRender(); }, 800); });
      // keep this popup in sync if messages change elsewhere (account-page chat / another tab)
      if (!window.__qpChatSync){
        window.__qpChatSync = 1;
        var qpSync = function (){ var l = document.getElementById("qp-log"); var p = document.getElementById("quick-pop"); if (l && p && p.classList.contains("show") && window.ENKO_CHAT) window.ENKO_CHAT.render(l); };
        window.addEventListener("enko:chat", qpSync);
        window.addEventListener("storage", function (e){ if (e.key === "enko_chat_v1" || e.key === "enko_accounts_v1") qpSync(); });
      }
    } else {
      inner.innerHTML =
        '<div class="quick-pop__head"><span class="quick-pop__ava quick-pop__ava--offline"><img class="qp-ava-logo" src="assets/logo-enko-white.png" alt="ENKO"></span><span><b>Михайло</b><span>Зараз не на зв’язку</span></span></div>'
        + '<p>Зараз неробочий час.<br>Відповідаємо: Пн–Пт 9:00–18:00</p>'
        + '<div class="quick-pop__btns"><button class="btn btn--primary btn--block" id="qp-callback" type="button">Залишити заявку</button></div>';
      var cb=document.getElementById("qp-callback"); if(cb) cb.addEventListener("click", function(e){ e.preventDefault(); hide("quick-pop"); openCallback(); });
    }
  }
  var qlChat = document.getElementById("ql-chat"), qlCall = document.getElementById("ql-call");
  window.__enkoShowChat = function (force){ renderQuickPop(force); show("quick-pop"); };
  if (qlChat) qlChat.addEventListener("click", function () { renderQuickPop(); show("quick-pop"); });
  if (qlCall) {
    // "Замовити дзвінок" має сенс лише в робочий час; поза ним ховаємо (є чат-плашка)
    if (!isWorking()) qlCall.style.display = "none";
    qlCall.addEventListener("click", function () { openCallback(); });
  }

  /* consult form */
  var consult = document.getElementById("consult-form");
  if (consult) consult.addEventListener("submit", function () {
    consult.style.display = "none";
    var ok = document.getElementById("consult-ok"); if (ok) ok.style.display = "block";
  });

  /* full-width bottom callback bar after 60s (separate from the 15s lead popup) */
  (function () {
    var KEY = "enko_callbar_seen";
    function seen(){ try { return sessionStorage.getItem(KEY) === "1"; } catch (e) { return false; } }
    function mark(){ try { sessionStorage.setItem(KEY, "1"); } catch (e) {} }
    // re-show on every page RELOAD (restart 15s countdown)
    try { var nv = (performance.getEntriesByType && performance.getEntriesByType("navigation")[0]) || {}; if ((nv.type || (performance.navigation && performance.navigation.type === 1 ? "reload" : "")) === "reload") sessionStorage.removeItem(KEY); } catch (e) {}
    var closeSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
    function build(force){
      if (document.getElementById("callbar")) return;
      var working = (typeof force === "boolean") ? force : isWorking();
      var bar = document.createElement("div"); bar.className = "callbar"; bar.id = "callbar";
      var form;
      if (working){
        form = '<div class="callbar__txt"><b>Хочете, зателефонуємо Вам?</b><span>Залиште номер — передзвонимо найближчим часом.</span></div>'
          + '<form class="callbar__form" onsubmit="return false"><input type="tel" placeholder="Ваш номер телефону" aria-label="Телефон" required><button class="btn" type="submit">Чекаю на дзвінок</button></form>';
      } else {
        var opts = ""; for (var h = 9; h <= 18; h++){ opts += "<option>" + ("0" + h).slice(-2) + ":00</option>"; }
        function ymd(d){ return d.getFullYear() + "-" + ("0"+(d.getMonth()+1)).slice(-2) + "-" + ("0"+d.getDate()).slice(-2); }
        var today = new Date(), maxD = new Date(); maxD.setDate(maxD.getDate() + 30);
        form = '<div class="callbar__txt"><b>На жаль, зараз у нас неробочий час.</b><span>Запишіться на дзвінок — зателефонуємо Вам у зручний час:</span></div>'
          + '<form class="callbar__form" onsubmit="return false"><input type="date" class="callbar__date" aria-label="Дата" min="' + ymd(today) + '" max="' + ymd(maxD) + '" value="' + ymd(today) + '"><select class="callbar__time" aria-label="Час">' + opts + '</select><input type="tel" placeholder="Ваш номер телефону" aria-label="Телефон" required><button class="btn" type="submit">Чекаю на дзвінок</button></form>';
      }
      bar.innerHTML = '<div class="container callbar__in">' + form + '<button class="callbar__close" aria-label="Закрити">' + closeSvg + '</button></div>';
      document.body.appendChild(bar);
      requestAnimationFrame(function (){ bar.classList.add("show"); });
      bar.querySelector(".callbar__close").addEventListener("click", function (){ bar.classList.remove("show"); mark(); });
      bar.querySelector(".callbar__form").addEventListener("submit", function (){
        bar.querySelector(".callbar__in").innerHTML = '<div class="callbar__txt"><b>Дякуємо! Ми зателефонуємо вам у вказаний час.</b></div>';
        mark(); setTimeout(function (){ bar.classList.remove("show"); }, 2400);
      });
    }
    window.__enkoShowCallbar = function (force){ var ex = document.getElementById("callbar"); if (ex) ex.parentNode.removeChild(ex); try { sessionStorage.removeItem(KEY); } catch (e) {} build(force); };

    /* ---- Cookie consent (показується одразу, на місці callbar) ----
       Координація таймерів: callbar зʼявляється через 60 с. Якщо користувач
       ПРИЙМАЄ/ВІДХИЛЯЄ cookie — таймер callbar перезапускається на свіжі 60 с
       від моменту рішення (щоб бар не зринав одразу після закриття cookie-банера).
       Якщо cookie-банер ігнорують — callbar усе одно зʼявиться о 60 с і перекриє його. */
    var CONSENT_KEY = "enko_cookie_consent";
    function consentDecided(){ try { return !!localStorage.getItem(CONSENT_KEY); } catch (e) { return false; } }
    function saveConsent(v){ try { localStorage.setItem(CONSENT_KEY, v); } catch (e) {} }
    var callbarTimer = null;
    function scheduleCallbar(delay){ if (callbarTimer) clearTimeout(callbarTimer); callbarTimer = setTimeout(function (){ if (!seen() && !document.getElementById("callbar")) build(); }, delay); }

    function buildCookie(force){
      if (!force && (consentDecided() || document.getElementById("cookiebar"))) return;
      if (force){ var old = document.getElementById("cookiebar"); if (old) old.parentNode.removeChild(old); }
      var bar = document.createElement("div"); bar.className = "callbar cookiebar"; bar.id = "cookiebar";
      bar.innerHTML = '<div class="container callbar__in cookiebar__in">'
        + '<div class="callbar__txt cookiebar__txt"><b>Ми використовуємо файли cookie</b>'
        + '<span>Необхідні cookies — для роботи сайту, а також аналітичні та маркетингові — для покращення сервісу й персоналізації реклами. Докладніше — у <a href="cookies.html">Політиці щодо файлів cookie</a>.</span></div>'
        + '<div class="cookiebar__btns">'
        + '<button class="btn" type="button" data-cc="all">Прийняти всі</button>'
        + '<button class="btn btn--ghost" type="button" data-cc="necessary">Відхилити необовʼязкові</button>'
        + '</div></div>';
      document.body.appendChild(bar);
      requestAnimationFrame(function (){ bar.classList.add("show"); });
      function decide(v){ saveConsent(v); bar.classList.remove("show"); setTimeout(function (){ if (bar.parentNode) bar.parentNode.removeChild(bar); }, 350); scheduleCallbar(barDelaySec("callbar") * 1000); }
      bar.querySelector('[data-cc="all"]').addEventListener("click", function (){ decide("all"); });
      bar.querySelector('[data-cc="necessary"]').addEventListener("click", function (){ decide("necessary"); });
    }

    window.__enkoShowCookie = function (){ buildCookie(true); };

    var ckMs = barDelaySec("cookie") * 1000;
    if (ckMs > 0) setTimeout(function (){ buildCookie(); }, ckMs); else buildCookie();
    scheduleCallbar(barDelaySec("callbar") * 1000); /* затримка керується з адмін-кабінету */
  })();
})();
