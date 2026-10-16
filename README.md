# Energie-Dashboard (Loxone → Infomaniak → Yodeck)

Energiefluss mit Wallboxen und Kennzahlen als Infoscreen-Seite im Format 2304×608 (weisser Grund, Lato, Goldakzent). Die Werte werden per HTTP-Aufruf eingeliefert, in MySQL gespeichert und vom Screen alle 10 Sekunden abgeholt. Das Frontend ist bewusst ES5 mit XMLHttpRequest, ohne Frameworks und Websockets, damit es im eingebetteten Browser des Yodeck-Players läuft.

```
Loxone / n8n / Node-RED ──GET──► push.php ──► MySQL (metrics, history)
                                                │
Yodeck-Player ◄── index.php (SVG + JS) ◄── api.php (JSON, alle 10 s)
```

Optional, wenn dein Miniserver kein HTTPS kann:

```
Loxone ──http (LAN)──► relay.js ──https──► push.php
```

## Dateien

```
energie-dashboard/
├── README.md
├── schema.sql            Tabellen (nur in phpMyAdmin ausführen, nicht hochladen)
├── relay/
│   └── relay.js          optionales LAN-Relay (HTTP -> HTTPS, ergänzt das Token)
└── dash/                 Document-Root der Subdomain auf Infomaniak
    ├── .htaccess         sperrt config.php und db.php
    ├── config.php        Zugangsdaten, Knoten, Kacheln
    ├── db.php            PDO-Verbindung
    ├── push.php          Werte entgegennehmen
    ├── api.php           Werte und Layout als JSON
    ├── index.php         das Dashboard
    └── fonts/            lato-latin-400/700/900-normal.woff2
```

## 1. Einrichtung auf Infomaniak

1. Im Manager eine Subdomain als Site anlegen (z. B. `dash.deinedomain.ch`), PHP 8.x wählen und das kostenlose SSL-Zertifikat aktivieren.
2. Eine MySQL-Datenbank mit Benutzer anlegen. Den Hostnamen aus dem Manager übernehmen (er ist nicht `localhost`).
3. In phpMyAdmin `schema.sql` ausführen.
4. Zwei Zufallswerte erzeugen, z. B. mit `openssl rand -hex 24`, und in `config.php` bei `PUSH_TOKEN` und `VIEW_KEY` eintragen. Dort auch die DB-Zugangsdaten setzen.
5. Den Inhalt von `dash/` (inklusive `.htaccess` und `fonts/`) per SFTP in den Document-Root der Subdomain hochladen.
6. Test mit curl:

   ```
   curl "https://dash.deinedomain.ch/push.php?t=DEIN_PUSH_TOKEN&pv_power=18.9&load_power=0.72&grid_power=-12.18&wb1_power=6&wb1_status=laedt"
   ```

   Die Antwort muss `ok 5` lauten.
7. Im Browser `https://dash.deinedomain.ch/?key=DEIN_VIEW_KEY` öffnen.
8. Prüfen, dass `https://dash.deinedomain.ch/config.php` mit "403 Forbidden" antwortet. Sonst greift die `.htaccess` nicht, und die Zugangsdaten wären abrufbar.

## 2. Datenpunkte

Alle Schlüssel dürfen nur aus `a-z`, `A-Z`, `0-9` und `_` bestehen (max. 40 Zeichen). Dezimalkomma wird akzeptiert.

| Schlüssel | Bedeutung | Einheit |
|---|---|---|
| `pv_power` | PV-Leistung | kW |
| `load_power` | Hausverbrauch ohne Wallboxen | kW |
| `wb1_power`, `wb2_power` | Leistung Wallbox 1 und 2 | kW |
| `wb1_status`, `wb2_status` | Status als Text (`bereit`, `laedt`, ...) | Text |
| `grid_power` | Netz, positiv = Bezug, negativ = Einspeisung | kW |
| `pv_day` | Tagesertrag | kWh |
| `self_use` | Eigenverbrauch heute | kWh |
| `feed_in` | Einspeisung heute | kWh |
| `wb_day` | Ladestrom heute | kWh |
| `temp_out` | Aussentemperatur | °C |
| `co2_saved` | CO2-Einsparung | kg |

