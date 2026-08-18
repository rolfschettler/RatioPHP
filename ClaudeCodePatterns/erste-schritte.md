# Erste Schritte -- Projektgrundgeruest aufbauen

## Was dieser Schritt macht

Claude Code erstellt das komplette Grundgeruest des Projekts:
- Alle Ordner gemaess Projektstruktur
- Composer mit Autoloader (PSR-4)
- Router (kopiert aus ClaudeCodePatterns/)
- Front Controller, BaseController, View-Renderer
- Bootstrap-Layout
- Login und Logout gemaess CLAUDE.md
- Eine erste lauffaehige Startseite

Ziel: Login funktioniert, Cookie wird gesetzt, Header zeigt Benutzername und Logout-Button.




---

## Prompt fuer Claude Code

Kopiere diesen Text in Claude Code:

```
Erstelle das Projekt-Grundgeruest gemaess CLAUDE.md.
Benoetigt werden:
1. Projektstruktur mit allen Ordnern und Basisdateien
2. Composer mit Autoloader PSR-4
3. Front Controller index.php im Projektstamm, NICHT in public
4. .htaccess im Projektstamm
5. Router: Lies ClaudeCodePatterns/router-implementation.php
   und kopiere den Inhalt 1:1 nach core/Router.php, nicht neu generieren
6. Routen-Dateien config/routes.php und config/routes.custom.php
7. BaseController in core/BaseController.php
8. View-Renderer in core/View.php
9. Bootstrap-Layout in views/layout.php
10. ansprechende Startseite mit HERO und integriertem  Login und Logout gemaess CLAUDE.md

```

Danach Farbe (beliebig) festlegen Bsp.:
```
Passe das Farbschema in public/css/app.css auf Blautöne an.
```


---

## Was danach zu tun ist

1. Im Projektordner Composer ausfuehren:
   ```
   composer install
   ```

2. Im Browser aufrufen:
   ```
   http://localhost/<projektordner>/
   ```

3. Erwartetes Ergebnis: Login-Seite erscheint, nach Anmeldung
   wird die Startseite angezeigt. Im Header rechts oben: Bootstrap Dropdown
   mit Benutzername -- Klick oeffnet Menue mit "Abmelden".

---

## Wenn etwas nicht stimmt

Fehlermeldung einfach in Claude Code einfuegen:
```
Ich bekomme folgenden Fehler: [Fehlermeldung hier einsetzen]
```

Claude Code korrigiert und erklaert was schiefgelaufen ist.

---

## .htaccess

Die Datei `.htaccess` kommt in den Projektstamm (wo `index.php` liegt).
Claude Code legt sie gelegentlich nicht automatisch an -- dann explizit anfordern:
```
Bitte lege die Datei .htaccess im Projektstamm an
```

Inhalt:
```apache
RewriteEngine On

# Nicht auf existierende Dateien anwenden
RewriteCond %{REQUEST_FILENAME} !-f

# Nicht auf existierende Verzeichnisse anwenden
RewriteCond %{REQUEST_FILENAME} !-d

# Alle uebrigen Requests zu index.php umleiten
# QSA = Query String Append (erhaelt GET-Parameter)
# L   = Last (beende Regelverarbeitung)
RewriteRule ^(.*)$ index.php [QSA,L]
```

Erklaerung:
- `RewriteEngine On` -- Aktiviert URL-Umschreibung in Apache
- `!-f` -- Bedingung: Request zeigt auf KEINE echte Datei
- `!-d` -- Bedingung: Request zeigt auf KEIN echtes Verzeichnis
- Nur wenn beide Bedingungen erfuellt: Weiterleitung zu `index.php`
- Statische Dateien (CSS, JS, Images) werden direkt geliefert -- kein Rewrite

Praktische Beispiele:
```
/app/login          -> index.php  (kein File, kein Dir -> Rewrite)
/app/public/app.css -> direkt     (Datei existiert -> kein Rewrite)
/app/?debug=1       -> index.php?debug=1  (QSA erhaelt Parameter)
```

Falls Fehler 404/500 nach Installation:
- Pruefen ob `mod_rewrite` in Apache aktiviert ist (`httpd.conf`)
- `AllowOverride All` muss fuer das Verzeichnis gesetzt sein

---

## Routen-Muster

So sieht eine vollstaendige `config/routes.php` aus.
Dieses Muster gilt fuer jedes neue Modul das hinzukommt:

```php
<?php
use Standard\Controllers\AuthController;
use Standard\Controllers\HomeController;
use Standard\Controllers\AdressenController;

// ------------------------------------------------------------
// Oeffentliche Routes (kein Login erforderlich)
// ------------------------------------------------------------

$router->add('/login',  AuthController::class, 'login',  ['auth' => false]);
$router->add('/logout', AuthController::class, 'logout', ['auth' => false]);

// ------------------------------------------------------------
// Home / Dashboard
// ------------------------------------------------------------

$router->add('/', HomeController::class, 'index');

// ------------------------------------------------------------
// Adressen-Modul
// ------------------------------------------------------------

$router->add('/adressen',              AdressenController::class, 'index');
$router->add('/adressen/neu',          AdressenController::class, 'neu');
$router->add('/adressen/speichern',    AdressenController::class, 'speichern');
$router->add('/adressen/bearbeiten',   AdressenController::class, 'bearbeiten');
$router->add('/adressen/aktualisieren',AdressenController::class, 'aktualisieren');
$router->add('/adressen/loeschen',     AdressenController::class, 'loeschen');
```

Namenskonventionen fuer Routen:
```
/modul                  -- Uebersichtsliste
/modul/neu              -- Formular fuer neuen Datensatz
/modul/speichern        -- Verarbeitet POST von /modul/neu
/modul/bearbeiten       -- Formular zum Bearbeiten (GET ?kennziffer=42)
/modul/aktualisieren    -- Verarbeitet POST von /modul/bearbeiten
/modul/loeschen         -- Verarbeitet POST mit kennziffer
```

Regeln:
- Oeffentliche Routes (`auth: false`) immer am Anfang
- Geschuetzte Routes ohne `['auth' => true]` -- Default reicht
- Controller immer mit `::class` referenzieren
- Kundenspezifische Routen gehoeren in `config/routes.custom.php`

---

## Naechster Schritt

Wenn das Grundgeruest laeuft, weiter mit:
`ClaudeCodePatterns/erster-api-aufruf.md`
