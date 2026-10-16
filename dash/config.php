<?php
// --- Datenbank (Werte aus dem Infomaniak-Manager) ---
const DB_HOST = 'xxxx.myd.infomaniak.com';
const DB_NAME = 'xxxx_dash';
const DB_USER = 'xxxx_dash';
const DB_PASS = 'HIER_PASSWORT';

// --- Zugriff ---
const PUSH_TOKEN = 'LANGER_ZUFAELLIGER_TOKEN_ZUM_SCHREIBEN';
const VIEW_KEY   = 'LANGER_ZUFAELLIGER_KEY_FUER_DIE_ANZEIGE';

// --- History: Wert pro Key höchstens alle X Sekunden, Aufbewahrung in Tagen ---
const HISTORY_INTERVAL = 60;
const HISTORY_DAYS     = 90;

date_default_timezone_set('Europe/Zurich');

/*
 * Knoten des Flussdiagramms
 *   id      eindeutig, nur a-z 0-9 _
 *   label   Anzeigename
 *   icon    solar | house | grid | ev | battery
 *   color   Kreisfarbe
 *   row     top | bottom (Position relativ zur Sammelschiene)
 *   key     Datenpunkt (Leistung in kW)
 *   dir     Richtung bei POSITIVEM Wert:
 *             toBus   = Knoten speist in die Schiene (PV, Netzbezug, Batterie entlädt)
 *             fromBus = Knoten bezieht von der Schiene (Verbraucher, Ladestation)
 *           Ein negativer Wert dreht die Richtung um (z. B. Netz -18 kW = Einspeisung).
 *   dec     Nachkommastellen
 *   sub     optional: Datenpunkt für eine Zusatzzeile (z. B. Status oder SoC)
 *   subUnit optional: Einheit für numerische Zusatzzeile (z. B. ' %')
 *
 * Kacheln rechts: label, key, unit, dec, color (optional: stale in Sekunden)
 */
const LAYOUT = [
    'title' => 'Energiefluss',
    'stale' => 900,
    'nodes' => [
        ['id' => 'pv',   'label' => 'PV',        'icon' => 'solar', 'color' => '#d9a21b', 'row' => 'top',    'key' => 'pv_power',   'dir' => 'toBus',   'dec' => 1],
        // ['id' => 'bat', 'label' => 'Batterie', 'icon' => 'battery', 'color' => '#7c5cbf', 'row' => 'top', 'key' => 'bat_power', 'dir' => 'toBus', 'dec' => 1, 'sub' => 'bat_soc', 'subUnit' => ' %'],
        ['id' => 'load', 'label' => 'Haus',      'icon' => 'house', 'color' => '#3b82c4', 'row' => 'bottom', 'key' => 'load_power', 'dir' => 'fromBus', 'dec' => 2],
        ['id' => 'wb1',  'label' => 'Wallbox 1', 'icon' => 'ev',    'color' => '#2f9e75', 'row' => 'bottom', 'key' => 'wb1_power',  'dir' => 'fromBus', 'dec' => 1, 'sub' => 'wb1_status'],
        ['id' => 'wb2',  'label' => 'Wallbox 2', 'icon' => 'ev',    'color' => '#2f9e75', 'row' => 'bottom', 'key' => 'wb2_power',  'dir' => 'fromBus', 'dec' => 1, 'sub' => 'wb2_status'],
        ['id' => 'grid', 'label' => 'Netz',      'icon' => 'grid',  'color' => '#c2553d', 'row' => 'bottom', 'key' => 'grid_power', 'dir' => 'toBus',   'dec' => 2],
    ],
    'tiles' => [
        // Reihenfolge zeilenweise, 3 Spalten: Spalte 1-2 = Energie, Spalte 3 = Umwelt
        ['label' => 'Tagesertrag',      'key' => 'pv_day',    'unit' => 'kWh', 'dec' => 1, 'color' => '#d9a21b'],
        ['label' => 'Eigenverbrauch',   'key' => 'self_use',  'unit' => 'kWh', 'dec' => 1, 'color' => '#3b82c4'],
        ['label' => 'Aussentemperatur', 'key' => 'temp_out',  'unit' => '°C',  'dec' => 1, 'color' => '#6b7a90'],
        ['label' => 'Einspeisung',      'key' => 'feed_in',   'unit' => 'kWh', 'dec' => 1, 'color' => '#c2553d'],
        ['label' => 'Ladestrom heute',  'key' => 'wb_day',    'unit' => 'kWh', 'dec' => 1, 'color' => '#2f9e75'],
        ['label' => 'CO2 Einsparung',   'key' => 'co2_saved', 'unit' => 'kg',  'dec' => 1, 'color' => '#7aa33a'],
    ],
];
