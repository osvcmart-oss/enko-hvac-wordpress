/* ENKO popups & bars — ported logic from prototype home-r1.js.
   Timings & working-hours come from ENKO_CFG (server options). */
(function () {
  "use strict";
  var CFG = window.ENKO_CFG || { delays: {}, working: true, tgLink: "#" };

  function el(html) { var d = document.createElement("div"); d.innerHTML = html.trim(); return d.firstChild; }
  function once(key) {
    try { if (sessionStorage.getItem(key)) return false; sessionStorage.setItem(key, "1"); } catch (e) {}
    return true;
  }

  // --- Lead popup -----------------------------------------------------------
  function showLead() {
    if (!once("enko_lead_seen")) return;
    var overlay = el(
      '<div class="enko-pop-overlay" role="dialog" aria-modal="true">' +
        '<div class="enko-pop">' +
          '<h3>Залиште заявку</h3>' +
          '<p>Підберемо кліматичне рішення під ваш об’єкт і передзвонимо.</p>' +
          '<p><a class="button" href="' + CFG.tgLink + '" target="_blank" rel="noopener">Написати в Telegram</a> ' +
          '<button class="button enko-ghost" type="button" data-enko-close>Пізніше</button></p>' +
        '</div></div>'
    );
    overlay.addEventListener("click", function (e) {
      if (e.target === overlay || e.target.hasAttribute("data-enko-close")) overlay.remove();
    });
    document.body.appendChild(overlay);
  }

  // --- Callback bar ---------------------------------------------------------
  function showCallbar() {
    if (!once("enko_callbar_seen")) return;
    var working = CFG.working;
    var bar = el(
      '<div class="enko-bar" role="region" aria-label="Передзвонимо">' +
        '<span>' + (working ? "Зателефонуємо вам за хвилину — залиште номер."
                             : "Ми поза робочим часом — залиште заявку, передзвонимо зранку.") + '</span>' +
        '<a class="button" href="' + CFG.tgLink + '" target="_blank" rel="noopener">Зв’язатися</a>' +
        '<button class="button enko-ghost" type="button" data-enko-close>✕</button>' +
      '</div>'
    );
    bar.querySelector("[data-enko-close]").addEventListener("click", function () { bar.classList.remove("show"); });
    document.body.appendChild(bar);
    requestAnimationFrame(function () { bar.classList.add("show"); });
  }

  // --- Cookie banner --------------------------------------------------------
  function showCookie() {
    try { if (localStorage.getItem("enko_cookie_consent")) return; } catch (e) {}
    var c = el(
      '<div class="enko-cookie" role="region" aria-label="Cookie">' +
        '<span>Ми використовуємо cookie для коректної роботи сайту.</span>' +
        '<button class="button" type="button" data-cc="all">Прийняти</button>' +
        '<button class="button enko-ghost" type="button" data-cc="necessary">Лише необхідні</button>' +
      '</div>'
    );
    c.addEventListener("click", function (e) {
      var v = e.target.getAttribute("data-cc");
      if (!v) return;
      try { localStorage.setItem("enko_cookie_consent", v); } catch (er) {}
      c.classList.remove("show");
    });
    document.body.appendChild(c);
    requestAnimationFrame(function () { c.classList.add("show"); });
  }

  // --- Chat launcher --------------------------------------------------------
  function launcher() {
    var l = el(
      '<div class="enko-launch">' +
        '<button class="chat" type="button" title="Чат" aria-label="Чат">💬</button>' +
        '<button class="call" type="button" title="Дзвінок" aria-label="Дзвінок">☎</button>' +
      '</div>'
    );
    l.querySelector(".chat").addEventListener("click", function () { window.open(CFG.tgLink, "_blank", "noopener"); });
    l.querySelector(".call").addEventListener("click", showCallbar);
    document.body.appendChild(l);
  }

  document.addEventListener("DOMContentLoaded", function () {
    var d = CFG.delays || {};
    launcher();
    setTimeout(showCookie, (d.cookie || 0) * 1000);
    setTimeout(showLead, (d.lead || 30) * 1000);
    setTimeout(showCallbar, (d.callbar || 60) * 1000);
  });
})();
