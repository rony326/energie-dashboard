<?php
require_once __DIR__ . '/config.php';

if (!hash_equals(VIEW_KEY, (string)($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('forbidden');
}
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Energie</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  @font-face { font-family: "Lato"; font-weight: 400; src: url("fonts/lato-latin-400-normal.woff2") format("woff2"); }
  @font-face { font-family: "Lato"; font-weight: 700; src: url("fonts/lato-latin-700-normal.woff2") format("woff2"); }
  @font-face { font-family: "Lato"; font-weight: 900; src: url("fonts/lato-latin-900-normal.woff2") format("woff2"); }

  :root {
    --bg: #ffffff;
    --ink: #1b2330;
    --muted: #8a93a3;
    --line: #e6e9ef;
    --gold: #b8964a;
    --gold-dark: #94742f;
    --stale: #c3c9d3;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html, body { width: 100%; height: 100%; overflow: hidden; background: var(--bg); }
  body { font-family: "Lato", "Helvetica Neue", Arial, sans-serif; color: var(--ink); }

  #stage { position: absolute; left: 0; top: 0; width: 2304px; height: 608px;
           -webkit-transform-origin: 0 0; transform-origin: 0 0;
           padding: 30px 48px 26px; display: flex; flex-direction: column; }

  header { display: flex; align-items: flex-end; justify-content: space-between;
           padding-bottom: 14px; border-bottom: 2px solid var(--gold); }
  .eyebrow { font-size: 15px; font-weight: 700; letter-spacing: .16em; text-transform: uppercase; color: var(--gold-dark); }
  .title { font-size: 38px; font-weight: 900; line-height: 1.05; margin-top: 4px; letter-spacing: -.01em; }

  #body { flex: 1; display: flex; min-height: 0; margin-top: 10px; }
  #flow { width: 1200px; height: 100%; padding-right: 24px; }
  #flow svg { width: 100%; height: 100%; display: block; }

  #tiles { flex: 1; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); border-left: 1px solid var(--line); }
  .tile { padding: 0 36px; display: flex; flex-direction: column; justify-content: center; min-width: 0; }
  .tile.cl { border-left: 1px solid var(--line); }
  .tile.rt { border-top: 1px solid var(--line); }
  .tile .t { font-size: 16px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: var(--muted); white-space: nowrap; }
  .tile .dot { display: inline-block; width: 11px; height: 11px; border-radius: 50%; margin-right: 10px; vertical-align: 0; }
  .tile .v { margin-top: 10px; white-space: nowrap; }
  .num { font-size: 96px; font-weight: 900; line-height: 1; letter-spacing: -.03em; color: var(--ink); }
  .unit { font-size: 30px; font-weight: 700; color: var(--gold-dark); margin-left: 10px; }
  .num.stale { color: var(--stale); }

  svg text { font-family: "Lato", "Helvetica Neue", Arial, sans-serif; text-anchor: middle; }
  .lbl { fill: #1b2330; font-size: 27px; font-weight: 900; }
  .nme { fill: #8a93a3; font-size: 14px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
  .sub { fill: #94742f; font-size: 15px; font-weight: 700; }
  svg text.stale { fill: #c3c9d3; }

  .dash { fill: none; stroke: #b8964a; stroke-width: 4; stroke-linecap: butt; stroke-dasharray: 10 8;
          -webkit-animation: fl 1s linear infinite; animation: fl 1s linear infinite; }
  .dash.rev { -webkit-animation-direction: reverse; animation-direction: reverse; }
  .dash.off { -webkit-animation: none; animation: none; stroke: #d9dde4; stroke-dasharray: none; stroke-width: 3; }
  @-webkit-keyframes fl { to { stroke-dashoffset: -18; } }
  @keyframes fl { to { stroke-dashoffset: -18; } }

  #msg { position: absolute; right: 48px; bottom: 6px; font-size: 14px; color: #c2410c; }
</style>
</head>
<body>

<div id="stage">
  <header>
    <div>
      <div class="eyebrow" id="eyebrow">Energie</div>
      <div class="title" id="title"></div>
    </div>
  </header>
  <div id="body">
    <div id="flow"></div>
    <div id="tiles"></div>
  </div>
  <div id="msg"></div>
</div>

<script>
(function () {
  var REFRESH_MS = 10000;
  var R = 40, RING = 50, GAP = 108, SLOT = 260, WHITE = '#ffffff';

  var G = 'fill="none" stroke="' + WHITE + '" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"';
  var ICONS = {
    solar: '<g ' + G + '><polygon points="-18,4 18,4 25,28 -25,28"/><line x1="0" y1="4" x2="0" y2="28"/>' +
           '<line x1="-21.5" y1="16" x2="21.5" y2="16"/><circle cx="0" cy="-14" r="7"/>' +
           '<line x1="0" y1="-27" x2="0" y2="-23"/><line x1="-14" y1="-24" x2="-11" y2="-21"/>' +
           '<line x1="14" y1="-24" x2="11" y2="-21"/></g>',
    house: '<g ' + G + '><polyline points="-26,-2 0,-24 26,-2"/><polyline points="-18,-8 -18,22 18,22 18,-8"/>' +
           '<polyline points="-6,22 -6,6 6,6 6,22"/></g>',
    grid:  '<polygon points="6,-28 -14,4 -2,4 -6,28 14,-6 2,-6" fill="' + WHITE + '"/>',
    ev:    '<g ' + G + '><rect x="-20" y="-26" width="26" height="50" rx="4"/>' +
           '<path d="M6,-8 L16,-8 Q22,-8 22,-2 L22,14"/></g>' +
           '<polygon points="-3,-18 -13,0 -8,0 -10,16 0,-4 -5,-4" fill="' + WHITE + '"/>',
    battery: '<g ' + G + '><rect x="-26" y="-13" width="48" height="26" rx="3"/></g>' +
             '<rect x="22" y="-5" width="5" height="10" fill="' + WHITE + '"/>' +
             '<rect x="-20" y="-7" width="10" height="14" fill="' + WHITE + '"/>' +
             '<rect x="-6" y="-7" width="10" height="14" fill="' + WHITE + '"/>'
  };

  var m = /[?&]key=([^&]*)/.exec(location.search);
  var KEY = m ? m[1] : '';
  var CFG = null, L = null, SEG = 0;

  function byId(id) { return document.getElementById(id); }

  function esc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function setClass(e, name, on) {
    var c = (' ' + (e.getAttribute('class') || '') + ' ').replace(' ' + name + ' ', ' ');
    c = c.replace(/^\s+|\s+$/g, '');
    e.setAttribute('class', on ? (c ? c + ' ' : '') + name : c);
  }

  function setFlow(e, f) {
    var c = 'dash';
    if (Math.abs(f) < 0.05) c += ' off';
    else if (f < 0) c += ' rev';
    e.setAttribute('class', c);
  }

  function fit() {
    var s = Math.min(window.innerWidth / 2304, window.innerHeight / 608);
    var st = byId('stage');
    st.style.webkitTransform = 'scale(' + s + ')';
    st.style.transform = 'scale(' + s + ')';
  }

  function nodeSvg(n) {
    var x = n._x, cy = n._cy, up = n._top;
    var yv = up ? cy - 62 : cy + 84;
    var yn = up ? cy - 90 : cy + 108;
    var ys = up ? cy - 108 : cy + 128;
    var s = '';
    s += '<circle cx="' + x + '" cy="' + cy + '" r="' + RING + '" fill="none" stroke="' + n.color + '" stroke-opacity="0.3" stroke-width="2"/>';
    s += '<circle cx="' + x + '" cy="' + cy + '" r="' + R + '" fill="' + n.color + '"/>';
    s += '<g transform="translate(' + x + ',' + cy + ') scale(0.92)">' + (ICONS[n.icon] || ICONS.house) + '</g>';
    s += '<text id="v_' + n.id + '" class="lbl" x="' + x + '" y="' + yv + '">–</text>';
    s += '<text class="nme" x="' + x + '" y="' + yn + '">' + esc(n.label) + '</text>';
    if (n.sub) s += '<text id="s_' + n.id + '" class="sub" x="' + x + '" y="' + ys + '"></text>';
    return s;
  }

  function build(layout) {
    var i, j, n, top = [], bottom = [], all = [];
    L = layout;

    for (i = 0; i < L.nodes.length; i++) {
      n = L.nodes[i];
      if (n.row === 'top') top.push(n); else bottom.push(n);
    }

    var slots = Math.max(top.length, bottom.length, 1);
    var W = Math.max(700, slots * SLOT);
    var busY = (top.length ? GAP + 110 : 0) + 20;
    var H = busY + (bottom.length ? GAP + 130 : 0) + 10;

    function place(row, cy, isTop) {
      for (var k = 0; k < row.length; k++) {
        row[k]._x = Math.round(W * (k + 0.5) / row.length * 100) / 100;
        row[k]._cy = cy;
        row[k]._top = isTop;
        all.push(row[k]);
      }
    }
    place(top, busY - GAP, true);
    place(bottom, busY + GAP, false);

    var U = [];
    for (i = 0; i < all.length; i++) {
      if (U.indexOf(all[i]._x) < 0) U.push(all[i]._x);
    }
    U.sort(function (a, b) { return a - b; });
    for (i = 0; i < all.length; i++) all[i]._j = U.indexOf(all[i]._x);
    SEG = Math.max(U.length - 1, 0);

    var s = '<svg viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg">';
    for (j = 0; j < SEG; j++) {
      s += '<path id="b_' + j + '" class="dash off" d="M' + U[j] + ' ' + busY + ' L' + U[j + 1] + ' ' + busY + '"/>';
    }
    for (i = 0; i < all.length; i++) {
      n = all[i];
      var y1 = n._top ? n._cy + RING : n._cy - RING;
      s += '<path id="c_' + n.id + '" class="dash off" d="M' + n._x + ' ' + y1 + ' L' + n._x + ' ' + busY + '"/>';
    }
    for (i = 0; i < all.length; i++) s += nodeSvg(all[i]);
    s += '</svg>';

    byId('title').textContent = L.title || '';
    byId('flow').innerHTML = s;

    var cols = 3, rows = Math.max(1, Math.ceil(L.tiles.length / cols));
    var t = '';
    for (i = 0; i < L.tiles.length; i++) {
      var tl = L.tiles[i];
      var cls = 'tile' + (i % cols !== 0 ? ' cl' : '') + (i >= cols ? ' rt' : '');
      t += '<div class="' + cls + '">' +
           '<div class="t"><span class="dot" style="background:' + tl.color + '"></span>' + esc(tl.label) + '</div>' +
           '<div class="v"><span class="num" id="t_' + i + '">–</span>' +
           '<span class="unit">' + esc(tl.unit || '') + '</span></div></div>';
    }
    var tiles = byId('tiles');
    tiles.innerHTML = t;
    tiles.style.gridTemplateRows = 'repeat(' + rows + ', minmax(0, 1fr))';
  }

  function render(d) {
    var v = d.values, stale = L.stale || 900, i, j, n, e, ok, isOld, tb, el;
    var inj = [];

    for (i = 0; i < L.nodes.length; i++) {
      n = L.nodes[i];
      e = v[n.key];
      ok = !!e && typeof e.v === 'number';
      isOld = !ok || (d.now - e.ts) > stale;
      tb = 0;

      el = byId('v_' + n.id);
      el.textContent = ok ? e.v.toFixed(n.dec === undefined ? 1 : n.dec) + ' kW' : '–';
      setClass(el, 'stale', isOld);

      if (!isOld) tb = (n.dir === 'fromBus') ? -e.v : e.v;

      if (n.sub) {
        var se = v[n.sub], st = '';
        if (se) st = (typeof se.v === 'number') ? se.v.toFixed(0) + (n.subUnit || '') : se.v;
        byId('s_' + n.id).textContent = st;
      }

      setFlow(byId('c_' + n.id), tb);
      inj[n._j] = (inj[n._j] || 0) + tb;
    }

    var acc = 0;
    for (j = 0; j < SEG; j++) {
      acc += (inj[j] || 0);
      setFlow(byId('b_' + j), acc);
    }

    for (i = 0; i < L.tiles.length; i++) {
      var tl = L.tiles[i], te = v[tl.key];
      var tok = !!te && typeof te.v === 'number';
      el = byId('t_' + i);
      el.textContent = tok ? te.v.toFixed(tl.dec === undefined ? 1 : tl.dec) : '–';
      setClass(el, 'stale', !tok || (d.now - te.ts) > (tl.stale || stale));
    }
  }

  function load() {
    var x = new XMLHttpRequest();
    x.open('GET', 'api.php?key=' + KEY + '&_=' + new Date().getTime(), true);
    x.onreadystatechange = function () {
      if (x.readyState !== 4) return;
      var msg = byId('msg');
      if (x.status !== 200) { msg.textContent = 'Keine Verbindung'; return; }
      try {
        var d = JSON.parse(x.responseText);
        if (CFG === null) { CFG = d.cfg; build(d.layout); }
        else if (d.cfg !== CFG) { location.reload(); return; }
        render(d);
        msg.textContent = '';
      } catch (err) {
        msg.textContent = 'Datenfehler';
      }
    };
    x.send();
  }

  fit();
  window.onresize = fit;
  load();
  setInterval(load, REFRESH_MS);
})();
</script>
</body>
</html>
