/* ENKO — гібрид кошика.
   Кошик живе у браузері (прототипний enko.js: localStorage enko_cart_v1) —
   лічильник, каунтер на кнопках, синхронізація каталог↔товар і сторінка
   кошика працюють нативно. Цей скрипт лише при НАДСИЛАННІ заявки збирає
   позиції й створює замовлення WooCommerce + сповіщення (REST checkout-items).
   Перехоплення «Додати в заявку» НЕ робимо — це робить enko.js. */
(function () {
  "use strict";
  var CFG = window.ENKO_CFG || {};
  var BASE = CFG.restUrl || "/wp-json/enko/v1/";

  function val(sel) { var el = document.querySelector(sel); return el ? el.value.trim() : ""; }

  // Honeypot у форму кошика (див. enko-forms.js — та сама механіка).
  function plantHp() {
    var f = document.getElementById("cart-form");
    if (!f || f.querySelector("[name=enko_hp]")) return;
    var i = document.createElement("input");
    i.type = "text"; i.name = "enko_hp"; i.value = "";
    i.tabIndex = -1; i.autocomplete = "off";
    i.setAttribute("aria-hidden", "true");
    i.style.cssText = "position:absolute!important;left:-9999px!important;top:-9999px!important;width:1px;height:1px;opacity:0";
    f.appendChild(i);
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", plantHp);
  else plantHp();

  // Форма-заявка на сторінці кошика (#cart-form): не блокуємо enko.js (він показує
  // «Дякуємо» і чистить кошик), а паралельно створюємо замовлення Woo з localStorage.
  document.addEventListener("submit", function (e) {
    var f = e.target;
    if (!f || f.id !== "cart-form") return;

    var items = [];
    try { items = JSON.parse(localStorage.getItem("enko_cart_v1") || "[]"); } catch (err) { items = []; }
    if (!items.length) return;

    var payload = {
      name: val("#c-name"),
      phone: val("#c-phone"),
      email: val("#c-email"),
      question: val("#c-q"),
      enko_hp: val("#cart-form [name=enko_hp]"),
      items: items.map(function (i) {
        return { id: i.id, name: i.name, ver: i.ver, qty: i.qty, uah: i.uah, eur: i.eur };
      })
    };
    fetch(BASE + "checkout-items", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify(payload)
    }).catch(function () {});
  }, true);
})();
