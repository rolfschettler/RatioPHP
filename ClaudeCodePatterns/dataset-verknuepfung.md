# Dataset-Verknuepfung
# ClaudeCodePatterns/dataset-verknuepfung.md
# NUR LESEN -- nicht bearbeiten

---

## Definition

**„Dataset"** bezeichnet in dieser Dokumentation immer das Ergebnis
eines einzelnen API-Calls -- das PHP-Array das `\api_post()` zurueckliefert.

```php
// $rechnungen ist das Dataset der Tabelle RECHNUNG
$rechnungen = \api_post('/rechnung/getRechnung', [...]);

// $kunden ist das Dataset der Tabelle KUNDE
$kunden = \api_post('/kunden/getKunden', [...]);
```

Im Prompt schreibt Harry: „Dataset RECHNUNG" oder „Dataset KUNDE" --
Claude Code weiss damit welcher API-Call gemeint ist.

---

## Grundregel -- ABSOLUT VERBINDLICH

**Statt SQL-JOINs: Datasets separat per API holen, in PHP zusammenfuehren.**

Claude Code darf **NIEMALS**:
- Einen SQL-JOIN generieren
- Eine `IN()`-Liste generieren
- SQL veraendern oder ergaenzen -- Harry liefert immer den fertigen SQL-String
- Einen API-Call pro Datensatz absetzen (N+1 -- verboten)

Claude Code muss **IMMER**:
- Jeden Dataset mit einem einzigen API-Call holen (Ausnahme: `-->*`, siehe unten)
- Die Zusammenfuehrung ausschliesslich in PHP erledigen
- `array_column()` fuer Indexierung und Gruppierung verwenden

---

## Notation

Harry beschreibt Beziehungen zwischen Datasets mit einer Pfeil-Notation im Prompt.
Claude Code liest die Notation und generiert daraus mechanisch das PHP -- ohne Rueckfrage.

### Regeln fuer die Notation -- ZWINGEND

**1. Der Pfeil zeigt IMMER von N nach 1 (vom Fremdschluessel zum Primaerschluessel).**

**2. Jede Beziehung wird als eigene Zeile geschrieben.**

**3. Der Haupt-Dataset (der Dataset ueber den iteriert wird) wird mit `HAUPT:` explizit benannt.**

**4. Nur diese drei Symbole sind gueltig:**

| Symbol  | Bedeutung                                          | PHP-Ergebnis am Haupt-Datensatz |
|---------|----------------------------------------------------|---------------------------------|
| `-->`   | N:1 -- Lookup-Tabelle klein -- komplett holen      | ein Objekt                      |
| `-->*`  | N:1 -- Lookup-Tabelle gross -- nur benoetigte Saetze holen | ein Objekt             |
| `<--`   | 1:N -- Kind-Datensaetze sammeln                    | ein Array                       |

**5. Jede andere Pfeilschreibweise ist ungueltig:**
```
UNGUELTIG:  ->   <-   ->*   <-*   =>   <=
GUELTIG:   -->  <--  -->*
```

Bei ungueltiger Notation IMMER Rueckfrage -- niemals raten oder interpretieren:
```
Die Notation '->' ist nicht definiert.
Gueltige Symbole: '-->' (N:1 klein), '-->*' (N:1 gross), '<--' (1:N).
Bitte Notation korrigieren.
```

### Notation im Prompt

```
HAUPT: RECHNUNG

RECHNUNG [KUNDENID]  -->  KUNDE [KENNZIFFER]
RECHNUNG [RENR]      <--  RECHNUNGSPOSITION [RENR]
```

Bedeutung:
- `HAUPT: RECHNUNG` -- ueber RECHNUNG wird iteriert (der Haupt-Loop in PHP)
- `-->` -- jede Rechnung bekommt ein KUNDE-Objekt angehaengt
- `<--` -- jede Rechnung bekommt ein Array von RECHNUNGSPOSITIONEN angehaengt

---

## PHP-Muster `-->` (N:1, kleine Lookup-Tabelle)

