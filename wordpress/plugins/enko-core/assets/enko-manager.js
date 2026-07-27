/* ENKO — кабінет менеджера (SSM), фронт-сторінка /manager/.
   Порт прототипу admin.html + home-r2.js (розділ F) на REST enko/v1/mgr/*.
   Курс — server-side (mgr/rate). Затримки/години/тест-кнопки попапів —
   localStorage + відкриття /?poptest=<id> (як прототип; home-r1.js це читає). */
(function () {
  "use strict";
  var CFG = window.ENKO_CFG || {};
  var REST = CFG.restUrl || "/wp-json/enko/v1/";

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
  function initials(name) { var p = String(name || "").trim().split(/\s+/); if (!p[0]) return "EN"; return ((p[0][0] || "") + (p[1] ? p[1][0] : "")).toUpperCase(); }
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }

  var STATE = { users: [], managers: [], rate: 45, selected: 0, chatLast: 0, chatTimer: null };

  /* =================== ЛОГІН =================== */
  function wireLogin() {
    var form = document.getElementById("admin-login-form");
    if (!form) return;
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var login = $("#adm-login").value.trim(), pass = $("#adm-pass").value;
      var err = $("#adm-err");
      err.classList.remove("show");
      api("mgr/login", "POST", { login: login, password: pass }).then(function (j) {
        if (j && j.ok) { window.location.href = j.redirect || CFG.managerUrl; }
        else { err.textContent = (j && j.msg) || "Невірний логін або пароль."; err.classList.add("show"); }
      });
    });
  }

  /* =================== ЗАСТОСУНОК =================== */
  function initApp() {
    var app = document.getElementById("admin-app");
    if (!app) return;
    wireLogout();
    wireRate();
    wireSync();
    initTests();
    loadUsers();
    wireAddUser();
  }

  function wireLogout() {
    var b = document.querySelector("[data-admin-logout]");
    if (b) b.addEventListener("click", function () { api("auth/logout", "POST", {}).then(function () { window.location.href = "/"; }); });
  }

  /* ---- Курс (server-side) ---- */
  function wireRate() {
    var input = document.getElementById("rate-input"), form = document.getElementById("admin-rate-form");
    if (!input || !form) return;
    // Лише цифри + один розділювач (крапка АБО кома) + максимум 2 знаки після:
    // "45", "45.5", "45,50". Зайве відсікається прямо при введенні.
    input.addEventListener("input", function () {
      var c = String(input.value || "").replace(/[^0-9.,]/g, "");
      var m = c.match(/^(\d+)([.,]?)(\d{0,2})/);
      input.value = m ? m[1] + m[2] + m[3] : "";
    });
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var raw = String(input.value || "").trim();
      if (!/^\d+([.,]\d{1,2})?$/.test(raw)) { input.focus(); return; }
      var v = parseFloat(raw.replace(",", "."));
      if (!(v >= 1 && v <= 1000)) { input.focus(); return; }
      api("mgr/rate", "POST", { rate: v }).then(function (j) {
        if (j && j.ok) {
          input.value = j.rate;
          var saved = document.getElementById("rate-saved");
          if (saved) { saved.classList.add("show"); setTimeout(function () { saved.classList.remove("show"); }, 2400); }
        }
      });
    });
  }

  /* ---- Тест попапів/барів + затримки + робочі години (localStorage, як прототип) ---- */
  function initTests() {
    var host = document.getElementById("admin-tests-rows");
    if (!host) return;
    var card = document.getElementById("admin-tests"), toggle = document.getElementById("admin-tests-toggle");
    if (toggle && card) toggle.addEventListener("click", function () {
      var open = card.classList.toggle("is-open");
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
    });

    var hoursHost = document.getElementById("admin-tests-hours");
    function whVal(k, d) { var v = localStorage.getItem(k); return /^\d{1,2}:\d{2}$/.test(v || "") ? v : d; }
    function hoursNote() {
      return "Робочий час: <b>Пн–Пт, " + whVal("enko_work_start", "09:00") + "–" + whVal("enko_work_end", "18:00") + "</b>. Поза цим діапазоном і у вихідні — неробочий час: «неробочі» версії попапів, кнопка автодзвінка ховається. Враховується локальний час пристрою відвідувача.";
    }
    if (hoursHost) {
      hoursHost.innerHTML = '<div class="ath-fields">'
        + '<label class="ath-field"><span>Початок</span><input type="time" id="wh-start" value="' + whVal("enko_work_start", "09:00") + '"></label>'
        + '<label class="ath-field"><span>Кінець</span><input type="time" id="wh-end" value="' + whVal("enko_work_end", "18:00") + '"></label>'
        + '<i class="admin-test-saved" id="wh-saved">збережено ✓</i></div><p class="ath-note" id="wh-note">' + hoursNote() + '</p>';
      hoursHost.addEventListener("change", function (e) {
        if (!e.target.closest("#wh-start,#wh-end")) return;
        try { localStorage.setItem("enko_work_start", $("#wh-start").value || "09:00"); localStorage.setItem("enko_work_end", $("#wh-end").value || "18:00"); } catch (x) {}
        api("mgr/site-settings", "POST", { work_start: $("#wh-start").value || "09:00", work_end: $("#wh-end").value || "18:00" }); // → сервер (впливає на попапи/чат + адмінку)
        $("#wh-note").innerHTML = hoursNote();
        var sv = $("#wh-saved"); if (sv) { sv.classList.add("show"); setTimeout(function () { sv.classList.remove("show"); }, 1800); }
      });
    }

    var defs = { lead: 30, callbar: 60, cookie: 0 };
    function curSec(k) { var v = parseFloat(localStorage.getItem("enko_delay_" + k)); return (isFinite(v) && v >= 0) ? v : defs[k]; }
    var rows = [
      { title: "Лід-форма", hint: "Спливаюча форма «залиште контакт»", delay: "lead", btns: [["lead", "Переглянути"]] },
      { title: "Колбек-бар", hint: "Нижня смуга «передзвонимо вам»", delay: "callbar", btns: [["bar", "Неробочий час"], ["bar2", "Робочий час"]] },
      { title: "Cookie-банер", hint: "Згода на використання файлів cookie", delay: "cookie", btns: [["cookie", "Переглянути"]] },
      { title: "Чат швидкого звʼязку", hint: "Відкривається по кліку на зелений кружечок", delay: null, btns: [["chat", "Неробочий час"], ["chat2", "Робочий час"]] }
    ];
    function btnCell(b) { return b ? '<button type="button" class="btn btn--ghost btn--s" data-poptest="' + b[0] + '">' + b[1] + '</button>' : ""; }
    host.innerHTML = rows.map(function (r) {
      var delay = r.delay
        ? '<label class="admin-test-delay"><span>Зʼявляється через</span><input type="number" min="0" max="600" step="1" data-delay="' + r.delay + '" value="' + curSec(r.delay) + '"><span>с' + (defs[r.delay] === 0 ? ' · 0 = одразу' : '') + '</span><i class="admin-test-saved" data-saved>збережено ✓</i></label>'
        : '<span class="admin-test-delay admin-test-delay--manual">без таймера — по кліку</span>';
      return '<div class="admin-test-row"><div class="atr-info"><b>' + r.title + '</b><span>' + r.hint + '</span></div>'
        + '<div class="atr-b1">' + btnCell(r.btns[0]) + '</div><div class="atr-b2">' + btnCell(r.btns[1]) + '</div><div class="atr-delay">' + delay + '</div></div>';
    }).join("");
    host.addEventListener("click", function (e) {
      var b = e.target.closest("[data-poptest]"); if (!b) return;
      window.open("/?poptest=" + encodeURIComponent(b.getAttribute("data-poptest")), "_blank", "noopener");
    });
    host.addEventListener("change", function (e) {
      var inp = e.target.closest("[data-delay]"); if (!inp) return;
      var v = Math.max(0, Math.min(600, parseInt(inp.value, 10) || 0)); inp.value = v;
      try { localStorage.setItem("enko_delay_" + inp.getAttribute("data-delay"), String(v)); } catch (_) {}
      var _b = {}; _b["delay_" + inp.getAttribute("data-delay")] = v; api("mgr/site-settings", "POST", _b); // → сервер
      var sv = inp.parentNode.querySelector("[data-saved]"); if (sv) { sv.classList.add("show"); setTimeout(function () { sv.classList.remove("show"); }, 1800); }
    });
  }

  /* ---- Список клієнтів ---- */
  function loadUsers() {
    api("mgr/users").then(function (j) {
      if (!j || !j.ok) return;
      STATE.users = j.users; STATE.managers = j.managers; STATE.rate = j.rate;
      var ri = document.getElementById("rate-input"); if (ri) ri.value = j.rate;
      renderList();
      renderDetail();
    });
  }
  function renderList() {
    var box = document.getElementById("admin-userlist"); if (!box) return;
    if (!STATE.users.length) { box.innerHTML = '<div class="admin-empty">Поки немає зареєстрованих користувачів.</div>'; return; }
    box.innerHTML = STATE.users.map(function (u) {
      var disc = u.discount > 0 ? '<span class="disc-tag">−' + u.discount + '%</span>' : "";
      return '<button type="button" class="admin-user' + (u.id === STATE.selected ? " active" : "") + '" data-id="' + u.id + '">'
        + '<span class="ava">' + esc(initials(u.name)) + '</span>'
        + '<span class="admin-user__info"><b>' + esc(u.name) + '</b><span>' + esc(u.email) + '</span></span>' + disc + '</button>';
    }).join("");
    $$("button.admin-user", box).forEach(function (b) {
      b.addEventListener("click", function () { selectUser(parseInt(b.getAttribute("data-id"), 10)); });
    });
  }
  function selectUser(id) {
    STATE.selected = id; STATE.chatLast = 0;
    if (STATE.chatTimer) { clearInterval(STATE.chatTimer); STATE.chatTimer = null; }
    renderList();
    var box = document.getElementById("admin-detail");
    if (box) box.innerHTML = '<div class="admin-detail__empty"><p>Завантаження…</p></div>';
    api("mgr/user?id=" + id).then(function (j) { if (j && j.ok) renderDetail(j); });
  }

  function renderDetail(data) {
    var box = document.getElementById("admin-detail"); if (!box) return;
    if (!data || !STATE.selected) {
      box.innerHTML = '<div class="admin-detail__empty"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg><p>Оберіть користувача зі списку зліва, щоб редагувати дані та знижку.</p></div>';
      return;
    }
    var u = data.profile, orders = data.orders || [];
    var entityLabel = u.entity === "ur" ? "Юридична особа" : "Фізична особа";
    var mgrOpts = '<option value="0">— дефолтний —</option>' + STATE.managers.map(function (m) {
      return '<option value="' + m.id + '"' + (u.managerId === m.id ? " selected" : "") + '>' + esc(m.name) + '</option>';
    }).join("");

    box.innerHTML =
      '<div class="acc-card__head" style="margin-bottom:10px"><div class="acc-card__head-l"><h2>Картка користувача</h2>'
      + '<span class="sub" style="margin:0">Зареєстровано: ' + esc(u.registered) + ' · ' + entityLabel + '</span></div></div>'
      + '<div class="admin-detail">'
      /* профіль */
      + '<details class="admin-sec" open><summary>Профіль<span class="admin-sec__hint">' + esc(u.email) + '</span></summary><div class="admin-sec__body">'
      + '<div class="field-row"><div class="field"><label>Імʼя</label><input id="ad-fn" value="' + esc(u.firstName) + '"></div>'
      + '<div class="field"><label>Прізвище</label><input id="ad-ln" value="' + esc(u.lastName) + '"></div></div>'
      + '<div class="field-row"><div class="field"><label>Email (логін)</label><input class="ro-locked" value="' + esc(u.email) + '" readonly disabled></div>'
      + '<div class="field"><label>Телефон</label><input id="ad-phone" value="' + esc(u.phone) + '"></div></div>'
      + '<div class="field-row"><div class="field"><label>Місто</label><input id="ad-city" value="' + esc(u.city) + '"></div>'
      + '<div class="field"><label>Тип співпраці</label><input id="ad-coop" value="' + esc(u.coop) + '"></div></div>'
      + '<div class="field-row"><div class="field"><label>Назва компанії</label><input id="ad-company" value="' + esc(u.company) + '"></div>'
      + '<div class="field"><label>ЄДРПОУ / ІПН</label><input id="ad-edrpou" value="' + esc(u.edrpou) + '"></div></div>'
      + '</div></details>'
      /* знижка */
      + '<details class="admin-sec" open><summary>Індивідуальна знижка<span class="admin-sec__hint">' + (u.discount ? "−" + u.discount + "%" : "немає") + '</span></summary><div class="admin-sec__body">'
      + '<div class="admin-disc-row"><div class="field"><label>Знижка, %</label><div class="disc-apply"><input id="ad-disc" type="number" min="0" max="99" step="1" value="' + (u.discount || 0) + '"></div></div></div>'
      + '<p class="sub" style="margin:0;max-width:360px">Вмикає показ ціни зі знижкою в каталозі та на сторінці товару для цього користувача. 0 — знижки немає.</p></div></details>'
      /* менеджер */
      + '<details class="admin-sec" open><summary>Закріплений менеджер</summary><div class="admin-sec__body">'
      + '<div class="field"><label>Менеджер</label><select id="ad-manager">' + mgrOpts + '</select></div></div></details>'
      /* доступ */
      + '<details class="admin-sec"><summary>Доступ до кабінету<span class="admin-sec__hint">пароль</span></summary><div class="admin-sec__body">'
      + '<p class="sub" style="margin:0 0 10px">Пароль зберігається у зашифрованому вигляді. Надішліть користувачу лист для встановлення нового пароля.</p>'
      + '<button type="button" class="btn btn--ghost btn--s" id="ad-pass-reset"><svg viewBox="0 0 24 24" width="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/></svg>Скинути та надіслати на email</button>'
      + '<span class="admin-saved" id="ad-pass-saved" style="margin-left:10px">Лист надіслано ✓</span></div></details>'
      /* чат */
      + '<details class="admin-sec" open><summary>Чат із клієнтом<span class="admin-sec__hint">та сама розмова, що в кабінеті</span></summary><div class="admin-sec__body">'
      + '<div class="chat-log adm-chat-log" id="adm-chat-log"></div>'
      + '<form class="chat-input" id="adm-chat-form"><input id="adm-chat-msg" type="text" placeholder="Відповідь клієнту…" autocomplete="off"><button class="chat-send" type="submit" aria-label="Надіслати"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></svg></button></form>'
      + '</div></details>'
      /* історія */
      + '<details class="admin-sec"><summary>Історія заявок<span class="admin-sec__hint">' + (orders.length ? orders.length + " шт." : "немає") + '</span></summary><div class="admin-sec__body">'
      + (orders.length ? orders.map(function (o) {
        var rows = o.items.map(function (i) { return '<tr><td class="oi-qty">×' + i.qty + '</td><td class="oi-name">' + esc(i.name) + '</td><td class="oi-code">' + (i.sku ? esc(i.sku) : "—") + '</td><td class="oi-price">' + fmt(i.uah) + ' грн</td></tr>'; }).join("");
        return '<div class="order-row"><div class="order-row__head"><div class="order-row__info"><b>Заявка №' + esc(o.number) + '</b><span class="order-row__date">' + esc(o.date) + '</span></div>'
          + '<div class="order-row__right"><span class="ostatus">' + esc(o.status) + '</span><div class="oprice">' + fmt(o.uah) + ' грн</div></div></div>'
          + '<table class="order-items"><thead><tr><th class="oi-qty">К-сть</th><th>Позиція</th><th>Код</th><th class="oi-price">Сума</th></tr></thead><tbody>' + rows + '</tbody></table></div>';
      }).join("") : '<p class="sub" style="margin:0">Заявок поки немає.</p>')
      + '</div></details>'
      + '<div class="admin-actions"><button type="button" class="btn btn--primary btn--m" id="ad-save">Зберегти зміни</button>'
      + '<span class="admin-saved" id="ad-saved">Збережено ✓ — дані користувача оновлено</span></div>'
      + '</div>';

    // save
    $("#ad-save").addEventListener("click", function () {
      api("mgr/user-update", "POST", {
        id: STATE.selected,
        firstName: $("#ad-fn").value.trim(), lastName: $("#ad-ln").value.trim(),
        phone: $("#ad-phone").value.trim(), city: $("#ad-city").value.trim(),
        coop: $("#ad-coop").value.trim(), company: $("#ad-company").value.trim(), edrpou: $("#ad-edrpou").value.trim(),
        discount: parseInt($("#ad-disc").value, 10) || 0,
        managerId: parseInt($("#ad-manager").value, 10) || 0
      }).then(function (j) {
        if (!j || !j.ok) return;
        var sv = $("#ad-saved"); if (sv) { sv.classList.add("show"); setTimeout(function () { sv.classList.remove("show"); }, 2400); }
        loadUsers();
      });
    });
    // reset password
    $("#ad-pass-reset").addEventListener("click", function () {
      api("auth/forgot", "POST", { email: u.email }).then(function () {
        var sv = $("#ad-pass-saved"); if (sv) { sv.classList.add("show"); setTimeout(function () { sv.classList.remove("show"); }, 2400); }
      });
    });
    // chat
    wireMgrChat(data.chat || []);
  }

  function wireMgrChat(initialMsgs) {
    var log = document.getElementById("adm-chat-log"), form = document.getElementById("adm-chat-form"), inp = document.getElementById("adm-chat-msg");
    if (!log) return;
    var cliName = (STATE.users.filter(function (x) { return x.id === STATE.selected; })[0] || {}).name || "Клієнт";
    function add(m) {
      var mine = m.from === "support";
      var div = document.createElement("div");
      div.className = "msg " + (mine ? "user" : "support");
      div.innerHTML = '<span class="who">' + (mine ? "Ви (менеджер)" : esc(cliName)) + '</span>' + esc(m.text);
      log.appendChild(div); log.scrollTop = log.scrollHeight;
      if (m.id && m.id > STATE.chatLast) STATE.chatLast = m.id;
    }
    log.innerHTML = "";
    if (initialMsgs.length) initialMsgs.forEach(add);
    else log.innerHTML = '<p class="sub" style="margin:0">Повідомлень ще немає. Напишіть клієнту перше повідомлення.</p>';

    if (STATE.chatTimer) clearInterval(STATE.chatTimer);
    STATE.chatTimer = setInterval(function () {
      if (!STATE.selected) return;
      api("mgr/chat?user=" + STATE.selected + "&since=" + STATE.chatLast).then(function (j) {
        if (j && j.ok && j.messages && j.messages.length) {
          if (log.querySelector("p.sub")) log.innerHTML = "";
          j.messages.forEach(add);
        }
      });
    }, 5000);

    if (form) form.addEventListener("submit", function (e) {
      e.preventDefault();
      var t = inp.value.trim(); if (!t) return;
      if (log.querySelector("p.sub")) log.innerHTML = "";
      add({ from: "support", text: t }); inp.value = "";
      api("mgr/chat-send", "POST", { user: STATE.selected, text: t });
    });
  }

  /* ---- Синхронізація каталогу з Google Sheet ---- */
  function wireSync() {
    var b = document.getElementById("sync-catalog"); if (!b) return;
    var out = document.getElementById("sync-result");
    b.addEventListener("click", function () {
      b.disabled = true;
      if (out) { out.style.color = "var(--muted)"; out.textContent = "Синхронізація… (може зайняти кілька секунд)"; }
      api("mgr/sync-catalog", "POST", {}).then(function (j) {
        b.disabled = false;
        if (!j || !j.ok) { if (out) { out.style.color = "#c0392b"; out.textContent = (j && j.msg) || "Помилка синхронізації."; } return; }
        var msg = "✓ Готово: створено " + j.created + ", оновлено " + j.updated + " (усього рядків: " + j.total + ")";
        if (j.by_status) {
          var bs = j.by_status, parts = [];
          if (bs["опублікований"]) parts.push("активних " + bs["опублікований"]);
          if (bs["прихований"]) parts.push("прихованих " + bs["прихований"]);
          if (bs["чорновик"]) parts.push("чорновиків " + bs["чорновик"]);
          if (bs["вимкнений"]) parts.push("вимкнених " + bs["вимкнений"]);
          if (parts.length) msg += " · з них: " + parts.join(", ");
        }
        if (out) {
          out.style.color = "#2FA36B";
          out.innerHTML = esc(msg)
            + (j.errors && j.errors.length
              ? '<div style="color:#c0392b;margin-top:6px;font-size:13px">Помилки:<br>' + j.errors.map(esc).join("<br>") + '</div>'
              : '')
            + (j.warnings && j.warnings.length
              ? '<div style="color:#b8860b;margin-top:6px;font-size:13px">⚠ Увага:<br>' + j.warnings.map(esc).join("<br>") + '</div>'
              : '');
        }
        loadUsers();
      });
    });
  }

  function wireAddUser() {
    var b = document.getElementById("admin-add-demo");
    if (!b) return;
    b.addEventListener("click", function () {
      STATE.selected = 0;
      renderList();
      renderNewUserCard();
    });
  }

  /* Порожня картка нового користувача (замість нативного prompt). Зберегти → mgr/user-create. */
  function renderNewUserCard() {
    var box = document.getElementById("admin-detail"); if (!box) return;
    var mgrOpts = '<option value="0">— дефолтний —</option>' + STATE.managers.map(function (m) {
      return '<option value="' + m.id + '">' + esc(m.name) + '</option>';
    }).join("");
    box.innerHTML =
      '<div class="acc-card__head" style="margin-bottom:10px"><div class="acc-card__head-l"><h2>Новий користувач</h2>'
      + '<span class="sub" style="margin:0">Заповніть дані та збережіть — обліковий запис створиться, а на email піде запрошення встановити пароль.</span></div></div>'
      + '<div class="admin-detail">'
      + '<details class="admin-sec" open><summary>Профіль</summary><div class="admin-sec__body">'
      + '<div class="field-row"><div class="field"><label>Імʼя</label><input id="nu-fn"></div>'
      + '<div class="field"><label>Прізвище</label><input id="nu-ln"></div></div>'
      + '<div class="field-row"><div class="field"><label>Email (логін) *</label><input id="nu-email" type="email" placeholder="you@email.com"></div>'
      + '<div class="field"><label>Телефон *</label><input id="nu-phone" placeholder="+380 __ ___ __ __"></div></div>'
      + '<div class="field-row"><div class="field"><label>Місто</label><input id="nu-city"></div>'
      + '<div class="field"><label>Тип співпраці</label><input id="nu-coop"></div></div>'
      + '<div class="field-row"><div class="field"><label>Назва компанії</label><input id="nu-company"></div>'
      + '<div class="field"><label>ЄДРПОУ / ІПН</label><input id="nu-edrpou"></div></div>'
      + '</div></details>'
      + '<details class="admin-sec" open><summary>Індивідуальна знижка</summary><div class="admin-sec__body">'
      + '<div class="admin-disc-row"><div class="field"><label>Знижка, %</label><div class="disc-apply"><input id="nu-disc" type="number" min="0" max="99" step="1" value="0"></div></div></div></div></details>'
      + '<details class="admin-sec" open><summary>Закріплений менеджер</summary><div class="admin-sec__body">'
      + '<div class="field"><label>Менеджер</label><select id="nu-manager">' + mgrOpts + '</select></div></div></details>'
      + '<div class="admin-actions"><button type="button" class="btn btn--primary btn--m" id="nu-create">Створити та надіслати запрошення</button>'
      + '<button type="button" class="btn btn--ghost btn--m" id="nu-cancel">Скасувати</button>'
      + '<span class="admin-saved" id="nu-msg"></span></div>'
      + '</div>';

    function showErr(t) { var e = $("#nu-msg"); if (e) { e.textContent = t; e.style.color = "#c0392b"; e.classList.add("show"); } }
    $("#nu-create").addEventListener("click", function () {
      var email = $("#nu-email").value.trim(), phone = $("#nu-phone").value.trim();
      if (!email) { showErr("Вкажіть email"); $("#nu-email").focus(); return; }
      if (!phone) { showErr("Вкажіть телефон"); $("#nu-phone").focus(); return; }
      var btn = $("#nu-create"); btn.disabled = true;
      api("mgr/user-create", "POST", {
        email: email, phone: phone,
        firstName: $("#nu-fn").value.trim(), lastName: $("#nu-ln").value.trim(),
        city: $("#nu-city").value.trim(), coop: $("#nu-coop").value.trim(),
        company: $("#nu-company").value.trim(), edrpou: $("#nu-edrpou").value.trim(),
        discount: parseInt($("#nu-disc").value, 10) || 0,
        managerId: parseInt($("#nu-manager").value, 10) || 0
      }).then(function (j) {
        btn.disabled = false;
        if (j && j.ok && j.user) {
          api("mgr/users").then(function (r) {
            if (r && r.ok) { STATE.users = r.users; STATE.managers = r.managers; renderList(); }
            selectUser(j.user.id);
          });
        } else {
          showErr(j && j.error === "exists" ? "Користувач із таким email вже існує" : ((j && j.msg) || "Не вдалося створити"));
        }
      });
    });
    $("#nu-cancel").addEventListener("click", function () { renderDetail(); });
  }

  function init() { wireLogin(); initApp(); }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();
})();