Damit die Linien auf der Sammelschiene plausibel laufen, sollte die Bilanz aufgehen: `pv_power + grid_power (Bezug) = load_power + wb1_power + wb2_power`. Ist `load_power` der Gesamtverbrauch inklusive Wallboxen, werden die Ladestationen doppelt gezählt, und die Linien am Rand zeigen einen Restwert.

## 3. Werte einliefern

`push.php` akzeptiert GET und POST. Es gibt zwei Formen:

```
# mehrere Werte in einem Aufruf (empfohlen)
/push.php?t=TOKEN&pv_power=4.2&load_power=0.7&wb1_status=bereit

# einzelner Wert
/push.php?t=TOKEN&k=pv_power&v=4.2
```

Zahlen werden als Zahl gespeichert (und alle 60 s in die History geschrieben), alles andere als Text. Text mit Sonderzeichen muss URL-codiert sein (`ä` = `%C3%A4`). Am einfachsten verwendest du ASCII wie `laedt`.

### n8n

Ein **HTTP Request**-Node mit Methode GET, URL `https://dash.deinedomain.ch/push.php` und Query-Parametern `t`, `pv_power`, `load_power` und so weiter. n8n codiert die Werte selbst. Als Auslöser genügt ein **Schedule Trigger** alle 10 bis 30 Sekunden.

### Node-RED

Ein **http request**-Node mit Methode GET und URL `https://dash.deinedomain.ch/push.php?t=TOKEN&pv_power={{payload.pv}}` (Mustache-Syntax aktivieren, oder die URL in einem Function-Node zusammensetzen).

## 4. Loxone-Anleitung

Die Menünamen können je nach Loxone-Config-Version leicht abweichen. Im Zweifel hilft die Loxone-Dokumentation zum Baustein "Virtueller Ausgang".

### Variante A: Miniserver ruft direkt auf

Das geht nur, wenn dein Miniserver HTTPS-Adressen im virtuellen Ausgang unterstützt. Ob das bei deinem Modell und deiner Firmware der Fall ist, solltest du vorab prüfen. Ein Aufruf über unverschlüsseltes HTTP ins Internet ist keine gute Idee, weil das Token im Klartext übertragen würde.

1. In Loxone Config unter **Virtuelle Ein-/Ausgänge** einen **Virtuellen Ausgang** anlegen.
   - Adresse: `https://dash.deinedomain.ch`
   - Die Option aktivieren, die die Verbindung nach dem Senden schliesst (Bezeichnung je nach Version).
2. Unter dem virtuellen Ausgang pro Wert einen **Virtuellen Ausgangsbefehl** anlegen.
   - Befehl bei EIN: `/push.php?t=DEIN_PUSH_TOKEN&pv_power=<v>`
   - `<v>` wird durch den Analogwert am Eingang des Befehls ersetzt.
3. Den Eingang des Befehls mit dem Ausgang des passenden Bausteins verbinden (PV-Leistung, Temperatur, Zähler und so weiter).
4. Für jeden weiteren Wert einen weiteren Befehl mit anderem Schlüssel anlegen (`load_power`, `wb1_power`, ...).
5. Testen: Konfiguration auf den Miniserver speichern und im Browser nachsehen, ob `https://dash.deinedomain.ch/api.php?key=DEIN_VIEW_KEY` den Wert mit aktuellem Zeitstempel (`ts`) enthält.

### Variante B: über das LAN-Relay (empfohlen)

Der Miniserver spricht im LAN unverschlüsselt mit dem Relay, das Relay ergänzt das Token und leitet per HTTPS weiter. Das Token liegt so nur im Relay, nicht in der Loxone-Konfiguration.

