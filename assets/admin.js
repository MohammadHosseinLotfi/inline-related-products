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

  var searchInput = root.querySelector(".irp-search__input");
  var resultsBox = root.querySelector(".irp-search__results");
  var list = root.querySelector(".irp-blocks");
  var groupBtn = root.querySelector(".irp-toolbar__group");
  var groupLayout = root.querySelector(".irp-toolbar__layout");
  var timer = null;

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
    blocks.push({ key: uid(), type: "single", products: [id], layout: "card", placement: "manual", heading: 0 });
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
    blocks.push({ key: uid(), type: "group", products: ids, layout: groupLayout.value || "slider", placement: "manual", heading: 0 });
    render();
  });

  function ungroup(key) {
    var idx = blocks.findIndex(function (b) { return b.key === key; });
    if (idx === -1) { return; }
    var singles = blocks[idx].products.map(function (id) {
      return { key: uid(), type: "single", products: [id], layout: "card", placement: "manual", heading: 0 };
    });
    blocks.splice.apply(blocks, [idx, 1].concat(singles));
    render();
  }

  function removeBlock(key) { blocks = blocks.filter(function (b) { return b.key !== key; }); render(); }

  function removeFromGroup(key, id) {
    var b = blocks.find(function (x) { return x.key === key; });
    if (!b) { return; }
    b.products = b.products.filter(function (p) { return p !== id; });
    if (b.products.length === 1) { b.type = "single"; b.layout = "card"; }
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
          '<select class="irp-field irp-layout" data-key="' + b.key + '">' +
            '<option value="slider"' + (b.layout === "slider" ? " selected" : "") + ">" + esc(i18n.slider) + "</option>" +
            '<option value="grid"' + (b.layout === "grid" ? " selected" : "") + ">" + esc(i18n.grid) + "</option>" +
          "</select>" +
          '<button type="button" class="irp-block__ungroup" data-key="' + b.key + '">' + esc(i18n.ungroup) + "</button>" +
          '<button type="button" class="irp-block__del" data-key="' + b.key + '">' + esc(i18n.remove) + "</button>" +
        "</div>"
      ));
      var body = node('<div class="irp-block__body irp-block__body--group"></div>');
      b.products.forEach(function (id) { body.appendChild(node(miniProduct(id, b.key, true))); });
      wrap.appendChild(body);
    }
    wrap.appendChild(node(placementRow(b)));
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
    var b = blocks.find(function (x) { return x.key === key; });
    if (!b) { return; }
    if (t.classList.contains("irp-layout")) { b.layout = t.value; save(); }
    else if (t.classList.contains("irp-placement")) { b.placement = t.value; if (t.value === "manual") { b.heading = 0; } render(); }
    else if (t.classList.contains("irp-heading")) { b.heading = parseInt(t.value, 10) || 0; save(); }
  });

  list.addEventListener("click", function (e) {
    var t = e.target;
    if (t.classList.contains("irp-block__del")) { removeBlock(t.getAttribute("data-key")); }
    else if (t.classList.contains("irp-block__ungroup")) { ungroup(t.getAttribute("data-key")); }
    else if (t.classList.contains("irp-mini__x")) { removeFromGroup(t.getAttribute("data-key"), parseInt(t.getAttribute("data-id"), 10)); }
    else if (t.classList.contains("irp-copy")) {
      var code = t.getAttribute("data-code");
      if (navigator.clipboard) { navigator.clipboard.writeText(code); }
      var old = t.textContent; t.textContent = i18n.copied;
      setTimeout(function () { t.textContent = old; }, 1200);
    }
  });

  render();
})();
