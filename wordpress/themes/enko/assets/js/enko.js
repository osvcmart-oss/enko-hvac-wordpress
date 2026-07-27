/* ENKO theme — light front-end behaviours. Popups/bars live in enko-core. */
(function () {
  "use strict";

  // Sticky add-to-request bar on single product: show when the main buybox
  // scrolls out of view (mirrors prototype sticky-bar v2).
  function initStickyBar() {
    var bar = document.querySelector(".enko-sticky-bar");
    var anchor = document.querySelector(".single-product form.cart, .single-product .summary .price");
    if (!bar || !anchor || !("IntersectionObserver" in window)) return;
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) { bar.classList.toggle("show", !e.isIntersecting); });
    }, { rootMargin: "-40px 0px 0px 0px" });
    io.observe(anchor);
  }

  document.addEventListener("DOMContentLoaded", function () {
    initStickyBar();
  });
})();