1. Relay auf einem Docker-Host im LAN starten (`docker-compose.yml`, siehe unten).
2. In Loxone Config den Virtuellen Ausgang auf die Relay-Adresse zeigen lassen: `http://<IP-des-Relays>:8081`
3. Der Befehl ist dann nur noch: `/?pv_power=<v>`
4. Mehrere Werte gehen nur als getrennte Befehle (ein Befehl = ein Wert). Das Relay gibt jeden Aufruf einzeln weiter.

### Wichtig für beide Varianten

- **Zyklisches Senden:** Loxone sendet nur, wenn sich der Wert am Eingang ändert. Bleibt ein Wert lange konstant (z. B. die Temperatur), wird die Kachel nach 15 Minuten grau. Entweder sendest du zyklisch (z. B. mit einem Impulsgeber, der den Befehl alle 60 s auslöst), oder du lieferst die Werte stattdessen über n8n ein.
- **Dezimalzahlen:** Punkt und Komma werden beide akzeptiert.
- **Negative Werte:** `grid_power` darf negativ sein (Einspeisung).
- **Kontrolle:** Sieht der Dashboard-Screen einen Wert nicht, zuerst `api.php?key=...` im Browser öffnen. Steht der Wert dort nicht, kommt er nicht an. Steht er dort, aber nicht im Screen, stimmt der Schlüssel in `config.php` nicht.

### Relay starten

`docker-compose.yml` neben dem Ordner `relay/`:

```yaml
services:
  dash-relay:
    image: node:22-alpine
    container_name: dash-relay
    restart: unless-stopped
    working_dir: /app
    command: node relay.js
    environment:
      - TARGET=https://dash.deinedomain.ch/push.php
      - TOKEN=DEIN_PUSH_TOKEN
      - PORT=8081
    volumes:
      - ./relay:/app:ro
    ports:
      - "8081:8081"
```

Das Relay gehört nur ins interne VLAN. Es nimmt jeden Aufruf aus dem LAN an. Mit `http://<relay>:8081/health` kannst du prüfen, ob es läuft.

## 5. Konfiguration (`config.php`)

### Weitere Wallbox

In `LAYOUT['nodes']` einen Eintrag ergänzen. Die Positionen verteilen sich automatisch.

```php
['id' => 'wb3', 'label' => 'Wallbox 3', 'icon' => 'ev', 'color' => '#2f9e75', 'row' => 'bottom',
 'key' => 'wb3_power', 'dir' => 'fromBus', 'dec' => 1, 'sub' => 'wb3_status'],
```

Ab etwa fünf Knoten in einer Reihe wird das Diagramm breiter als der linke Bereich und skaliert herunter. Die Schrift wird dann kleiner.

### Batterie

Den auskommentierten Eintrag `bat` aktivieren. Positiv bedeutet Entladen, negativ Laden. `bat_soc` liefert den Ladestand in Prozent.

### Kacheln

Die Kacheln stehen zeilenweise in drei Spalten. Die Spalten 1 und 2 sind für Energiewerte gedacht, Spalte 3 für Umweltwerte. Eine zusätzliche Energiekachel gehört deshalb zwischen die vorhandenen Kacheln und nicht ans Ende.

### Knotenfelder

| Feld | Bedeutung |
|---|---|
| `id` | eindeutig, nur `a-z 0-9 _` |
| `label` | Anzeigename |
| `icon` | `solar`, `house`, `grid`, `ev`, `battery` |
| `color` | Kreisfarbe |
| `row` | `top` oder `bottom` (relativ zur Sammelschiene) |
| `key` | Datenpunkt (Leistung in kW) |
| `dir` | `toBus`: Knoten speist ein (PV, Netzbezug, Batterie entlädt). `fromBus`: Knoten bezieht (Verbraucher, Ladestation) |
| `dec` | Nachkommastellen |
| `sub`, `subUnit` | optionale Zusatzzeile (Status oder Ladestand) |

