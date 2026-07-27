/* ENKO — кабінет користувача (реальний WordPress).
   Перекриває localStorage-логіку прототипного enko.js:
   • auth-модалка (вхід/реєстрація/скидання) → REST enko/v1/auth/*
   • чип у шапці (#auth-area / #auth-area-m)
   • сторінка кабінету #enko-account-root ← account/me (профіль, знижка, менеджер,
     історія заявок, чат із polling)
   Тригери [data-auth-open]/[data-partner-open]/[data-logout] перехоплюються в
   CAPTURE-фазі + stopImmediatePropagation, щоб заблокувати делеговані bubble-
   слухачі enko.js (gotcha #9). */
(function () {
  "use strict";
  var CFG = window.ENKO_CFG || {};
  var REST = CFG.restUrl || "/wp-json/enko/v1/";

  /* ---------- утиліти ---------- */
  function api(path, method, body) {
    return fetch(REST + path, {
      method: method || "GET",
      headers: { "Content-Type": "application/json", "X-WP-Nonce": CFG.nonce || "" },
      credentials: "include",
      body: body ? JSON.stringify(body) : undefined
    }).then(function (r) { return r.json().then(function (j) { j._status = r.status; return j; }); });
  }
  function esc(s) { return String(s == null ? "" : s).replace(/[&<>"]/g, function (c) { return ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" })[c]; }); }
  function fmt(n) { return ("" + Math.round(+n || 0)).replace(/\B(?=(\d{3})+(?!\d))/g, " "); }
  function initials(name) {
    var p = String(name || "").trim().split(/\s+/);
    if (!p[0]) return "EN";
    return ((p[0][0] || "") + (p[1] ? p[1][0] : "")).toUpperCase();
  }
  function $(s, r) { return (r || document).querySelector(s); }

  /* ---------- чип у шапці ---------- */
  function renderAuthArea() {
    var nm = CFG.userName || "Кабінет";
    var chip = '<a class="account-chip" href="' + esc(CFG.accountUrl) + '" aria-label="Мій кабінет"><span class="ava">' + esc(initials(nm)) + '</span><span>' + esc(nm) + '</span></a>';
    var loginBtn = '<button class="auth-trigger" data-auth-open type="button"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M5 21v-2a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v2"/></svg>Увійти</button>';
    var slot = document.getElementById("auth-area");
    if (slot) slot.innerHTML = CFG.loggedIn ? chip : loginBtn;
    var chipM = '<a class="mnav-link mnav-account" href="' + esc(CFG.accountUrl) + '"><span class="ava">' + esc(initials(nm)) + '</span><span>Мій кабінет</span></a>';
    var loginM = '<button class="btn btn--ghost btn--block" data-auth-open type="button" style="margin-top:10px">Увійти або зареєструватися</button>';
    var slotM = document.getElementById("auth-area-m");
    if (slotM) slotM.innerHTML = CFG.loggedIn ? chipM : loginM;
  }

  /* ---------- auth-модалка ---------- */
  function buildAuthModal() {
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
      + '<div class="auth-err" id="login-err"></div>'
      + '<button class="btn btn--primary btn--block" type="submit" style="margin-top:18px">Увійти</button>'
      + '<p class="auth-switch">Ще не маєте акаунту? <button type="button" data-auth-tab="register">Стати партнером</button></p>'
      + '</form>'
      /* FORGOT */
      + '<form id="forgot-form" class="auth-pane" hidden novalidate>'
      + '<p class="modal__sub" style="margin-top:0">Введіть email, вказаний при реєстрації — ми надішлемо на нього лист із посиланням для встановлення нового пароля.</p>'
      + '<div class="field"><label for="fg-email">Email <span class="req-star">*</span></label><input id="fg-email" type="email" required autocomplete="email" placeholder="you@email.com"></div>'
      + '<div class="auth-err" id="forgot-err"></div>'
      + '<button class="btn btn--primary btn--block" type="submit" style="margin-top:18px">Надіслати лист для скидання</button>'
      + '<p class="auth-switch"><button type="button" data-auth-tab="login">← Повернутися до входу</button></p>'
      + '</form>'
      /* FORGOT OK */
      + '<div class="form-ok" id="forgot-ok" style="display:none">'
      + '<svg viewBox="0 0 24 24" width="46" fill="none" stroke="#2FA36B" stroke-width="2" style="margin:0 auto 10px"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/></svg>'
      + '<p style="font-size:18px;color:#1A1F2D">Лист надіслано</p>'
      + '<p style="color:#5B6472;font-weight:400;margin:6px 0 18px">Якщо акаунт із такою адресою існує, на нього надіслано інструкції для встановлення нового пароля. Перевірте вхідні та теку «Спам».</p>'
      + '<button class="btn btn--ghost" type="button" data-auth-tab="login">Повернутися до входу</button>'
      + '</div>'
      /* REGISTER */
      + '<form id="partner-form" class="auth-pane" hidden novalidate>'
      + '<p class="modal__sub" style="margin-top:0">Реєстрація партнера ENKO. Обовʼязкові поля — <b>Email</b> і <b>Телефон</b>; решту можна додати пізніше у кабінеті.</p>'
      + '<div style="position:absolute;left:-9999px" aria-hidden="true"><input id="pm-hp" type="text" tabindex="-1" autocomplete="off"></div>'
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
      + '<div class="field-row"><div class="field"><label for="pm-fn">Імʼя</label><input id="pm-fn" type="text" autocomplete="given-name" placeholder="Імʼя"></div>'
      + '<div class="field"><label for="pm-ln">Прізвище</label><input id="pm-ln" type="text" autocomplete="family-name" placeholder="Прізвище"></div></div>'
      + '<div class="field"><label for="pm-email">Email <span class="req-star">*</span></label><input id="pm-email" type="email" required autocomplete="email" placeholder="you@email.com"></div>'
      + '<div class="field"><label for="pm-phone">Телефон <span class="req-star">*</span></label><input id="pm-phone" type="tel" required autocomplete="tel" placeholder="+380 __ ___ __ __"></div>'
      + '<div class="field"><label for="pm-pass">Пароль <span class="sub" style="font-weight:400">(необовʼязково — можна встановити пізніше)</span></label><input id="pm-pass" type="password" autocomplete="new-password" placeholder="Мінімум 6 символів"></div>'
      + '<div class="pm-ur">'
      + '<div class="field"><label for="pm-company">Назва компанії</label><input id="pm-company" type="text" placeholder="ТОВ «...»"></div>'
      + '<div class="field"><label for="pm-edrpou">ЄДРПОУ / ІПН</label><input id="pm-edrpou" type="text" inputmode="numeric" placeholder="8 цифр"></div>'
      + '</div>'
      + '<div class="field"><label for="pm-city">Місто</label><input id="pm-city" type="text" autocomplete="address-level2" placeholder="Місто"></div>'
      + '<div class="field"><label for="pm-msg">Коментар</label><textarea id="pm-msg" placeholder="Коротко опишіть напрям співпраці або запит"></textarea></div>'
      + '<div class="auth-err" id="reg-err"></div>'
      + '<button class="btn btn--primary btn--block" type="submit" style="margin-top:18px">Зареєструватися</button>'
      + '<p class="auth-switch">Вже зареєстровані? <button type="button" data-auth-tab="login">Увійти</button></p>'
      + '</form>'
      + '</div></div>';
    document.body.appendChild(wrap.firstChild);

    var modalEl = document.getElementById("auth-modal");
    function close() { modalEl.classList.remove("open"); document.body.style.overflow = ""; }
    Array.prototype.forEach.call(modalEl.querySelectorAll("[data-pm-close]"), function (b) { b.addEventListener("click", close); });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape") close(); });
    modalEl.addEventListener("click", function (e) {
      var t = e.target.closest("[data-auth-tab]"); if (!t) return;
      setAuthTab(t.getAttribute("data-auth-tab"));
    });
    Array.prototype.forEach.call(modalEl.querySelectorAll('input[name="pm-entity"]'), function (r) {
      r.addEventListener("change", function () { $(".pm-ur", modalEl).classList.toggle("show", r.value === "ur" && r.checked); });
    });

    /* login */
    $("#login-form").addEventListener("submit", function (e) {
      e.preventDefault();
      var email = $("#lg-email").value.trim(), pass = $("#lg-pass").value;
      var err = $("#login-err");
      if (!email) { $("#lg-email").focus(); return; }
      err.classList.remove("show");
      api("auth/login", "POST", { email: email, password: pass }).then(function (j) {
        if (j && j.ok) { window.location.href = j.redirect || CFG.accountUrl; }
        else { err.textContent = (j && j.msg) || "Не вдалося увійти."; err.classList.add("show"); }
      });
    });
    /* forgot */
    $("#lg-forgot").addEventListener("click", function () { setAuthTab("forgot"); setTimeout(function () { var f = $("#fg-email"); if (f) f.focus(); }, 50); });
    $("#forgot-form").addEventListener("submit", function (e) {
      e.preventDefault();
      var email = $("#fg-email").value.trim();
      if (!email) { $("#fg-email").focus(); return; }
      api("auth/forgot", "POST", { email: email }).then(function () {
        $("#login-form").style.display = "none"; $("#partner-form").style.display = "none"; $("#forgot-form").style.display = "none";
        $(".auth-tabs", modalEl).style.display = "none";
        $("#forgot-ok").style.display = "block";
      });
    });
    /* register */
    $("#partner-form").addEventListener("submit", function (e) {
      e.preventDefault();
      var email = $("#pm-email").value.trim(), phone = $("#pm-phone").value.trim();
      var err = $("#reg-err"); err.classList.remove("show");
      if (!email) { $("#pm-email").focus(); return; }
      if (!phone) { $("#pm-phone").focus(); return; }
      var ent = (modalEl.querySelector('input[name="pm-entity"]:checked') || {}).value || "fiz";
      api("auth/register", "POST", {
        hp: $("#pm-hp").value,
        email: email, phone: phone, password: $("#pm-pass").value,
        firstName: $("#pm-fn").value.trim(), lastName: $("#pm-ln").value.trim(),
        entity: ent, coop: $("#pm-coop").value,
        company: $("#pm-company").value.trim(), edrpou: $("#pm-edrpou").value.trim(),
        city: $("#pm-city").value.trim(), note: $("#pm-msg").value.trim()
      }).then(function (j) {
        if (j && j.ok) { window.location.href = j.redirect || CFG.accountUrl; }
        else { err.textContent = (j && j.msg) || "Не вдалося зареєструватися."; err.classList.add("show"); }
      });
    });
  }
  function setAuthTab(mode) {
    var modalEl = document.getElementById("auth-modal"); if (!modalEl) return;
    var tabs = $(".auth-tabs", modalEl);
    $("#forgot-ok").style.display = "none";
    if (tabs) tabs.style.display = (mode === "forgot") ? "none" : "flex";
    ["#login-form", "#partner-form", "#forgot-form"].forEach(function (s) { var f = $(s); if (f) f.style.display = ""; });
    if (tabs) Array.prototype.forEach.call(tabs.querySelectorAll("[data-auth-tab]"), function (b) { b.classList.toggle("active", b.getAttribute("data-auth-tab") === mode); });
    $("#login-form").hidden = mode !== "login";
    $("#partner-form").hidden = mode !== "register";
    $("#forgot-form").hidden = mode !== "forgot";
    var le = $("#login-err"); if (le) le.classList.remove("show");
    var re = $("#reg-err"); if (re) re.classList.remove("show");
  }
  function openAuth(mode) {
    buildAuthModal();
    var m = document.getElementById("auth-modal");
    $("#forgot-ok").style.display = "none";
    $(".auth-tabs", m).style.display = "flex";
    setAuthTab(mode || "login");
    m.classList.add("open"); document.body.style.overflow = "hidden";
    var first = mode === "register" ? $("#pm-email") : $("#lg-email");
    if (first) setTimeout(function () { first.focus(); }, 60);
  }
  function doLogout() {
    api("auth/logout", "POST", {}).then(function (j) { window.location.href = (j && j.redirect) || "/"; });
  }

  /* CAPTURE-перехоплення тригерів (блокує bubble-слухачі enko.js) */
  document.addEventListener("click", function (e) {
    if (e.target.closest("[data-auth-open]")) { e.preventDefault(); e.stopImmediatePropagation(); openAuth("login"); return; }
    if (e.target.closest("[data-partner-open]")) { e.preventDefault(); e.stopImmediatePropagation(); openAuth("register"); return; }
    if (e.target.closest("[data-logout]")) { e.preventDefault(); e.stopImmediatePropagation(); doLogout(); return; }
  }, true);

  /* ---------- сторінка кабінету ---------- */
  function renderAccountPage(root) {
    var gate = root.querySelector(".account-gate");
    var app = document.getElementById("enko-account-app");
    if (!CFG.loggedIn) { if (gate) gate.style.display = "block"; if (app) app.style.display = "none"; return; }
    if (gate) gate.style.display = "none";
    if (app) app.style.display = "block";
    api("account/me").then(function (j) {
      if (!j || !j.ok) { if (gate) gate.style.display = "block"; if (app) app.style.display = "none"; return; }
      renderApp(app, j);
    });
  }

  function ordersHTML(orders) {
    if (!orders || !orders.length) return '<p class="acc-empty">Поки що немає заявок. Додайте техніку в заявку — і вона зʼявиться тут.</p>';
    return orders.map(function (o) {
      var rows = o.items.map(function (i) {
        return '<tr><td class="oi-qty">×' + i.qty + '</td><td class="oi-name">' + esc(i.name) + '</td><td class="oi-code">' + (i.sku ? esc(i.sku) : "—") + '</td><td class="oi-price">' + fmt(i.uah) + ' грн</td></tr>';
      }).join("");
      return '<div class="order-row">'
        + '<div class="order-row__head"><div class="order-row__info"><b>Заявка №' + esc(o.number) + '</b><span class="order-row__date">' + esc(o.date) + '</span></div>'
        + '<div class="order-row__right"><span class="ostatus">' + esc(o.status) + '</span><div class="oprice">' + fmt(o.uah) + ' грн</div></div></div>'
        + '<table class="order-items"><thead><tr><th class="oi-qty">К-сть</th><th>Позиція</th><th>Код</th><th class="oi-price">Сума</th></tr></thead><tbody>' + rows + '</tbody></table></div>';
    }).join("");
  }

  function renderApp(app, data) {
    var p = data.profile, m = data.manager;
    var name = (p.firstName || p.lastName) ? (p.firstName + " " + p.lastName).trim() : p.email;
    var entityLabel = p.entity === "ur" ? "Юридична особа" : "Фізична особа";
    var discBlock = (p.discount > 0)
      ? '<div class="acc-card acc-discount" id="acc-discount-block"><span class="acc-discount__pct">−' + p.discount + '%</span>'
        + '<div class="acc-discount__txt"><b>Ваша персональна знижка: −' + p.discount + '%</b><span>Діє на всі товари в каталозі та на сторінках товарів. Знижку встановлює ваш менеджер.</span></div></div>'
      : '';
    var mgrCard = m ? (
      '<div class="acc-card"><h2 class="mgr-head"><svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4"/><path d="M12 3l8 4v5c0 5-3.4 8.4-8 9-4.6-.6-8-4-8-9V7z"/></svg>Ваш менеджер</h2>'
      + '<div class="mgr"><span class="ava">' + esc(initials(m.name)) + '</span><div><b>' + esc(m.name) + '</b><span>' + esc(m.role) + '</span></div></div>'
      + '<div class="mgr-contacts">'
      + (m.phone ? '<a href="tel:' + esc(m.phone.replace(/[^+\d]/g, "")) + '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>' + esc(m.phone) + '</a>' : '')
      + '<a href="mailto:' + esc(m.email) + '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 5L2 7"/></svg>' + esc(m.email) + '</a>'
      + (data.tgBind && data.tgBind.deeplink
          ? '<a class="btn btn--primary btn--s mgr-tg-btn" href="' + esc(data.tgBind.deeplink) + '" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:6px;margin-top:8px;padding:7px 12px;font-size:13px;width:auto"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></svg>' + (data.tgBind.linked ? 'Відкрити чат у Telegram' : 'Написати в Telegram') + '</a>'
            + '<p class="mgr-tg-note" style="margin:6px 0 0;font-size:12px;line-height:1.5;color:#6b7280">' + (data.tgBind.linked ? '✓ Telegram під’єднано — пишіть там або тут, історія єдина.' : 'Відкриється бот: натисніть «Старт» — і чат із менеджером буде у вашому Telegram, синхронно з цим.') + '</p>'
          : '')
      + '</div>'
      + '<div class="mgr-rate"><span>Курс розрахунку вартості</span><b>' + fmt(data.rate) + ' грн / €</b></div>'
      + '</div>'
    ) : '';

    app.innerHTML =
      '<div class="account-hero"><span class="ava">' + esc(initials(name)) + '</span>'
      + '<div><h1>Вітаємо, ' + esc(p.firstName || "партнере") + '!</h1><p>' + esc(p.email) + ' · ' + entityLabel + '</p></div>'
      + '<button class="btn btn--ghost btn--m logout" data-logout type="button">Вийти</button></div>'
      + '<div class="account-layout"><div class="account-main">'
      + discBlock
      /* profile */
      + '<div class="acc-card"><div class="acc-card__head"><h2><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M5 21v-2a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v2"/></svg>Особисті дані</h2>'
      + '<button class="acc-edit-btn" id="acc-edit-toggle" type="button"><svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>Редагувати</button></div>'
      + '<dl class="acc-view" id="acc-view"></dl>'
      + '<form id="acc-form" class="acc-form" hidden>'
      + '<div class="field"><label>Імʼя</label><input id="a-fn" type="text" value="' + esc(p.firstName) + '"></div>'
      + '<div class="field"><label>Прізвище</label><input id="a-ln" type="text" value="' + esc(p.lastName) + '"></div>'
      + '<div class="field"><label>Телефон</label><input id="a-phone" type="tel" value="' + esc(p.phone) + '"></div>'
      + '<div class="field"><label>Тип співпраці</label><input id="a-coop" type="text" value="' + esc(p.coop) + '"></div>'
      + '<div class="field"><label>Місто</label><input id="a-city" type="text" value="' + esc(p.city) + '"></div>'
      + '<div class="field"><label>Назва компанії</label><input id="a-company" type="text" value="' + esc(p.company) + '"></div>'
      + '<div class="field"><label>ЄДРПОУ / ІПН</label><input id="a-edrpou" type="text" value="' + esc(p.edrpou) + '"></div>'
      + '<div class="acc-pass" id="acc-pass-wrap"><button type="button" class="btn btn--ghost btn--s" id="acc-pass-toggle"><svg viewBox="0 0 24 24" width="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>Змінити пароль</button>'
      + '<div class="field" id="acc-pass-field" hidden><label for="a-pass">Новий пароль</label><input id="a-pass" type="password" autocomplete="new-password" placeholder="Мінімум 6 символів"></div></div>'
      + '</form>'
      + '<div class="acc-actions" id="acc-edit-actions" hidden><button class="btn btn--primary btn--m" id="acc-save" type="button">Зберегти зміни</button><button class="btn btn--ghost btn--m" id="acc-cancel" type="button">Скасувати</button><span class="acc-saved" id="acc-saved">Збережено ✓</span></div>'
      + '</div>'
      /* orders */
      + '<div class="acc-card"><h2><svg viewBox="0 0 24 24"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/></svg>Історія заявок</h2>'
      + '<p class="sub">Заявки, які ви оформили на сайті.</p>' + ordersHTML(data.orders) + '</div>'
      + '</div>'
      /* sidebar */
      + '<aside class="account-side">' + mgrCard
      + '<div class="acc-card"><h2><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Чат з підтримкою</h2>'
      + '<div class="chat-log" id="chat-log"></div>'
      + '<form class="chat-input qp-chat-input" id="chat-form"><div class="qp-input-wrap"><textarea id="chat-msg" maxlength="1000" rows="1" placeholder="Ваше повідомлення" autocomplete="off"></textarea></div><button class="chat-send" type="submit" aria-label="Надіслати"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></svg></button></form>'
      + '</div>'
      + '</aside></div>';

    wireProfile(p);
    wireChat(m && m.name ? m.name : "Підтримка");
  }

  /* ---------- профіль ---------- */
  function wireProfile(p) {
    function renderView() {
      function row(l, v) { var e = !v; return '<div class="acc-view__row"><dt>' + l + '</dt><dd' + (e ? ' class="empty"' : '') + '>' + (e ? "—" : esc(v)) + '</dd></div>'; }
      $("#acc-view").innerHTML = row("Імʼя", p.firstName) + row("Прізвище", p.lastName) + row("Email", p.email)
        + row("Телефон", p.phone) + row("Тип особи", p.entity === "ur" ? "Юридична особа" : "Фізична особа")
        + row("Тип співпраці", p.coop) + row("Місто", p.city) + row("Назва компанії", p.company) + row("ЄДРПОУ / ІПН", p.edrpou);
    }
    function setEdit(on) {
      $("#acc-view").hidden = on; $("#acc-form").hidden = !on;
      $("#acc-edit-actions").hidden = !on; $("#acc-edit-toggle").hidden = on;
      var pf = $("#acc-pass-field"), pt = $("#acc-pass-toggle"), pi = $("#a-pass");
      if (pf) pf.hidden = true; if (pt) pt.hidden = false; if (pi) pi.value = "";
    }
    renderView();
    $("#acc-edit-toggle").addEventListener("click", function () { setEdit(true); });
    $("#acc-pass-toggle").addEventListener("click", function () { $("#acc-pass-field").hidden = false; this.hidden = true; $("#a-pass").focus(); });
    $("#acc-cancel").addEventListener("click", function () {
      $("#a-fn").value = p.firstName || ""; $("#a-ln").value = p.lastName || ""; $("#a-phone").value = p.phone || "";
      $("#a-coop").value = p.coop || ""; $("#a-city").value = p.city || ""; $("#a-company").value = p.company || ""; $("#a-edrpou").value = p.edrpou || "";
      setEdit(false);
    });
    $("#acc-save").addEventListener("click", function () {
      var np = $("#a-pass") ? $("#a-pass").value.trim() : "";
      var payload = {
        firstName: $("#a-fn").value.trim(), lastName: $("#a-ln").value.trim(), phone: $("#a-phone").value.trim(),
        coop: $("#a-coop").value.trim(), city: $("#a-city").value.trim(), company: $("#a-company").value.trim(), edrpou: $("#a-edrpou").value.trim()
      };
      if (np) payload.password = np;
      api("account/update", "POST", payload).then(function (j) {
        if (!j || !j.ok) return;
        if (j.reauth) { window.location.reload(); return; }
        p = j.profile;
        renderView(); setEdit(false);
        var hero = $(".account-hero h1"); if (hero) hero.textContent = "Вітаємо, " + (p.firstName || "партнере") + "!";
        var ava = $(".account-hero .ava"); if (ava) ava.textContent = initials((p.firstName + " " + p.lastName).trim() || p.email);
        var sv = $("#acc-saved"); if (sv) { sv.classList.add("show"); setTimeout(function () { sv.classList.remove("show"); }, 1800); }
      });
    });
  }

  /* ---------- чат (polling) ---------- */
  function wireChat(supportName) {
    supportName = supportName || "Підтримка";
    var log = $("#chat-log"), form = $("#chat-form"), inp = $("#chat-msg");
    if (!log || !form) return;
    var lastId = 0, empty = true;
    function add(msg) {
      if (empty) { log.innerHTML = ""; empty = false; }
      var div = document.createElement("div");
      div.className = "msg " + (msg.from === "user" ? "user" : "support");
      div.innerHTML = '<span class="who">' + (msg.from === "user" ? "Ви" : esc(supportName)) + '</span>' + esc(msg.text);
      log.appendChild(div); log.scrollTop = log.scrollHeight;
      if (msg.id && msg.id > lastId) lastId = msg.id;
    }
    function greeting() { log.innerHTML = '<div class="msg support"><span class="who">' + esc(supportName) + '</span>Вітаємо! Напишіть ваші запитання — менеджер відповість тут.</div>'; }
    function poll() {
      api("chat/poll?since=" + lastId).then(function (j) {
        if (j && j.ok && j.messages && j.messages.length) { j.messages.forEach(add); }
      });
    }
    greeting();
    poll();
    var timer = setInterval(poll, 5000);
    window.addEventListener("beforeunload", function () { clearInterval(timer); });
    if (inp) inp.addEventListener("keydown", function (e) { if (e.key === "Enter" && !e.shiftKey) { e.preventDefault(); form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event("submit", { cancelable: true })); } });
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var t = inp.value.trim(); if (!t) return;
      add({ from: "user", text: t }); inp.value = "";
      api("chat/send", "POST", { text: t }).then(function (j) { if (j && j.id && j.id > lastId) lastId = j.id; });
    });
  }

  /* ---------- персональні Telegram-кнопки (шапка/підвал/будь-де) ----------
     Кнопки бота без ?start= → при кліку тягнемо персональний deep-link:
     залогінений → токен акаунта; гість → g-<uuid>. Так і реєстровані, і гості
     ідентифікуються. Кабінет-кнопка має власний ?start= → не чіпаємо. */
  function wireTgLinks() {
    var bot = (CFG.tgBot || "EnkoSupportBot").toLowerCase();
    document.addEventListener("click", function (e) {
      var a = e.target.closest("a[href]"); if (!a) return;
      var href = a.getAttribute("href") || "";
      if (href.toLowerCase().indexOf("t.me/" + bot) === -1) return;   // не наш бот
      if (/[?&]start=/.test(href)) return;                            // вже персональний
      e.preventDefault();
      var w = window.open("", "_blank");                              // синхронно (без блокування попапів)
      fetch(REST + "tg/deeplink", { credentials: "include" })
        .then(function (r) { return r.json(); })
        .then(function (j) { var u = (j && j.deeplink) ? j.deeplink : href; if (w) { try { w.opener = null; } catch (x) {} w.location.href = u; } else { window.location.href = u; } })
        .catch(function () { if (w) { w.location.href = href; } });
    }, true);
  }

  /* ---------- init ---------- */
  function init() {
    renderAuthArea();
    wireTgLinks();
    var root = document.getElementById("enko-account-root");
    if (root) renderAccountPage(root);
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();
})();
