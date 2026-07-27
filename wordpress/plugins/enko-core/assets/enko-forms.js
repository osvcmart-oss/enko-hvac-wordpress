/* ENKO — реальна відправка заявок.
   Перехоплює submit прототипних форм (capture-фаза, ДО reset у enko.js) і
   надсилає дані на REST enko/v1/request (email + Telegram на бекенді).
   UX прототипу не чіпаємо — enko.js так само показує «Дякуємо». */
(function () {
  "use strict";
  var CFG = window.ENKO_CFG || {};
  var REST = (CFG.restUrl || "/wp-json/enko/v1/") + "request";

  function send(payload) {
    if (!payload || (!payload.phone && !payload.name)) return;
    try {
      fetch(REST, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
        keepalive: true
      }).catch(function () {});
    } catch (e) {}
  }

  function v(form, sel) { var el = form.querySelector(sel); return el ? String(el.value || "").trim() : ""; }
  function t(sel) { var el = document.querySelector(sel); return el ? el.textContent.trim() : ""; }

  // Honeypot: приховане поле у кожній лід-формі. Людина його не бачить і не
  // заповнює; бот-автозаповнювач — заповнює, і сервер тихо ігнорує заявку.
  function plantHp(f) {
    if (!f || f.querySelector("[name=enko_hp]")) return;
    var i = document.createElement("input");
    i.type = "text"; i.name = "enko_hp"; i.value = "";
    i.tabIndex = -1; i.autocomplete = "off";
    i.setAttribute("aria-hidden", "true");
    i.style.cssText = "position:absolute!important;left:-9999px!important;top:-9999px!important;width:1px;height:1px;opacity:0";
    f.appendChild(i);
  }
  // GDPR-рядок під кнопкою форми: «Надсилаючи форму, ви погоджуєтесь із
  // політикою конфіденційності» (текст і мову віддає сервер — ENKO_CONSENT).
  var CONSENT = (window.ENKO_CONSENT || {}).html || "";
  function plantConsent(f) {
    if (!CONSENT || !f || f.querySelector(".enko-consent")) return;
    var p = document.createElement("p");
    p.className = "enko-consent";
    p.innerHTML = CONSENT;
    p.style.cssText = "margin:8px 0 0;font-size:12px;line-height:1.45;opacity:.72;width:100%;flex-basis:100%;text-align:center";
    f.appendChild(p);
  }
  function plantAll() {
    ["#modal-form", "#lead-form", "#consult-form", ".callbar__form", "#cart-form"].forEach(function (s) {
      document.querySelectorAll(s).forEach(function (f) { plantHp(f); plantConsent(f); });
    });
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", plantAll);
  else plantAll();
  // Колбек-бар (та інші пізні форми) enko.js створює динамічно після затримки —
  // досаджуємо honeypot у міру появи. plantHp ідемпотентний, обхід дешевий.
  try {
    new MutationObserver(plantAll).observe(document.documentElement, { childList: true, subtree: true });
  } catch (e) {}

  document.addEventListener("submit", function (e) {
    var f = e.target;
    if (!f || f.tagName !== "FORM") return;
    var payload = null;

    if (f.id === "modal-form") {
      payload = {
        source: "modal",
        name: v(f, "[name=name]"),
        phone: v(f, "[name=phone]"),
        email: v(f, "[name=email]"),
        message: v(f, "[name=question]"),
        product: t("#modal-product-name")
      };
    } else if (f.id === "lead-form") {
      payload = { source: "lead", name: "", phone: v(f, "input[type=tel]"), message: "Запит на підбір (лід-попап)" };
    } else if (f.id === "consult-form") {
      var ins = f.querySelectorAll("input");
      payload = {
        source: "consult",
        name: ins[0] ? ins[0].value.trim() : "",
        phone: ins[1] ? ins[1].value.trim() : "",
        message: ins[2] && ins[2].value.trim() ? "Місто: " + ins[2].value.trim() : ""
      };
    } else if (f.classList && f.classList.contains("callbar__form")) {
      // Колбек-бар: робочий час — лише телефон; неробочий — + бажані дата/час дзвінка.
      var cbDate = v(f, "input[type=date]"), cbTime = v(f, "select");
      var when = (cbDate || cbTime) ? (" — бажаний час: " + [cbDate, cbTime].filter(Boolean).join(" ")) : "";
      payload = { source: "callbar", name: "", phone: v(f, "input[type=tel]"), message: "Замовлення дзвінка" + when };
    }
    if (payload) payload.enko_hp = v(f, "[name=enko_hp]");
    send(payload);
  }, true); // capture: спрацьовує до bubble-хендлера enko.js (form.reset)
})();
