(function () {
  "use strict";

  var root = document.getElementById("irp-app");
  if (!root || typeof IRP_ADMIN === "undefined") { return; }

  var input = document.getElementById("irp-blocks-input");
  var i18n = IRP_ADMIN.i18n;

  var headings = safeJSON(textOf("irp-headings"), []);
  var cache = safeJSON(textOf("irp-initial-products"), {});
  var blocks = safeJSON(input.value, []);
  if (!Array.isArray(blocks)) { blocks = []; }
  blocks = blocks.map(function (b) { return migrate(b); });

  var activeBp = {}; // key -> 'd' | 't' | 'm'

  var searchInput = root.querySelector(".irp-search__input");
  var resultsBox = root.querySelector(".irp-search__results");
  var list = root.querySelector(".irp-blocks");
  var groupBtn = root.querySelector(".irp-toolbar__group");
  var groupLayout = root.querySelector(".irp-toolbar__layout");
  var timer = null;

  var BPS = [
    { id: "d", label: i18n.bpDesktop },
    { id: "t", label: i18n.bpTablet },
    { id: "m", label: i18n.bpMobile }
  ];

  function textOf(id) { var n = document.getElementById(id); return n ? n.textContent : ""; }
  function safeJSON(s, fb) { try { var v = JSON.parse(s || ""); return v == null ? fb : v; } catch (e) { return fb; } }
  function uid() { return "irp" + Math.random().toString(36).slice(2, 8); }
  function save() { input.value = JSON.stringify(blocks); }
  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>\"']/g, function (c) {
      return ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '\"': "&quot;", "'": "&#39;" })[c];
    });
  }
  function node(html) {
    var t = document.createElement("template");
    t.innerHTML = html.trim();
    return t.content.firstChild;
  }
  function isUsed(id) { return blocks.some(function (b) { return b.products.indexOf(id) !== -1; }); }
  function pinfo(id) { return cache[id] || { title: "#" + id, thumb: "", price: "" }; }
  function bpDefaults() {
    return { mode: "slider", slides: 3, columns: 2, listDir: "h", cardDir: "h", showImage: true, showDesc: true, showPrice: true, showButton: true };
  }
  function clampNum(v, def) { var n = parseInt(v, 10); if (isNaN(n)) { n = def; } return Math.min(6, Math.max(1, n)); }

  // تضمین ساختار دستگاهی d/t/m روی هر بلوک (شامل ارتقای بلوک‌های تختِ قدیمی).
  function migrate(b) {
    b = b || {};
    if (!b.key) { b.key = uid(); }
    if (!Array.isArray(b.products)) { b.products = []; }
    if (!b.placement) { b.placement = "manual"; }
    if (typeof b.heading === "undefined") { b.heading = 0; }
    if (!b.d || typeof b.d !== "object") {
      var d = bpDefaults();
      if (b.layout === "grid") { d.mode = "grid"; }
      if (typeof b.slidesDesktop !== "undefined") { d.slides = clampNum(b.slidesDesktop, 3); }
      if (typeof b.columns !== "undefined") { d.columns = clampNum(b.columns, 2); }
      if (b.listDir === "v") { d.listDir = "v"; }
      if (b.cardDir === "v") { d.cardDir = "v"; }
      ["showImage", "showDesc", "showPrice", "showButton"].forEach(function (k) { if (k in b) { d[k] = !!b[k]; } });
      var mm = {};
      if (typeof b.slidesMobile !== "undefined" && clampNum(b.slidesMobile, 1) !== d.slides) { mm.slides = clampNum(b.slidesMobile, 1); }
      b.d = d; b.t = {}; b.m = mm;
    }
    if (!b.t || typeof b.t !== "object") { b.t = {}; }
    if (!b.m || typeof b.m !== "object") { b.m = {}; }
    ["layout", "slidesDesktop", "slidesMobile", "columns", "listDir", "cardDir", "showImage", "showDesc", "showPrice", "showButton"].forEach(function (k) { delete b[k]; });
    return b;
  }

  // مقدار مؤثر یک گزینه در یک بریک‌پوینت با ارث‌بری m ← t ← d.
  function eff(b, bp, key) {
    if (bp === "m" && key in b.m) { return b.m[key]; }
    if ((bp === "m" || bp === "t") && key in b.t) { return b.t[key]; }
    return b.d[key];
  }
  function setVal(b, bp, key, val) { if (bp === "d") { b.d[key] = val; } else { b[bp][key] = val; } }
  function hasOverrides(b, bp) { return bp !== "d" && b[bp] && Object.keys(b[bp]).length > 0; }
  function findBlock(key) { return blocks.find(function (x) { return x.key === key; }); }

  // ---------- جستجو ----------
  searchInput.addEventListener("input", function () {
    var q = searchInput.value.trim();
    clearTimeout(timer);
    if (q.length < 2) { resultsBox.hidden = true; resultsBox.innerHTML = ""; return; }
    timer = setTimeout(function () { doSearch(q); }, 300);
  });
  document.addEventListener("click", function (e) {
    if (!root.querySelector(".irp-search").contains(e.target)) { resultsBox.hidden = true; }
  });

  function doSearch(q) {
    resultsBox.hidden = false;
    resultsBox.innerHTML = '<div class="irp-note">' + esc(i18n.searching) + "</div>";
    fetch(IRP_ADMIN.restUrl + "?q=" + encodeURIComponent(q), { headers: { "X-WP-Nonce": IRP_ADMIN.nonce } })
      .then(function (r) { return r.json(); })
      .then(function (items) {
        resultsBox.innerHTML = "";
        if (!items || !items.length) { resultsBox.innerHTML = '<div class="irp-note">' + esc(i18n.noResults) + "</div>"; return; }
        items.forEach(function (p) {
          cache[p.id] = { title: p.title, thumb: p.thumb, price: p.price };
          var used = isUsed(p.id);
          var row = node(
            '<button type="button" class="irp-result" ' + (used ? "disabled" : "") + '>' +
              '<img class="irp-result__img" src="' + esc(p.thumb) + '" alt="">' +
              '<span class="irp-result__title">' + esc(p.title) + (p.sku ? ' <small>#' + esc(p.sku) + "</small>" : "") + "</span>" +
              '<span class="irp-result__price">' + p.price + "</span>" +
              (used ? '<span class="irp-result__used">' + esc(i18n.added) + "</span>" : "") +
            "</button>"
          );
          if (!used) {
            row.addEventListener("click", function () {
              addSingle(p.id);
              resultsBox.hidden = true; resultsBox.innerHTML = "";
              searchInput.value = ""; searchInput.focus();
            });
          }
          resultsBox.appendChild(row);
        });
      })
      .catch(function () { resultsBox.innerHTML = '<div class="irp-note">' + esc(i18n.error) + "</div>"; });
  }

  function addSingle(id) {
    if (isUsed(id)) { return; }
    blocks.push(migrate({ key: uid(), type: "single", products: [id], placement: "manual", heading: 0, d: bpDefaults(), t: {}, m: {} }));
    render();
  }

  // ---------- گروه‌بندی ----------
  groupBtn.addEventListener("click", function () {
    var checked = Array.prototype.map.call(
      list.querySelectorAll(".irp-block__check:checked"),
      function (c) { return c.getAttribute("data-key"); }
    );
    if (checked.length < 2) { window.alert(i18n.selectTwo); return; }
    var ids = [];
    blocks = blocks.filter(function (b) {
      if (checked.indexOf(b.key) !== -1 && b.type === "single") { ids.push(b.products[0]); return false; }
      return true;
    });
    if (ids.length < 2) { render(); return; }
    var gd = bpDefaults();
    gd.mode = (groupLayout.value === "grid") ? "grid" : "slider";
    blocks.push(migrate({ key: uid(), type: "group", products: ids, placement: "manual", heading: 0, d: gd, t: {}, m: {} }));
    render();
  });

  function ungroup(key) {
    var idx = blocks.findIndex(function (b) { return b.key === key; });
    if (idx === -1) { return; }
    var singles = blocks[idx].products.map(function (id) {
      return migrate({ key: uid(), type: "single", products: [id], placement: "manual", heading: 0, d: bpDefaults(), t: {}, m: {} });
    });
    blocks.splice.apply(blocks, [idx, 1].concat(singles));
    render();
  }

  function removeBlock(key) { blocks = blocks.filter(function (b) { return b.key !== key; }); render(); }

  function removeFromGroup(key, id) {
    var b = blocks.find(function (x) { return x.key === key; });
    if (!b) { return; }
    b.products = b.products.filter(function (p) { return p !== id; });
    if (b.products.length === 1) { b.type = "single"; }
    if (b.products.length === 0) { removeBlock(key); return; }
    render();
  }

  // ---------- رندر ----------
  function headingSelect(b) {
    var opts = '<option value="0">' + esc(i18n.chooseHeading) + "</option>";
    headings.forEach(function (h) {
      opts += '<option value="' + h.index + '"' + (Number(b.heading) === h.index ? " selected" : "") +
        ">H" + h.level + " · " + esc(h.text || ("#" + h.index)) + "</option>";
    });
    if (!headings.length) { opts = '<option value="0">' + esc(i18n.noHeadings) + "</option>"; }
    return '<select class="irp-field irp-heading" data-key="' + b.key + '">' + opts + "</select>";
  }

  function shortcodeRow(b) {
    var code = '[irp key=\"' + b.key + '\"]';
    return '<div class="irp-shortcode"><code>' + esc(code) + "</code>" +
      '<button type="button" class="button irp-copy" data-code="' + esc(code) + '">' + esc(i18n.copy) + "</button></div>";
  }

  function placementRow(b) {
    var manual = b.placement !== "auto";
    return '<div class="irp-row">' +
      '<label class="irp-lbl">' + esc(i18n.placement) + "</label>" +
      '<select class="irp-field irp-placement" data-key="' + b.key + '">' +
        '<option value="manual"' + (manual ? " selected" : "") + ">" + esc(i18n.manual) + "</option>" +
        '<option value="auto"' + (!manual ? " selected" : "") + ">" + esc(i18n.auto) + "</option>" +
      "</select>" +
      (manual ? shortcodeRow(b) : headingSelect(b)) +
    "</div>";
  }

  function tabToggle(b, bp, key, label) {
    return '<label class="irp-opt"><input type="checkbox" class="irp-toggle" data-key="' + b.key + '" data-bp="' + bp + '" data-opt="' + key + '"' + (eff(b, bp, key) ? " checked" : "") + "> " + esc(label) + "</label>";
  }

  function selectRow(b, bp, key, label, options) {
    var cur = eff(b, bp, key);
    var opts = "";
    options.forEach(function (o) { opts += '<option value="' + o.v + '"' + (cur === o.v ? " selected" : "") + ">" + esc(o.l) + "</option>"; });
    return '<div class="irp-opts__row"><label class="irp-lbl">' + esc(label) + "</label>" +
      '<select class="irp-field irp-opt-select" data-key="' + b.key + '" data-bp="' + bp + '" data-opt="' + key + '">' + opts + "</select></div>";
  }

  function numRow(b, bp, key, label) {
    return '<div class="irp-opts__row"><label class="irp-lbl">' + esc(label) + "</label>" +
      '<input type="number" min="1" max="6" step="1" class="irp-field irp-num" data-key="' + b.key + '" data-bp="' + bp + '" data-opt="' + key + '" value="' + clampNum(eff(b, bp, key), key === "slides" ? 3 : 2) + '"></div>';
  }

  function tabPanel(b, bp) {
    var html = '<div class="irp-panel">';
    html += '<div class="irp-opts__grid">' +
      tabToggle(b, bp, "showImage", i18n.showImage) +
      tabToggle(b, bp, "showDesc", i18n.showDesc) +
      tabToggle(b, bp, "showPrice", i18n.showPrice) +
      tabToggle(b, bp, "showButton", i18n.showButton) + "</div>";
    html += selectRow(b, bp, "cardDir", i18n.cardDir, [{ v: "h", l: i18n.cardH }, { v: "v", l: i18n.cardV }]);
    if (b.type === "group") {
      html += selectRow(b, bp, "mode", i18n.mode, [{ v: "slider", l: i18n.slider }, { v: "grid", l: i18n.grid }]);
      if (eff(b, bp, "mode") === "slider") {
        html += numRow(b, bp, "slides", i18n.slides);
      } else {
        html += selectRow(b, bp, "listDir", i18n.listDir, [{ v: "h", l: i18n.listH }, { v: "v", l: i18n.listV }]);
        if (eff(b, bp, "listDir") !== "v") { html += numRow(b, bp, "columns", i18n.columns); }
      }
    }
    if (bp !== "d") {
      html += '<div class="irp-panel__note">' + esc(i18n.inheritHint);
      if (hasOverrides(b, bp)) {
        html += ' <button type="button" class="irp-reset" data-key="' + b.key + '" data-bp="' + bp + '">' + esc(i18n.resetBp) + "</button>";
      }
      html += "</div>";
    }
    html += "</div>";
    return html;
  }

  function tabsRow(b) {
    var cur = activeBp[b.key] || "d";
    var tabs = '<div class="irp-tabs">';
    BPS.forEach(function (tb) {
      var ovr = hasOverrides(b, tb.id) ? " has-ovr" : "";
      var act = (tb.id === cur) ? " is-active" : "";
      tabs += '<button type="button" class="irp-tab' + act + ovr + '" data-key="' + b.key + '" data-bp="' + tb.id + '">' + esc(tb.label) + "</button>";
    });
    tabs += "</div>";
    return '<div class="irp-tabswrap">' + tabs + tabPanel(b, cur) + "</div>";
  }

  function miniProduct(id, key, removable) {
    var p = pinfo(id);
    return '<div class="irp-mini">' +
      '<img class="irp-mini__img" src="' + esc(p.thumb) + '" alt="">' +
      '<span class="irp-mini__title">' + esc(p.title) + "</span>" +
      (removable ? '<button type="button" class="irp-mini__x" data-key="' + key + '" data-id="' + id + '">×</button>' : "") +
    "</div>";
  }

  function renderBlock(b) {
    var wrap = node('<div class="irp-block irp-block--' + b.type + '"></div>');
    if (b.type === "single") {
      wrap.appendChild(node(
        '<div class="irp-block__head">' +
          '<label class="irp-block__sel"><input type="checkbox" class="irp-block__check" data-key="' + b.key + '"> ' + esc(i18n.selectGroup) + "</label>" +
          '<span class="irp-badge">' + esc(i18n.single) + "</span>" +
          '<button type="button" class="irp-block__del" data-key="' + b.key + '">' + esc(i18n.remove) + "</button>" +
        "</div>"
      ));
      wrap.appendChild(node('<div class="irp-block__body">' + miniProduct(b.products[0], b.key, false) + "</div>"));
    } else {
      wrap.appendChild(node(
        '<div class="irp-block__head">' +
          '<span class="irp-badge irp-badge--group">' + esc(i18n.group) + " (" + b.products.length + ")</span>" +
          '<button type="button" class="irp-block__ungroup" data-key="' + b.key + '">' + esc(i18n.ungroup) + "</button>" +
          '<button type="button" class="irp-block__del" data-key="' + b.key + '">' + esc(i18n.remove) + "</button>" +
        "</div>"
      ));
      var body = node('<div class="irp-block__body irp-block__body--group"></div>');
      b.products.forEach(function (id) { body.appendChild(node(miniProduct(id, b.key, true))); });
      wrap.appendChild(body);
    }
    wrap.appendChild(node(placementRow(b)));
    wrap.appendChild(node(tabsRow(b)));
    return wrap;
  }

  function render() {
    save();
    list.innerHTML = "";
    if (!blocks.length) { list.appendChild(node('<p class="irp-empty">' + esc(i18n.empty) + "</p>")); return; }
    blocks.forEach(function (b) { list.appendChild(renderBlock(b)); });
  }

  // ---------- رویدادها ----------
  list.addEventListener("change", function (e) {
    var t = e.target;
    var key = t.getAttribute("data-key");
    if (!key) { return; }
    var b = findBlock(key);
    if (!b) { return; }
    var bp = t.getAttribute("data-bp");
    if (t.classList.contains("irp-placement")) { b.placement = t.value; if (t.value === "manual") { b.heading = 0; } render(); }
    else if (t.classList.contains("irp-heading")) { b.heading = parseInt(t.value, 10) || 0; save(); }
    else if (t.classList.contains("irp-toggle")) { setVal(b, bp, t.getAttribute("data-opt"), t.checked); render(); }
    else if (t.classList.contains("irp-opt-select")) { setVal(b, bp, t.getAttribute("data-opt"), t.value); render(); }
    else if (t.classList.contains("irp-num")) { setVal(b, bp, t.getAttribute("data-opt"), clampNum(t.value, t.getAttribute("data-opt") === "slides" ? 3 : 2)); render(); }
  });

  list.addEventListener("click", function (e) {
    var btn = e.target.closest ? e.target.closest("button") : e.target;
    if (!btn) { return; }
    if (btn.classList.contains("irp-tab")) { activeBp[btn.getAttribute("data-key")] = btn.getAttribute("data-bp"); render(); }
    else if (btn.classList.contains("irp-reset")) { var rb = findBlock(btn.getAttribute("data-key")); if (rb) { rb[btn.getAttribute("data-bp")] = {}; render(); } }
    else if (btn.classList.contains("irp-block__del")) { removeBlock(btn.getAttribute("data-key")); }
    else if (btn.classList.contains("irp-block__ungroup")) { ungroup(btn.getAttribute("data-key")); }
    else if (btn.classList.contains("irp-mini__x")) { removeFromGroup(btn.getAttribute("data-key"), parseInt(btn.getAttribute("data-id"), 10)); }
    else if (btn.classList.contains("irp-copy")) {
      var code = btn.getAttribute("data-code");
      if (navigator.clipboard) { navigator.clipboard.writeText(code); }
      var old = btn.textContent; btn.textContent = i18n.copied;
      setTimeout(function () { btn.textContent = old; }, 1200);
    }
  });

  render();
})();
