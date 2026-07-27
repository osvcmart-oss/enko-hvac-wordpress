/* ENKO — пошук по каталогу: автодоповнення у шапці (desktop + mobile).
   Дебаунс ~220мс → REST enko/v1/search → випадаючий список (фото+ціна+категорія)
   + підказки категорій. Клавіатура ↑↓/Enter/Esc. Enter без вибору → сторінка
   результатів. Прогресивно: форма й так сабмітить GET на /poshuk/ (працює без JS). */
(function () {
  "use strict";
  var CFG = window.ENKO_CFG || {};
  var REST = (CFG.restUrl || "/wp-json/enko/v1/");
  var PAGE = CFG.searchUrl || "/poshuk/";

  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"]/g, function (c) {
      return ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" })[c];
    });
  }
  function fmt(n) { return ("" + Math.round(+n || 0)).replace(/\B(?=(\d{3})+(?!\d))/g, " "); }
  function debounce(fn, ms) { var t; return function () { var a = arguments, c = this; clearTimeout(t); t = setTimeout(function () { fn.apply(c, a); }, ms); }; }

  function priceHtml(p) {
    var uah = p.uah ? fmt(p.uah) + " ₴" : "";
    var eur = p.eur ? " / " + fmt(p.eur) + " €" : "";
    return uah ? '<span class="enko-ac__price">' + esc(uah + eur) + "</span>" : "";
  }

  function render(box, data, q, input) {
    var html = "";
    if (data.categories && data.categories.length) {
      html += '<div class="enko-ac__sec">Категорії</div>';
      data.categories.slice(0, 2).forEach(function (c) {
        html += '<a class="enko-ac__cat" href="' + esc(c.url) + '"><svg viewBox="0 0 24 24" width="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h18M3 12h18M3 17h12"/></svg>' + esc(c.name) + "</a>";
      });
    }
    if (data.products && data.products.length) {
      html += '<div class="enko-ac__sec">Товари</div>';
      data.products.forEach(function (p) {
        html += '<a class="enko-ac__item" href="' + esc(p.url) + '">'
          + (p.img ? '<img class="enko-ac__img" src="' + esc(p.img) + '" alt="" loading="lazy">' : '<span class="enko-ac__img enko-ac__img--ph"></span>')
          + '<span class="enko-ac__txt"><span class="enko-ac__name">' + esc(p.name) + "</span>"
          + (p.cat ? '<span class="enko-ac__cat-l">' + esc(p.cat) + "</span>" : "")
          + "</span>" + priceHtml(p) + "</a>";
      });
    }
    if (!html) {
      html = '<div class="enko-ac__empty">Нічого не знайдено за «' + esc(q) + '».<br>'
        + '<a href="' + esc(PAGE) + '">Переглянути весь каталог →</a></div>';
    } else if (data.total > (data.products ? data.products.length : 0)) {
      html += '<a class="enko-ac__all" href="' + esc(PAGE) + "?q=" + encodeURIComponent(q) + '">Усі результати (' + data.total + ") →</a>";
    }
    box.innerHTML = html;
    // position:fixed під полем — щоб overflow:hidden форми прототипу (.is-open) не обрізав випадайку.
    var r = input.getBoundingClientRect();
    box.style.position = "fixed";
    box.style.left = r.left + "px";
    box.style.top = (r.bottom + 6) + "px";
    box.style.width = Math.max(r.width, 260) + "px";
    box.style.right = "auto";
    box.hidden = false;
  }

  function wire(form) {
    var input = form.querySelector('input[type="search"], input[name="q"]');
    if (!input) return;
    input.setAttribute("name", "q");
    input.setAttribute("autocomplete", "off");
    var box = document.createElement("div");
    box.className = "enko-ac";
    box.hidden = true;
    document.body.appendChild(box);            // у body, повз overflow:hidden форми

    var lastQ = "", controller = null;

    function close() { box.hidden = true; box.innerHTML = ""; }
    function reposition() {
      if (box.hidden) return;
      var r = input.getBoundingClientRect();
      box.style.left = r.left + "px";
      box.style.top = (r.bottom + 6) + "px";
      box.style.width = Math.max(r.width, 260) + "px";
    }
    window.addEventListener("scroll", reposition, true);
    window.addEventListener("resize", reposition);

    var run = debounce(function () {
      var q = input.value.trim();
      lastQ = q;
      if (q.length < 2) { close(); return; }
      if (window.AbortController) { if (controller) controller.abort(); controller = new AbortController(); }
      var opts = controller ? { signal: controller.signal } : {};
      fetch(REST + "search?q=" + encodeURIComponent(q) + "&limit=5", opts)
        .then(function (r) { return r.json(); })
        .then(function (j) { if (input.value.trim() === lastQ && j && j.ok) render(box, j, q, input); })
        .catch(function () {});
    }, 220);

    input.addEventListener("input", run);
    input.addEventListener("focus", function () { if (input.value.trim().length >= 2 && box.innerHTML) box.hidden = false; });

    // Клавіатура: ↑↓ між пунктами, Enter — перейти, Esc — закрити.
    input.addEventListener("keydown", function (e) {
      var items = Array.prototype.slice.call(box.querySelectorAll("a"));
      var cur = box.querySelector("a.is-active");
      var i = items.indexOf(cur);
      if (e.key === "ArrowDown") { e.preventDefault(); if (box.hidden) return; var n = items[Math.min(i + 1, items.length - 1)] || items[0]; if (n) { if (cur) cur.classList.remove("is-active"); n.classList.add("is-active"); n.scrollIntoView({ block: "nearest" }); } }
      else if (e.key === "ArrowUp") { e.preventDefault(); var pchoice = items[Math.max(i - 1, 0)]; if (pchoice) { if (cur) cur.classList.remove("is-active"); pchoice.classList.add("is-active"); pchoice.scrollIntoView({ block: "nearest" }); } }
      else if (e.key === "Enter") { if (cur) { e.preventDefault(); window.location.href = cur.getAttribute("href"); } }
      else if (e.key === "Escape") { close(); }
    });

    // Сабміт форми (Enter без вибору) → сторінка результатів.
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var q = input.value.trim();
      if (q) window.location.href = PAGE + "?q=" + encodeURIComponent(q);
    });

    document.addEventListener("click", function (e) { if (!form.contains(e.target)) close(); });
  }

  function init() {
    var forms = document.querySelectorAll("form.header-search, form.mnav-search");
    Array.prototype.forEach.call(forms, wire);
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();
})();
