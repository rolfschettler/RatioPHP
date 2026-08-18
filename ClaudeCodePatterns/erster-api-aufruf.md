# Erster API-Aufruf -- Datenliste aus RATIOserver

## Voraussetzung: Login muss zuerst laufen

**Ohne funktionierenden Login gibt es keine Daten.**
Der Cookie mit dem JWT-Token wird beim Login gesetzt -- ohne ihn
liefert jeder API-Aufruf eine leere Liste oder einen Fehler.

Wenn noch kein Login vorhanden ist, zuerst diesen Prompt ausfuehren:

```
Erstelle einen Login gemaess CLAUDE.md.
```

Erst wenn der Login funktioniert und der Cookie gesetzt ist, weiter mit den Varianten unten.

---

## Was dieser Schritt macht

- Erster echter Datenabruf vom Delphi-Backend
- Controller holt Daten per api_post()
- View zeigt sie als Bootstrap-Tabelle an

Voraussetzung: Login funktioniert, Cookie ist gesetzt.

---

## Wichtiger Hinweis: API-Pfad

Der Pfad vor dem Endpunkt-Namen entspricht dem Controller-Namen in RATIOserver --
er muss nicht identisch mit dem Tabellennamen sein.

Beispiele:
```
Tabelle ADRESSEN, Pfad = Tabellenname:  /adressen/getAdressen
Tabelle EINSATZ, Pfad abweichend:       /dispo/getEinsatz
```

Im Prompt den vollstaendigen API-Pfad angeben wenn er vom Tabellennamen abweicht:

```
Lege eine Seite mit Tabelle fuer die Tabelle EINSATZ an.
Felder: VON,BIS,BEZEICHNUNG,DIENSTNR,FAHRER1,FAHRER2,FAHRZEUG,PERSONEN,ZIELORT .
Sortiert: VON
Alle API-Endpunkte fuer EINSATZ verwenden den Pfad /dispo/ als Praefix.
Die Route soll /einsatz sein.
verlinke die Route auf dem Startbildschirm
```



## Variante A -- Ohne Pagination

Kopiere diesen Text in Claude Code:
Beispiel 1:

```
Lege eine Seite mit Tabelle fuer die Tabelle ADRESSEN an.
Felder: kennziffer, name2, name1, strasse, ort, email.
Sortierung nach name2.
Die Route soll /adressen sein.
verlinke die Route auf dem Startbildschirm
```

Beispiel 2:
```
Lege eine Seite mit Tabelle für die DB-Tabelle EINSATZ an .
Felder: nr,VON,BIS,BEZEICHNUNG,DIENSTNR,FAHRER1,FAHRER2,FAHRZEUG,PERSONEN,ZIELORT .
Sortierung nach von.
Es werden folgende Parameter von "dispo/getEinsatzfiltered" erwartet: von(datum), bis(datum) und ein optionaler Parameter timemode: boolean bzw. null.
timemode=true bedeutet "überlappende Zeiträume"
Die Parameter von und bis  sollen per Default auf Anfang und ende des aktuellen Monats verweisen. es soll auch einen Button "Heute" geben.
Alle API-Endpunkte fuer EINSATZ verwenden den Pfad /dispo/ als Praefix.
Die Route soll /einsatz sein.
verlinke die Route auf dem Startbildschirm
```

## Variante B -- Mit Pagination

Kopiere diesen Text in Claude Code:

```
Lege eine Seite mit Tabelle und Pagination fuer die Tabelle ADRESSEN an.
Felder: kennziffer, name2, name1, strasse, ort, email.
Sortierung nach name2.
20 Eintraege pro Seite.
Die Route soll /adressen sein.
verlinke die Route auf dem Startbildschirm
```

Claude Code wird:
1. Verfuegbare Felder per curl gegen RATIOserver testen
   (Token aus ClaudeCodePatterns/token.local.txt)
2. AdressenController in standard/Controllers/ anlegen
3. View in standard/Views/adressen/index.php anlegen
4. Pagination-Komponente aus ClaudeCodePatterns/pagination-component.php kopieren
5. Route in config/routes.php eintragen

---

## Was zu pruefen ist

1. Im Browser aufrufen:
   ```
   http://localhost/<projektordner>/adressen
   ```

2. Erwartetes Ergebnis:
   - Tabelle mit Adressdaten aus RATIOserver
   - Spalten: Nr., Name, Vorname, Strasse, Ort, E-Mail
   - Sortiert nach Name
   - Bei Variante B: Pagination-Leiste unten

3. Typische Fehler und Loesung:
   - Leere Seite: Cookie fehlt -- neu einloggen
   - Keine Daten bei Pagination: Controller liest $result['data'] statt
     $result['data']['data'] -- Fehlermeldung an Claude Code weitergeben
   - 404: Route nicht eingetragen -- Claude Code nach routes.php fragen

---

## Variante C -- Mit /select und Dataset-Verknuepfung