Ein negativer Wert dreht die Flussrichtung um (z. B. Netz -12 kW = Einspeisung).

## 6. Yodeck

- Region: 2304×608 (Seitenverhältnis 3,79:1). Auf einem Full-HD-Screen passt die Hälfte davon (1152×304). Die Seite skaliert sich auf die Regionsgrösse.
- Inhalt: **Web Page** mit der URL `https://dash.deinedomain.ch/?key=DEIN_VIEW_KEY`.
- Der Hintergrund der Seite ist reinweiss und passt zur weissen Kachel im Layout.
- Die Yodeck-Vorschau kann leer bleiben. Entscheidend ist der echte Player. Teste die URL auch im Browser auf dem Windows-Player selbst.

## 7. Verhalten

- **Alter von Werten:** Ist ein Wert älter als 15 Minuten (`stale` in `config.php`), wird er grau. Beim Knoten steht dann ein Strich, die Linie steht still.
- **Linien:** Aktive Linien laufen in Gold und zeigen die Richtung. Unter 0,05 kW stehen sie still und werden grau.
- **Konfigurationsänderung:** Ändert sich `LAYOUT` in `config.php`, lädt sich die Seite beim nächsten Abruf selbst neu.
- **History:** Pro Schlüssel wird höchstens alle 60 s ein Wert gespeichert und nach 90 Tagen gelöscht. Darauf lassen sich später Verläufe aufsetzen.
- **Fehlermeldungen:** Unten rechts erscheint "Keine Verbindung" oder "Datenfehler" in kleiner roter Schrift, wenn `api.php` nicht antwortet.

## 8. Fehlersuche

| Symptom | Ursache und Abhilfe |
|---|---|
| Seite zeigt "forbidden" | `key` in der URL stimmt nicht mit `VIEW_KEY` überein |
| `push.php` antwortet 403 | `t` stimmt nicht mit `PUSH_TOKEN` überein |
| `push.php` antwortet `db error` | DB-Zugangsdaten oder Hostname falsch, Schema nicht angelegt. Details im PHP-Fehlerlog bei Infomaniak |
| `push.php` antwortet `ok 0` | Schlüssel ungültig (erlaubt sind nur `a-z A-Z 0-9 _`) |
| Alle Werte grau | Werte kommen nicht mehr an oder sind älter als 15 Minuten. `api.php` prüfen |
| Schrift sieht anders aus | Ordner `fonts/` fehlt oder liegt nicht neben `index.php` |
| Leere Seite im Yodeck-Player | URL zuerst im Browser auf dem Player testen. Dann `api.php?key=...` direkt aufrufen |

## 9. Sicherheit

- `VIEW_KEY` steht in der URL. Wer die URL kennt, sieht das Dashboard. Das ist für einen Infoscreen meist in Ordnung. Für mehr Schutz zusätzlich Basic-Auth auf Infomaniak aktivieren, dann muss die Anzeige den Zugang in der URL mitgeben.
- `PUSH_TOKEN` schützt nur das Schreiben. Verwende einen langen Zufallswert und gib ihn nur über HTTPS aus der Hand.
- Die Dateien `config.php` und `db.php` sind per `.htaccess` gesperrt. Nach dem Hochladen prüfen (siehe Einrichtung, Schritt 8).
- Das Relay (Variante B) gehört nur ins interne Netz und nicht ins Internet.

## 10. Wartung

- Zugangsdaten rotieren: neuen Wert in `config.php` setzen, danach Loxone-Befehle bzw. Relay-Umgebung (`TOKEN`) und die Yodeck-URL (`key`) anpassen.
- History aufräumen, falls nötig: `DELETE FROM history WHERE ts < UNIX_TIMESTAMP() - 90*86400;`
- Schriften: Lato in den Gewichten 400, 700 und 900 (`@fontsource/lato`, Latin-Teilmenge) liegen lokal im Ordner `fonts/`. So braucht der Player keinen Zugriff auf Google Fonts.
