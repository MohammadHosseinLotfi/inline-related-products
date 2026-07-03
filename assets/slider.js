(function () {
  "use strict";

  function initSlider(root) {
    var track = root.querySelector(".irp-slider__track");
    var prev = root.querySelector(".irp-slider__prev");
    var next = root.querySelector(".irp-slider__next");
    if (!track) { return; }

    function step() {
      var first = track.querySelector("li");
      var gap = parseInt(getComputedStyle(track).columnGap || getComputedStyle(track).gap || "14", 10) || 14;
      return first ? first.getBoundingClientRect().width + gap : 320;
    }

    // در چیدمان RTL جهت scrollLeft منفی است؛ با Math.abs امن رفتار می‌کنیم.
    function update() {
      var max = track.scrollWidth - track.clientWidth - 1;
      var pos = Math.abs(track.scrollLeft);
      if (prev) { prev.disabled = pos <= 0; }
      if (next) { next.disabled = pos >= max; }
    }

    if (next) { next.addEventListener("click", function () { track.scrollBy({ left: -step(), behavior: "smooth" }); }); }
    if (prev) { prev.addEventListener("click", function () { track.scrollBy({ left: step(), behavior: "smooth" }); }); }
    track.addEventListener("scroll", function () { window.requestAnimationFrame(update); }, { passive: true });
    window.addEventListener("resize", update);
    update();
  }

  document.addEventListener("DOMContentLoaded", function () {
    var sliders = document.querySelectorAll("[data-irp-slider]");
    Array.prototype.forEach.call(sliders, initSlider);
  });
})();
