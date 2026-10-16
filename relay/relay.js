'use strict';

// Optionales LAN-Relay: nimmt einfache HTTP-GETs aus dem LAN an (z. B. von Loxone),
// ergänzt das Push-Token und leitet per HTTPS an push.php weiter.
//
// Aufruf aus Loxone:  http://<relay-ip>:8081/?pv_power=4.2&load_power=0.7
// Weiterleitung an:   TARGET + ?pv_power=4.2&load_power=0.7&t=TOKEN

var http = require('http');
var https = require('https');

var TARGET = process.env.TARGET;               // z. B. https://dash.deinedomain.ch/push.php
var TOKEN = process.env.TOKEN;                 // Push-Token aus config.php
var PORT = parseInt(process.env.PORT || '8081', 10);

if (!TARGET || !TOKEN) {
  console.error('TARGET und TOKEN müssen gesetzt sein.');
  process.exit(1);
}

var server = http.createServer(function (req, res) {
  var inUrl = new URL(req.url, 'http://localhost');

  if (inUrl.pathname === '/health') {
    res.writeHead(200, { 'Content-Type': 'text/plain' });
    return res.end('ok');
  }

  if (!inUrl.search || inUrl.search === '?') {
    res.writeHead(400, { 'Content-Type': 'text/plain' });
    return res.end('keine Werte');
  }

  var out = new URL(TARGET);
  inUrl.searchParams.forEach(function (value, key) {
    if (key !== 't') out.searchParams.set(key, value);
  });
  out.searchParams.set('t', TOKEN);

  var lib = out.protocol === 'https:' ? https : http;
  var up = lib.get(out, { timeout: 10000 }, function (r) {
    var body = '';
    r.setEncoding('utf8');
    r.on('data', function (c) { body += c; });
    r.on('end', function () {
      res.writeHead(r.statusCode || 502, { 'Content-Type': 'text/plain' });
      res.end(body);
    });
  });

  up.on('timeout', function () { up.destroy(new Error('timeout')); });
  up.on('error', function (e) {
    console.error('Weiterleitung fehlgeschlagen:', e.message);
    res.writeHead(502, { 'Content-Type': 'text/plain' });
    res.end('upstream error');
  });
});

server.listen(PORT, function () {
  console.log('Relay auf Port ' + PORT + ' -> ' + TARGET);
});
