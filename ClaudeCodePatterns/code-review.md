# Code Review -- Qualitaetspruefung gegen CLAUDE.md

## Wann ausfuehren

- Nach jeder Entwicklungsrunde
- Bevor ein Modul als "fertig" gilt
- Vor jedem Deployment

---

## Prompt fuer Claude Code

Kopiere diesen Text in Claude Code:

```
Fuehre einen Code-Review durch. Pruefe ALLE Dateien in
standard, custom, core, config und views gegen die CLAUDE.md.

Pruefe auf folgende Abweichungen und melde jede mit Datei und Zeilennummer:

1. API-AUFRUFE
   - api_post() ohne fuehrendes Backslash (muss \api_post() sein)
   - fields mit dem Wert Stern im Request-Body (muss immer Array sein)
   - limit oder offset im Request-Body (Pagination ist verboten)
   - orderby mit DESC oder ASC (nur Feldname erlaubt)

2. PFADE UND REDIRECTS
   - header('Location: ...') ohne APP_BASE
   - Hardcodierte Verzeichnisnamen wie app, ratioapp o.ae. in Pfaden
   - Links und Form-Actions ohne APP_BASE

3. DATEI-ZUGRIFFE
   - Zugriff auf token.local.txt ausserhalb von Bash-Tests
   - define('JWT_TOKEN', ...) in index.php

4. STRUKTUR
   - Controller nicht in standard\Controllers oder custom\Controllers
   - Views nicht in standard\Views oder custom\Views
   - Neue Dateien in ClaudeCodePatterns (verboten)
   - Kopierter Code statt Komponente (gleicher HTML-Block in 2 oder mehr Views)

5. BOOTSTRAP UND VIEWS
   - Eigenes CSS (style-Attribut oder style-Tag) statt Bootstrap-Klassen
   - Datumsausgabe ohne date('d.m.Y', strtotime(...))
   - JavaScript-Dialoge wie alert(), confirm() oder prompt() --
     muessen als Bootstrap-Modal-Komponente in views\components\ ausgefuehrt werden

Ausgabe als Liste:
[DATEI] Zeile XX: Beschreibung der Abweichung
```

---

## Erwartete Ausgabe

Keine Meldungen = alles sauber.

Beispiel einer Meldung:
```
[standard\Controllers\AdressenController.php] Zeile 12:
api_post() ohne fuehrendes Backslash

[standard\Views\adressen\form.php] Zeile 34:
header('Location: ...') ohne APP_BASE
```

---

## Nach dem Review

Gefundene Abweichungen beheben:
```
Behebe alle gemeldeten Abweichungen aus dem Code-Review.
```