`/select` wird verwendet wenn kein passender `getXxx`-Endpunkt existiert --
z.B. fuer Abfragen ueber mehrere Tabellen die in PHP zusammengefuehrt werden.

**Wichtig:** Harry schreibt den SQL immer vollstaendig selbst -- Claude Code generiert keinen SQL.

### Einfacher /select ohne Parameter

```
Lege eine Seite mit Tabelle an.

Abfrage:
/select
SELECT kennziffer, name1, name2, ort FROM adressen ORDER BY name2
```

### /select mit Parametern

Parameter werden mit einer speziellen Notation angegeben:

| Notation | Quelle |
|---|---|
| `:[GET.param]` | URL-Parameter `$_GET['param']` |
| `:[POST.param]` | POST-Body `$_POST['param']` |
| `:[datasetname.feld]` | Ergebnis einer vorherigen Abfrage |
| `:["wert"]` | Konstanter Wert (immer in `"`, egal ob String oder Zahl) |

### Pfeil-Notation fuer Dataset-Verknuepfung

| Notation | Bedeutung |
|---|---|
| `A.FREMDKEY -> B.KEY` | N:1 Lookup -- Lookup-Tabelle komplett holen, in PHP indexieren |
| `A.FREMDKEY ->* B.KEY` | N:1 Lookup -- Lookup-Tabelle ist gross -- `getXxxById` pro eindeutigem Fremdschluessel |
| `A.KEY <- B.FREMDKEY` | 1:N -- jeder Satz in A hat mehrere Saetze in B |

**Wann `->` vs `->*`:**
- `->` -- Lookup-Tabelle ueberschaubar (Laender, Status, Kategorien)
- `->*` -- Lookup-Tabelle gross (Kunden, Artikel, Mitarbeiter mit vielen Tausend Saetzen)
- `->*` setzt voraus: das zentrale Dataset ist selbst ueberschaubar (gefilterte Liste)

```
Lege eine Seite "Auftragsdetail" an.

Abfrage 1 -- Dataset: kunde:
Endpunkt: /select
SELECT kennziffer, name1, name2 FROM adressen WHERE kennziffer = :[GET.id]

Abfrage 2 -- Dataset: auftraege:
Endpunkt: /select
SELECT auftrnr, datum, betrag FROM auftraege
WHERE kundenid = :[kunde.kennziffer]
AND land = :["DE"]
AND status = :[POST.status]
```

### Dataset-Verknuepfung mit Pfeil-Notation

Wenn Daten aus mehreren Datasets zusammengefuehrt werden muessen,
beschreibt Harry die Beziehung mit Pfeilen -- Claude Code generiert das PHP-Mapping:

| Notation | Bedeutung |
|---|---|
| `A.FREMDKEY -> B.KEY` | N:1 -- jeder Satz in A hat genau einen Satz in B (Lookup) |
| `A.KEY <- B.FREMDKEY` | 1:N -- jeder Satz in A hat mehrere Saetze in B |

```
Lege eine Seite "Rechnungsuebersicht" an.

Abfrage 1 -- Dataset: rechnungen:
Endpunkt: rechnung/getRechnungen
Felder: renr, kundenid, betrag, datum
Sortierung nach renr.

Abfrage 2 -- Dataset: kunden:
Endpunkt: adressen/getAdressen
Felder: kennziffer, name1, name2
Sortierung nach kennziffer.

Verknuepfung:
RECHNUNG.KUNDENID -> KUNDE.KENNZIFFER
```

Verkettetes Beispiel (1:N + N:1 gleichzeitig):

```
Lege eine Seite "Rechnungsdetail" an.

Abfrage 1 -- Dataset: rechnungen:
Endpunkt: rechnung/getRechnungen
Felder: renr, kundenid, betrag
Sortierung nach renr.

Abfrage 2 -- Dataset: kunden:
Endpunkt: adressen/getAdresseById
Felder: id, name1, name2

Abfrage 3 -- Dataset: positionen:
Endpunkt: rechnung/getPositionen
Felder: posnr, renr, artikel, menge, preis
Sortierung nach renr.

Verknuepfung:
RECHNUNGSPOSITION <- RECHNUNG.KUNDENID ->* KUNDE.ID
```

Claude Code wird daraus:
1. Drei separate API-Calls generieren -- einen pro Dataset
2. Kunden per `array_column()` nach `id` indexieren (N:1)
3. Positionen per `array_column()` nach `renr` gruppieren (1:N)
4. Alles in einem einzigen `foreach` zusammenfuehren
5. Keinen JOIN und kein `IN()` verwenden -- niemals

---

## Was danach kommt

Wenn die Liste laeuft, weiter mit CRUD:
```
Die Adressen sollen editierbar sein: INSERT, EDIT, DELETE.
Verwende das Muster aus ClaudeCodePatterns/controller-example.php.
```

---

## Naechster Schritt

Wenn CRUD funktioniert:
Ab jetzt neue Module nach demselben Muster anlegen.
