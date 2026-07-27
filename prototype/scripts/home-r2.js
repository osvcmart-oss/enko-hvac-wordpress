/* =========================================================================
   ENKO — Home R2 interactions (правки Зустрічі 4, 13.06.2026).
   Ізольовано: відкат = прибрати <link home-r2.css> + <script home-r2.js>.
   Працює ПОВЕРХ enko.js / home-r1.js, нічого не ламаючи.
   ========================================================================= */
(function () {
  "use strict";
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  function parseNum(s){ return parseInt(String(s == null ? "" : s).replace(/[^\d]/g, ""), 10) || 0; }
  function fmt(n){ return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, "\u00A0"); }
  function esc(s){ return String(s == null ? "" : s).replace(/[&<>"]/g, function (c){ return ({ "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;" })[c]; }); }
  function getRate(){ try { var r = parseFloat(localStorage.getItem("enko_eur_rate")); return (r && r > 0) ? r : 45; } catch (e) { return 45; } }
  function ready(fn){ if (document.readyState !== "loading") fn(); else document.addEventListener("DOMContentLoaded", fn); }

  /* ---- placeholder messenger links (B2 / H — реальні значення TBD) ---- */
  var LINKS = {
    phone: "tel:+380777147777",
    mail:  "mailto:info@enkogroup.com.ua",
    tg:    "https://t.me/EnkoGroup",
    vb:    "viber://chat?number=%2B380777147777",
    wa:    "https://wa.me/380777147777"
  };

  /* =====================================================================
     C3 · РОБОЧІ ГОДИНИ — єдине джерело правди + тема за часом доби
     ===================================================================== */
  /* Робочі години — єдине джерело правди. Діапазон керується з адмін-кабінету
     (enko_work_start / enko_work_end, формат HH:MM). Поза діапазоном і у вихідні
     (Сб/Нд) — неробочий час. */
  function enkoWorkMinutes(){
    function p(v, def){ var m = /^(\d{1,2}):(\d{2})$/.exec(String(v || "").trim()); if (!m) return def; var h = +m[1], mi = +m[2]; return (h >= 0 && h <= 24 && mi >= 0 && mi < 60) ? h * 60 + mi : def; }
    var s, e; try { s = localStorage.getItem("enko_work_start"); e = localStorage.getItem("enko_work_end"); } catch (x) {}
    return { s: p(s, 540), e: p(e, 1080) }; // 09:00–18:00 за замовчуванням
  }
  function isWorking(){ var d = new Date(), day = d.getDay(), cur = d.getHours() * 60 + d.getMinutes(), r = enkoWorkMinutes(); return day >= 1 && day <= 5 && cur >= r.s && cur < r.e; }
  window.enkoIsWorking = isWorking;
  function applyDayTheme(){
    var on = isWorking();
    document.body.classList.toggle("enko-working", on);
    document.body.classList.toggle("enko-offhours", !on);
  }

  /* =====================================================================
     E2 · ПЕРСОНАЛЬНА ЗНИЖКА — джерело: enko_accounts_v1 (керує менеджер)
     ===================================================================== */
  function getUser(){ try { return JSON.parse(localStorage.getItem("enko_user_v1") || "null"); } catch (e) { return null; } }
  function getAccounts(){ try { return JSON.parse(localStorage.getItem("enko_accounts_v1") || "[]"); } catch (e) { return []; } }
  function findAccount(email){ if (!email) return null; return getAccounts().filter(function (x){ return (x.email || "").toLowerCase() === email.toLowerCase(); })[0] || null; }
  function currentDiscount(){
    var u = getUser(); if (!u) return 0;
    var acc = findAccount(u.email);
    var d = acc && acc.discount != null ? acc.discount : (u.discount || 0);
    d = parseInt(d, 10) || 0;
    return Math.max(0, Math.min(99, d));
  }

  function decoratePriceRow(row, fresh){
    if (!row) return;
    var uahEl = row.querySelector(".price__uah");
    var eurEl = row.querySelector(".price__eur");
    if (!uahEl) return;
    // прибрати раніше вставлені вузли
    $$(".price__old,.disc-badge", row).forEach(function (n){ n.remove(); });
    if (fresh || uahEl.dataset.base == null){
      uahEl.dataset.base = parseNum(uahEl.textContent);
      if (eurEl) eurEl.dataset.base = parseNum(eurEl.textContent);
    }
    var origUah = +uahEl.dataset.base || 0;
    var origEur = eurEl ? (+eurEl.dataset.base || 0) : 0;
    var d = currentDiscount();
    if (!d){
      uahEl.textContent = fmt(origUah) + " грн";
      if (eurEl) eurEl.textContent = fmt(origEur) + " €";
      return;
    }
    var newUah = Math.round(origUah * (1 - d / 100));
    var newEur = Math.round(origEur * (1 - d / 100));
    var oldU = document.createElement("span"); oldU.className = "price__old"; oldU.textContent = fmt(origUah) + " грн";
    row.insertBefore(oldU, uahEl);
    uahEl.textContent = fmt(newUah) + " грн";
    if (eurEl) eurEl.textContent = fmt(newEur) + " €";
    var badge = document.createElement("span"); badge.className = "disc-badge"; badge.textContent = "−" + d + "%";
    row.appendChild(badge);
  }

  /* ---- PDP price (buybox + sticky) ---- */
  function initPdpDiscount(){
    var bigRow = $("#price-uah") && $("#price-uah").closest(".price__row");
    var sbRow  = $("#sb-price-uah") && $("#sb-price-uah").closest(".price__row");
    if (!bigRow && !sbRow) return;
    function refresh(fresh){ decoratePriceRow(bigRow, fresh); decoratePriceRow(sbRow, fresh); }
    refresh(true);
    // re-apply after each version change (enko.js перезаписує ціну на оригінал)
    $$(".ver-btn").forEach(function (b){ b.addEventListener("click", function (){ setTimeout(function (){ refresh(true); }, 0); }); });
    window.addEventListener("storage", function (e){ if (e.key === "enko_accounts_v1" || e.key === "enko_user_v1") refresh(false); });
  }

  /* ---- Catalog cards ---- */
  function initCatalogDiscount(){
    var grid = document.getElementById("catalog-grid"); if (!grid) return;
    function decorate(){
      $$(".prod-card .price__row", grid).forEach(function (row){ decoratePriceRow(row, true); });
    }
    // картки рендеряться enko.js (innerHTML) — спостерігаємо за оновленнями
    var mo = new MutationObserver(function (){ decorate(); maybeNote(); });
    mo.observe(grid, { childList: true });
    function maybeNote(){
      var head = $(".catalog-head"); if (!head) return;
      var ex = document.getElementById("catalog-disc-note");
      var d = currentDiscount();
      if (d && !ex){
        var n = document.createElement("div"); n.id = "catalog-disc-note"; n.className = "catalog-disc-note";
        n.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#2FA36B" stroke-width="2" stroke-linecap="round"><path d="M20.59 13.41 13.42 20.6a2 2 0 0 1-2.83 0l-7.59-7.59a2 2 0 0 1-.59-1.41V4a2 2 0 0 1 2-2h7.59a2 2 0 0 1 1.41.59l7.59 7.59a2 2 0 0 1 0 2.83z"/><path d="M7 7h.01"/></svg>Ваша персональна знижка <b>−' + d + '%</b> застосована до всіх цін';
        head.appendChild(n);
      } else if (!d && ex){ ex.remove(); }
    }
    decorate(); maybeNote();
    window.addEventListener("storage", function (e){ if (e.key === "enko_accounts_v1" || e.key === "enko_user_v1") { decorate(); maybeNote(); } });
  }

  /* ---- Cabinet block «Ваша знижка» ---- */
  function initCabinetDiscount(){
    var app = document.getElementById("account-app"); if (!app) return;
    function inject(){
      var main = $(".account-main", app); if (!main) return;
      var ex = document.getElementById("acc-discount-block");
      var d = currentDiscount();
      if (d && !ex){
        var box = document.createElement("div");
        box.id = "acc-discount-block"; box.className = "acc-card acc-discount";
        box.innerHTML = '<span class="acc-discount__pct">−' + d + '%</span>'
          + '<div class="acc-discount__txt"><b>Ваша персональна знижка: −' + d + '%</b>'
          + '<span>Діє на всі товари в каталозі та на сторінках товарів. Знижку встановлює ваш менеджер.</span></div>';
        main.insertBefore(box, main.firstChild);
      } else if (!d && ex){ ex.remove(); }
    }
    var mo = new MutationObserver(function (){ inject(); });
    mo.observe(app, { childList: true, subtree: true });
    inject();
  }

  /* ---- статичні картки товарів поза каталогом (напр. «Схожі товари» на PDP) ---- */
  function initStaticCardsDiscount(){
    var grid = document.getElementById("catalog-grid");
    var rows = $$(".prod-card .price__row").filter(function (r){ return !grid || !grid.contains(r); });
    if (!rows.length) return;
    function refresh(fresh){ rows.forEach(function (r){ decoratePriceRow(r, fresh); }); }
    refresh(true);
    window.addEventListener("storage", function (e){ if (e.key === "enko_accounts_v1" || e.key === "enko_user_v1") refresh(false); });
  }

  /* ---- Cart page «Ваша заявка»: знижка + клікабельні картки + ліміти к-сті ---- */
  function hrefForItem(id){
    if (!id) return "catalog.html";
    if (/KAYSUN-CASUAL/i.test(id)) return "kaysun-casual.html"; // єдина реальна PDP у прототипі
    return "catalog.html"; // демо-товари без власної сторінки → каталог
  }
  var MAX_QTY = 100;
  function initCartDiscount(){
    var list = document.getElementById("cart-items"); if (!list) return;
    var sumU = document.getElementById("sum-uah"), sumE = document.getElementById("sum-eur");
    function cartTotals(){
      var c = []; try { c = JSON.parse(localStorage.getItem("enko_cart_v1") || "[]"); } catch (e) {}
      var rate = getRate(); // live EUR→грн, щоб підсумок збігався з лінійними цінами
      return c.reduce(function (a, i){ a.u += (i.eur ? Math.round(i.eur * rate) : (i.uah || 0)) * (i.qty || 0); a.e += (i.eur || 0) * (i.qty || 0); return a; }, { u: 0, e: 0 });
    }
    function rowQty(row){
      var cell = row.querySelector(".qty__n"); if (!cell) return 1;
      return parseInt(cell.tagName === "INPUT" ? cell.value : cell.textContent, 10) || 1;
    }
    function qtyStates(){
      $$(".cart-row", list).forEach(function (row){
        var n = rowQty(row);
        var dec = row.querySelector('[data-act="dec"]'), inc = row.querySelector('[data-act="inc"]');
        if (dec) dec.classList.toggle("is-disabled", n <= 1);   // −1 сірий на одиничці
        if (inc) inc.classList.toggle("is-disabled", n >= MAX_QTY); // максимум 100
      });
    }
    function convertQtyInputs(){
      $$(".cart-row", list).forEach(function (row){
        var cell = row.querySelector(".qty__n");
        if (cell && cell.tagName === "SPAN"){
          var inp = document.createElement("input");
          inp.className = "qty__n"; inp.type = "text"; inp.setAttribute("inputmode", "numeric");
          inp.setAttribute("aria-label", "Кількість"); inp.value = (parseInt(cell.textContent, 10) || 1);
          cell.replaceWith(inp);
        }
      });
    }
    function updateBadge(){
      var c = []; try { c = JSON.parse(localStorage.getItem("enko_cart_v1") || "[]"); } catch (e) {}
      var n = c.reduce(function (a, i){ return a + (i.qty || 0); }, 0);
      $$(".cart-badge").forEach(function (el){ el.textContent = n; el.classList.toggle("show", n > 0); });
    }
    function commitQty(inp){
      var row = inp.closest(".cart-row"); if (!row) return;
      var id = row.getAttribute("data-id");
      var n = Math.max(1, Math.min(MAX_QTY, parseInt(inp.value, 10) || 1));
      inp.value = n;
      var c = []; try { c = JSON.parse(localStorage.getItem("enko_cart_v1") || "[]"); } catch (e) {}
      var item = c.filter(function (x){ return x.id === id; })[0]; if (!item) return;
      item.qty = n;
      try { localStorage.setItem("enko_cart_v1", JSON.stringify(c)); } catch (e) {}
      // оновити лінійну ціну рядка (база = одинична × к-сть, за живим курсом), далі decorate перерахує знижку/підсумок
      var rate = getRate();
      var unitU = item.eur ? Math.round(item.eur * rate) : (item.uah || 0);
      var pUah = row.querySelector(".cart-row__price .price__uah"), pEur = row.querySelector(".cart-row__price .price__eur");
      if (pUah) pUah.dataset.base = unitU * n;
      if (pEur) pEur.dataset.base = (item.eur || 0) * n;
      decorate();
    }
    function decorate(){
      var d = currentDiscount();
      convertQtyInputs();
      $$(".cart-row__price", list).forEach(function (p){ decoratePriceRow(p, false); });
      qtyStates();
      updateBadge();
      var old = document.getElementById("cart-disc-lines"); if (old) old.remove();
      var totalRow = $(".cart-total");
      var lbl = totalRow && totalRow.querySelector(".lbl");
      var t = cartTotals();
      if (d && t.u > 0){
        var newU = Math.round(t.u * (1 - d / 100)), newE = Math.round(t.e * (1 - d / 100));
        if (sumU) sumU.textContent = fmt(newU) + " грн";
        if (sumE) sumE.textContent = fmt(newE) + " €";
        if (lbl) lbl.textContent = "Разом зі знижкою";
        var box = document.createElement("div"); box.id = "cart-disc-lines"; box.className = "cart-disc-lines";
        box.innerHTML = '<div class="cart-disc-row"><span>Сума без знижки</span><span class="price__old" style="margin:0">' + fmt(t.u) + ' грн</span></div>'
          + '<div class="cart-disc-row cart-disc-row--save"><span>Персональна знижка <b>−' + d + '%</b></span><span>−' + fmt(t.u - newU) + ' грн</span></div>';
        if (totalRow && totalRow.parentNode) totalRow.parentNode.insertBefore(box, totalRow);
      } else {
        if (lbl) lbl.textContent = "Орієнтовна сума";
        if (sumU) sumU.textContent = fmt(t.u) + " грн";
        if (sumE) sumE.textContent = fmt(t.e) + " €";
      }
    }
    // блокувати −1 на одиничці та +1 на максимумі (перехоплюємо ДО обробника enko.js)
    list.addEventListener("click", function (e){
      var btn = e.target.closest("[data-act]"); if (!btn) return;
      var row = btn.closest(".cart-row"); if (!row) return;
      var n = rowQty(row);
      var act = btn.getAttribute("data-act");
      if ((act === "dec" && n <= 1) || (act === "inc" && n >= MAX_QTY)){ e.stopPropagation(); e.preventDefault(); }
    }, true);
    // клік будь-де по картці (крім к-сті та видалення) → сторінка товару
    list.addEventListener("click", function (e){
      if (e.target.closest(".qty") || e.target.closest("[data-act]") || e.target.closest("a")) return;
      var row = e.target.closest(".cart-row"); if (!row) return;
      window.location.href = hrefForItem(row.getAttribute("data-id"));
    });
    // ввід кількості з клавіатури (замість багатьох кліків)
    list.addEventListener("change", function (e){ if (e.target.classList.contains("qty__n")) commitQty(e.target); });
    list.addEventListener("keydown", function (e){ if (e.target.classList.contains("qty__n") && e.key === "Enter"){ e.preventDefault(); e.target.blur(); } });
    new MutationObserver(function (){ decorate(); }).observe(list, { childList: true });
    decorate();
    window.addEventListener("storage", function (e){ if (e.key === "enko_accounts_v1" || e.key === "enko_user_v1") decorate(); });
  }

  /* =====================================================================
     G · РОЗШИРЮВАНИЙ ПЕРЕМИКАЧ МОВ (легко додати SK / EN)
     ===================================================================== */
  var LANGS = [
    { code: "UA", label: "UA" },
    { code: "RU", label: "RU" }
    // щоб додати мову — допишіть рядок, напр.: { code: "EN", label: "EN" }, { code: "SK", label: "SK" }
    // (реальний переклад — на етапі WP через плагін; тут — структурний перемикач)
  ];
  function initLangPills(){
    var saved = null; try { saved = localStorage.getItem("enko_sel_lang"); } catch (e) {}
    $$(".lang-pills").forEach(function (box){
      box.innerHTML = LANGS.map(function (l){
        var on = (saved ? l.code === saved : l.code === "UA");
        return '<button type="button" class="' + (on ? "active" : "") + '" data-lang="' + l.code + '">' + l.label + '</button>';
      }).join("");
      $$("button", box).forEach(function (b){
        b.addEventListener("click", function (){
          $$(".lang-pills button").forEach(function (x){ x.classList.toggle("active", x.getAttribute("data-lang") === b.getAttribute("data-lang")); });
          try { localStorage.setItem("enko_sel_lang", b.getAttribute("data-lang")); } catch (e) {}
        });
      });
    });
  }

  /* =====================================================================
     B1 · КОЛЬОРОВІ БРЕНД-ІКОНКИ + B2 · клікабельні лінки
     ===================================================================== */
  var GLYPH = {
    tg: '<svg viewBox="0 0 24 24" fill="#fff"><path d="M9.04 15.47 8.7 19.9c.4 0 .58-.17.79-.38l1.86-1.79 3.88 2.85c.71.39 1.22.19 1.41-.66l2.55-11.95c.24-1.07-.39-1.5-1.08-1.24L4.6 10.6c-1.04.4-1.03.99-.18 1.25l3.78 1.18 8.78-5.53c.41-.27.79-.12.48.16z"/></svg>',
    vb: '<svg viewBox="0 0 24 24" fill="#fff"><path d="M12 2C7 2 3 5.6 3 10c0 1.9.8 3.7 2.1 5.1-.1 1.2-.5 2.4-1.1 3.2-.2.3.1.7.5.6 1.5-.3 2.8-.9 3.6-1.5 1 .3 2.2.5 3.4.5 5 0 9-3.6 9-8s-4-8-9-8zm3.6 11.3c-.3.8-1.6 1.5-2.5 1.3-2.3-.5-4.4-2.6-4.9-4.9-.2-.9.5-2.2 1.3-2.5.3-.1.7 0 .9.3l.7 1.2c.1.2.1.5-.1.7l-.41.41c.4.9 1.1 1.6 2 2l.41-.41c.2-.2.5-.2.7-.1l1.2.7c.3.2.4.6.3.9z"/></svg>',
    wa: '<svg viewBox="0 0 24 24" fill="#fff"><path d="M12 2a10 10 0 0 0-8.5 15.2L2 22l4.9-1.5A10 10 0 1 0 12 2zm0 18a8 8 0 0 1-4.1-1.1l-.3-.2-2.9.9.9-2.8-.2-.3A8 8 0 1 1 12 20zm4.4-5.5c-.2-.1-1.4-.7-1.6-.8-.2-.1-.4-.1-.5.1l-.7.9c-.1.2-.3.2-.5.1a6.5 6.5 0 0 1-3.2-2.8c-.1-.2 0-.4.1-.5l.4-.5c.1-.2.1-.3.2-.5 0-.2 0-.3-.1-.4l-.7-1.7c-.2-.4-.4-.4-.5-.4h-.5c-.2 0-.4.1-.6.3a2.7 2.7 0 0 0-.9 2c0 1.2.9 2.4 1 2.6.1.2 1.8 2.7 4.3 3.8 1.6.7 2.2.7 3 .6.5-.1 1.4-.6 1.6-1.1.2-.6.2-1 .1-1.1-.1-.1-.2-.2-.4-.3z"/></svg>'
  };
  function detectKind(el){
    var t = ((el.getAttribute("aria-label") || "") + " " + (el.getAttribute("title") || "") + " " + el.textContent).toLowerCase();
    if (t.indexOf("telegram") > -1) return "tg";
    if (t.indexOf("viber") > -1) return "vb";
    if (t.indexOf("whatsapp") > -1) return "wa";
    return null;
  }
  function initBrandIcons(){
    // top-bar: кольоровий фон + білий гліф
    $$(".topbar-msgr a").forEach(function (a){
      var k = detectKind(a); if (!k) return;
      a.classList.add("msgr--" + k);
      a.innerHTML = GLYPH[k];
      a.href = LINKS[k]; a.target = "_blank"; a.rel = "noopener";
    });
    // footer contacts: кольоровий чіп + текст
    $$(".footer-contacts a").forEach(function (a){
      var k = detectKind(a); if (!k) return;
      var label = a.textContent.trim();
      a.innerHTML = '<span class="msgr-chip msgr--' + k + '">' + GLYPH[k] + '</span>' + label;
      a.href = LINKS[k]; a.target = "_blank"; a.rel = "noopener";
    });
    // PDP channels + quick-pop tg / account manager
    $$(".buybox__channels a, .quick-pop__btns a, .mgr-contacts a").forEach(function (a){
      var k = detectKind(a); if (k && (a.getAttribute("href") === "#" || !a.getAttribute("href"))) { a.href = LINKS[k]; a.target = "_blank"; a.rel = "noopener"; }
    });
  }

  /* =====================================================================
     A6 · ГРАФІК РОБОТИ — першим у блоці контактів і у футері
     ===================================================================== */
  function initScheduleFirst(){
    // футер: пересунути рядок-графік (без <a>, зі <span>) на початок списку
    $$(".footer-contacts").forEach(function (ul){
      var sched = $$("li", ul).filter(function (li){ return !li.querySelector("a") && li.querySelector("span"); })[0];
      if (sched){ sched.classList.add("is-schedule"); if (ul.firstChild !== sched) ul.insertBefore(sched, ul.firstChild); }
    });
    // contacts.html: у aside «Наші контакти» підняти «Графік» нагору
    $$(".about-facts dl").forEach(function (dl){
      var facts = $$(".fact", dl);
      var sched = facts.filter(function (f){ var dt = f.querySelector("dt"); return dt && /графік/i.test(dt.textContent); })[0];
      if (sched && dl.firstChild !== sched) dl.insertBefore(sched, dl.firstChild);
    });
  }

  /* =====================================================================
     B3 · кнопка «Замовити консультацію» у блоці «Контакти»
     ===================================================================== */
  function initContactsConsult(){
    var aside = $$(".about-facts").filter(function (a){ var h = a.querySelector("h3"); return h && /наші контакти/i.test(h.textContent); })[0];
    if (!aside || aside.querySelector(".contacts-consult-btn")) return;
    var btn = document.createElement("button");
    btn.type = "button";
    btn.className = "btn btn--primary btn--block contacts-consult-btn";
    btn.textContent = "Замовити консультацію";
    btn.addEventListener("click", function (){ if (window.enkoOpenModal) window.enkoOpenModal(""); });
    aside.appendChild(btn);
  }

  /* =====================================================================
     C6 · кліки телефонних іконок ураховують робочі години
     (неробочий час → відкриваємо заявку «передзвонимо», а не дзвінок)
     ===================================================================== */
  function initPhoneHours(){
    $$('.topbar__phone, .footer-contacts a[href^="tel:"], .buybox__channels a[href^="tel:"]').forEach(function (a){
      a.title = isWorking() ? "Дзвоніть — ми на зв'язку (Пн–Пт 9:00–18:00)" : "Зараз неробочий час — залиште заявку, передзвонимо";
      a.addEventListener("click", function (e){
        if (!isWorking() && window.enkoOpenModal){ e.preventDefault(); window.enkoOpenModal(""); }
      });
    });
  }

  /* =====================================================================
     C5 · колбек-бар: підняти launcher, коли бар видно (без накладань)
     ===================================================================== */
  function initCallbarOffset(){
    function sync(){ var bar = document.getElementById("callbar"); document.body.classList.toggle("callbar-on", !!(bar && bar.classList.contains("show"))); }
    var bodyMo = new MutationObserver(function (){
      var bar = document.getElementById("callbar");
      if (bar && !bar.__r2watched){ bar.__r2watched = 1; new MutationObserver(sync).observe(bar, { attributes: true, attributeFilter: ["class"] }); sync(); }
    });
    bodyMo.observe(document.body, { childList: true });
    sync();
  }

  /* =====================================================================
     C4 · точка інтеграції Telegram-бота (прототип)
     Реальна відправка — на етапі WP (бекенд → Telegram Bot API, односторонньо).
     ===================================================================== */
  window.ENKO_TG = {
    endpoint: "", // TODO(WP): URL бекенд-обробника, що шле заявку в Telegram-бот
    send: function (payload){
      // прототип: лог + (за наявності endpoint) fetch. Реальна логіка — у WP.
      try { console.info("[ENKO] заявка → Telegram-бот (прототип):", payload); } catch (e) {}
      return Promise.resolve({ ok: true, prototype: true });
    }
  };

  /* Сповіщення про заявку — точки інтеграції (реальна відправка = бекенд WP).
     Прототип лише логує; на WP кожен метод стане PHP-обробником (Telegram Bot API + mail()). */
  window.ENKO_NOTIFY = {
    config: { managerEmail: "info@enkogroup.com.ua", managerTelegram: "@EnkoGroup", botEndpoint: "" }, // TODO(WP)
    managerTelegram: function (p){ if (window.ENKO_TG) window.ENKO_TG.send(p); try { console.info("[ENKO] Telegram менеджеру → " + this.config.managerTelegram, p); } catch (e) {} },
    managerEmail: function (p){ try { console.info("[ENKO] email менеджеру → " + this.config.managerEmail, p); } catch (e) {} },
    clientEmail: function (p){ try { console.info("[ENKO] лист-підтвердження клієнту → " + (p.clientEmail || "—"), p); } catch (e) {} }
  };

  function enkoToast(msg, isError){
    var t = document.getElementById("toast");
    if (!t){ try { alert(msg); } catch (e) {} return; }
    var m = t.querySelector(".toast__msg"); if (m) m.textContent = msg;
    t.classList.toggle("toast--error", !!isError);
    t.classList.add("show");
    clearTimeout(t.__r2); t.__r2 = setTimeout(function (){ t.classList.remove("show"); t.classList.remove("toast--error"); }, 4200);
  }

  /* Сабміт заявки з кошика: enko.js зберігає заявку в кабінет (історія) і показує успіх;
     тут додаємо сповіщення менеджеру (Telegram+email), лист клієнту і збагачуємо текст подяки. */
  function initCartSubmit(){
    var form = document.getElementById("cart-form"); if (!form) return;
    // якщо email некоректний — спливаюче сповіщення (без форми), сабміт не виконуємо
    var submitBtn = form.querySelector('button[type="submit"]');
    var emailEl = document.getElementById("c-email");
    if (submitBtn && emailEl){
      submitBtn.addEventListener("click", function (e){
        if (emailEl.value.trim() && !emailEl.checkValidity()){
          e.preventDefault(); e.stopPropagation();
          enkoToast("Ваш email має некоректний формат — змініть його в кабінеті або введіть коректну адресу.", true);
        }
      });
    }
    form.addEventListener("submit", function (){
      setTimeout(function (){
        var ok = document.getElementById("cart-ok");
        if (!ok || getComputedStyle(ok).display === "none") return; // enko-валідація не пройшла — нічого не шлемо
        var orders = []; try { orders = JSON.parse(localStorage.getItem("enko_orders_v1") || "[]"); } catch (e) {}
        var user = getUser();
        var email = (document.getElementById("c-email") || {}).value || (user && user.email) || "";
        var payload = { order: orders[0] || null, user: user || null, clientEmail: email, at: new Date().toISOString() };
        window.ENKO_NOTIFY.managerTelegram(payload);
        window.ENKO_NOTIFY.managerEmail(payload);
        window.ENKO_NOTIFY.clientEmail(payload);
        var p = ok.querySelector("p");
        if (p){
          var extra = document.getElementById("cart-ok-extra");
          if (!extra){ extra = document.createElement("p"); extra.id = "cart-ok-extra"; extra.className = "cart-ok-extra"; p.insertAdjacentElement("afterend", extra); }
          extra.innerHTML = 'Підтвердження надіслано' + (email ? ' на <b>' + esc(email) + '</b>' : '') + '. Заявку збережено у вашому кабінеті (Історія заявок), а менеджер отримав сповіщення в Telegram і на email.';
        }
      }, 0);
    });
  }

  /* =====================================================================
     A1 / A4 · HERO-СЛАЙДЕР: 6 реальних фото категорій, без стрілок,
     безперервне авто-гортання кожні 10 c (тільки index)
     ===================================================================== */
  var HERO_SLIDES = [
    { img: "assets/catalog/conditioners.webp", alt: "Кондиціонери", cls: "hero__slide--cond" },
    { img: "assets/catalog/vrf.webp",          alt: "Мультизональні VRF", cls: "hero__slide--vrf" },
    { img: "assets/catalog/heat-pumps.webp",   alt: "Теплові насоси", cls: "hero__slide--hp" },
    { img: "assets/catalog/ventilation.webp",  alt: "Вентиляція", cls: "hero__slide--vent" },
    { img: "assets/catalog/microclimate.webp", alt: "Мікроклімат", cls: "hero__slide--micro" },
    { img: "assets/catalog/fancoils.webp",     alt: "Фанкойли", cls: "hero__slide--fan" }
  ];
  function initHeroSlider(){
    var hero = $(".hero"); if (!hero) return;
    var bg = $(".hero__bg", hero); if (!bg) return;
    // побудувати слайди-фото в існуючому .hero__bg
    bg.innerHTML = HERO_SLIDES.map(function (s, i){
      var cls = "hero__slide hero__slide--photo" + (s.cls ? " " + s.cls : "") + (i === 0 ? " is-active" : "");
      var style = s.cls ? "" : ' style="background-image:url(\'' + s.img + '\')"';
      return '<div class="' + cls + '" role="img" aria-label="' + s.alt + '"' + style + '></div>';
    }).join("");
    var slides = $$(".hero__slide", bg);
    // крапки-індикатори (без стрілок prev/next)
    var dots = document.createElement("div"); dots.className = "hero__dots";
    dots.innerHTML = slides.map(function (_, i){ return '<button type="button" class="' + (i === 0 ? "is-active" : "") + '" aria-label="Слайд ' + (i + 1) + '"></button>'; }).join("");
    hero.appendChild(dots);
    var dotBtns = $$("button", dots);
    var idx = 0, timer = null;
    function go(n){
      idx = (n + slides.length) % slides.length;
      slides.forEach(function (s, i){ s.classList.toggle("is-active", i === idx); });
      dotBtns.forEach(function (d, i){ d.classList.toggle("is-active", i === idx); });
    }
    function start(){ stop(); timer = setInterval(function (){ go(idx + 1); }, 10000); }
    function stop(){ if (timer) clearInterval(timer); }
    // крапки дозволяють перемкнутись вручну, але авто-таймер не зупиняється на hover
    dotBtns.forEach(function (d, i){ d.addEventListener("click", function (){ go(i); start(); }); });
    start();
  }

  /* =====================================================================
     D8 · ПОРТФОЛІО — лайтбокс fullscreen + prev/next (about.html#refs)
     ===================================================================== */
  function initRefLightbox(){
    var cards = $$(".ref-grid .ref-card"); if (!cards.length) return;
    var items = cards.map(function (c){
      var h = c.querySelector("h3"), loc = c.querySelector(".ref-card__loc"), ph = c.querySelector(".ph span"), im = c.querySelector(".ref-img");
      return {
        title: h ? h.textContent.trim() : "Об'єкт",
        loc: loc ? loc.textContent.trim() : "",
        cap: ph ? ph.innerHTML : "Фото об'єкта",
        img: im ? im.getAttribute("src") : null
      };
    });
    var lb = document.createElement("div");
    lb.className = "reflb"; lb.setAttribute("role", "dialog"); lb.setAttribute("aria-label", "Перегляд об'єкта");
    lb.innerHTML = '<div class="reflb__overlay" data-reflb-close></div>'
      + '<button class="reflb__close" data-reflb-close aria-label="Закрити"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>'
      + '<button class="reflb__nav reflb__nav--prev" aria-label="Попереднє"><svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg></button>'
      + '<div class="reflb__stage"><div class="reflb__media"><span></span></div>'
      + '<div class="reflb__cap"><div><b></b><span class="reflb__loc"></span></div><span class="reflb__count"></span></div></div>'
      + '<button class="reflb__nav reflb__nav--next" aria-label="Наступне"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></button>';
    document.body.appendChild(lb);
    var cur = 0;
    var mediaEl = $(".reflb__media", lb), titleEl = $(".reflb__cap b", lb), locEl = $(".reflb__loc", lb), countEl = $(".reflb__count", lb);
    function render(){
      var it = items[cur];
      mediaEl.innerHTML = it.img
        ? '<img src="' + it.img + '" alt="' + it.title.replace(/"/g, "&quot;") + '">'
        : '<span>' + it.cap + '</span>';
      titleEl.textContent = it.title; locEl.textContent = it.loc;
      countEl.textContent = (cur + 1) + " / " + items.length;
    }
    function open(i){ cur = i; render(); lb.classList.add("open"); document.body.style.overflow = "hidden"; }
    function close(){ lb.classList.remove("open"); document.body.style.overflow = ""; }
    function move(d){ cur = (cur + d + items.length) % items.length; render(); }
    cards.forEach(function (c, i){
      c.setAttribute("role", "button"); c.setAttribute("tabindex", "0");
      c.addEventListener("click", function (){ open(i); });
      c.addEventListener("keydown", function (e){ if (e.key === "Enter" || e.key === " ") { e.preventDefault(); open(i); } });
    });
    $$("[data-reflb-close]", lb).forEach(function (b){ b.addEventListener("click", close); });
    $(".reflb__nav--prev", lb).addEventListener("click", function (){ move(-1); });
    $(".reflb__nav--next", lb).addEventListener("click", function (){ move(1); });
    document.addEventListener("keydown", function (e){
      if (!lb.classList.contains("open")) return;
      if (e.key === "Escape") close();
      else if (e.key === "ArrowLeft") move(-1);
      else if (e.key === "ArrowRight") move(1);
    });
  }

  /* =====================================================================
     F · КАБІНЕТ-МЕНЕДЖЕР (admin.html) — прототип на localStorage
     ===================================================================== */
  var ADMIN = { login: "admin", pass: "demo" }; // прототип; WP — у БД, змінювані
  function adminSeed(){
    // якщо немає жодного акаунта — створимо демо-користувача для перевірки
    var acc = getAccounts();
    if (!acc.length){
      acc.push({ firstName: "Олександр", lastName: "Іваненко", email: "demo@enko.ua", phone: "+380 67 000 00 00",
        entity: "fiz", coop: "Постачання обладнання (роздріб/дилер)", city: "Київ", company: "", edrpou: "",
        discount: 0, extra: [], registeredAt: new Date().toLocaleDateString("uk-UA") });
      try { localStorage.setItem("enko_accounts_v1", JSON.stringify(acc)); } catch (e) {}
    }
  }
  function adminSaveAccounts(list){ try { localStorage.setItem("enko_accounts_v1", JSON.stringify(list)); } catch (e) {} }
  function initAdmin(){
    var page = document.getElementById("admin-page"); if (!page) return;
    var loginEl = document.getElementById("admin-login");
    var appEl = document.getElementById("admin-app");
    function loggedIn(){ try { return sessionStorage.getItem("enko_admin_session") === "1"; } catch (e) { return false; } }
    function show(which){
      if (loginEl) loginEl.style.display = which === "login" ? "block" : "none";
      if (appEl) appEl.style.display = which === "app" ? "block" : "none";
    }
    // login
    var form = document.getElementById("admin-login-form");
    if (form) form.addEventListener("submit", function (e){
      e.preventDefault();
      var l = $("#adm-login").value.trim(), p = $("#adm-pass").value;
      var err = $("#adm-err");
      if (l === ADMIN.login && p === ADMIN.pass){
        try { sessionStorage.setItem("enko_admin_session", "1"); } catch (er) {}
        if (err) err.classList.remove("show");
        renderApp(); show("app");
      } else { if (err) err.classList.add("show"); }
    });
    document.addEventListener("click", function (e){
      if (e.target.closest("[data-admin-logout]")){ try { sessionStorage.removeItem("enko_admin_session"); } catch (er) {} show("login"); }
    });

    var selectedEmail = null;
    function esc(s){ return String(s == null ? "" : s).replace(/[&<>"]/g, function (c){ return ({ "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;" })[c]; }); }
    function initials(u){ var a = (u.firstName||"").trim(), b = (u.lastName||"").trim(); return ((a[0]||"")+(b[0]||"")).toUpperCase() || (u.email||"E")[0].toUpperCase(); }

    function renderApp(){
      adminSeed();
      renderList();
      renderDetail();
      wireRate();
    }
    function wireRate(){
      var input = document.getElementById("rate-input");
      var rform = document.getElementById("admin-rate-form");
      if (!input || !rform || rform.dataset.wired) return;
      rform.dataset.wired = "1";
      var RATE_KEY = "enko_eur_rate", DEF = 45;
      function cur(){ var r = parseFloat(localStorage.getItem(RATE_KEY)); return (r && r > 0) ? r : DEF; }
      input.value = cur();
      rform.addEventListener("submit", function (e){
        e.preventDefault();
        var v = parseFloat(input.value);
        if (!(v > 0)){ input.focus(); return; }
        v = Math.round(v * 100) / 100;
        try { localStorage.setItem(RATE_KEY, String(v)); } catch (er) {}
        input.value = v;
        var saved = document.getElementById("rate-saved");
        if (saved){ saved.classList.add("show"); setTimeout(function (){ saved.classList.remove("show"); }, 2400); }
      });
    }
    function renderList(){
      var box = document.getElementById("admin-userlist"); if (!box) return;
      var acc = getAccounts();
      if (!acc.length){ box.innerHTML = '<div class="admin-empty">Поки немає зареєстрованих користувачів.</div>'; return; }
      box.innerHTML = acc.map(function (u){
        var name = ((u.firstName||"") + " " + (u.lastName||"")).trim() || u.email;
        var disc = (u.discount && +u.discount > 0) ? '<span class="disc-tag">−' + (+u.discount) + '%</span>' : "";
        return '<button type="button" class="admin-user' + (u.email === selectedEmail ? " active" : "") + '" data-email="' + esc(u.email) + '">'
          + '<span class="ava">' + esc(initials(u)) + '</span>'
          + '<span class="admin-user__info"><b>' + esc(name) + '</b><span>' + esc(u.email) + '</span></span>' + disc + '</button>';
      }).join("");
      $$("button.admin-user", box).forEach(function (b){
        b.addEventListener("click", function (){ selectedEmail = b.getAttribute("data-email"); renderList(); renderDetail(); });
      });
    }
    function admChatRender(){
      var box = document.getElementById("adm-chat-log"); if (!box) return;
      var acc = findAccount(selectedEmail); if (!acc){ box.innerHTML = ""; return; }
      var msgs = Array.isArray(acc.chat) ? acc.chat : [];
      var cname = ((acc.firstName||"") + " " + (acc.lastName||"")).trim() || acc.email || "Клієнт";
      box.innerHTML = msgs.length
        ? msgs.map(function (m){ var mine = m.from === "support"; return '<div class="msg ' + (mine ? "user" : "support") + '"><span class="who">' + (mine ? "Ви (менеджер)" : esc(cname)) + '</span>' + esc(m.text) + '</div>'; }).join("")
        : '<p class="sub" style="margin:0">Повідомлень ще немає. Напишіть клієнту перше повідомлення.</p>';
      box.scrollTop = box.scrollHeight;
    }
    // keep the manager chat fresh if the client writes (another tab) — wired once
    if (!window.__admChatSync){
      window.__admChatSync = 1;
      window.addEventListener("enko:chat", function (){ admChatRender(); });
      window.addEventListener("storage", function (e){ if (e.key === "enko_accounts_v1" || e.key === "enko_chat_v1" || e.key === "enko_user_v1") admChatRender(); });
    }

    function renderDetail(){
      var box = document.getElementById("admin-detail"); if (!box) return;
      var u = findAccount(selectedEmail);
      if (!u){
        box.innerHTML = '<div class="admin-detail__empty"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg><p>Оберіть користувача зі списку зліва, щоб редагувати дані та знижку.</p></div>';
        return;
      }
      var orders = [];
      try { orders = JSON.parse(localStorage.getItem("enko_orders_v1") || "[]"); } catch (e) {}
      var extra = Array.isArray(u.extra) ? u.extra : [];
      var entityLabel = u.entity === "ur" ? "Юридична особа" : "Фізична особа";
      function vRow(label, val){ var empty = !val; return '<div class="acc-view__row"><dt>' + label + '</dt><dd' + (empty ? ' class="empty"' : '') + '>' + (empty ? "—" : esc(val)) + '</dd></div>'; }
      var EDIT_SVG = '<svg viewBox="0 0 24 24" width="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>';
      var caret = '<span class="admin-sec__hint">' + esc(u.email) + '</span>';
      box.innerHTML =
        '<div class="acc-card__head" style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px;flex-wrap:wrap">'
        + '<div class="acc-card__head-l"><h2>Картка користувача</h2><span class="sub" style="margin:0">Зареєстровано: ' + esc(u.registeredAt || "—") + '</span></div>'
        + '<div class="admin-del-wrap"><button type="button" class="admin-del-btn" id="ad-del"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>Видалити</button>'
        + '<div class="admin-del-confirm" id="ad-del-confirm" hidden><p>Видалити цього користувача? Дію не можна скасувати.</p><div class="admin-del-confirm__btns"><button type="button" class="btn btn--s admin-del-yes" id="ad-del-yes">Так, видалити</button><button type="button" class="btn btn--ghost btn--s" id="ad-del-no">Скасувати</button></div></div>'
        + '</div></div>'
        + '<div class="admin-detail">'
        /* ---- ПРОФІЛЬ (перегляд + редагування) ---- */
        + '<details class="admin-sec" open data-sec="profile"><summary>Профіль<span class="admin-sec__hint">ім’я, контакти, компанія</span></summary><div class="admin-sec__body">'
        + '<div class="sec-actions"><button type="button" class="acc-edit-btn sec-edit" data-sec-edit>' + EDIT_SVG + '<span>Редагувати</span></button></div>'
        + '<div class="field-row"><div class="field"><label>Ім’я</label><input id="ad-fn" value="' + esc(u.firstName) + '" maxlength="20" readonly></div>'
        + '<div class="field"><label>Прізвище</label><input id="ad-ln" value="' + esc(u.lastName) + '" maxlength="20" readonly></div></div>'
        + '<div class="field-row"><div class="field"><label>Email (логін)</label><input class="ro-locked" value="' + esc(u.email) + '" readonly disabled title="Логін змінюється в розділі «Доступ до кабінету»"></div>'
        + '<div class="field"><label>Тип особи</label><input class="ro-locked" value="' + esc(entityLabel) + '" readonly disabled></div></div>'
        + '<div class="field-row"><div class="field"><label>Телефон</label><input id="ad-phone" value="' + esc(u.phone) + '" maxlength="14" readonly></div>'
        + '<div class="field"><label>Місто</label><input id="ad-city" value="' + esc(u.city) + '" maxlength="20" readonly></div></div>'
        + '<div class="field-row"><div class="field"><label>Тип співпраці</label><input id="ad-coop" value="' + esc(u.coop) + '" maxlength="20" readonly></div>'
        + '<div class="field"><label>Назва компанії</label><input id="ad-company" value="' + esc(u.company) + '" maxlength="20" readonly></div></div>'
        + '<div class="field-row"><div class="field"><label>ЄДРПОУ / ІПН</label><input id="ad-edrpou" value="' + esc(u.edrpou) + '" maxlength="20" inputmode="numeric" readonly></div><div class="field"></div></div>'
        + '</div></details>'
        /* ---- ДОСТУП ДО КАБІНЕТУ (перегляд + редагування) ---- */
        + '<details class="admin-sec" data-sec="access"><summary>Доступ до кабінету<span class="admin-sec__hint">логін і пароль</span></summary><div class="admin-sec__body">'
        + '<div class="sec-actions"><button type="button" class="acc-edit-btn sec-edit" data-sec-edit>' + EDIT_SVG + '<span>Редагувати</span></button></div>'
        + '<div class="field-row"><div class="field"><label>Логін (email)</label><input id="ad-email" value="' + esc(u.email) + '" type="email" maxlength="60" readonly></div>'
        + '<div class="field"><label>Пароль</label><div class="pass-field"><input id="ad-pass" type="text" value="' + esc(u.password || "") + '" placeholder="(не встановлено)" readonly><button type="button" class="pass-toggle" id="ad-pass-toggle" aria-label="Показати або сховати пароль"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div></div>'
        + '<div class="admin-pass-actions"><button type="button" class="btn btn--ghost btn--s" id="ad-pass-gen"><svg viewBox="0 0 24 24" width="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 2v6h-6M3 12a9 9 0 0 1 15-6.7L21 8M3 22v-6h6M21 12a9 9 0 0 1-15 6.7L3 16"/></svg>Згенерувати новий</button>'
        + '<button type="button" class="btn btn--ghost btn--s" id="ad-pass-reset"><svg viewBox="0 0 24 24" width="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/></svg>Скинути та надіслати на email</button></div>'
        + '<p class="sub" id="ad-pass-note" style="margin:0">Логін збігається з email. За потреби змініть логін, встановіть новий пароль або надішліть користувачу лист зі скиданням.</p>'
        + '</div></details>'
        /* ---- ЗНИЖКА ---- */
        + '<details class="admin-sec" open><summary>Індивідуальна знижка<span class="admin-sec__hint">' + (u.discount ? "−" + (+u.discount) + "%" : "немає") + '</span></summary><div class="admin-sec__body">'
        + '<div class="admin-disc-row"><div class="field"><label>Знижка, %</label><div class="disc-apply"><input id="ad-disc" type="number" min="0" max="99" step="1" value="' + (u.discount || 0) + '"><button type="button" class="btn btn--primary btn--s" id="ad-disc-apply">Застосувати</button></div><span class="admin-saved disc-saved-below" id="ad-disc-saved">Застосовано ✓</span></div>'
        + '<p class="sub" style="margin:0;max-width:360px">Вмикає показ ціни зі знижкою в каталозі та на сторінці товару для цього користувача. <br>0 — знижки немає.</p></div></details>'
        /* ---- ДОДАТКОВІ ПОЛЯ ---- */
        + '<details class="admin-sec" data-sec="extra"><summary>Додаткові поля<span class="admin-sec__hint">' + (extra.length ? extra.length + " шт." : "немає") + '</span></summary><div class="admin-sec__body">'
        + '<div class="sec-actions"><button type="button" class="acc-edit-btn sec-edit" data-sec-edit>' + EDIT_SVG + '<span>Редагувати</span></button></div>'
        + '<div class="admin-extra" id="ad-extra">' + extra.map(function (f, i){ return extraRow(f.k, f.v, i); }).join("") + '</div>'
        + '<button type="button" class="btn btn--ghost btn--s" id="ad-extra-add"><svg viewBox="0 0 24 24" width="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>Додати поле</button></div></details>'
        /* ---- ЧАТ ІЗ КЛІЄНТОМ ---- */
        + '<details class="admin-sec" open><summary>Чат із клієнтом<span class="admin-sec__hint">та сама розмова, що в кабінеті клієнта</span></summary><div class="admin-sec__body">'
        + '<div class="chat-log adm-chat-log" id="adm-chat-log"></div>'
        + '<form class="chat-input" id="adm-chat-form"><input id="adm-chat-msg" type="text" placeholder="Відповідь клієнту…" autocomplete="off"><button class="chat-send" type="submit" aria-label="Надіслати"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></svg></button></form>'
        + '</div></details>'
        /* ---- ІСТОРІЯ ЗАЯВОК ---- */
        + '<details class="admin-sec"><summary>Історія заявок<span class="admin-sec__hint">' + (orders.length ? orders.length + " шт." : "немає") + '</span></summary><div class="admin-sec__body">'
        + (orders.length ? orders.map(function (o){
            var od = o.discount || 0;
            var rows = o.items.map(function (i){
              var nm = esc(i.name + (i.ver ? " (" + i.ver + ")" : ""));
              var code = i.id ? esc(i.id) : "—";
              var price = (i.uah != null) ? fmt(i.uah) + " грн" : "—";
              var dcell = od ? "−" + od + "%" : "—";
              return '<tr><td class="oi-qty">×' + i.qty + '</td><td class="oi-name">' + nm + '</td><td class="oi-code">' + code + '</td><td class="oi-price">' + price + '</td><td class="oi-disc">' + dcell + '</td></tr>';
            }).join("");
            return '<div class="order-row"><div class="order-row__head"><div class="order-row__info"><b>Заявка ' + esc(o.id) + '</b><span class="order-row__date">' + esc(o.date) + '</span></div>'
              + '<div class="order-row__right"><span class="ostatus">' + esc(o.status) + '</span><div class="oprice">' + fmt(o.uah) + ' грн</div>'
              + (od ? '<div class="odisc">Знижка −' + od + '%</div>' : '') + '</div></div>'
              + '<table class="order-items"><thead><tr><th class="oi-qty">К-сть</th><th>Позиція</th><th>Код</th><th class="oi-price">Ціна</th><th class="oi-disc">Знижка</th></tr></thead>'
              + '<tbody>' + rows + '</tbody></table></div>';
          }).join("") : '<p class="sub" style="margin:0">Заявок поки немає.</p>')
        + '</div></details>'
        + '<div class="admin-actions"><button type="button" class="btn btn--primary btn--m" id="ad-save">Зберегти зміни</button>'
        + '<span class="admin-saved" id="ad-saved">Збережено ✓ — ціни користувача оновлено</span>'
        + '<span class="admin-err" id="ad-err"></span></div>'
        + '</div>';

      // live input filters: ЄДРПОУ/ІПН — лише цифри (до 20)
      var edrEl = document.getElementById("ad-edrpou");
      if (edrEl) edrEl.addEventListener("input", function (){ this.value = this.value.replace(/\D/g, "").slice(0, 20); });

      // per-section Edit ↔ Save: same fields toggle between read-only text and inputs (no duplicate blocks)
      $$("[data-sec-edit]", box).forEach(function (b){
        b.addEventListener("click", function (){
          var sec = b.closest(".admin-sec"); if (!sec) return;
          var editing = sec.classList.toggle("is-editing");
          var inputs = $$("input:not(.ro-locked), textarea", sec);
          inputs.forEach(function (i){ i.readOnly = !editing; });
          var lbl = b.querySelector("span"); if (lbl) lbl.textContent = editing ? "Зберегти" : "Редагувати";
          if (editing){ var f = inputs[0]; if (f){ try { f.focus(); } catch (e) {} } }
          else { saveUser(); }
        });
      });

      // manager chat
      admChatRender();
      var admForm = document.getElementById("adm-chat-form");
      if (admForm) admForm.addEventListener("submit", function (e){
        e.preventDefault();
        var inp = document.getElementById("adm-chat-msg"); var t = inp.value.trim(); if (!t) return;
        var list = getAccounts(); var idx = -1; list.forEach(function (x, k){ if ((x.email||"").toLowerCase() === (selectedEmail||"").toLowerCase()) idx = k; });
        if (idx < 0) return;
        var arr = Array.isArray(list[idx].chat) ? list[idx].chat : [];
        arr.push({ from: "support", text: t }); list[idx].chat = arr; adminSaveAccounts(list);
        var cur = getUser(); if (cur && (cur.email||"").toLowerCase() === (selectedEmail||"").toLowerCase()){ cur.chat = arr; try { localStorage.setItem("enko_user_v1", JSON.stringify(cur)); } catch (er) {} }
        inp.value = ""; admChatRender();
        try { window.dispatchEvent(new CustomEvent("enko:chat")); } catch (er) {}
      });

      function extraRow(k, v, i){
        return '<div class="admin-extra__row" data-i="' + i + '"><input class="ad-ek" placeholder="Назва поля" value="' + esc(k) + '" readonly><input class="ad-ev" placeholder="Значення" value="' + esc(v) + '" readonly>'
          + '<button type="button" class="ad-ex-del" aria-label="Видалити"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>';
      }
      var extraBox = document.getElementById("ad-extra");
      document.getElementById("ad-extra-add").addEventListener("click", function (){
        extraBox.insertAdjacentHTML("beforeend", extraRow("", "", extraBox.children.length));
        var last = extraBox.lastElementChild; if (last) $$("input", last).forEach(function (i){ i.readOnly = false; });
        wireExtraDel();
      });
      function wireExtraDel(){ $$(".ad-ex-del", extraBox).forEach(function (b){ b.onclick = function (){ b.closest(".admin-extra__row").remove(); }; }); }
      wireExtraDel();

      // --- access / password ---
      var passEl = document.getElementById("ad-pass");
      var discApply = document.getElementById("ad-disc-apply");
      if (discApply) discApply.addEventListener("click", function (){ saveUser(true); var s = document.getElementById("ad-disc-saved"); if (s){ s.classList.add("show"); setTimeout(function (){ s.classList.remove("show"); }, 2200); } });
      var passNote = document.getElementById("ad-pass-note");
      function genPass(){ var s = "abcdefghjkmnpqrstuvwxyz23456789"; var o = ""; for (var i = 0; i < 8; i++) o += s[Math.floor(Math.random() * s.length)]; return o; }
      var passToggle = document.getElementById("ad-pass-toggle");
      if (passToggle) passToggle.addEventListener("click", function (){ passEl.type = passEl.type === "password" ? "text" : "password"; });
      var passGen = document.getElementById("ad-pass-gen");
      if (passGen) passGen.addEventListener("click", function (){ passEl.type = "text"; passEl.value = genPass(); if (passNote){ passNote.textContent = "Згенеровано новий пароль. Натисніть «Зберегти зміни», щоб застосувати."; passNote.style.color = ""; } });
      var passReset = document.getElementById("ad-pass-reset");
      if (passReset) passReset.addEventListener("click", function (){
        var temp = genPass(); passEl.type = "text"; passEl.value = temp;
        // зберігаємо одразу (у бойовій версії — лист WP із посиланням-токеном)
        var list = getAccounts(); var idx = -1;
        list.forEach(function (x, k){ if ((x.email||"").toLowerCase() === (selectedEmail||"").toLowerCase()) idx = k; });
        if (idx >= 0){ list[idx] = Object.assign({}, list[idx], { password: temp }); adminSaveAccounts(list); }
        if (passNote){ passNote.innerHTML = 'Лист зі скиданням пароля надіслано на <b>' + esc(u.email) + '</b>. Тимчасовий пароль: <b>' + esc(temp) + '</b> (у бойовій версії — посилання у листі).'; passNote.style.color = "#2FA36B"; }
      });

      function saveUser(silent){
        var list = getAccounts();
        var idx = -1; list.forEach(function (x, k){ if ((x.email||"").toLowerCase() === (selectedEmail||"").toLowerCase()) idx = k; });
        if (idx < 0) return;
        var exBox = document.getElementById("ad-extra");
        var newExtra = exBox ? $$(".admin-extra__row", exBox).map(function (r){ return { k: r.querySelector(".ad-ek").value.trim(), v: r.querySelector(".ad-ev").value.trim() }; }).filter(function (f){ return f.k || f.v; }) : (Array.isArray(list[idx].extra) ? list[idx].extra : []);
        var passEl2 = document.getElementById("ad-pass");
        if (!silent){
          var vEmail = $("#ad-email").value.trim();
          var vPass = passEl2 ? passEl2.value.trim() : "";
          var errEl = document.getElementById("ad-err");
          var vShow = function (m, f){ if (errEl){ errEl.textContent = m; errEl.classList.add("show"); } var a = box.querySelector('details[data-sec="access"]'); if (a) a.open = true; if (f){ try { f.focus(); } catch (e) {} } };
          if (vEmail.indexOf("@") === -1){ vShow("Email має містити символ «@».", $("#ad-email")); return; }
          if (!vPass || vPass.length < 4){ vShow("Пароль не може бути порожнім і коротшим за 4 символи.", passEl2); return; }
          if (errEl){ errEl.textContent = ""; errEl.classList.remove("show"); }
        }
        var dscRaw = parseInt($("#ad-disc").value, 10) || 0;
        var updated = Object.assign({}, list[idx], {
          firstName: $("#ad-fn").value.trim(), lastName: $("#ad-ln").value.trim(),
          email: $("#ad-email").value.trim(), phone: $("#ad-phone").value.trim(),
          city: $("#ad-city").value.trim(), coop: $("#ad-coop").value.trim(),
          company: $("#ad-company").value.trim(), edrpou: $("#ad-edrpou").value.replace(/\D/g, "").slice(0, 20),
          discount: Math.max(0, Math.min(99, dscRaw)),
          password: passEl2 ? passEl2.value.trim() : (list[idx].password || ""),
          extra: newExtra
        });
        list[idx] = updated; adminSaveAccounts(list);
        // sync the logged-in session if it's the same person (instant price effect)
        var cur = getUser();
        if (cur && (cur.email||"").toLowerCase() === (selectedEmail||"").toLowerCase()){
          try { localStorage.setItem("enko_user_v1", JSON.stringify(Object.assign({}, cur, updated))); } catch (e) {}
        }
        selectedEmail = updated.email;
        renderList();
        var pe = box.querySelector(".ro-locked"); if (pe) pe.value = updated.email; // keep profile login display in sync
        if (!silent){ var saved = document.getElementById("ad-saved"); if (saved){ saved.classList.add("show"); setTimeout(function (){ saved.classList.remove("show"); }, 2200); } }
      }
      document.getElementById("ad-save").addEventListener("click", function (){ saveUser(false); });
      var delBtn = document.getElementById("ad-del");
      var delConfirm = document.getElementById("ad-del-confirm");
      if (delBtn) delBtn.addEventListener("click", function (e){ e.stopPropagation(); if (delConfirm) delConfirm.hidden = !delConfirm.hidden; });
      if (delConfirm) delConfirm.addEventListener("click", function (e){ e.stopPropagation(); });
      var delNo = document.getElementById("ad-del-no");
      if (delNo) delNo.addEventListener("click", function (){ if (delConfirm) delConfirm.hidden = true; });
      if (!window.__admDelOutside){ window.__admDelOutside = 1; document.addEventListener("click", function (){ var c = document.getElementById("ad-del-confirm"); if (c) c.hidden = true; }); }
      var delYes = document.getElementById("ad-del-yes");
      if (delYes) delYes.addEventListener("click", function (){
        var list = getAccounts().filter(function (x){ return (x.email||"").toLowerCase() !== (selectedEmail||"").toLowerCase(); });
        adminSaveAccounts(list); selectedEmail = null; renderList(); renderDetail();
      });
    }

    // демо-кнопка «додати користувача»
    document.addEventListener("click", function (e){
      if (e.target.closest("#admin-add-demo")){
        var list = getAccounts();
        var n = list.length + 1;
        list.push({ firstName: "Тест", lastName: "Партнер " + n, email: "partner" + n + "@enko.ua", phone: "+380 50 000 0" + n,
          entity: "ur", coop: "Дилерство та торгівля", city: "Львів", company: "ТОВ «Партнер " + n + "»", edrpou: "1234567" + n,
          discount: 0, extra: [], registeredAt: new Date().toLocaleDateString("uk-UA") });
        adminSaveAccounts(list); renderList();
      }
    });

    if (loggedIn()){ renderApp(); show("app"); } else { show("login"); }
  }

  /* =====================================================================
     ПОШУК у шапці: фокус/клік на лупу → поле розгортається праворуч до краю
     контентної смуги (перекриває корзину/«Увійти»/«Залишити заявку»), лишаючись
     на тому ж місці по вертикалі; placeholder тримає слот → сусіди не зміщуються.
     ===================================================================== */
  function initSearchExpand(){
    $$(".site-header .header-search").forEach(function (form){
      var input = form.querySelector("input");
      var btn = form.querySelector("button");
      var hin = form.closest(".header__in");
      if (!input || !hin) return;
      function expand(){
        if (form.getAttribute("data-expanded")) return;
        form.style.left = "";
        var hinRect = hin.getBoundingClientRect();
        var rect = form.getBoundingClientRect();
        var pr = parseFloat(getComputedStyle(hin).paddingRight) || 24;
        // правий край = фактичний правий край групи кнопок (корзина/Увійти/Залишити заявку),
        // щоб поле точно докривало їх; запасний варіант — контентний край контейнера
        var hr = hin.querySelector(".header__right");
        var edge = hr ? hr.getBoundingClientRect().right : (hinRect.right - pr);
        var topRel = Math.round(rect.top - hinRect.top);
        var leftRel = Math.round(rect.left - hinRect.left);
        var h = Math.round(rect.height), w0 = Math.round(rect.width);
        var openW = Math.max(Math.round(edge - rect.left), w0);
        var ph = document.createElement("span");
        ph.setAttribute("data-search-ph", "");
        ph.style.cssText = "display:block;flex:0 0 " + w0 + "px;width:" + w0 + "px;height:" + h + "px;margin-left:auto";
        form.parentNode.insertBefore(ph, form);
        form.style.position = "absolute"; form.style.transform = "none";
        form.style.top = topRel + "px"; form.style.left = leftRel + "px";
        form.style.right = "auto"; form.style.bottom = "auto";
        form.style.width = openW + "px"; form.style.height = h + "px";
        form.style.margin = "0"; form.style.maxWidth = "none";
        form.style.zIndex = "60"; form.style.background = "#fff"; form.style.boxShadow = "var(--sh-hover)";
        form.setAttribute("data-expanded", "1");
      }
      function collapse(){
        var ph = form.parentNode && form.parentNode.querySelector("[data-search-ph]");
        if (ph) ph.remove();
        ["position","transform","top","left","right","bottom","width","height","margin","maxWidth","zIndex","background","boxShadow"]
          .forEach(function (p){ form.style[p] = ""; });
        form.removeAttribute("data-expanded");
      }
      input.addEventListener("focus", function (){ requestAnimationFrame(expand); });
      input.addEventListener("blur", function (){ if (!input.value) collapse(); });
      if (btn) btn.addEventListener("click", function (e){ e.preventDefault(); input.focus(); });
      window.addEventListener("resize", function (){ if (form.getAttribute("data-expanded")) { collapse(); expand(); } });
    });
  }

  /* =====================================================================
     Фото-іконки месенджерів (кольорові кружечки) — шапка + футер, усі сторінки.
     Запускати ПІСЛЯ initBrandIcons (воно проставляє класи msgr--tg/vb/wa).
     ===================================================================== */
  function initMessengerPhotos(){
    var ICON = { tg: "assets/messengers/tg.webp", vb: "assets/messengers/viber.webp", wa: "assets/messengers/whatsapp.webp" };
    function kindFrom(el){ return el.classList.contains("msgr--tg") ? "tg" : el.classList.contains("msgr--vb") ? "vb" : el.classList.contains("msgr--wa") ? "wa" : null; }
    function run(){
      $$(".topbar-msgr a").forEach(function (a){
        var k = kindFrom(a); if (!k || a.getAttribute("data-photo")) return;
        a.setAttribute("data-photo", "1"); a.classList.add("msgr-photo");
        a.innerHTML = '<img src="' + ICON[k] + '" alt="" loading="lazy">';
      });
      $$(".footer-contacts a").forEach(function (a){
        var chip = a.querySelector(".msgr-chip"); if (!chip || a.getAttribute("data-photo")) return;
        var k = kindFrom(chip); if (!k) return;
        a.setAttribute("data-photo", "1"); a.classList.add("msgr-photo");
        var label = a.textContent.trim();
        a.innerHTML = '<img class="msgr-ico" src="' + ICON[k] + '" alt="" loading="lazy">' + label;
      });
    }
    run(); setTimeout(run, 200); setTimeout(run, 700);
  }

  /* Каталог: швидкі кнопки типу кондиціонера — одразу вмикають фільтр «Тип» */
  function initCatalogTypeButtons(){
    var wrap = document.getElementById("type-quick"); if (!wrap) return;
    var btns = $$(".type-quick__btn", wrap);
    var sub = document.getElementById("type-sub");
    var subBtns = sub ? $$(".type-sub__btn", sub) : [];
    function setBlockActive(val){
      subBtns.forEach(function (b){ b.classList.toggle("active", b.getAttribute("data-block") === val); });
      var r = document.querySelector('input[name="f-block"][value="' + val + '"]'); if (r) r.checked = true;
    }
    function syncActive(val){
      btns.forEach(function (b){ b.classList.toggle("active", b.getAttribute("data-type") === val); });
      if (sub){ sub.hidden = (val !== "multi"); if (val !== "multi") setBlockActive("all"); }
    }
    btns.forEach(function (b){
      b.addEventListener("click", function (){
        var val = b.getAttribute("data-type");
        var radio = document.querySelector('input[name="f-sub"][value="' + val + '"]');
        if (radio){ radio.checked = true; radio.dispatchEvent(new Event("change", { bubbles: true })); }
        syncActive(val);
      });
    });
    // сабфільтр мульти-спліт (Зовнішні/Внутрішні блоки) — той самий каталог, без нової сторінки
    subBtns.forEach(function (b){
      b.addEventListener("click", function (){
        setBlockActive(b.getAttribute("data-block"));
        var sr = document.querySelector('input[name="f-sub"]:checked'); if (sr) sr.dispatchEvent(new Event("change", { bubbles: true }));
      });
    });
    // тримати кнопки в синхроні, якщо тип змінили з бокової панелі/чіпів
    $$('input[name="f-sub"]').forEach(function (r){ r.addEventListener("change", function (){ if (r.checked) syncActive(r.value); }); });
    // предзастосувати тип із URL (?type=wall|console|duct|cassette|floorceil|multi)
    try {
      var t = new URLSearchParams(location.search).get("type");
      if (t){
        var radio = document.querySelector('input[name="f-sub"][value="' + t + '"]');
        if (radio){ radio.checked = true; radio.dispatchEvent(new Event("change", { bubbles: true })); syncActive(t); }
      }
    } catch (e) {}
  }

  /* Документація товару: клік по документу → прев'ю (PDF у модалці), кнопка — завантаження.
     Недоступні документи (.doc-item--off) — сірі й некликабельні. */
  function initDocPreview(){
    var items = $$(".doc-item[data-doc]"); if (!items.length) return;
    function download(url, name){ var a = document.createElement("a"); a.href = url; a.download = name || ""; document.body.appendChild(a); a.click(); a.remove(); }
    var lb = document.createElement("div");
    lb.className = "doclb"; lb.setAttribute("role", "dialog"); lb.setAttribute("aria-label", "Перегляд документа");
    lb.innerHTML = '<div class="doclb__overlay" data-doclb-close></div>'
      + '<div class="doclb__panel">'
      + '<div class="doclb__bar"><b class="doclb__title"></b>'
      + '<div class="doclb__actions"><a class="btn btn--primary btn--s doclb__dl" download>Завантажити</a>'
      + '<button class="doclb__close" data-doclb-close aria-label="Закрити"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div></div>'
      + '<iframe class="doclb__frame" title="Документ"></iframe></div>';
    document.body.appendChild(lb);
    var frame = $(".doclb__frame", lb), titleEl = $(".doclb__title", lb), dlBtn = $(".doclb__dl", lb);
    function open(url, title){ frame.src = url; titleEl.textContent = title; dlBtn.href = url; dlBtn.setAttribute("download", title + ".pdf"); lb.classList.add("open"); document.body.style.overflow = "hidden"; }
    function close(){ lb.classList.remove("open"); frame.src = "about:blank"; document.body.style.overflow = ""; }
    items.forEach(function (it){
      var url = it.getAttribute("data-doc"), title = it.getAttribute("data-title");
      it.addEventListener("click", function (e){ if (e.target.closest("[data-dl]")) return; open(url, title); });
      it.addEventListener("keydown", function (e){ if (e.key === "Enter" || e.key === " ") { e.preventDefault(); open(url, title); } });
      var dl = it.querySelector("[data-dl]");
      if (dl) dl.addEventListener("click", function (e){ e.stopPropagation(); download(url, title + ".pdf"); });
    });
    $$("[data-doclb-close]", lb).forEach(function (b){ b.addEventListener("click", close); });
    document.addEventListener("keydown", function (e){ if (e.key === "Escape" && lb.classList.contains("open")) close(); });
  }

  /* «Каталог» у шапці — це кнопка-тригер дропдауну (показ по hover). Клік по ній
     раніше нічого не робив (а на тач-екрані меню взагалі не відкривалось). Робимо
     клік робочим: веде на сторінку каталогу кондиціонерів. Підпункти й далі по hover. */
  function initNavTrigger(){
    $$(".nav-trigger").forEach(function (btn){
      btn.addEventListener("click", function (){ window.location.href = "catalog.html"; });
    });
  }

  /* Об'єкти (about.html): фільтр за типом будівлі (чіпи), мульти-вибір + скидання; текст-інтро на всю ширину */
  function initRefFilter(){
    var grid = document.querySelector(".ref-grid"); if (!grid) return;
    var cards = $$(".ref-card", grid);
    var sec = grid.closest(".section"); var head = sec && sec.querySelector(".section-head");
    if (head) head.style.maxWidth = "none";
    cards.forEach(function (c){ var s = c.querySelector(".ref-cat span"); c.__cat = s ? s.textContent.trim() : ""; });
    var cats = []; cards.forEach(function (c){ if (c.__cat && cats.indexOf(c.__cat) < 0) cats.push(c.__cat); });
    if (cats.length < 2) return;
    var bar = document.createElement("div"); bar.className = "ref-filter";
    bar.innerHTML = '<span class="ref-filter__lead">Тип об’єкта:</span>'
      + cats.map(function (c){ return '<button type="button" class="ref-filter__btn" data-cat="' + c + '">' + c + '</button>'; }).join("")
      + '<button type="button" class="ref-filter__reset" hidden>Скинути</button>';
    grid.parentNode.insertBefore(bar, grid);
    var active = []; var resetBtn = bar.querySelector(".ref-filter__reset");
    function apply(){
      cards.forEach(function (c){ c.style.display = (!active.length || active.indexOf(c.__cat) >= 0) ? "" : "none"; });
      $$(".ref-filter__btn", bar).forEach(function (b){ b.classList.toggle("active", active.indexOf(b.getAttribute("data-cat")) >= 0); });
      resetBtn.hidden = active.length === 0;
    }
    $$(".ref-filter__btn", bar).forEach(function (b){
      b.addEventListener("click", function (){ var cat = b.getAttribute("data-cat"), i = active.indexOf(cat); if (i >= 0) active.splice(i, 1); else active.push(cat); apply(); });
    });
    resetBtn.addEventListener("click", function (){ active = []; apply(); });
  }

  /* =====================================================================
     PDP · колбек/sticky-бар не має перекривати floating launcher і контент.
     Коли sticky-бар «Додати в заявку» з'являється — піднімаємо кружечки
     зв'язку вище за нього (одне над одним) і додаємо нижній відступ сторінці.
     ===================================================================== */
  /* Адмін-кабінет: керування попапами/барами сайту.
     • кнопки відкривають публічну сторінку з потрібним станом (?poptest=);
     • поле «через N с» зберігає затримку авто-появи бару в localStorage
       (читається home-r1.js при завантаженні сторінок) — без правок коду.
     Панель живе у #admin-app → доступна лише після авторизації адміністратора. */
  function initAdminTests(){
    var host = document.getElementById("admin-tests-rows");
    if (!host) return;

    /* згортання панелі — за замовчуванням згорнута (лише заголовок) */
    var card = document.getElementById("admin-tests");
    var toggle = document.getElementById("admin-tests-toggle");
    if (toggle && card){
      toggle.addEventListener("click", function (){
        var open = card.classList.toggle("is-open");
        toggle.setAttribute("aria-expanded", open ? "true" : "false");
      });
    }

    /* ── Робочі години (Пн–Пт), керують станом «робочий/неробочий» по всьому сайту ── */
    var hoursHost = document.getElementById("admin-tests-hours");
    function pad2(n){ return ("0" + n).slice(-2); }
    function whVal(key, def){ var v = localStorage.getItem(key); return /^\d{1,2}:\d{2}$/.test(v || "") ? v : def; }
    function hoursNote(){
      var s = whVal("enko_work_start", "09:00"), e = whVal("enko_work_end", "18:00");
      return "Робочий час: <b>Пн–Пт, " + s + "–" + e + "</b>. Поза цим діапазоном і у вихідні (Сб, Нд) — неробочий час: показуються «неробочі» версії попапів, а кнопка автодзвінка ховається. Враховується локальний час пристрою відвідувача.";
    }
    if (hoursHost){
      hoursHost.innerHTML =
        '<div class="ath-fields">'
        + '<label class="ath-field"><span>Початок</span><input type="time" id="wh-start" value="' + whVal("enko_work_start", "09:00") + '"></label>'
        + '<label class="ath-field"><span>Кінець</span><input type="time" id="wh-end" value="' + whVal("enko_work_end", "18:00") + '"></label>'
        + '<i class="admin-test-saved" id="wh-saved">збережено ✓</i>'
        + '</div><p class="ath-note" id="wh-note">' + hoursNote() + '</p>';
      hoursHost.addEventListener("change", function (e){
        var inp = e.target.closest("#wh-start,#wh-end"); if (!inp) return;
        var s = document.getElementById("wh-start").value || "09:00";
        var en = document.getElementById("wh-end").value || "18:00";
        try { localStorage.setItem("enko_work_start", s); localStorage.setItem("enko_work_end", en); } catch (x) {}
        var note = document.getElementById("wh-note"); if (note) note.innerHTML = hoursNote();
        var sv = document.getElementById("wh-saved"); if (sv){ sv.classList.add("show"); setTimeout(function (){ sv.classList.remove("show"); }, 1800); }
      });
    }

    /* ── Бари/попапи: вирівняні в колонки (кнопки одна під одною) ── */
    var defs = (window.ENKO_BARS && window.ENKO_BARS.defaults) || { lead: 30, callbar: 60, cookie: 0 };
    function curSec(k){ var v = parseFloat(localStorage.getItem("enko_delay_" + k)); return (isFinite(v) && v >= 0) ? v : defs[k]; }
    var rows = [
      { title: "Лід-форма", hint: "Спливаюча форма «залиште контакт»", delay: "lead", btns: [["lead", "Переглянути"]] },
      { title: "Колбек-бар", hint: "Нижня смуга «передзвонимо вам»", delay: "callbar", btns: [["bar", "Неробочий час"], ["bar2", "Робочий час"]] },
      { title: "Cookie-банер", hint: "Згода на використання файлів cookie", delay: "cookie", btns: [["cookie", "Переглянути"]] },
      { title: "Чат швидкого зв'язку", hint: "Відкривається по кліку на зелений кружечок", delay: null, btns: [["chat", "Неробочий час"], ["chat2", "Робочий час"]] }
    ];
    function btnCell(b){ return b ? '<button type="button" class="btn btn--ghost btn--s" data-poptest="' + b[0] + '">' + b[1] + '</button>' : ""; }
    host.innerHTML = rows.map(function (r){
      var b1 = btnCell(r.btns[0]), b2 = btnCell(r.btns[1]);
      var delay = r.delay
        ? '<label class="admin-test-delay"><span>Зʼявляється через</span><input type="number" min="0" max="600" step="1" data-delay="' + r.delay + '" value="' + curSec(r.delay) + '"><span>с' + (defs[r.delay] === 0 ? ' · 0 = одразу' : '') + '</span><i class="admin-test-saved" data-saved>збережено ✓</i></label>'
        : '<span class="admin-test-delay admin-test-delay--manual">без таймера — по кліку</span>';
      return '<div class="admin-test-row">'
        + '<div class="atr-info"><b>' + r.title + '</b><span>' + r.hint + '</span></div>'
        + '<div class="atr-b1">' + b1 + '</div>'
        + '<div class="atr-b2">' + b2 + '</div>'
        + '<div class="atr-delay">' + delay + '</div>'
        + '</div>';
    }).join("");
    host.addEventListener("click", function (e){
      var b = e.target.closest("[data-poptest]"); if (!b) return;
      window.open("index.html?poptest=" + encodeURIComponent(b.getAttribute("data-poptest")), "_blank", "noopener");
    });
    host.addEventListener("change", function (e){
      var inp = e.target.closest("[data-delay]"); if (!inp) return;
      var k = inp.getAttribute("data-delay");
      var v = Math.max(0, Math.min(600, parseInt(inp.value, 10) || 0));
      inp.value = v;
      try { localStorage.setItem("enko_delay_" + k, String(v)); } catch (_) {}
      var saved = inp.parentNode.querySelector("[data-saved]");
      if (saved){ saved.classList.add("show"); setTimeout(function (){ saved.classList.remove("show"); }, 1800); }
    });
  }

  function initStickybarOffset(){
    var bar = document.querySelector(".sticky-bar");
    if (!bar) return;
    function sync(){ document.body.classList.toggle("stickybar-on", bar.classList.contains("show")); }
    new MutationObserver(sync).observe(bar, { attributes: true, attributeFilter: ["class"] });
    sync();
  }

  /* PDP sticky-бар, рядок 2 — ЗАГАЛЬНА сума всіх позицій заявки (з урахуванням
     персональної знижки). Оновлюється при додаванні/зміні к-сті та між вкладками. */
  function initStickyCartTotal(){
    var uahEl = document.getElementById("sb-cart-uah");
    if (!uahEl) return;
    var eurEl = document.getElementById("sb-cart-eur");
    function totals(){
      var c = []; try { c = JSON.parse(localStorage.getItem("enko_cart_v1") || "[]"); } catch (e) {}
      var rate = getRate();
      return c.reduce(function (a, i){
        a.u += (i.eur ? Math.round(i.eur * rate) : (i.uah || 0)) * (i.qty || 0);
        a.e += (i.eur || 0) * (i.qty || 0);
        return a;
      }, { u: 0, e: 0 });
    }
    function update(){
      var t = totals(), d = currentDiscount();
      var u = d ? Math.round(t.u * (1 - d / 100)) : t.u;
      var e = d ? Math.round(t.e * (1 - d / 100)) : t.e;
      uahEl.textContent = fmt(u) + " грн";
      if (eurEl) eurEl.textContent = fmt(e) + " €";
    }
    update();
    var badge = document.querySelector(".cart-badge");
    if (badge) new MutationObserver(update).observe(badge, { childList: true, characterData: true, subtree: true });
    document.addEventListener("click", function (e){ if (e.target.closest("[data-add-request]")) setTimeout(update, 0); });
    window.addEventListener("storage", function (e){ if (e.key === "enko_cart_v1" || e.key === "enko_accounts_v1" || e.key === "enko_user_v1") update(); });
  }

  /* =====================================================================
     PDP · «Технічні характеристики за версіями»: на мобільному таблиця
     показується цілком, але дрібно; двома пальцями — масштаб, перетягуванням
     (одним або двома пальцями) — рух. Подвійний тап — швидкий зум.
     На десктопі поведінка незмінна (горизонтальний скрол .table-wrap).
     ===================================================================== */
  function initSpecZoom(){
    var wraps = $$(".table-wrap");
    if (!wraps.length || !window.PointerEvent) return;
    var mq = window.matchMedia("(max-width:760px)");

    wraps.forEach(function (wrap){
      var table = wrap.querySelector(".spec-table");
      if (!table) return;

      var st = { scale: 1, x: 0, y: 0, min: 1, max: 3.2, active: false };
      var pts = new Map();
      var base = null;
      var lastTap = 0;

      function tW(){ return table.offsetWidth || 640; }
      function tH(){ return table.offsetHeight || 480; }
      function capH(){ return Math.min(Math.round(window.innerHeight * 0.62), 460); }

      function apply(){ table.style.transform = "translate(" + st.x + "px," + st.y + "px) scale(" + st.scale + ")"; }
      function clamp(){
        var vw = wrap.clientWidth, vh = wrap.clientHeight;
        var sw = tW() * st.scale, sh = tH() * st.scale;
        st.x = Math.max(Math.min(0, vw - sw), Math.min(0, st.x));
        st.y = Math.max(Math.min(0, vh - sh), Math.min(0, st.y));
      }
      function fit(){
        if (!st.active) return;
        if (!table.offsetWidth) return; // панель ще прихована — переміряємо пізніше
        st.min = Math.min(1, wrap.clientWidth / tW());
        st.scale = st.min; st.x = 0; st.y = 0;
        wrap.style.height = Math.min(tH() * st.min, capH()) + "px";
        clamp(); apply();
      }
      function enable(){
        if (st.active) return;
        st.active = true;
        wrap.classList.add("spec-zoomable");
        table.style.transformOrigin = "0 0";
        if (!wrap.__hint){
          var h = document.createElement("p");
          h.className = "spec-zoom-hint";
          h.innerHTML = '<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3M11 8v6M8 11h6"/></svg>Двома пальцями — масштаб, перетягуванням — рух. Подвійний тап — зум.';
          wrap.parentNode.insertBefore(h, wrap);
          wrap.__hint = h;
        }
        wrap.__hint.style.display = "";
        requestAnimationFrame(fit);
      }
      function disable(){
        if (!st.active) return;
        st.active = false;
        wrap.classList.remove("spec-zoomable");
        wrap.style.height = "";
        table.style.transform = "";
        table.style.transformOrigin = "";
        if (wrap.__hint) wrap.__hint.style.display = "none";
      }

      function mid(a){ return { x: (a[0].x + a[1].x) / 2, y: (a[0].y + a[1].y) / 2 }; }
      function dist(a){ return Math.hypot(a[0].x - a[1].x, a[0].y - a[1].y); }
      function rel(p){ var r = wrap.getBoundingClientRect(); return { x: p.x - r.left, y: p.y - r.top }; }

      wrap.addEventListener("pointerdown", function (e){
        if (!st.active) return;
        pts.set(e.pointerId, { x: e.clientX, y: e.clientY });
        try { wrap.setPointerCapture(e.pointerId); } catch (_) {}
        var a = Array.prototype.slice.call(pts.values());
        if (pts.size === 1){ base = { single: true, x: st.x, y: st.y, px: e.clientX, py: e.clientY }; }
        else if (pts.size === 2){
          var m = rel(mid(a));
          base = { single: false, dist: dist(a), scale: st.scale, cx: (m.x - st.x) / st.scale, cy: (m.y - st.y) / st.scale };
        }
      });

      wrap.addEventListener("pointermove", function (e){
        if (!st.active || !pts.has(e.pointerId)) return;
        pts.set(e.pointerId, { x: e.clientX, y: e.clientY });
        var a = Array.prototype.slice.call(pts.values());
        if (pts.size >= 2 && base && !base.single){
          var ns = Math.max(st.min, Math.min(st.max, base.scale * (dist(a) / (base.dist || 1))));
          var m = rel(mid(a));
          st.scale = ns;
          st.x = m.x - base.cx * ns;   // зум + рух двома пальцями навколо середини
          st.y = m.y - base.cy * ns;
          clamp(); apply(); e.preventDefault();
        } else if (pts.size === 1 && base && base.single){
          st.x = base.x + (e.clientX - base.px);
          st.y = base.y + (e.clientY - base.py);
          clamp(); apply(); e.preventDefault();
        }
      });

      function lift(e){
        if (pts.has(e.pointerId)) pts.delete(e.pointerId);
        try { wrap.releasePointerCapture(e.pointerId); } catch (_) {}
        var a = Array.prototype.slice.call(pts.entries());
        if (pts.size === 1){ base = { single: true, x: st.x, y: st.y, px: a[0][1].x, py: a[0][1].y }; }
        else if (pts.size === 0){ base = null; }
      }
      wrap.addEventListener("pointerup", lift);
      wrap.addEventListener("pointercancel", lift);

      // подвійний тап — зум туди/назад
      wrap.addEventListener("pointerup", function (e){
        if (!st.active || pts.size !== 0) return;
        var now = Date.now();
        if (now - lastTap < 300){
          var m = rel({ x: e.clientX, y: e.clientY });
          var ns = st.scale > st.min + 0.05 ? st.min : Math.min(st.max, st.min * 2.2);
          st.x = m.x - ((m.x - st.x) / st.scale) * ns;
          st.y = m.y - ((m.y - st.y) / st.scale) * ns;
          st.scale = ns; clamp(); apply();
          lastTap = 0;
        } else { lastTap = now; }
      });

      // переміряти, коли вкладка «Характеристики» стає видимою
      var panel = wrap.closest(".tab-panel");
      if (panel){
        new MutationObserver(function (){ if (st.active && panel.classList.contains("active")) requestAnimationFrame(fit); })
          .observe(panel, { attributes: true, attributeFilter: ["class"] });
      }

      function sync(){ if (mq.matches) enable(); else disable(); }
      if (mq.addEventListener) mq.addEventListener("change", sync); else mq.addListener(sync);
      window.addEventListener("resize", function (){ if (st.active) fit(); });
      sync();
    });
  }

  /* ===================== INIT ===================== */
  ready(function (){
    applyDayTheme();
    initLangPills();
    initBrandIcons();
    initMessengerPhotos();
    initSearchExpand();
    initScheduleFirst();
    initContactsConsult();
    initPhoneHours();
    initCallbarOffset();
    initHeroSlider();
    initPdpDiscount();
    initCatalogDiscount();
    initStaticCardsDiscount();
    initCabinetDiscount();
    initCartDiscount();
    initRefLightbox();
    initRefFilter();
    initCatalogTypeButtons();
    initNavTrigger();
    initDocPreview();
    initAdmin();
    initCartSubmit();
    initStickybarOffset();
    initStickyCartTotal();
    initSpecZoom();
    initAdminTests();
  });
})();
