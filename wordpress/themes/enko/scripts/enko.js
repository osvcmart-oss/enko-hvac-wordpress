/* =========================================================================
   ENKO — shared vanilla JS (no frameworks; WordPress-portable).
   Handles: mobile nav, lang/currency switch, request modal, toast,
   tabs, version selector, lightbox, sticky bar, tweaks panel.
   Every block guards for element existence (shared across pages).
   ========================================================================= */
(function () {
  "use strict";
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  /* ---------- MOBILE NAV ---------- */
  var burger = $(".burger"), mnav = $(".mobile-nav"), mclose = $(".mobile-nav__close");
  function setMnav(open){ if(mnav){ mnav.classList.toggle("open", open); document.body.style.overflow = open ? "hidden" : ""; } }
  if (burger) burger.addEventListener("click", function () { setMnav(true); });
  if (mclose) mclose.addEventListener("click", function () { setMnav(false); });

  /* ---------- PLACEHOLDER LINKS ---------- */
  // Bare href="#" links must not jump to the top of the page in this MVP draft.
  document.addEventListener("click", function (e) {
    var a = e.target.closest("a");
    if (a && a.getAttribute("href") === "#") e.preventDefault();
  });

  /* ---------- MESSENGER LINKS (wire Telegram icons site-wide) ---------- */
  // top-bar + footer Telegram icons → офіційний канал. Один спільний хендл.
  var TG_URL = "https://t.me/EnkoGroup";
  $$('a[aria-label="Telegram"], a[title="Telegram"]').forEach(function (a){
    if (!a.getAttribute("href") || a.getAttribute("href") === "#"){ a.href = TG_URL; a.target = "_blank"; a.rel = "noopener"; }
  });
  $$(".footer-contacts a").forEach(function (a){
    if (/telegram/i.test(a.textContent) && (!a.getAttribute("href") || a.getAttribute("href") === "#")){ a.href = TG_URL; a.target = "_blank"; a.rel = "noopener"; }
  });

  /* ---------- LANG / CURRENCY DROPDOWN SELECTORS (UI placeholders, persisted) ---------- */
  // RU/EN/SK and EUR are structural placeholders in this MVP draft;
  // selection is remembered across pages via localStorage and applied to the label.
  function loadSel(key, def){ try { return localStorage.getItem(key) || def; } catch(e){ return def; } }
  $$(".dd-select").forEach(function (dd) {
    var key = "enko_sel_" + dd.getAttribute("data-dd");
    var btn = $(".dd-select__btn", dd);
    var valEl = $(".dd-select__val", dd);
    var items = $$(".dd-select__menu li", dd);
    // restore saved choice
    var saved = loadSel(key, null);
    if (saved) {
      items.forEach(function (li) {
        var on = li.getAttribute("data-val") === saved;
        li.classList.toggle("active", on);
        if (on && valEl) valEl.textContent = li.getAttribute("data-label") || li.textContent;
      });
    }
    btn.addEventListener("click", function (e) {
      e.stopPropagation();
      var wasOpen = dd.classList.contains("open");
      $$(".dd-select.open").forEach(function (x) { x.classList.remove("open"); });
      dd.classList.toggle("open", !wasOpen);
      btn.setAttribute("aria-expanded", String(!wasOpen));
    });
    items.forEach(function (li) {
      li.addEventListener("click", function () {
        items.forEach(function (x) { x.classList.remove("active"); });
        li.classList.add("active");
        if (valEl) valEl.textContent = li.getAttribute("data-label") || li.textContent;
        try { localStorage.setItem(key, li.getAttribute("data-val")); } catch (e) {}
        dd.classList.remove("open");
        btn.setAttribute("aria-expanded", "false");
      });
    });
  });
  document.addEventListener("click", function () {
    $$(".dd-select.open").forEach(function (x) { x.classList.remove("open"); $(".dd-select__btn", x).setAttribute("aria-expanded","false"); });
  });

  /* ---------- REQUEST MODAL ("Запитати спеціаліста" / "Залишити заявку") ---------- */
  var modal = $("#request-modal");
  function openModal(productName) {
    if (!modal) return;
    var tag = $("#modal-product-tag"), name = $("#modal-product-name");
    if (productName && tag && name) { name.textContent = productName; tag.style.display = "flex"; }
    else if (tag) { tag.style.display = "none"; }
    $("#modal-form").style.display = "";
    $("#modal-ok").style.display = "none";
    modal.classList.add("open");
    document.body.style.overflow = "hidden";
    var first = $("#modal-form input"); if (first) setTimeout(function(){ first.focus(); }, 60);
  }
  function closeModal() { if (modal) { modal.classList.remove("open"); document.body.style.overflow = ""; } }
  window.enkoOpenModal = openModal;

  $$("[data-modal-open]").forEach(function (b) {
    b.addEventListener("click", function () {
      var pn = b.getAttribute("data-product");
      // pull live version label for PDP
      if (pn === "__current__") pn = currentProductLabel();
      openModal(pn);
    });
  });
  if (modal) {
    $$("[data-modal-close]", modal).forEach(function (b) { b.addEventListener("click", closeModal); });
    var form = $("#modal-form");
    var fq = $("#f-q", modal); if (fq) fq.setAttribute("maxlength", "500");
    if (form) form.addEventListener("submit", function (e) {
      e.preventDefault();
      form.style.display = "none";
      $("#modal-ok").style.display = "block";
    });
  }
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      closeModal(); setMnav(false); closeLightbox();
      $$(".dd-select.open").forEach(function (x) { x.classList.remove("open"); });
    }
  });

  /* ---------- TOAST ---------- */
  var toast = $("#toast");
  function showToast(msg) {
    if (!toast) return;
    if (msg) $(".toast__msg", toast).textContent = msg;
    toast.classList.add("show");
    clearTimeout(toast._t);
    toast._t = setTimeout(function () { toast.classList.remove("show"); }, 2600);
  }

  /* ========================================================================
     CART / "ЗАЯВКА" — persistent across pages & sessions (localStorage).
     Acts like a basket: collects multiple products from the whole catalog,
     survives refresh / return after days. No checkout — leads to a request.
     ======================================================================== */
  var CART_KEY = "enko_cart_v1";
  var EUR_RATE_KEY = "enko_eur_rate", EUR_RATE_DEFAULT = 45;
  function getRate(){ try { var r = parseFloat(localStorage.getItem(EUR_RATE_KEY)); return (r && r > 0) ? r : EUR_RATE_DEFAULT; } catch (e) { return EUR_RATE_DEFAULT; } }
  function parseNum(s){ return parseInt(String(s == null ? "" : s).replace(/[^\d]/g, ""), 10) || 0; }
  function uahFromEur(eur){ return Math.round(parseNum(eur) * getRate()); }
  function fmt(n){ return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, "\u00A0"); }
  function T(s){ return (window.ENKO_I18N && window.ENKO_I18N[s]) || s; }
  function plural(n, one, few, many){
    var m10 = n % 10, m100 = n % 100;
    if (m10 === 1 && m100 !== 11) return one;
    if (m10 >= 2 && m10 <= 4 && (m100 < 10 || m100 >= 20)) return few;
    return many;
  }
  function getCart(){
    try {
      var c = JSON.parse(localStorage.getItem(CART_KEY) || "[]");
      // normalise legacy / stale photo paths so a removed asset never 404s
      c.forEach(function (i){ if (i && i.photo && /kaysun-prodigy-indoor\.png/.test(i.photo)) i.photo = "/wp-content/uploads/enko/products-kaysun-casual-indoor.webp"; });
      return c;
    } catch (e) { return []; }
  }
  function saveCart(c){ try { localStorage.setItem(CART_KEY, JSON.stringify(c)); } catch (e) {} updateCartUI(); }
  function cartCount(){ return getCart().reduce(function (n, i){ return n + i.qty; }, 0); }
  function addToCart(item){
    var c = getCart();
    var ex = c.filter(function (x){ return x.id === item.id; })[0];
    if (ex) { ex.qty += 1; } else { item.qty = 1; c.push(item); }
    saveCart(c);
  }
  function pdpCurrentItem(){
    var d = VERSIONS[currentVer];
    return {
      id: PDP_SKU + currentVer,
      name: PDP_NAME,
      ver: currentVer + " · " + d.model,
      spec: d.cool + " кВт · R-32 · " + PDP_ENERGY,
      uah: uahFromEur(d.eur), eur: parseNum(d.eur),
      img: "внутр. блок",
      photo: (function (){ var g = document.getElementById("gallery-main-img"); return g ? g.getAttribute("src") : ""; })()
    };
  }
  function itemFromBtn(b){
    return {
      id: b.getAttribute("data-id") || b.getAttribute("data-name"),
      name: b.getAttribute("data-name") || "Товар",
      ver: b.getAttribute("data-ver") || "",
      spec: b.getAttribute("data-spec") || "",
      uah: parseNum(b.getAttribute("data-uah")), eur: parseNum(b.getAttribute("data-eur")),
      img: b.getAttribute("data-img") || "товар",
      photo: b.getAttribute("data-photo") || ""
    };
  }

  /* ADD buttons (PDP buybox/sticky + every catalog card) — delegated for dynamic cards */
  document.addEventListener("click", function (e) {
    var b = e.target.closest("[data-add-request]"); if (!b) return;
    var item = b.getAttribute("data-product") === "__current__" ? pdpCurrentItem() : itemFromBtn(b);
    addToCart(item);
    showToast(T("Додано в заявку: ") + item.name);
  });

  /* PDP buybox: per-version (per-SKU) qty badge on the "Додати в заявку" button.
     Shows how many of the CURRENTLY selected version are already in the request;
     switching version swaps the count. Header bag badge keeps the grand total. */
  function skuQtyForVer(v){
    if (typeof PDP_SKU === "undefined") return 0;
    var id = PDP_SKU + v;
    var it = getCart().filter(function (x){ return x.id === id; })[0];
    return it ? it.qty : 0;
  }
  /* per-SKU qty badge shown on an "Додати в заявку" button (PDP buybox + catalog cards). */
  var INCART_BAG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>';
  function setBtnQty(b, q){
    var c = b.querySelector(".btn-incart");
    if (!c){
      c = document.createElement("span");
      c.className = "btn-incart";
      c.setAttribute("aria-hidden", "true");
      c.innerHTML = INCART_BAG + '<span class="btn-incart__n"></span>';
      b.appendChild(c);
    }
    var nEl = c.querySelector(".btn-incart__n");
    var prev = nEl.textContent;
    nEl.textContent = q;
    c.hidden = q < 1;
    var lab = b.querySelector(".btn-label"); if (lab) lab.textContent = q > 0 ? T("Додано в заявку") : T("Додати в заявку");
    if (q > 0 && String(q) !== prev){
      c.classList.remove("is-bump"); void c.offsetWidth; c.classList.add("is-bump");
    }
  }
  function refreshBuyboxQty(){
    var q = skuQtyForVer(typeof currentVer === "undefined" ? "" : currentVer);
    $$('[data-add-request][data-product="__current__"]').forEach(function (b){ setBtnQty(b, q); });
  }
  /* catalog/related cards: each card's button is a distinct SKU keyed by data-id */
  function refreshCardQty(){
    var cart = getCart();
    $$(".card-add[data-add-request]").forEach(function (b){
      var id = b.getAttribute("data-id") || b.getAttribute("data-name");
      var it = cart.filter(function (x){ return x.id === id; })[0];
      setBtnQty(b, it ? it.qty : 0);
    });
  }

  /* Badge(s) in header / mobile nav */
  function updateCartUI(){
    var n = cartCount();
    $$(".cart-badge").forEach(function (el){ el.textContent = n; el.classList.toggle("show", n > 0); });
    refreshBuyboxQty();
    refreshCardQty();
    renderCart();
  }

  /* ---------- CART PAGE (cart.html) ---------- */
  function rowHTML(i){
    var unitU = i.eur ? uahFromEur(i.eur) : i.uah; // live UAH from current rate when EUR is known
    var lineU = fmt(unitU * i.qty), lineE = fmt(i.eur * i.qty);
    var _nm = T(i.name), _ver = i.ver;
    if (!_ver) { var _mi = _nm.indexOf(" AKAY"); if (_mi > 0) { var _model = _nm.slice(_mi + 1); _nm = _nm.slice(0, _mi); var _vn = (String(i.id).match(/-(\d+)$/) || [])[1] || ""; _ver = _vn ? (_vn + " · " + _model) : _model; } }
    var _lnk = (window.ENKO_CART_LINKS && window.ENKO_CART_LINKS[i.id]) || i.link || "";
    var _nameHTML = _lnk ? '<a class="cart-row__namelink" href="' + esc(_lnk) + '">' + esc(_nm) + '</a>' : esc(_nm);
    var _mediaInner = (i.photo ? '<img class="prod-photo" src="' + esc(i.photo) + '" alt="' + esc(_nm) + '">' : '<span>' + esc(i.img) + '</span>');
    return '<div class="cart-row" data-id="' + esc(i.id) + '">'
      + '<div class="cart-row__media"><div class="ph">' + (_lnk ? '<a href="' + esc(_lnk) + '">' + _mediaInner + '</a>' : _mediaInner) + '</div></div>'
      + '<div class="cart-row__info"><b>' + _nameHTML + '</b>'
        + (_ver ? '<span class="cart-row__ver">' + T("Версія:") + ' ' + esc(_ver) + '</span>' : '')
        + (i.spec ? '<span class="cart-row__spec">' + esc(i.spec) + '</span>' : '')
        + '<button class="cart-row__rm-text" data-act="remove">' + T("Видалити") + '</button></div>'
      + '<div class="qty" role="group" aria-label="' + T("Кількість") + '">'
        + '<button data-act="dec" aria-label="' + T("Зменшити") + '">\u2212</button>'
        + '<span class="qty__n">' + i.qty + '</span>'
        + '<button data-act="inc" aria-label="' + T("Збільшити") + '">+</button></div>'
      + '<div class="cart-row__price"><span class="price__main price__uah">' + lineU + ' грн</span>'
        + '<span class="price__eur">' + lineE + ' €</span></div>'
      + '<button class="cart-row__rm" data-act="remove" aria-label="' + T("Видалити товар") + '">'
        + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>'
      + '</div>';
  }
  function renderCart(){
    var list = $("#cart-items"); if (!list) return;
    var c = getCart();
    var empty = $("#cart-empty"), grid = $("#cart-grid");
    if (!c.length) { if (empty) empty.style.display = ""; if (grid) grid.style.display = "none"; return; }
    if (empty) empty.style.display = "none"; if (grid) grid.style.display = "";
    list.innerHTML = c.map(rowHTML).join("");
    var tu = c.reduce(function (s, i){ return s + (i.eur ? uahFromEur(i.eur) : i.uah) * i.qty; }, 0);
    var te = c.reduce(function (s, i){ return s + i.eur * i.qty; }, 0);
    var qn = c.reduce(function (n, i){ return n + i.qty; }, 0);
    setText("#sum-uah", fmt(tu) + " грн");
    setText("#sum-eur", fmt(te) + " €");
    setText("#sum-count", qn + " " + plural(qn, T("товар"), T("товари"), T("товарів")));
    setText("#cart-count-label", qn + " " + plural(qn, T("товар"), T("товари"), T("товарів")));
  }
  var cartItemsEl = document.getElementById("cart-items");
  if (cartItemsEl) {
    cartItemsEl.addEventListener("click", function (e){
      var btn = e.target.closest("[data-act]"); if (!btn) return;
      var row = btn.closest(".cart-row"); if (!row) return;
      var id = row.getAttribute("data-id"), act = btn.getAttribute("data-act");
      var c = getCart();
      var idx = -1; c.forEach(function (x, k){ if (x.id === id) idx = k; });
      if (idx < 0) return;
      if (act === "inc") c[idx].qty += 1;
      else if (act === "dec") { c[idx].qty -= 1; if (c[idx].qty < 1) c.splice(idx, 1); }
      else if (act === "remove") c.splice(idx, 1);
      saveCart(c);
    });
  }
  // Cart-page request form
  var cartForm = document.getElementById("cart-form");
  if (cartForm) {
    // LOGGED-IN FLOW: prefill identity from the cabinet, collapse those fields
    // to a summary, and don't require re-entering name/phone.
    var cartUser = null; try { cartUser = JSON.parse(localStorage.getItem("enko_user_v1") || "null"); } catch (e) {}
    if (cartUser){
      var byName = ((cartUser.firstName || "") + " " + (cartUser.lastName || "")).trim();
      var fn = $("#c-name"), fp = $("#c-phone"), fe = $("#c-email");
      if (fn){ fn.value = byName; fn.required = false; }
      if (fp){ fp.value = cartUser.phone || ""; fp.required = false; }
      if (fe){ fe.value = cartUser.email || ""; }
      ["#c-name","#c-phone","#c-email"].forEach(function (sel){ var el = $(sel); if (el && el.closest(".field")) el.closest(".field").style.display = "none"; });
      var note = document.createElement("div");
      note.className = "form-product-tag";
      note.style.marginTop = "0";
      note.innerHTML = '<svg viewBox="0 0 24 24" width="18" fill="none" stroke="#6E54A6" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M5 21v-2a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v2"/></svg><span>' + T("Заявка від") + ' <b>' + esc(byName || cartUser.email) + '</b>' + (cartUser.email ? '<br>· ' + esc(cartUser.email) : "") + (cartUser.phone ? '<br>· ' + esc(cartUser.phone) : "") + '</span>';
      var h3 = cartForm.querySelector("h3");
      if (h3) h3.insertAdjacentElement("afterend", note); else cartForm.insertBefore(note, cartForm.firstChild);
    }
    cartForm.addEventListener("submit", function (e){
      e.preventDefault();
      // record this submission into the user's order/request history
      try {
        var c0 = getCart();
        if (c0.length) {
          var orders = JSON.parse(localStorage.getItem("enko_orders_v1") || "[]");
          // discount actually shown to the customer = account-registry value (capped), like home-r2
          var orderDisc = 0; try { var su = getUser(); if (su){ var acc = findAccount(su.email); var dd = acc && acc.discount != null ? acc.discount : (su.discount || 0); orderDisc = Math.max(0, Math.min(99, parseInt(dd, 10) || 0)); } } catch (e) {}
          // snapshot unit prices at the LIVE rate so history matches what was on screen
          var unitU = function (i){ return i.eur ? uahFromEur(i.eur) : (i.uah || 0); };
          var fullU = c0.reduce(function (s, i){ return s + unitU(i) * i.qty; }, 0);
          var fullE = c0.reduce(function (s, i){ return s + (i.eur || 0) * i.qty; }, 0);
          orders.unshift({
            id: "ENK-" + Date.now().toString().slice(-6),
            date: new Date().toLocaleDateString("uk-UA"),
            items: c0.map(function (i){ return { id: i.id, name: i.name, ver: i.ver, qty: i.qty, uah: unitU(i), eur: i.eur }; }),
            count: c0.reduce(function (n, i){ return n + i.qty; }, 0),
            uah: Math.round(fullU * (1 - orderDisc / 100)),
            eur: Math.round(fullE * (1 - orderDisc / 100)),
            discount: orderDisc,
            status: "Відправлено"
          });
          localStorage.setItem("enko_orders_v1", JSON.stringify(orders));
        }
      } catch (err) {}
      try { localStorage.removeItem(CART_KEY); } catch (err) {}
      // update badge to 0, then reveal success (skip re-render which would show empty state)
      $$(".cart-badge").forEach(function (el){ el.textContent = "0"; el.classList.remove("show"); });
      var grid = $("#cart-grid"); if (grid) grid.style.display = "none";
      var empty = $("#cart-empty"); if (empty) empty.style.display = "none";
      var ok = $("#cart-ok"); if (ok) ok.style.display = "block";
      window.scrollTo({ top: 0, behavior: "smooth" });
    });
  }
  updateCartUI();

  /* ===================== PDP-ONLY ===================== */

  /* ---------- VERSION SELECTOR ---------- */
  var VERSIONS = (window.ENKO_PDP && window.ENKO_PDP.versions) || {
    "26": { model:"AKAY-C 26 DR13", cool:"2.6", heat:"3.0", noise:"25", breaker:"10 A", area:"20–25", uah:"20 250", eur:"450" },
    "35": { model:"AKAY-C 35 DR13", cool:"3.5", heat:"3.8", noise:"25", breaker:"10 A", area:"25–35", uah:"22 500", eur:"500" },
    "52": { model:"AKAY-C 52 DR12", cool:"5.28", heat:"5.57", noise:"26", breaker:"12 A", area:"35–50", uah:"38 250", eur:"850" },
    "71": { model:"AKAY-C 71 DR12", cool:"7.03", heat:"7.33", noise:"36", breaker:"16 A", area:"50–70", uah:"49 500", eur:"1100" }
  };
  var currentVer = "26";
  var PDP_NAME = (window.ENKO_PDP && window.ENKO_PDP.name) || "Кондиціонер Kaysun Casual";
  var PDP_SKU = (window.ENKO_PDP && window.ENKO_PDP.skuBase) || "EN-AC-KAYSUN-CASUAL-";
  var PDP_ENERGY = (window.ENKO_PDP && window.ENKO_PDP.energy) || "A++/A+";
  function currentProductLabel() {
    return PDP_NAME + " " + currentVer + " (" + VERSIONS[currentVer].model + ")";
  }
  function setText(sel, v) { var el = $(sel); if (el) el.textContent = v; }
  function applyVersion(v) {
    var d = VERSIONS[v]; if (!d) return;
    currentVer = v;
    $$(".ver-btn").forEach(function (b) { b.classList.toggle("active", b.getAttribute("data-ver") === v); });
    setText("#price-uah", fmt(uahFromEur(d.eur)) + " грн");
    setText("#price-eur", d.eur + " €");
    setText("#sb-price-uah", fmt(uahFromEur(d.eur)) + " грн");
    setText("#sb-price-eur", d.eur + " €");
    setText("#sp-cool", d.cool); setText("#sp-heat", d.heat);
    setText("#sp-noise", d.noise); setText("#sp-area", d.area);
    setText("#sp-breaker", d.breaker);
    setText("#meta-model", d.model);
    setText("#sb-model", PDP_NAME + " · вер. " + v);
    // highlight active column in spec table
    $$(".spec-table .col-active").forEach(function (c) { c.classList.remove("col-active"); });
    $$('.spec-table [data-col="' + v + '"]').forEach(function (c) { c.classList.add("col-active"); });
    refreshBuyboxQty();
  }
  $$(".ver-btn").forEach(function (b) {
    b.addEventListener("click", function () { applyVersion(b.getAttribute("data-ver")); });
  });
  if ($(".ver-btn")) { var _qv=new URLSearchParams(location.search).get("ver"); applyVersion(_qv && VERSIONS[_qv] ? _qv : ((document.querySelector(".ver-btn") && document.querySelector(".ver-btn").getAttribute("data-ver")) || "26")); }

  /* ---------- TABS ---------- */
  $$(".tabs").forEach(function (tabs) {
    var btns = $$(".tab-btn", tabs), panels = $$(".tab-panel", tabs);
    btns.forEach(function (btn) {
      btn.addEventListener("click", function () {
        var id = btn.getAttribute("data-tab");
        btns.forEach(function (b) { b.classList.toggle("active", b === btn); b.setAttribute("aria-selected", b === btn); });
        panels.forEach(function (p) { p.classList.toggle("active", p.getAttribute("data-panel") === id); });
      });
    });
  });

  /* ---------- GALLERY THUMBS + LIGHTBOX ---------- */
  var gMain = $(".gallery__main"), gLabel = $("#gallery-main-label"), gImg = $("#gallery-main-img");
  $$(".thumb").forEach(function (t) {
    t.addEventListener("click", function () {
      $$(".thumb").forEach(function (x) { x.classList.remove("active"); });
      t.classList.add("active");
      var img = t.getAttribute("data-img");
      if (img && gImg) { gImg.src = img; gImg.alt = (t.getAttribute("data-label") || "").replace(/<br>/g, " "); }
      if (gLabel) gLabel.innerHTML = t.getAttribute("data-label") || gLabel.innerHTML;
    });
  });
  var lightbox = $("#lightbox"), lbImg = lightbox ? lightbox.querySelector("[data-lightbox-img]") : null;
  var lbList = $$(".thumb").map(function (t) {
    return { src: t.getAttribute("data-img"), alt: (t.getAttribute("data-label") || "").replace(/<br>/g, " ").trim() };
  }).filter(function (x) { return x.src; });
  var lbIndex = 0;
  function showLb(i) {
    if (!lbList.length || !lbImg) return;
    lbIndex = (i + lbList.length) % lbList.length;
    lbImg.src = lbList[lbIndex].src;
    lbImg.alt = lbList[lbIndex].alt;
    var counter = lightbox.querySelector(".lb-counter");
    if (counter) counter.textContent = (lbIndex + 1) + " / " + lbList.length;
    // keep main gallery + thumbs in sync
    if (gImg) { gImg.src = lbList[lbIndex].src; gImg.alt = lbList[lbIndex].alt; }
    var thumbs = $$(".thumb");
    thumbs.forEach(function (t, k) { t.classList.toggle("active", k === lbIndex); });
  }
  function openLightbox() {
    if (!lightbox) return;
    var start = 0;
    if (lbList.length && gImg) { lbList.forEach(function (x, k) { if (x.src === gImg.getAttribute("src") || gImg.src.indexOf(x.src) !== -1) start = k; }); }
    if (lbList.length) showLb(start); else if (lbImg && gImg) lbImg.src = gImg.src;
    lightbox.classList.add("open"); document.body.style.overflow = "hidden";
  }
  function closeLightbox() { if (lightbox) { lightbox.classList.remove("open"); document.body.style.overflow = ""; } }
  if (gMain) gMain.addEventListener("click", openLightbox);
  if (lightbox) $$("[data-lightbox-close]", lightbox).forEach(function (b) { b.addEventListener("click", closeLightbox); });
  // inject prev/next arrows + counter (only when there are several photos)
  if (lightbox && lbList.length > 1) {
    var panel = lightbox.querySelector(".modal__panel");
    if (panel) {
      var arrowSvg = function (d) { return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="' + d + '"/></svg>'; };
      var prev = document.createElement("button");
      prev.type = "button"; prev.className = "lb-nav lb-nav--prev"; prev.setAttribute("aria-label", "Попереднє фото");
      prev.innerHTML = arrowSvg("m15 18-6-6 6-6");
      var next = document.createElement("button");
      next.type = "button"; next.className = "lb-nav lb-nav--next"; next.setAttribute("aria-label", "Наступне фото");
      next.innerHTML = arrowSvg("m9 18 6-6-6-6");
      var counter = document.createElement("div");
      counter.className = "lb-counter";
      panel.appendChild(prev); panel.appendChild(next); panel.appendChild(counter);
      prev.addEventListener("click", function (e) { e.stopPropagation(); showLb(lbIndex - 1); });
      next.addEventListener("click", function (e) { e.stopPropagation(); showLb(lbIndex + 1); });
    }
  }
  document.addEventListener("keydown", function (e) {
    if (!lightbox || !lightbox.classList.contains("open") || lbList.length < 2) return;
    if (e.key === "ArrowLeft") { e.preventDefault(); showLb(lbIndex - 1); }
    else if (e.key === "ArrowRight") { e.preventDefault(); showLb(lbIndex + 1); }
  });

  /* ---------- STICKY BOTTOM BAR ---------- */
  var sticky = $(".sticky-bar"), anchor = $("#buybox-actions");
  if (sticky && anchor) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { sticky.classList.toggle("show", !en.isIntersecting && en.boundingClientRect.top < 0); });
    }, { threshold: 0 });
    io.observe(anchor);
  }

  /* ========================================================================
     CATALOG LISTING (catalog.html) — filters + sort + view, no reload.
     Curated set of controls (not overwhelming). Cards rendered from data so
     filtering is instant; the WP build uses the Woo product loop instead.
     ======================================================================== */
  /* ----- Товари каталогу + рендер картки (модульна область: каталог + «схожі» на PDP) ----- */
  var PRODUCTS = (window.ENKO_PRODUCTS && window.ENKO_PRODUCTS.length) ? window.ENKO_PRODUCTS : [
      // Kaysun Casual (R-32, DC Inverter, A++) — настінні спліт-системи, 4 типорозміри
      { id:"EN-AC-KAYSUN-CASUAL-26", name:"Kaysun Casual AKAY-C 26", brand:"Kaysun", series:"casual", type:"wall", sub:"split", btu:9, area:25, power:2.6, range:"2.6", uah:20250, eur:450, energy:"A++", wifi:1, minTemp:-15, badge:"new", pop:95, rating:4.8, warranty:3, stock:"in", eta:0, href:"kaysun-casual.html", img:"настінний блок", photo:"/wp-content/uploads/enko/products-kaysun-casual-indoor.webp" },
      { id:"EN-AC-KAYSUN-CASUAL-35", name:"Kaysun Casual AKAY-C 35", brand:"Kaysun", series:"casual", type:"wall", sub:"split", btu:12, area:35, power:3.5, range:"3.5", uah:23850, eur:530, energy:"A++", wifi:1, minTemp:-15, badge:"", pop:88, rating:4.7, warranty:3, stock:"in", eta:0, href:"kaysun-casual.html", img:"настінний блок", photo:"/wp-content/uploads/enko/products-kaysun-casual-indoor.webp" },
      { id:"EN-AC-KAYSUN-CASUAL-52", name:"Kaysun Casual AKAY-C 52", brand:"Kaysun", series:"casual", type:"wall", sub:"split", btu:18, area:50, power:5.28, range:"5.28", uah:29250, eur:650, energy:"A++", wifi:1, minTemp:-15, badge:"", pop:80, rating:4.7, warranty:3, stock:"in", eta:0, href:"kaysun-casual.html", img:"настінний блок", photo:"/wp-content/uploads/enko/products-kaysun-casual-indoor.webp" },
      { id:"EN-AC-KAYSUN-CASUAL-71", name:"Kaysun Casual AKAY-C 71", brand:"Kaysun", series:"casual", type:"wall", sub:"split", btu:24, area:70, power:7.03, range:"7.03", uah:35550, eur:790, energy:"A++", wifi:1, minTemp:-15, badge:"", pop:78, rating:4.6, warranty:3, stock:"order", eta:0, href:"kaysun-casual.html", img:"настінний блок", photo:"/wp-content/uploads/enko/products-kaysun-casual-indoor.webp" },
      // Kaysun Prodigy PRO (R-32, A+++) — настінні спліт-системи, 4 типорозміри
      { id:"EN-AC-KAYSUN-PRODIGY-26", name:"Kaysun Prodigy PRO AKAY-P 26", brand:"Kaysun", series:"prodigy", type:"wall", sub:"split", btu:9, area:25, power:2.73, range:"2.73", uah:26550, eur:590, energy:"A+++", wifi:1, minTemp:-15, badge:"hit", pop:96, rating:4.9, warranty:3, stock:"in", eta:0, href:"kaysun-prodigy.html", img:"настінний блок", photo:"/wp-content/uploads/enko/products-kaysun-casual-indoor.webp" },
      { id:"EN-AC-KAYSUN-PRODIGY-35", name:"Kaysun Prodigy PRO AKAY-P 35", brand:"Kaysun", series:"prodigy", type:"wall", sub:"split", btu:12, area:35, power:3.52, range:"3.52", uah:30150, eur:670, energy:"A+++", wifi:1, minTemp:-15, badge:"", pop:90, rating:4.8, warranty:3, stock:"in", eta:0, href:"kaysun-prodigy.html", img:"настінний блок", photo:"/wp-content/uploads/enko/products-kaysun-casual-indoor.webp" },
      { id:"EN-AC-KAYSUN-PRODIGY-52", name:"Kaysun Prodigy PRO AKAY-P 52", brand:"Kaysun", series:"prodigy", type:"wall", sub:"split", btu:18, area:50, power:5.28, range:"5.28", uah:36450, eur:810, energy:"A+++", wifi:1, minTemp:-15, badge:"", pop:82, rating:4.8, warranty:3, stock:"in", eta:0, href:"kaysun-prodigy.html", img:"настінний блок", photo:"/wp-content/uploads/enko/products-kaysun-casual-indoor.webp" },
      { id:"EN-AC-KAYSUN-PRODIGY-71", name:"Kaysun Prodigy PRO AKAY-P 71", brand:"Kaysun", series:"prodigy", type:"wall", sub:"split", btu:24, area:70, power:7.04, range:"7.04", uah:43650, eur:970, energy:"A+++", wifi:1, minTemp:-15, badge:"", pop:80, rating:4.7, warranty:3, stock:"order", eta:0, href:"kaysun-prodigy.html", img:"настінний блок", photo:"/wp-content/uploads/enko/products-kaysun-casual-indoor.webp" },
      /* ТИМЧАСОВІ демо-картки (поки немає реальних товарів цих типів) — прибрати при наповненні каталогу */
      { id:"EN-DEMO-CONSOLE", name:"Консольний кондиціонер (демо)", brand:"Kaysun", series:"casual", type:"console", sub:"split", btu:12, area:35, power:3.5, range:"3.5", uah:27900, eur:620, energy:"A++", wifi:1, minTemp:-15, badge:"", pop:60, rating:0, warranty:3, stock:"order", eta:0, href:"#", img:"консольний блок", photo:"/wp-content/uploads/enko/types-console.webp" },
      { id:"EN-DEMO-DUCT", name:"Канальний кондиціонер (демо)", brand:"Kaysun", series:"casual", type:"duct", sub:"split", btu:24, area:70, power:7.0, range:"7.0", uah:54000, eur:1200, energy:"A+", wifi:0, minTemp:-15, badge:"", pop:58, rating:0, warranty:3, stock:"order", eta:0, href:"#", img:"канальний блок", photo:"/wp-content/uploads/enko/types-duct.webp" },
      { id:"EN-DEMO-CASSETTE", name:"Касетний кондиціонер (демо)", brand:"Kaysun", series:"casual", type:"cassette", sub:"split", btu:18, area:55, power:5.2, range:"5.2", uah:44100, eur:980, energy:"A++", wifi:0, minTemp:-15, badge:"", pop:56, rating:0, warranty:3, stock:"order", eta:0, href:"#", img:"касетний блок", photo:"/wp-content/uploads/enko/types-cassette.webp" },
      { id:"EN-DEMO-FLOORCEIL", name:"Підлогово-стельовий (демо)", brand:"Kaysun", series:"casual", type:"floorceil", sub:"split", btu:18, area:50, power:5.0, range:"5.0", uah:33300, eur:740, energy:"A++", wifi:1, minTemp:-15, badge:"", pop:54, rating:0, warranty:3, stock:"order", eta:0, href:"#", img:"підлогово-стельовий блок", photo:"/wp-content/uploads/enko/types-floorceil.webp" },
      { id:"EN-DEMO-MULTI-ODU", name:"Мульти-спліт · зовнішній блок (демо)", brand:"Kaysun", series:"casual", type:"multi", block:"outdoor", sub:"multi", btu:36, area:90, power:10.5, range:"10.5", uah:67500, eur:1500, energy:"A++", wifi:0, minTemp:-15, badge:"", pop:52, rating:0, warranty:3, stock:"order", eta:0, href:"#", img:"зовнішній блок", photo:"/wp-content/uploads/enko/types-outdoor.webp" },
      { id:"EN-DEMO-MULTI-IDU", name:"Мульти-спліт · внутрішній блок (демо)", brand:"Kaysun", series:"casual", type:"multi", block:"indoor", sub:"multi", btu:9, area:25, power:2.6, range:"2.6", uah:17100, eur:380, energy:"A++", wifi:1, minTemp:-15, badge:"", pop:50, rating:0, warranty:3, stock:"order", eta:0, href:"#", img:"внутрішній блок", photo:"/wp-content/uploads/enko/types-wall.webp" }
    ];
    var BADGE_LABEL = { "new":["Новинка","flag--new"], "hit":["Хіт","flag--hit"], "sale":["Знижка","flag--sale"] };
    var STOCK_LBL = { "in":["В наявності","stock--in"], "order":["Під замовлення","stock--order"], "soon":["Очікується","stock--soon"] };
    function stockHTML(p){
      var s = STOCK_LBL[p.stock] || STOCK_LBL["order"];
      var txt = s[0]; if (p.stock === "soon" && p.eta) txt += " · ~" + p.eta + " дн.";
      return '<span class="stock-ind ' + s[1] + '"><i></i>' + txt + '</span>';
    }
    function ratingHTML(r){
      return ""; // відгуки/оцінки прибрано на вимогу — функціонал не використовується
    }

  function cardHTML(p){
      var flag = p.badge && BADGE_LABEL[p.badge]
        ? '<div class="badge-flag"><span class="flag ' + BADGE_LABEL[p.badge][1] + '">' + BADGE_LABEL[p.badge][0] + '</span></div>' : "";
      var wifiBadge = p.wifi ? '<span class="spec-badge">WiFi</span>' : "";
      var BRAND_LOGO = { "Kaysun":"kaysun", "LG":"lg", "Panasonic":"panasonic", "Juwent":"juwent", "Klimor":"klimor" };
      var brandBadge = BRAND_LOGO[p.brand]
        ? '<span class="spec-badge spec-badge--brand"><img src="/wp-content/themes/enko/assets/brands/' + BRAND_LOGO[p.brand] + '.svg" alt="' + p.brand + '"></span>'
        : '<span class="spec-badge">' + p.brand + '</span>';
      var detail = p.href === "#" ? '#' : p.href;
      var _m = p.name.indexOf(" AKAY");
      var nameMain = _m > 0 ? p.name.slice(0, _m) : p.name;
      var nameModel = _m > 0 ? p.name.slice(_m + 1) : "";
      var nameModelHTML = nameModel ? '<span class="prod-card__model">' + nameModel + '</span>' : "";
      return '<article class="prod-card">'
        + '<div class="prod-card__media">' + flag + '<a class="prod-card__media-link" href="' + detail + '" tabindex="-1" aria-hidden="true"><div class="ph">' + (p.photo ? '<img class="prod-photo" src="' + p.photo + '" alt="' + p.name + '">' : '<span>Фото: ' + p.img + '</span>') + '</div></a></div>'
        + '<div class="prod-card__body">'
        + '<div class="prod-card__badges">' + brandBadge + wifiBadge + '<span class="spec-badge">' + p.energy + '</span></div>'
        + '<h3 class="prod-card__name"><a href="' + detail + '">' + nameMain + nameModelHTML + '</a></h3>'
        + '<p class="prod-card__minispec">' + p.range + ' кВт · ' + p.btu + 'K BTU · R-32 · ' + p.energy + '</p>'
        + '<div class="prod-card__meta">' + stockHTML(p) + ratingHTML(p.rating) + '</div>'
        + '<div class="prod-card__foot">'
        + '<div class="price"><span class="price__label">від · орієнтовна</span>'
        + '<span class="price__row"><span class="price__main price__uah">' + fmt(uahFromEur(p.eur)) + ' грн</span><span class="price__eur">' + fmt(p.eur) + ' €</span></span></div>'
        + '<div class="prod-card__btns"><a href="' + detail + '" class="btn btn--ghost btn--s">Детальніше</a>'
        + '<button class="card-add" data-add-request data-id="' + p.id + '" data-name="' + p.name + '" data-spec="' + p.range + ' кВт · R-32 · ' + p.energy + '" data-uah="' + uahFromEur(p.eur) + '" data-eur="' + p.eur + '" data-img="' + p.img + '" data-photo="' + (p.photo || "") + '" aria-label="' + T("Додати в заявку") + '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg><span class="btn-label">' + T("Додати в заявку") + '</span></button></div>'
        + '</div></div></article>';
    }

  var catGrid = document.getElementById("catalog-grid");
  if (catGrid) {
    function getChecked(name){ return $$('input[name="' + name + '"]:checked').map(function (i){ return i.value; }); }
    function getRadio(name){ var r = $('input[name="' + name + '"]:checked'); return r ? r.value : "all"; }

    function matchPower(p, f){ if (f === "all") return true; if (f === "lt35") return p < 3.5; if (f === "mid") return p >= 3.5 && p <= 5.5; if (f === "gt55") return p > 5.5; return true; }
    function matchPrice(v, f){ if (f === "all") return true; if (f === "lt25") return v < 25000; if (f === "mid") return v >= 25000 && v <= 40000; if (f === "gt40") return v > 40000; return true; }
    function matchArea(a, f){ if (f === "all") return true; if (f === "lt25") return a <= 25; if (f === "a2540") return a > 25 && a <= 40; if (f === "a4060") return a > 40 && a <= 60; if (f === "gt60") return a > 60; return true; }

    function currentFilters(){
      return {
        sub: getRadio("f-sub"),
        block: getRadio("f-block"),
        brand: getChecked("f-brand"),
        series: getChecked("f-series"),
        area: getRadio("f-area"),
        btu: getChecked("f-btu"),
        power: getRadio("f-power"),
        price: getRadio("f-price"),
        energy: getChecked("f-energy"),
        wifi: $("#f-wifi") && $("#f-wifi").checked
      };
    }
    function passes(p, f){
      if (f.sub !== "all" && p.type !== f.sub) return false;
      if (f.block && f.block !== "all" && p.block !== f.block) return false;
      if (f.brand.length && f.brand.indexOf(p.brand) < 0) return false;
      if (f.series.length && f.series.indexOf(p.series) < 0) return false;
      if (!matchArea(p.area, f.area)) return false;
      if (f.btu.length && f.btu.indexOf(String(p.btu)) < 0) return false;
      if (!matchPower(p.power, f.power)) return false;
      if (!matchPrice(p.uah, f.price)) return false;
      if (f.energy.length && f.energy.indexOf(p.energy) < 0) return false;
      if (f.wifi && !p.wifi) return false;
      return true;
    }
    function sortList(list, mode){
      var a = list.slice();
      if (mode === "price-asc") a.sort(function (x, y){ return x.uah - y.uah; });
      else if (mode === "price-desc") a.sort(function (x, y){ return y.uah - x.uah; });
      else if (mode === "new") a.sort(function (x, y){ return (y.badge === "new") - (x.badge === "new") || y.pop - x.pop; });
      else a.sort(function (x, y){ return y.pop - x.pop; });
      return a;
    }
    var POWER_LBL = { lt35:"до 3.5 кВт", mid:"3.5–5.5 кВт", gt55:"понад 5.5 кВт" };
    var PRICE_LBL = { lt25:"до 25 000 грн", mid:"25–40 тис. грн", gt40:"понад 40 000 грн" };
    var SUB_LBL = { wall:"Настінні", console:"Консольні", duct:"Канальні", cassette:"Касетні", floorceil:"Підлогово-стельові", multi:"Мульти-спліт" };
    var AREA_LBL = { lt25:"до 25 м²", a2540:"25–40 м²", a4060:"40–60 м²", gt60:"60+ м²" };
    var SERIES_LBL = { casual:"Casual", prodigy:"Prodigy PRO" };
    function chipsHTML(f){
      var out = [];
      if (f.sub !== "all") out.push(["f-sub", f.sub, SUB_LBL[f.sub]]);
      f.brand.forEach(function (b){ out.push(["f-brand", b, b]); });
      f.series.forEach(function (s){ out.push(["f-series", s, SERIES_LBL[s] || s]); });
      if (f.area !== "all") out.push(["f-area", f.area, AREA_LBL[f.area]]);
      f.btu.forEach(function (b){ out.push(["f-btu", b, b + "K BTU"]); });
      if (f.power !== "all") out.push(["f-power", f.power, POWER_LBL[f.power]]);
      if (f.price !== "all") out.push(["f-price", f.price, PRICE_LBL[f.price]]);
      f.energy.forEach(function (e){ out.push(["f-energy", e, "Клас " + e]); });
      if (f.wifi) out.push(["f-wifi", "wifi", "WiFi"]);
      return out.map(function (c){
        return '<button class="filter-chip" data-fname="' + c[0] + '" data-fval="' + c[1] + '">' + c[2]
          + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>';
      }).join("");
    }

    function render(){
      var f = currentFilters();
      var list = sortList(PRODUCTS.filter(function (p){ return passes(p, f); }), $("#sort") ? $("#sort").value : "pop");
      catGrid.innerHTML = list.map(cardHTML).join("");
      refreshCardQty();
      setText("#catalog-count", list.length + " " + plural(list.length, T("товар"), T("товари"), T("товарів")));
      var chips = chipsHTML(f);
      var chipWrap = $("#catalog-chips");
      if (chipWrap) chipWrap.innerHTML = chips;
      var wrap = $("#catalog-chips-wrap"); if (wrap) wrap.style.display = chips ? "flex" : "none";
      var empty = $("#catalog-empty"); if (empty) empty.style.display = list.length ? "none" : "block";
    }

    // After a filter change, bring the start of the listing (filters + first row)
    // to just under the sticky header — but ONLY if it's currently scrolled out of view.
    function absTop(el){ var y = 0; while (el){ y += el.offsetTop; el = el.offsetParent; } return y; }
    function revealTop(){
      var layout = $(".catalog-layout"); if (!layout) return;
      var headerH = 88; // sticky header + small gap
      var grid = document.getElementById("catalog-grid");
      var firstCard = grid && grid.querySelector(".prod-card");
      var refAbs = firstCard ? absTop(firstCard) : absTop(layout);
      // Reveal only if the first product row is currently hidden above the fold
      // (behind the sticky header or scrolled past). If it's still visible — don't move.
      if (window.scrollY + headerH > refAbs) {
        window.scrollTo(0, Math.max(0, absTop(layout) - headerH));
      }
    }
    function renderAndReveal(){ render(); revealTop(); }

    // react to any filter change
    $$(".filters input, #sort").forEach(function (el){ el.addEventListener("change", renderAndReveal); });

    // active-filter chips remove
    var chipWrap = $("#catalog-chips");
    if (chipWrap) chipWrap.addEventListener("click", function (e){
      var c = e.target.closest(".filter-chip"); if (!c) return;
      var name = c.getAttribute("data-fname"), val = c.getAttribute("data-fval");
      if (name === "f-wifi") { $("#f-wifi").checked = false; }
      else {
        var input = $('input[name="' + name + '"][value="' + val + '"]');
        if (input) { if (input.type === "radio") { var allOpt = $('input[name="' + name + '"][value="all"]'); if (allOpt) allOpt.checked = true; } else input.checked = false; }
      }
      renderAndReveal();
    });

    // clear all
    var clearAll = $("#filters-clear");
    if (clearAll) clearAll.addEventListener("click", function (){
      $$(".filters input[type=checkbox]").forEach(function (i){ i.checked = false; });
      $$('.filters input[type=radio][value="all"]').forEach(function (i){ i.checked = true; });
      renderAndReveal();
    });

    // grid / list view toggle
    $$("[data-view]").forEach(function (b){
      b.addEventListener("click", function (){
        var v = b.getAttribute("data-view");
        $$("[data-view]").forEach(function (x){ x.classList.toggle("active", x === b); });
        catGrid.classList.toggle("is-list", v === "list");
      });
    });

    // mobile filters drawer
    var filtersEl = $(".filters"), openF = $("#filters-open"), closeF = $("#filters-close");
    var fOverlay = $("#filters-overlay");
    function setFilters(open){
      if (filtersEl){ filtersEl.classList.toggle("open", open); document.body.style.overflow = open ? "hidden" : ""; }
      if (fOverlay) fOverlay.classList.toggle("show", open);
    }
    if (openF) openF.addEventListener("click", function (){ setFilters(true); });
    if (closeF) closeF.addEventListener("click", function (){ setFilters(false); });
    if (fOverlay) fOverlay.addEventListener("click", function (){ setFilters(false); });

    render();
  }

  /* ========================================================================
     AUTH · PARTNER REGISTRATION · ACCOUNT (cabinet)
     Registration = site sign-up. State persists in localStorage. The header
     control + registration modal are injected here, so every page gets them.
     ======================================================================== */
  /* PDP: «схожі товари» — ті самі картки, що в каталозі, з реальними даними (інша серія) */
  (function(){
    var simGrid = document.getElementById("similar-grid");
    if (!simGrid || typeof PRODUCTS === "undefined") return;
    var skuBase = (window.ENKO_PDP && window.ENKO_PDP.skuBase) || "EN-AC-KAYSUN-CASUAL-";
    var sim = PRODUCTS.filter(function(p){ return p.id.indexOf(skuBase) !== 0; }).sort(function(a,b){ return b.pop - a.pop; }).slice(0,4);
    simGrid.innerHTML = sim.map(cardHTML).join("");
    refreshCardQty();
  })();

  var USER_KEY = "enko_user_v1", CHAT_KEY = "enko_chat_v1", ORDERS_KEY = "enko_orders_v1";
  var MANAGER = { name: "Андрій Коваль", role: "Персональний менеджер ENKO", phone: "+380 (44) 000-00-01", email: "info@enkogroup.com.ua", tg: "@enko_manager" };

  function getUser(){ try { return JSON.parse(localStorage.getItem(USER_KEY) || "null"); } catch (e) { return null; } }
  function setUser(u){ try { localStorage.setItem(USER_KEY, JSON.stringify(u)); } catch (e) {} }
  function logoutUser(){ try { localStorage.removeItem(USER_KEY); } catch (e) {} }
  function getAccounts(){ try { return JSON.parse(localStorage.getItem("enko_accounts_v1") || "[]"); } catch (e) { return []; } }
  function upsertAccount(u, prevEmail){ try { var newE = (u.email||"").toLowerCase(), oldE = (prevEmail||"").toLowerCase(); var a = getAccounts().filter(function (x){ var e = (x.email||"").toLowerCase(); return e !== newE && (!oldE || e !== oldE); }); a.push(u); localStorage.setItem("enko_accounts_v1", JSON.stringify(a)); } catch (e) {} }
  function findAccount(email){ return getAccounts().filter(function (x){ return (x.email||"").toLowerCase() === email.toLowerCase(); })[0] || null; }
  function genTempPass(){ var s = "abcdefghjkmnpqrstuvwxyz23456789"; var o = ""; for (var i = 0; i < 8; i++) o += s[Math.floor(Math.random() * s.length)]; return o; }
  function getOrders(){ try { return JSON.parse(localStorage.getItem(ORDERS_KEY) || "[]"); } catch (e) { return []; } }
  function getChat(){ try { return JSON.parse(localStorage.getItem(CHAT_KEY) || "[]"); } catch (e) { return []; } }
  function setChat(c){ try { localStorage.setItem(CHAT_KEY, JSON.stringify(c)); } catch (e) {} }
  function esc(s){ return String(s == null ? "" : s).replace(/[&<>"]/g, function (c){ return ({ "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;" })[c]; }); }
  function userInitials(u){
    var a = (u.firstName || "").trim(), b = (u.lastName || "").trim();
    if (!a && !b) return "EN";
    return ((a[0] || "") + (b[0] || "")).toUpperCase();
  }

  /* ========================================================================
     UNIFIED SUPPORT CHAT — single source for the account-page card AND the
     floating quick-pop (same data + same logic, called from two places).
     • Logged-in: messages live on the user's account record (enko_accounts_v1),
       so the history is tied to the person — it survives logout/login and shows
       on any device where they sign in (in WP this record is server-side).
     • Anonymous: per-browser store (enko_chat_v1) — persists for this browser
       across popup close/reopen and page navigation (same effect as a cookie).
     Closing the chat (×) only hides it; nothing is ever auto-deleted.
     ======================================================================== */
  function chatGet(){
    var u = getUser();
    if (u){ var acc = findAccount(u.email); if (acc && Array.isArray(acc.chat)) return acc.chat.slice(); if (Array.isArray(u.chat)) return u.chat.slice(); return []; }
    try { return JSON.parse(localStorage.getItem(CHAT_KEY) || "[]"); } catch (e) { return []; }
  }
  function chatSet(arr){
    var u = getUser();
    if (u){
      var accs = getAccounts(), idx = -1;
      accs.forEach(function (x, k){ if ((x.email||"").toLowerCase() === (u.email||"").toLowerCase()) idx = k; });
      if (idx >= 0){ accs[idx].chat = arr; try { localStorage.setItem("enko_accounts_v1", JSON.stringify(accs)); } catch (e) {} }
      u.chat = arr; setUser(u);
    } else {
      try { localStorage.setItem(CHAT_KEY, JSON.stringify(arr)); } catch (e) {}
    }
    try { window.dispatchEvent(new CustomEvent("enko:chat")); } catch (e) {}
  }
  function chatEnsureGreeting(){ var c = chatGet(); if (!c.length){ c = [{ from:"support", text:"Вітаємо! Напишіть ваші запитання." }]; chatSet(c); } return c; }
  function chatAdd(from, text){ var c = chatGet(); c.push({ from: from, text: String(text == null ? "" : text) }); chatSet(c); return c; }
  function chatRenderInto(logEl){
    if (!logEl) return;
    logEl.innerHTML = chatGet().map(function (m){
      return '<div class="msg ' + (m.from === "user" ? "user" : "support") + '"><span class="who">' + (m.from === "user" ? "Ви" : "Підтримка") + '</span>' + esc(m.text) + '</div>';
    }).join("");
    logEl.scrollTop = logEl.scrollHeight;
  }
  window.ENKO_CHAT = { get: chatGet, set: chatSet, add: chatAdd, ensureGreeting: chatEnsureGreeting, render: chatRenderInto, reply: "Дякуємо! Передаю запит менеджеру — він відповість тут найближчим часом." };

  /* ---- header + mobile auth control ---- */
  function renderAuthArea(){
    var u = getUser();
    var slot = document.getElementById("auth-area");
    if (slot) slot.innerHTML = u
      ? '<a class="account-chip" href="account.html" aria-label="Мій кабінет"><span class="ava">' + esc(userInitials(u)) + '</span><span>' + esc(u.firstName || "Кабінет") + '</span></a>'
      : '<button class="auth-trigger" data-auth-open type="button"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M5 21v-2a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v2"/></svg>Увійти</button>';
    var slotM = document.getElementById("auth-area-m");
    if (slotM) slotM.innerHTML = u
      ? '<a class="mnav-link mnav-account" href="account.html"><span class="ava">' + esc(userInitials(u)) + '</span><span>Мій кабінет</span></a>'
      : '<button class="btn btn--ghost btn--block" data-auth-open type="button" style="margin-top:10px">Увійти або зареєструватися</button>';
  }
  (function injectAuth(){
    var right = $(".header__right");
    if (right && !document.getElementById("auth-area")){
      var slot = document.createElement("div");
      slot.id = "auth-area"; slot.className = "auth-area";
      var reqBtn = right.querySelector(".btn--primary");
      right.insertBefore(slot, reqBtn || right.firstChild);
    }
    var mnav = $(".mobile-nav");
    if (mnav && !document.getElementById("auth-area-m")){
      var slotM = document.createElement("div"); slotM.id = "auth-area-m";
      var blockBtn = mnav.querySelector(".btn--block");
      if (blockBtn) mnav.insertBefore(slotM, blockBtn); else mnav.appendChild(slotM);
    }
    renderAuthArea();
  })();

  /* ---- registration modal (built once, appended to body) ---- */
  function buildAuthModal(){
    if (document.getElementById("auth-modal")) return;
    var wrap = document.createElement("div");
    wrap.innerHTML =
    '<div class="modal" id="auth-modal" role="dialog" aria-modal="true" aria-labelledby="auth-title">'
    + '<div class="modal__overlay" data-pm-close></div>'
    + '<div class="modal__panel" style="width:min(560px,100%)">'
    + '<button class="modal__close" data-pm-close aria-label="Закрити"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>'
    + '<h3 id="auth-title">Вхід та реєстрація</h3>'
    + '<div class="auth-tabs"><button type="button" data-auth-tab="login" class="active">Вхід</button><button type="button" data-auth-tab="register">Реєстрація</button></div>'
    /* LOGIN */
    + '<form id="login-form" class="auth-pane" novalidate>'
    + '<p class="modal__sub" style="margin-top:0">Увійдіть у свій кабінет за email, який ви вказали при реєстрації.</p>'
    + '<div class="field"><label for="lg-email">Email <span class="req-star">*</span></label><input id="lg-email" type="email" required autocomplete="email" placeholder="you@email.com"></div>'
    + '<div class="field"><label for="lg-pass">Пароль</label><input id="lg-pass" type="password" autocomplete="current-password" placeholder="••••••••"></div>'
    + '<div class="auth-forgot-row"><button type="button" class="auth-link" id="lg-forgot">Забули пароль?</button></div>'
    + '<div class="auth-err" id="login-err">Акаунт з таким email не знайдено. Зареєструйтесь як партнер.</div>'
    + '<button class="btn btn--primary btn--block" type="submit" style="margin-top:18px">Увійти</button>'
    + '<p class="auth-switch">Ще не маєте акаунту? <button type="button" data-auth-tab="register">Стати партнером</button></p>'
    + '</form>'
    /* FORGOT PASSWORD */
    + '<form id="forgot-form" class="auth-pane" hidden novalidate>'
    + '<p class="modal__sub" style="margin-top:0">Введіть email, вказаний при реєстрації — ми надішлемо на нього лист із посиланням для встановлення нового пароля.</p>'
    + '<div class="field"><label for="fg-email">Email <span class="req-star">*</span></label><input id="fg-email" type="email" required autocomplete="email" placeholder="you@email.com"></div>'
    + '<div class="auth-err" id="forgot-err">Акаунт з таким email не знайдено. Перевірте адресу або зареєструйтесь.</div>'
    + '<button class="btn btn--primary btn--block" type="submit" style="margin-top:18px">Надіслати лист для скидання</button>'
    + '<p class="auth-switch"><button type="button" data-auth-tab="login">← Повернутися до входу</button></p>'
    + '</form>'
    /* FORGOT SUCCESS */
    + '<div class="form-ok" id="forgot-ok" style="display:none">'
    + '<svg viewBox="0 0 24 24" width="46" fill="none" stroke="#2FA36B" stroke-width="2" style="margin:0 auto 10px"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/></svg>'
    + '<p style="font-size:18px;color:#1A1F2D">Лист надіслано</p>'
    + '<p style="color:#5B6472;font-weight:400;margin:6px 0 18px">Інструкції для встановлення нового пароля надіслано на <b id="forgot-ok-email"></b>. Перевірте вхідні та теку «Спам».</p>'
    + '<button class="btn btn--ghost" type="button" data-auth-tab="login">Повернутися до входу</button>'
    + '</div>'
    /* REGISTER */
    + '<form id="partner-form" class="auth-pane" hidden novalidate>'
    + '<p class="modal__sub" style="margin-top:0">Реєстрація партнера ENKO. Обов’язкові поля — <b>Email</b> і <b>Телефон</b>; решту можна додати пізніше у кабінеті.</p>'
    + '<div class="field"><label>Тип особи</label><div class="seg">'
    + '<label><input type="radio" name="pm-entity" value="fiz" checked><span>Фізична особа</span></label>'
    + '<label><input type="radio" name="pm-entity" value="ur"><span>Юридична особа</span></label>'
    + '</div></div>'
    + '<div class="field"><label for="pm-coop">Який тип співпраці цікавить</label><select id="pm-coop">'
    + '<option>Постачання обладнання (роздріб/дилер)</option>'
    + '<option>Проєктування великих систем</option>'
    + '<option>Монтаж / підрядні роботи</option>'
    + '<option>Дилерство та торгівля</option>'
    + '<option>Інше</option>'
    + '</select></div>'
    + '<div class="field-row"><div class="field"><label for="pm-fn">Ім’я</label><input id="pm-fn" type="text" autocomplete="given-name" placeholder="Ім’я"></div>'
    + '<div class="field"><label for="pm-ln">Прізвище</label><input id="pm-ln" type="text" autocomplete="family-name" placeholder="Прізвище"></div></div>'
    + '<div class="field"><label for="pm-email">Email <span class="req-star">*</span></label><input id="pm-email" type="email" required autocomplete="email" placeholder="you@email.com"></div>'
    + '<div class="field"><label for="pm-phone">Телефон <span class="req-star">*</span></label><input id="pm-phone" type="tel" required autocomplete="tel" placeholder="+380 __ ___ __ __"></div>'
    + '<div class="field"><label for="pm-pass">Пароль <span class="sub" style="font-weight:400">(необов’язково — можна встановити пізніше)</span></label><input id="pm-pass" type="password" autocomplete="new-password" placeholder="Мінімум 6 символів"></div>'
    + '<div class="pm-ur">'
    + '<div class="field"><label for="pm-company">Назва компанії</label><input id="pm-company" type="text" placeholder="ТОВ «...»"></div>'
    + '<div class="field"><label for="pm-edrpou">ЄДРПОУ / ІПН</label><input id="pm-edrpou" type="text" inputmode="numeric" placeholder="8 цифр"></div>'
    + '</div>'
    + '<div class="field"><label for="pm-city">Місто</label><input id="pm-city" type="text" autocomplete="address-level2" placeholder="Місто"></div>'
    + '<div class="field"><label for="pm-msg">Коментар</label><textarea id="pm-msg" placeholder="Коротко опишіть напрям співпраці або запит"></textarea></div>'
    + '<button class="btn btn--primary btn--block" type="submit" style="margin-top:18px">Зареєструватися</button>'
    + '<p class="auth-switch">Вже зареєстровані? <button type="button" data-auth-tab="login">Увійти</button></p>'
    + '</form>'
    /* SUCCESS */
    + '<div class="form-ok" id="auth-ok">'
    + '<svg viewBox="0 0 24 24" width="46" fill="none" stroke="#2FA36B" stroke-width="2" style="margin:0 auto 10px"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>'
    + '<p style="font-size:18px;color:#1A1F2D" id="auth-ok-title">Вітаємо!</p>'
    + '<p style="color:#5B6472;font-weight:400;margin:6px 0 18px">Вам доступний особистий кабінет із чатом підтримки та історією заявок.</p>'
    + '<a href="account.html" class="btn btn--primary">Перейти в кабінет</a>'
    + '</div>'
    + '</div></div>';
    document.body.appendChild(wrap.firstChild);

    var modalEl = document.getElementById("auth-modal");
    function close(){ modalEl.classList.remove("open"); document.body.style.overflow = ""; }
    $$("[data-pm-close]", modalEl).forEach(function (b){ b.addEventListener("click", close); });
    document.addEventListener("keydown", function (e){ if (e.key === "Escape") close(); });

    // tab switching (also used by the inline switch links)
    modalEl.addEventListener("click", function (e){
      var t = e.target.closest("[data-auth-tab]"); if (!t) return;
      setAuthTab(t.getAttribute("data-auth-tab"));
    });
    // entity toggle
    $$('input[name="pm-entity"]', modalEl).forEach(function (r){
      r.addEventListener("change", function (){ $(".pm-ur", modalEl).classList.toggle("show", r.value === "ur" && r.checked); });
    });

    function showSuccess(title){
      $("#login-form").style.display = "none";
      $("#partner-form").style.display = "none";
      $(".auth-tabs", modalEl).style.display = "none";
      var t = $("#auth-ok-title"); if (t) t.textContent = title;
      $("#auth-ok").style.display = "block";
    }

    // login submit
    $("#login-form").addEventListener("submit", function (e){
      e.preventDefault();
      var email = $("#lg-email").value.trim();
      var pass = $("#lg-pass") ? $("#lg-pass").value : "";
      var err = $("#login-err");
      if (!email){ $("#lg-email").focus(); return; }
      var acc = findAccount(email);
      if (!acc){ err.textContent = "Акаунт з таким email не знайдено. Зареєструйтесь як партнер."; err.classList.add("show"); return; }
      if (acc.password && pass && pass !== acc.password){ err.textContent = "Невірний пароль. Скористайтесь «Забули пароль?»."; err.classList.add("show"); return; }
      err.classList.remove("show");
      setUser(acc); renderAuthArea(); showSuccess("З поверненням, " + (acc.firstName || "партнере") + "!");
    });

    // forgot password
    var forgotBtn = $("#lg-forgot");
    if (forgotBtn) forgotBtn.addEventListener("click", function (){
      setAuthTab("forgot");
      var fe = $("#fg-email"); if (fe) setTimeout(function (){ fe.focus(); }, 50);
    });
    $("#forgot-form").addEventListener("submit", function (e){
      e.preventDefault();
      var email = $("#fg-email").value.trim();
      var err = $("#forgot-err");
      if (!email){ $("#fg-email").focus(); return; }
      var acc = findAccount(email);
      if (!acc){ err.classList.add("show"); return; }
      err.classList.remove("show");
      // прототип: генеруємо тимчасовий пароль і зберігаємо (у WP — лист із посиланням-токеном)
      acc.password = genTempPass(); upsertAccount(acc);
      var em = $("#forgot-ok-email"); if (em) em.textContent = email;
      $("#login-form").style.display = "none";
      $("#partner-form").style.display = "none";
      $("#forgot-form").style.display = "none";
      $(".auth-tabs", modalEl).style.display = "none";
      $("#forgot-ok").style.display = "block";
    });

    // register submit
    $("#partner-form").addEventListener("submit", function (e){
      e.preventDefault();
      var email = $("#pm-email").value.trim();
      var phone = $("#pm-phone").value.trim();
      if (!email){ $("#pm-email").focus(); return; }
      if (!phone){ $("#pm-phone").focus(); return; }
      var entity = ($('input[name="pm-entity"]:checked', modalEl) || {}).value || "fiz";
      var user = {
        firstName: $("#pm-fn").value.trim(), lastName: $("#pm-ln").value.trim(),
        email: email, phone: $("#pm-phone").value.trim(), entity: entity, coop: $("#pm-coop").value,
        company: $("#pm-company").value.trim(), edrpou: $("#pm-edrpou").value.trim(),
        city: $("#pm-city").value.trim(), note: $("#pm-msg").value.trim(),
        password: ($("#pm-pass") ? $("#pm-pass").value : ""),
        registeredAt: new Date().toLocaleDateString("uk-UA")
      };
      upsertAccount(user); setUser(user); renderAuthArea(); showSuccess("Вітаємо, ви зареєстровані!");
    });
  }
  function setAuthTab(mode){
    var modalEl = document.getElementById("auth-modal"); if (!modalEl) return;
    var tabs = $(".auth-tabs", modalEl);
    // restore base panes (in case a success screen was shown)
    var ok = $("#auth-ok"); if (ok) ok.style.display = "none";
    var fok = $("#forgot-ok"); if (fok) fok.style.display = "none";
    if (tabs) tabs.style.display = (mode === "forgot") ? "none" : "flex";
    ["#login-form", "#partner-form", "#forgot-form"].forEach(function (s){ var f = $(s); if (f) f.style.display = ""; });
    if (tabs) $$("[data-auth-tab]", tabs).forEach(function (b){ b.classList.toggle("active", b.getAttribute("data-auth-tab") === mode); });
    $("#login-form").hidden = mode !== "login";
    $("#partner-form").hidden = mode !== "register";
    var ff = $("#forgot-form"); if (ff) ff.hidden = mode !== "forgot";
    var le = $("#login-err"); if (le) le.classList.remove("show");
    var fe = $("#forgot-err"); if (fe) fe.classList.remove("show");
  }
  function openAuth(mode){
    buildAuthModal();
    var m = document.getElementById("auth-modal");
    // reset
    $("#login-form").style.display = ""; $("#partner-form").style.display = "";
    $(".auth-tabs", m).style.display = "flex";
    $("#auth-ok").style.display = "none";
    $("#login-err").classList.remove("show");
    setAuthTab(mode || "login");
    m.classList.add("open"); document.body.style.overflow = "hidden";
    var first = mode === "register" ? $("#pm-email") : $("#lg-email");
    if (first) setTimeout(function (){ first.focus(); }, 60);
  }
  document.addEventListener("click", function (e){
    if (e.target.closest("[data-auth-open]")) { e.preventDefault(); openAuth("login"); }
    if (e.target.closest("[data-partner-open]")) { e.preventDefault(); openAuth("register"); }
    if (e.target.closest("[data-logout]")) { e.preventDefault(); logoutUser(); window.location.href = "index.html"; }
  });

  /* ---- ACCOUNT / CABINET page ---- */
  var accountPage = document.getElementById("account-page");
  if (accountPage){
    var gate = document.getElementById("account-gate");
    var app = document.getElementById("account-app");
    var u = getUser();
    if (u){
      // повна синхронізація: акаунт-реєстр (enko_accounts_v1) — джерело правди.
      // Підтягуємо знижку/пароль/поля, які менеджер міг змінити, поки користувач був відсутній.
      var rec = findAccount(u.email);
      if (rec){ u = Object.assign({}, u, rec); setUser(u); }
    }
    if (!u){
      if (gate) gate.style.display = "block";
      if (app) app.style.display = "none";
    } else {
      if (gate) gate.style.display = "none";
      if (app){ app.style.display = "block"; renderAccount(u, app); }
    }
  }

  function renderAccount(u, app){
    var orders = getOrders();
    var ordersHTML = orders.length ? orders.map(function (o){
      var od = o.discount || 0;
      var rows = o.items.map(function (i){
        var nm = esc(i.name + (i.ver ? " (" + i.ver + ")" : ""));
        var code = i.id ? esc(i.id) : "—";
        var price = (i.uah != null) ? fmt(i.uah) + " грн" : "—";
        var dcell = od ? "−" + od + "%" : "—";
        return '<tr><td class="oi-qty">×' + i.qty + '</td><td class="oi-name">' + nm + '</td><td class="oi-code">' + code + '</td><td class="oi-price">' + price + '</td><td class="oi-disc">' + dcell + '</td></tr>';
      }).join("");
      return '<div class="order-row">'
        + '<div class="order-row__head"><div class="order-row__info"><b>Заявка ' + esc(o.id) + '</b><span class="order-row__date">' + esc(o.date) + '</span></div>'
        + '<div class="order-row__right"><span class="ostatus">' + esc(o.status) + '</span><div class="oprice">' + fmt(o.uah) + ' грн</div>'
        + (od ? '<div class="odisc">Знижка −' + od + '%</div>' : '') + '</div></div>'
        + '<table class="order-items"><thead><tr><th class="oi-qty">К-сть</th><th>Позиція</th><th>Код</th><th class="oi-price">Ціна</th><th class="oi-disc">Знижка</th></tr></thead>'
        + '<tbody>' + rows + '</tbody></table></div>';
    }).join("") : '<p class="acc-empty">Поки що немає заявок. Додайте техніку в заявку — і вона з’явиться тут.</p>';

    var entityLabel = u.entity === "ur" ? "Юридична особа" : "Фізична особа";
    app.innerHTML =
      '<div class="account-hero"><span class="ava">' + esc(userInitials(u)) + '</span>'
      + '<div><h1>Вітаємо, ' + (esc(u.firstName) || "партнере") + '!</h1><p>' + esc(u.email) + ' · ' + entityLabel + '</p></div>'
      + '<button class="btn btn--ghost btn--m logout" data-logout type="button">Вийти</button></div>'
      + '<div class="account-layout"><div class="account-main">'
      /* profile */
      + '<div class="acc-card"><div class="acc-card__head"><h2><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M5 21v-2a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v2"/></svg>Особисті дані</h2>'
      + '<button class="acc-edit-btn" id="acc-edit-toggle" type="button"><svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>Редагувати</button></div>'
      + '<dl class="acc-view" id="acc-view"></dl>'
      + '<form id="acc-form" class="acc-form" hidden>'
      + '<div class="field"><label>Ім’я</label><input id="a-fn" type="text" value="' + esc(u.firstName) + '"></div>'
      + '<div class="field"><label>Прізвище</label><input id="a-ln" type="text" value="' + esc(u.lastName) + '"></div>'
      + '<div class="field"><label>Email</label><input id="a-email" type="email" value="' + esc(u.email) + '"></div>'
      + '<div class="field"><label>Телефон</label><input id="a-phone" type="tel" value="' + esc(u.phone) + '"></div>'
      + '<div class="field"><label>Тип співпраці</label><input id="a-coop" type="text" value="' + esc(u.coop) + '"></div>'
      + '<div class="field"><label>Місто</label><input id="a-city" type="text" value="' + esc(u.city) + '"></div>'
      + '<div class="field"><label>Назва компанії</label><input id="a-company" type="text" value="' + esc(u.company) + '"></div>'
      + '<div class="field"><label>ЄДРПОУ / ІПН</label><input id="a-edrpou" type="text" value="' + esc(u.edrpou) + '"></div>'
      + '<div class="acc-pass" id="acc-pass-wrap"><button type="button" class="btn btn--ghost btn--s" id="acc-pass-toggle"><svg viewBox="0 0 24 24" width="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>Змінити пароль</button>'
      + '<div class="field" id="acc-pass-field" hidden><label for="a-pass">Новий пароль</label><input id="a-pass" type="password" autocomplete="new-password" placeholder="Мінімум 6 символів"></div></div>'
      + '</form>'
      + '<div class="acc-actions" id="acc-edit-actions" hidden><button class="btn btn--primary btn--m" id="acc-save" type="button">Зберегти зміни</button><button class="btn btn--ghost btn--m" id="acc-cancel" type="button">Скасувати</button><span class="acc-saved" id="acc-saved">Збережено ✓</span></div>'
      + '</div>'
      /* orders */
      + '<div class="acc-card"><h2><svg viewBox="0 0 24 24"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/></svg>Історія заявок</h2>'
      + '<p class="sub">Заявки, які ви оформили на сайті.</p>' + ordersHTML + '</div>'
      + '</div>'
      /* sidebar: manager + chat */
      + '<aside class="account-side">'
      + '<div class="acc-card"><h2 class="mgr-head"><svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4"/><path d="M12 3l8 4v5c0 5-3.4 8.4-8 9-4.6-.6-8-4-8-9V7z"/></svg>Ваш менеджер</h2>'
      + '<div class="mgr"><span class="ava">' + (MANAGER.name.split(" ").map(function(w){return w[0];}).join("")) + '</span><div><b>' + MANAGER.name + '</b><span>' + MANAGER.role + '</span></div></div>'
      + '<div class="mgr-contacts">'
      + '<a href="tel:' + MANAGER.phone.replace(/[^+\d]/g,"") + '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>' + MANAGER.phone + '</a>'
      + '<a href="mailto:' + MANAGER.email + '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 5L2 7"/></svg>' + MANAGER.email + '</a>'
      + '<a href="https://t.me/' + MANAGER.tg.replace(/^@/, "") + '" target="_blank" rel="noopener" aria-label="Telegram менеджера"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></svg>' + MANAGER.tg + '</a>'
      + '</div>'
      + '<div class="mgr-rate"><span>Курс розрахунку вартості</span><b>' + getRate() + ' грн / €</b></div>'
      + '</div>'
      + '<div class="acc-card"><h2><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Чат з підтримкою</h2>'
      + '<div class="chat-log" id="chat-log"></div>'
      + '<form class="chat-input qp-chat-input" id="chat-form"><div class="qp-input-wrap"><textarea id="chat-msg" maxlength="200" rows="1" placeholder="Ваше повідомлення" autocomplete="off"></textarea><span class="qp-counter" id="chat-counter">0 / 200</span></div><button class="chat-send" type="submit" aria-label="Надіслати"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></svg></button></form>'
      + '</div>'
      + '</aside></div>';

    // profile view/edit toggle
    function renderProfileView(){
      var view = document.getElementById("acc-view"); if (!view) return;
      function row(label, val){ var empty = !val; return '<div class="acc-view__row"><dt>' + label + '</dt><dd' + (empty ? ' class="empty"' : '') + '>' + (empty ? "—" : esc(val)) + '</dd></div>'; }
      view.innerHTML = row("Ім’я", u.firstName) + row("Прізвище", u.lastName) + row("Email", u.email)
        + row("Телефон", u.phone) + row("Тип особи", u.entity === "ur" ? "Юридична особа" : "Фізична особа")
        + row("Тип співпраці", u.coop) + row("Місто", u.city) + row("Назва компанії", u.company) + row("ЄДРПОУ / ІПН", u.edrpou);
    }
    function setEditMode(on){
      var view = document.getElementById("acc-view"), form = document.getElementById("acc-form");
      var actions = document.getElementById("acc-edit-actions"), toggle = document.getElementById("acc-edit-toggle");
      if (view) view.hidden = on; if (form) form.hidden = !on;
      if (actions) actions.hidden = !on; if (toggle) toggle.hidden = on;
      // reset the password sub-field whenever we enter/leave edit mode
      var pf = document.getElementById("acc-pass-field"), pt = document.getElementById("acc-pass-toggle"), pi = document.getElementById("a-pass");
      if (pf) pf.hidden = true; if (pt) pt.hidden = false; if (pi) pi.value = "";
    }
    renderProfileView();
    var editToggle = document.getElementById("acc-edit-toggle");
    if (editToggle) editToggle.addEventListener("click", function (){ setEditMode(true); });
    var passToggle = document.getElementById("acc-pass-toggle");
    if (passToggle) passToggle.addEventListener("click", function (){
      var pf = document.getElementById("acc-pass-field");
      if (pf){ pf.hidden = false; passToggle.hidden = true; var pi = document.getElementById("a-pass"); if (pi) pi.focus(); }
    });
    var cancelBtn = document.getElementById("acc-cancel");
    if (cancelBtn) cancelBtn.addEventListener("click", function (){
      $("#a-fn").value = u.firstName || ""; $("#a-ln").value = u.lastName || ""; $("#a-email").value = u.email || "";
      $("#a-phone").value = u.phone || ""; $("#a-coop").value = u.coop || ""; $("#a-city").value = u.city || "";
      $("#a-company").value = u.company || ""; $("#a-edrpou").value = u.edrpou || "";
      setEditMode(false);
    });

    // wire profile save
    var saveBtn = document.getElementById("acc-save");
    if (saveBtn) saveBtn.addEventListener("click", function (){
      var prevEmail = u.email; // login key may change — remove the old record, don't duplicate
      // merge form edits ONTO the canonical account record so manager-set fields
      // (discount, extra, password) survive a profile edit instead of being wiped.
      var existing = findAccount(prevEmail) || {};
      u = Object.assign({}, existing, u);
      u.firstName = $("#a-fn").value.trim(); u.lastName = $("#a-ln").value.trim();
      u.email = $("#a-email").value.trim(); u.phone = $("#a-phone").value.trim();
      u.coop = $("#a-coop").value.trim(); u.city = $("#a-city").value.trim();
      u.company = $("#a-company").value.trim(); u.edrpou = $("#a-edrpou").value.trim();
      var np = $("#a-pass"); if (np && np.value.trim()) u.password = np.value.trim();
      setUser(u); upsertAccount(u, prevEmail); renderAuthArea();
      var hero = $(".account-hero h1"); if (hero) hero.textContent = "Вітаємо, " + (u.firstName || "партнере") + "!";
      var ava = $(".account-hero .ava"); if (ava) ava.textContent = userInitials(u);
      renderProfileView(); setEditMode(false);
      var saved = document.getElementById("acc-saved"); if (saved){ saved.classList.add("show"); setTimeout(function (){ saved.classList.remove("show"); }, 1800); }
    });

    // support chat — unified store (account-tied when logged in; per-browser otherwise), synced with the floating quick-pop
    if (window.ENKO_CHAT) window.ENKO_CHAT.ensureGreeting();
    function renderChat(){ if (window.ENKO_CHAT) window.ENKO_CHAT.render(document.getElementById("chat-log")); }
    renderChat();
    var chatMsg = document.getElementById("chat-msg");
    var chatCard = chatMsg && chatMsg.closest(".acc-card");
    var chatForm = document.getElementById("chat-form");
    var chatCounter = document.getElementById("chat-counter");
    function chatUpd(){ if (chatCounter && chatMsg) chatCounter.textContent = chatMsg.value.length + " / 200"; }
    if (chatMsg){
      chatUpd();
      chatMsg.addEventListener("input", chatUpd);
      chatMsg.addEventListener("focus", function (){ if (chatCard) chatCard.classList.add("chat-active"); if (chatForm) chatForm.classList.add("is-typing"); });
      chatMsg.addEventListener("blur", function (){ if (!chatMsg.value.trim()){ if (chatCard) chatCard.classList.remove("chat-active"); if (chatForm) chatForm.classList.remove("is-typing"); } });
      chatMsg.addEventListener("keydown", function (e){ if (e.key === "Enter" && !e.shiftKey){ e.preventDefault(); if (chatForm.requestSubmit) chatForm.requestSubmit(); else chatForm.dispatchEvent(new Event("submit", { cancelable: true })); } });
    }
    if (chatForm) chatForm.addEventListener("submit", function (e){
      e.preventDefault();
      var inp = document.getElementById("chat-msg"); var t = inp.value.trim(); if (!t || !window.ENKO_CHAT) return;
      window.ENKO_CHAT.add("user", t); inp.value = ""; renderChat();
      setTimeout(function (){ window.ENKO_CHAT.add("support", window.ENKO_CHAT.reply); renderChat(); }, 900);
    });
    window.addEventListener("enko:chat", renderChat);
    window.addEventListener("storage", function (e){ if (e.key === "enko_chat_v1" || e.key === "enko_accounts_v1" || e.key === "enko_user_v1") renderChat(); });
  }

  /* ---------- TWEAKS PANEL (host protocol) ---------- */
  var CTA = {
    violet: { c: "#6E54A6", h: "#5d4691", ink:"#fff" },
    amber:  { c: "#F5A623", h: "#e0940f", ink:"#1A1F2D" }
  };
  var tw = $("#tweaks");
  var state = { cta: "violet" };
  try { var saved = JSON.parse(localStorage.getItem("enko_tweaks") || "{}"); Object.assign(state, saved); } catch (e) {}
  function applyTweaks() {
    var c = CTA[state.cta] || CTA.violet;
    document.documentElement.style.setProperty("--cta", c.c);
    document.documentElement.style.setProperty("--cta-hover", c.h);
    document.documentElement.style.setProperty("--cta-ink", c.ink);
    $$("#tweaks [data-cta]").forEach(function (b) { b.classList.toggle("active", b.getAttribute("data-cta") === state.cta); });
    try { localStorage.setItem("enko_tweaks", JSON.stringify(state)); } catch (e) {}
  }
  if (tw) {
    $$("#tweaks [data-cta]").forEach(function (b) {
      b.addEventListener("click", function () { state.cta = b.getAttribute("data-cta"); applyTweaks(); });
    });
    var twClose = $("#tweaks-close");
    if (twClose) twClose.addEventListener("click", function () { tw.classList.remove("visible"); notifyHost(false); });
  }
  applyTweaks();

  // Tweaks host protocol: toolbar toggles visibility
  function notifyHost(open){ try{ parent.postMessage({ type:"tweaks:visibility", visible:open }, "*"); }catch(e){} }
  window.addEventListener("message", function (e) {
    var d = e.data || {};
    if (d.type === "tweaks:toggle" || d.type === "tweaks:show" || d.type === "tweaks:open") {
      if (tw) tw.classList.toggle("visible", d.visible !== false);
    }
    if (d.type === "tweaks:hide" || d.type === "tweaks:close") { if (tw) tw.classList.remove("visible"); }
  });
  try { parent.postMessage({ type:"tweaks:ready" }, "*"); } catch (e) {}

})();