**Wann:** Lookup-Tabelle ist ueberschaubar -- z.B. Laender, Status, Kategorien.

**Strategie:** Beide Datasets komplett holen -- Lookup per `array_column` indexieren -- in einem Durchgang mergen.

Beispiel-Notation:
```
HAUPT: RECHNUNG

RECHNUNG [KUNDENID] --> KUNDE [KENNZIFFER]
```

```php
// 1. Haupt-Dataset holen
$rechnungen = \api_post('/rechnung/getRechnung', [
    'fields'  => ['renr', 'kundenid', 'betrag', 'datum'],
    'orderby' => 'renr',
]);

// 2. Lookup-Dataset komplett holen
$kunden = \api_post('/kunden/getKunden', [
    'fields'  => ['kennziffer', 'name1', 'name2'],
    'orderby' => 'kennziffer',
]);

// 3. Lookup-Dataset per Primaerschluessel indexieren
//    Ergebnis: [ primaerschluessel => Datensatz ]
$kundenIndex = array_column($kunden['data'], null, 'kennziffer');

// 4. Merge -- jeder Rechnung das passende Kunden-Objekt zuordnen
//    $r['kunde'] ist danach ein einzelnes Objekt (oder null)
foreach ($rechnungen['data'] as &$r) {
    $r['kunde'] = $kundenIndex[$r['kundenid']] ?? null;
}
unset($r);
```

---

## PHP-Muster `-->*` (N:1, grosse Lookup-Tabelle)

**Wann:** Lookup-Tabelle ist gross -- z.B. Kunden, Artikel, Mitarbeiter mit vielen Tausend Saetzen.
**Voraussetzung:** Der Haupt-Dataset ist selbst ueberschaubar (gefilterte Liste oder Einzelseite).

**Strategie:** Eindeutige Fremdschluessel sammeln -- pro eindeutiger ID genau einen `getXxxById`-Call -- mergen.

Beispiel-Notation:
```
HAUPT: RECHNUNG

RECHNUNG [KUNDENID] -->* KUNDE [KENNZIFFER]
```

```php
// 1. Haupt-Dataset holen
$rechnungen = \api_post('/rechnung/getRechnung', [
    'fields'  => ['renr', 'kundenid', 'betrag', 'datum'],
    'orderby' => 'renr',
]);

// 2. Eindeutige Fremdschluessel sammeln
//    array_unique verhindert doppelte API-Calls fuer denselben Datensatz
$kundenIds = array_unique(array_column($rechnungen['data'], 'kundenid'));

// 3. Pro eindeutiger ID genau einen getXxxById-Call -- NIEMALS pro Datensatz
$kundenIndex = [];
foreach ($kundenIds as $id) {
    $k = \api_post('/kunden/getKundeById', ['kennziffer' => $id]);
    $kundenIndex[$id] = $k['data'][0] ?? null;
}

// 4. Merge -- jeder Rechnung das passende Kunden-Objekt zuordnen
//    $r['kunde'] ist danach ein einzelnes Objekt (oder null)
foreach ($rechnungen['data'] as &$r) {
    $r['kunde'] = $kundenIndex[$r['kundenid']] ?? null;
}
unset($r);
```

**NIEMALS -- N+1-Fehler:**
```php
// FALSCH -- ein API-Call pro Datensatz, egal ob ID doppelt vorkommt
foreach ($rechnungen['data'] as &$r) {
    $k = \api_post('/kunden/getKundeById', ['kennziffer' => $r['kundenid']]);
    $r['kunde'] = $k['data'][0] ?? null;
}
```

---

## PHP-Muster `<--` (1:N, Kind-Datensaetze)

**Wann:** Ein Haupt-Datensatz hat mehrere zugehoerige Kind-Datensaetze.

**Strategie:** Beide Datasets komplett holen -- Kind-Dataset nach Fremdschluessel gruppieren -- in einem Durchgang mergen.

Beispiel-Notation:
```
HAUPT: RECHNUNG

RECHNUNG [RENR] <-- RECHNUNGSPOSITION [RENR]
```

