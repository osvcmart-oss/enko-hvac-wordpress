/* ENKO — анонімний чат (фаза 2, вхід А: віджет на сайті).
   Перекриває прототипний window.ENKO_CHAT (localStorage + автовідповідь) на
   реальний REST guest/send + guest/poll для НЕзалогінених. Плаваючий чат
   (home-r1.js renderQuickPop) викликає ENKO_CHAT.add/render/ensureGreeting.
   Дедуплікація за id повідомлення + звірка оптимістичного показу з polling —
   щоб власне повідомлення не двоїлось (gotcha: оптимістик + 5с-polling). */
(function () {
  "use strict";
  var CFG = window.ENKO_CFG || {};
  if (CFG.loggedIn) { return; }                 // лише гості; залогінені — кабінет-чат
  var REST = CFG.restUrl || "/wp-json/enko/v1/";

  function esc(s) { return String(s == null ? "" : s).replace(/[&<>"]/g, function (c) { return ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" })[c]; }); }
  function jget(path) { return fetch(REST + path, { credentials: "include" }).then(function (r) { return r.json(); }); }
  function jpost(path, body) {
    return fetch(REST + path, {
      method: "POST", credentials: "include",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body || {})
    }).then(function (r) { return r.json(); });
  }

  var SUPPORT = "Михайло";

  var CHAT = {
    reply: "",                                  // прототип шле фейкову автовідповідь — глушимо
    _msgs: [],                                  // {from, text, id?, pending?}
    _seen: {},                                  // серверні id, вже додані в _msgs
    _lastId: 0,
    _timer: null,
    _greeted: false,

    ensureGreeting: function () {
      if (this._greeted) { return; }
      this._greeted = true;
      if (!this._msgs.length) {
        this._msgs.push({ from: "support", text: "Вітаємо! Напишіть ваше запитання — менеджер відповість тут." });
      }
      this._startPoll();
    },

    add: function (author, text) {
      if (author === "support") { return; }     // ігноруємо прототипну автовідповідь
      text = String(text || "").trim();
      if (!text) { return; }
      this._msgs.push({ from: "user", text: text, pending: true });   // оптимістично
      var self = this;
      jpost("guest/send", { text: text, page: location.href }).then(function () { self._pollOnce(); }).catch(function () {});
      this._startPoll();
    },

    render: function (el) {
      if (!el) { return; }
      el.innerHTML = this._msgs.map(function (m) {
        return '<div class="msg ' + (m.from === "user" ? "user" : "support") + '"><span class="who">'
          + (m.from === "user" ? "Ви" : esc(SUPPORT)) + '</span>' + esc(m.text) + '</div>';
      }).join("");
      el.scrollTop = el.scrollHeight;
    },

    _startPoll: function () {
      if (this._timer) { return; }
      var self = this;
      this._pollOnce();
      this._timer = setInterval(function () { self._pollOnce(); }, 5000);
      window.addEventListener("beforeunload", function () { clearInterval(self._timer); });
    },

    _pollOnce: function () {
      var self = this;
      jget("guest/poll?since=" + this._lastId).then(function (j) {
        if (!j || !j.messages || !j.messages.length) { return; }
        var changed = false;
        j.messages.forEach(function (m) {
          if (m.id > self._lastId) { self._lastId = m.id; }
          if (self._seen[m.id]) { return; }     // вже маємо це повідомлення
          self._seen[m.id] = 1;
          if (m.from === "user") {
            // звірити з оптимістичним (ще не підтвердженим) тим самим текстом
            var matched = false;
            for (var i = 0; i < self._msgs.length; i++) {
              var o = self._msgs[i];
              if (o.pending && o.from === "user" && o.text === m.text) { o.pending = false; o.id = m.id; matched = true; break; }
            }
            if (!matched) { self._msgs.push({ from: "user", text: m.text, id: m.id }); }
          } else {
            self._msgs.push({ from: "support", text: m.text, id: m.id });
          }
          changed = true;
        });
        if (changed) {
          var l = document.getElementById("qp-log"), p = document.getElementById("quick-pop");
          if (l && p && p.classList.contains("show")) { self.render(l); }
          try { window.dispatchEvent(new Event("enko:chat")); } catch (e) {}
        }
      }).catch(function () {});
    }
  };

  window.ENKO_CHAT = CHAT;
})();