```php
// 1. Haupt-Dataset holen
$rechnungen = \api_post('/rechnung/getRechnung', [
    'fields'  => ['renr', 'kundenid', 'betrag', 'datum'],
    'orderby' => 'renr',
]);

// 2. Kind-Dataset komplett holen
$positionen = \api_post('/rechnungsposition/getRechnungsposition', [
    'fields'  => ['posnr', 'renr', 'artikel', 'menge', 'preis'],
    'orderby' => 'renr',
]);

// 3. Kind-Dataset nach Fremdschluessel gruppieren
//    Ergebnis: [ fremdschluessel => [ Datensatz, Datensatz, ... ] ]
$positionenNachRechnung = [];
foreach ($positionen['data'] as $p) {
    $positionenNachRechnung[$p['renr']][] = $p;
}

// 4. Merge -- jeder Rechnung ihr Array von Positionen zuordnen
//    $r['positionen'] ist danach ein Array (leer wenn keine Positionen vorhanden)
foreach ($rechnungen['data'] as &$r) {
    $r['positionen'] = $positionenNachRechnung[$r['renr']] ?? [];
}
unset($r);
```

---

## PHP-Muster kombiniert (`-->` und `<--`)

Mehrere Beziehungszeilen werden in EINEM einzigen Merge-Durchgang aufgeloest.

Beispiel-Notation:
```
HAUPT: RECHNUNG

RECHNUNG [KUNDENID]  -->  KUNDE [KENNZIFFER]
RECHNUNG [RENR]      <--  RECHNUNGSPOSITION [RENR]
```

```php
// 1. Alle Datasets holen -- je ein API-Call
$rechnungen = \api_post('/rechnung/getRechnung', [
    'fields'  => ['renr', 'kundenid', 'betrag', 'datum'],
    'orderby' => 'renr',
]);

$kunden = \api_post('/kunden/getKunden', [
    'fields'  => ['kennziffer', 'name1', 'name2'],
    'orderby' => 'kennziffer',
]);

$positionen = \api_post('/rechnungsposition/getRechnungsposition', [
    'fields'  => ['posnr', 'renr', 'artikel', 'menge', 'preis'],
    'orderby' => 'renr',
]);

// 2. Index aufbauen fuer jede '-->' Beziehung
$kundenIndex = array_column($kunden['data'], null, 'kennziffer');

// 3. Gruppierung aufbauen fuer jede '<--' Beziehung
$positionenNachRechnung = [];
foreach ($positionen['data'] as $p) {
    $positionenNachRechnung[$p['renr']][] = $p;
}

// 4. Alle Beziehungen in EINEM einzigen Merge-Durchgang aufloesen
foreach ($rechnungen['data'] as &$r) {
    $r['kunde']      = $kundenIndex[$r['kundenid']] ?? null;       // --> ein Objekt
    $r['positionen'] = $positionenNachRechnung[$r['renr']] ?? [];  // <-- ein Array
}
unset($r);
```

---

## Mehrere unabhaengige Lookups

Jede `-->` Zeile ergibt einen eigenen Index.
Alle Indices werden in einem einzigen Merge-Durchgang aufgeloest.

Beispiel-Notation:
```
HAUPT: RECHNUNG

RECHNUNG [KUNDENID]  -->  KUNDE [KENNZIFFER]
RECHNUNG [ARTIKELID] -->  ARTIKEL [NR]
```

```php
// Indices aufbauen -- einer pro '-->' Zeile
$kundenIndex  = array_column($kunden['data'],  null, 'kennziffer');
$artikelIndex = array_column($artikel['data'], null, 'nr');

// Ein einziger Merge-Durchgang fuer alle Lookups
foreach ($rechnungen['data'] as &$r) {
    $r['kunde']   = $kundenIndex[$r['kundenid']]   ?? null;
    $r['artikel'] = $artikelIndex[$r['artikelid']] ?? null;
}
unset($r);
```
