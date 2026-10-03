# CLAUDE.md — PHP RATIOserver Frontend

## Projektkontext

PHP-Frontend fuer einen Delphi REST-API-Server (RATIOserver).
Alle relevanten API-Informationen stehen direkt in dieser CLAUDE.md.
PHP holt Daten per HTTP vom Backend und rendert fertige HTML-Seiten (MVC-Struktur).
PHP macht KEINE direkte Datenbankverbindung -- das uebernimmt ausschliesslich RATIOserver.

Entwickler:
- **Rolf** -- Architektur, RATIOserver-Backend, Delphi
- **Harry** -- PHP-Frontend, Views, Bootstrap

---

## Projektstruktur

```
/project
├── index.php                       <- Front Controller (einziger Einstiegspunkt)
├── .htaccess                       <- Alle Requests -> index.php
├── public/                         <- Statische Assets
│   └── css/
│       └── app.css                 <- Layout-CSS (aus ClaudeCodePatterns kopiert)
│
├── core/                           <- Framework-Kern (wird bei Updates ersetzt)
│   ├── Router.php                  <- URL-Matching + Dispatch + Portalgrenze
│   ├── Api.php                     <- api_post() + Token-Handling
│   ├── Auth.php                    <- Portal des Anmelders aus dem Token (role.typ)
│   ├── BaseController.php          <- Basis fuer alle Controller
│   ├── Portal.php                  <- Definition der Portale (Start, Praefix, Label, Registrierung)
│   ├── Codec.php                   <- Codieren/DeCodieren aus rechtelib.pas (USERS.passwort)
│   ├── Fehler.php                  <- System- vs. Benutzerfehler (siehe Abschnitt Fehlerbehandlung)
│   ├── Meldungen.php               <- Einziger Meldungsspeicher (System, Benutzer, Erfolg)
│   ├── Pruefung.php                <- Formularpruefung aus Feld-Definitionen
│   ├── Anfrage.php                 <- Routenpfad, Client-IP, umleiten()
│   ├── RateLimit.php               <- Begrenzung von Versuchen (Login, Registrierung)
│   └── View.php                    <- render() / layout()
│
├── standard/                       <- Standardmodule (gleich fuer alle Kunden)
│   ├── Controllers/
│   │   ├── HomeController.php      <- Startseiten aller Portale
│   │   └── BeispielController.php
│   └── Views/
│       ├── home/
│       │   ├── index.php           <- Kundenportal-Startseite (/)
│       │   ├── mitarbeiter.php     <- Mitarbeiterportal-Startseite (/mitarbeiter)
│       │   └── fahrer.php          <- Fahrerportal-Startseite (/fahrer)
│       └── beispiel/
│           └── index.php
│
├── custom/                         <- Kundenspezifische Module (update-sicher)
│   ├── Controllers/
│   │   └── SpezialController.php
│   └── Views/
│       └── spezial/
│           └── index.php
│
├── views/                          <- Gemeinsame View-Bausteine
│   ├── layout.php                  <- Haupt-Layout (portalabhaengig -- siehe Abschnitt Layout)
│   └── components/
│       ├── header.php              <- Portalabhaengige Navigation (siehe Abschnitt Portalstruktur)
│       ├── login-modal.php         <- Login-Modal (jedes Portal, baut auf modal.php auf)
│       ├── modal.php               <- Einziges Modal-Geruest (View::komponente)
│       ├── meldungen.php           <- Einzige Meldungsdarstellung (View::komponente)
│       ├── flash.php               <- Erfolgsmeldungen
│       ├── systemfehler.php        <- Reservierter Bereich fuer Systemfehler (unter dem Header)
│       ├── fehler-dialog.php       <- Dialog fuer Benutzerfehler
│       └── pagination.php          <- Bootstrap-Pagination (siehe Abschnitt Pagination)
│
├── config/
│   ├── routes.php                  <- Routen Standardmodule
│   └── routes.custom.php           <- Routen Custom-Module
│
├── ClaudeCodePatterns/             <- NUR LESEN -- keine neuen Dateien anlegen
│   ├── token.local.txt             <- JWT-Token lokal (NICHT eingecheckt)
│   ├── erste-schritte.md           <- Einstieg fuer neue Projekte
│   ├── erster-api-aufruf.md        <- Erster API-Aufruf
│   ├── router-implementation.php   <- Fertiger Router-Code (1:1 kopieren)
│   ├── index-example.php           <- Fertiger Front Controller (1:1 kopieren)
│   ├── controller-example.php      <- CRUD-Muster fuer Controller
│   ├── pagination-component.php    <- Pagination-Komponente (1:1 kopieren)
│   ├── layout.php                  <- Haupt-Layout-Template (1:1 nach views/layout.php)
│   ├── app.css                     <- Layout-CSS (1:1 nach public/css/app.css)
│   ├── Api.php                     <- HTTP-Client mit Debug-Logging (1:1 nach core/Api.php)
│   ├── debug.php                   <- Debug-Panel Komponente (1:1 nach views/components/debug.php)
│   ├── layout-pattern.md           <- Anleitung fuer Layout-Erstellung
│   └── dataset-verknuepfung.md     <- Regeln und PHP-Muster fuer Dataset-Verknuepfung (NUR LESEN)
│
└── CLAUDE.md
```

---

## ClaudeCodePatterns -- Schreibschutz

**Claude Code darf in `ClaudeCodePatterns/` KEINE neuen Dateien anlegen -- nur lesen.**
Der Ordner enthaelt getestete Referenz-Implementierungen die 1:1 kopiert werden.

---

## Portalstruktur

Die App besteht aus drei getrennten Portalen. Alle teilen Layout, Theming und
Framework-Kern -- unterscheiden sich aber in Navigation, Login und Registrierung.

| Portal | Einstieg | Routen-Praefix | Auth | Inhalt |
|---|---|---|---|---|
| **Kundenportal** | `/` -- Default-Einstieg | `/kunde` | Login per Modal | Registrierung (`typ='kunde'`) |
| **Mitarbeiterportal** | `/mitarbeiter` -- nur direkt per URL | `/mitarbeiter` | Login per Modal | Alle geschuetzten Module (EINSATZ, ANMIETIMPORT, ...) plus Mitarbeiter-Registrierung (`typ='mitarbeiter'`) |
| **Fahrerportal** | `/fahrer` -- nur direkt per URL | `/fahrer` | Login per Modal | Fahrer-Registrierung (`typ='fahrer'`). Noch keine Fachmodule. |

Jede Seite eines Portals liegt unter dessen Praefix. Daraus leitet der Router
das Portal der Route ab und vergleicht es mit `typ` aus dem Token -- siehe
Abschnitt **Routenkonventionen und Portalgrenzen**.

Einzige Stelle, an der Einstieg und Praefix auseinanderfallen: das Kundenportal.
`/` bleibt der oeffentliche Einstieg, die uebrigen Kundenseiten liegen unter
`/kunde/...`. Die Praefix-URL `/kunde` selbst ist keine Seite -- sie leitet auf
`/` um (`HomeController::kunde()`), damit sie nicht als 404 endet. Bei
`/mitarbeiter` und `/fahrer` ist der Praefix gleichzeitig die Startseite.

### core/Portal.php -- die Definition der Portale

Startseite, Routen-Praefix, Portal-Label und Registrierungspfad jedes Portals
stehen **ausschliesslich** in `core/Portal.php`. Header, Login-Modal, Router,
`AuthController` und `RegistrierungController` lesen von dort.

```php
Portal::name($wertAusRequest)    // Whitelist-Abbildung, Unbekanntes -> 'kunde'
Portal::start('fahrer')          // '/fahrer'    -- Startseite
Portal::praefix('fahrer')        // '/fahrer'    -- Basis fuer Modul-URLs
Portal::label('fahrer')          // 'Fahrerportal'
Portal::registrierung('fahrer')  // '/fahrer/registrieren', oder null
Portal::darfAlles('mitarbeiter') // true -- darf auch fremde Portalrouten sehen
Portal::ausPfad('/fahrer/touren')// 'fahrer' -- Portal einer Route, null = kein Praefix
Portal::ausTyp('fahrer')         // 'fahrer' -- typ aus dem Token, null = unbekannt
```

**`name()` und `ausTyp()` nicht verwechseln** -- die beiden Abbildungen haben
absichtlich unterschiedliches Verhalten bei Unbekanntem:

| | Eingabe | Unbekannter Wert | Verwendung |
|---|---|---|---|
| `name()` | Wert aus einem **Request** (`$_POST['portal']`, `?portal=..`) | wird `'kunde'` | Weiterleitungsziele -- fail-safe nach aussen |
| `ausTyp()` | `role.typ` aus dem **Token** | wird `null` | Zugriffsentscheidungen -- fail-closed |

In REGISTRIERUNG stehen Legacy-Saetze mit `typ='Standard'` und `typ=NULL`. Mit
`name()` bekaemen die Kundenrechte -- deshalb fuer jede Zugriffsentscheidung
**ausschliesslich `ausTyp()`** (bzw. `Core\Auth::portal()`, das es aufruft).

**Niemals einen Portalpfad woanders hinschreiben** -- kein `$x === 'mitarbeiter' ? ... : ...`
in Views oder Controllern. Mit drei Portalen ist jeder solche Ternaeroperator
bereits falsch. Modul-URLs immer `Portal::praefix($portal) . '/modul'`.

### Ein weiteres Portal anlegen

1. Eintrag in `core/Portal.php` (`start`, `praefix`, `label`, `registrierung`,
   `darf_alles`)
2. Action in `HomeController` + View `standard/Views/home/<portal>.php`
3. Routen in `config/routes.php` (eigener Block, alle Pfade unter dem neuen
   Praefix, Startseite `['auth' => false]`)
4. Nur falls es eine Registrierung geben soll: Variante in
   `RegistrierungController::PORTALE`

Mehr nicht -- Header, Login-Modal, Login- und Logout-Ziele sowie die
Portalgrenze im Router funktionieren dann von selbst.

### Grundregeln

- `/` ist IMMER das Kundenportal -- der Default-Einstieg fuer externe Besucher.
- Mitarbeiter- und Fahrerportal werden ausschliesslich ueber ihre URL angesteuert.
  **Es gibt KEINEN Link vom Kundenportal in eines der internen Portale** --
  keine Portalwahl, kein Umschalter, kein Hinweis im Footer.
- **Jedes Portal hat einen eigenen Login** (Bootstrap-Modal, dieselbe Komponente).
  Unterschieden wird nur das Weiterleitungsziel: das Formular schickt ein verstecktes
  Feld `portal` mit, der Logout-Link haengt `?portal=..` an. Der `AuthController`
  bildet den Wert ueber `Portal::name()` ab -- niemals einen Pfad aus dem Request
  uebernehmen.
- Es gibt nur EINEN Login-Endpunkt fuer alle Portale: `/login` prueft gegen
  REGISTRIERUNG, wo die Registrierungen aller Portale nebeneinander liegen,
  unterschieden nur durch `typ`. Der Benutzername wird per `UPPER()` auf beiden
  Seiten verglichen (`DataModulLoginClass.pas`) -- Gross-/Kleinschreibung spielt
  beim Anmelden keine Rolle.
- Jedes Portal zeigt nur seine eigenen Menuepunkte.
- **Jeder Logout-Link braucht `?portal=..`.** Fehlt er, landet der Nutzer im
  Kundenportal (Default) -- ein Fahrer also im falschen Portal.

### Portal-Kontext: die Variable `$portal`

Jeder `render()`-Aufruf gibt sein Portal mit. Zulaessige Werte sind die Schluessel
aus `core/Portal.php`: `'kunde'` (Default), `'mitarbeiter'` und `'fahrer'`.
`core/View.php` reicht den Wert ans Layout durch, `views/layout.php`
und `views/components/header.php` werten ihn aus.

```php
// Kundenportal-Seite
$this->render('registrierung/index', [
    'page_title' => 'Registrieren',
    'portal'     => 'kunde',
]);

// Mitarbeiterportal-Seite
$this->render('einsatz/index', [
    'page_title'  => 'Einsatz-Uebersicht',
    'portal'      => 'mitarbeiter',
]);
```

**WICHTIG fuer Claude Code:** `'portal'` bei JEDEM neuen Controller mitgeben --
auch im Kundenportal, wo `'kunde'` ohnehin der Default ist. Fehlt der Wert bei einem
Mitarbeiter-Modul, rendert das Layout die Seite mit Kunden-Navigation und ohne
Login-Modal -- der Benutzer kann sich dann nicht mehr anmelden.

`$portal` steuert konkret:

| Was | `'kunde'` | `'mitarbeiter'` | `'fahrer'` |
|---|---|---|---|
| Brand-Link im Header | `/` | `/mitarbeiter` | `/fahrer` |
| Menuepunkte | nur Start | nur Start | nur Start |
| Rechte Navigation | Anmelden-Modal bzw. Benutzer-Dropdown | dito | dito |
| Login-Modal im HTML | wird gerendert | wird gerendert | wird gerendert |
| Verstecktes Feld `portal` im Modal | `kunde` | `mitarbeiter` | `fahrer` |
| Registrierungs-Link im Modal | `/kunde/registrieren` | `/mitarbeiter/registrieren` | `/fahrer/registrieren` |
| Ziel nach Login/Logout | `/` | `/mitarbeiter` | `/fahrer` |
| Benutzername im Footer | ja, falls eingeloggt | ja, falls eingeloggt | ja, falls eingeloggt |
| Portal-Label neben Brand | "Kundenportal" | "Mitarbeiterportal" | "Fahrerportal" |

Alle Werte dieser Tabelle stammen aus `core/Portal.php` -- die Spalten sind
nicht einzeln im Code ausprogrammiert.

### Keine Feature-Links im Header

**Der Header verlinkt KEINE Features/Module** -- in keinem Portal.
Er enthaelt ausschliesslich:

- Brand (fuehrt zur Portal-Startseite)
- Portal-Label
- Menuepunkt "Start"
- Anmelden-Modal bzw. Benutzer-Dropdown mit Abmelden

Module werden ausschliesslich ueber die Kacheln der Portal-Startseite erreicht.
Beim Anlegen eines neuen Moduls also **keinen Menuepunkt** in
`views/components/header.php` ergaenzen.

### Neues Modul zuordnen

- Geschuetztes Modul (Default `auth: true`) -> Mitarbeiterportal:
  Route unter `/mitarbeiter/...`, `'portal' => 'mitarbeiter'` im `render()`
  und Kachel in `standard/Views/home/mitarbeiter.php` ergaenzen.
- Oeffentliches Modul fuer Kunden -> Kundenportal: Route unter `/kunde/...`,
  `'portal' => 'kunde'` und Einstieg in `standard/Views/home/index.php`.
- In beiden Faellen: `views/components/header.php` bleibt unangetastet.

---

## Routenkonventionen und Portalgrenzen

Der Router laesst nicht jeden Angemeldeten auf jede Route. Grundlage ist ein
Vergleich zweier Werte:

- **Portal der Route** -- aus dem Routen-Praefix (`Portal::ausPfad()`)
- **Portal des Anmelders** -- `role.typ` aus dem Token (`Core\Auth::portal()`)

### Praefix-Konvention

**Jede Portalseite liegt unter dem Praefix ihres Portals.** Aus dem Pfad ergibt
sich das Portal automatisch -- eine Portalangabe kann also nicht vergessen
werden.

| Pfad | Portal |
|---|---|
| `/` | kunde (Startseite, praefixlos) |
| `/kunde` | kunde -- leitet auf `/` um (die Praefix-URL selbst ist keine Seite) |
| `/kunde/...` | kunde |
| `/mitarbeiter/...` | mitarbeiter |
| `/fahrer/...` | fahrer |
| `/login`, `/logout` | portaluebergreifend -- `['portal' => Router::ALLE]` |

Eine Route ohne Praefix (und ohne `portal`-Option) gehoert zu keinem Portal.
Der Router weist sie ab: bei `DEBUG` mit HTTP 500 und Klartextmeldung, sonst
mit 404. Er raet NICHT, und er laesst sie auch nicht ungeschuetzt durch.

`Router::ALLE` ist ausschliesslich fuer `/login` und `/logout` gedacht --
Abmelden muss auch mit einem Token funktionieren, dessen `typ` zu keinem Portal
gehoert. Kein Fachmodul bekommt diesen Wert.

### Zugriffsregel

`typ='mitarbeiter'` darf jede Route besuchen (`darf_alles` in `core/Portal.php`),
alle anderen nur ihr eigenes Portal:

| Token | `/` und `/kunde/...` | `/mitarbeiter/...` | `/fahrer/...` | `/login`, `/logout` |
|---|---|---|---|---|
| kein Token | nur `auth => false` | nur `auth => false` | nur `auth => false` | ja |
| `typ=kunde` | ja | nein | nein | ja |
| `typ=fahrer` | nein | nein | ja | ja |
| `typ=mitarbeiter` | ja | ja | ja | ja |
| `typ` unbekannt/leer | wie "kein Token" | wie "kein Token" | wie "kein Token" | ja |

- **Fremdes Portal** (angemeldet, falsches Portal): Flash-Fehler „Diese Seite
  gehört nicht zu Ihrem Portal." + Weiterleitung auf `Portal::start($tokenPortal)`.
- **Kein verwertbarer Token** auf einer Route mit `auth => true`: Weiterleitung
  auf die Startseite des ZIELPORTALS mit `?login=1` -- nicht mehr fest auf
  `/mitarbeiter`.

### core/Auth.php -- das Portal des Tokens

```php
Auth::portal()                 // Portal aus dem Cookie jwt_token, oder null
Auth::portalAusToken($token)   // dasselbe fuer einen frisch erhaltenen Token
```

Gelesen wird **lokal aus dem JWT-Payload** -- ausdruecklich KEIN Aufruf von
`/verifytoken`. Die Portalpruefung laeuft damit ohne Roundtrip und ohne
Abhaengigkeit von der Erreichbarkeit des Backends.

Zwei Eigenheiten des Payloads (beide live verifiziert), die beim Anfassen dieser
Klasse wichtig sind:

1. **base64url, nicht base64** -- `strtr($teil, '-_', '+/')` plus Padding auf ein
   Vielfaches von 4.
2. **`role` ist ein JSON-String, kein Objekt** -- `uJWTUtils` haengt den Claim
   per `AddPair('role', ARole)` als Zeichenkette an. Es braucht also ein
   **zweites** `json_decode`.

`exp` wird bewusst nicht geprueft. Falls das jemand nachruestet: `exp` und `iat`
sind **keine UTC-Zeitstempel** -- Delphi setzt `IncMinute(Now, ..)` mit lokaler
Zeit, die JOSE-Bibliothek schreibt sie als UTC, der Wert ist um den
Zeitzonen-Offset verschoben. Vergleich also gegen lokale Zeit, nicht `time()`.

**Was die Pruefung leistet und was nicht:** Die Signatur wird nicht geprueft
(das Secret liegt nur im RATIOserver). Ein selbst gebautes oder abgelaufenes
Cookie kommt durch das PHP-Gate und die Portalseite rendert -- jeder
Datenzugriff bleibt aber leer, weil RATIOserver den Token bei jedem
`api_post()` ablehnt. Das Gate ist eine **Portal-Wegweisung**, keine
Zugriffskontrolle auf Daten; die sitzt im Backend. Wer das Gate selbst
unumgehbar braucht, muesste `/verifytoken` pro Request aufrufen -- bewusst
verworfen.

### Regeln fuer Claude Code

- Neue Route IMMER unter das Praefix ihres Portals -- `/mitarbeiter/lieferungen`,
  nicht `/lieferungen`
- `['portal' => Router::ALLE]` nur fuer `/login` und `/logout`
- Zugriffsentscheidungen ausschliesslich ueber `Auth::portal()` bzw.
  `Portal::ausTyp()` -- niemals `Portal::name()`, niemals `role.typ` selbst
  parsen
- Kein `/verifytoken`-Aufruf fuer die Portalpruefung nachruesten
- Modul-URLs in Views und Controllern aus `Portal::praefix()` bauen -- kein
  Praefix ausschreiben. Muster in `EinsatzController` und
  `AnmietimportController`: je eine Konstante `PORTAL` und `MODUL`, daraus
  `Portal::praefix(self::PORTAL) . self::MODUL`

---

## Registrierung

Alle Portale registrieren ueber denselben Controller
(`standard/Controllers/RegistrierungController.php`), dieselbe Verarbeitung und
denselben View. Die Unterschiede stehen ausschliesslich in der Konstante `PORTALE`.
Getrennte Formulare wuerden mit der Zeit auseinanderdriften -- deshalb NIE
kopieren, sondern eine weitere Variante in `PORTALE` ergaenzen.

| Portal | Route | `typ` | Zugang | `username` | Adresse | Passwort |
|---|---|---|---|---|---|---|
| Kundenportal | `/kunde/registrieren` | `kunde` | offen | E-Mail-Adresse | ADRESSEN wird angelegt bzw. verknuepft | eigenes Portalpasswort mit Wiederholung |
| Mitarbeiterportal | `/mitarbeiter/registrieren` | `mitarbeiter` | nur mit Loginname + Passwort aus USERS | USERS-Loginname | keine | das USERS-Passwort, keine Wiederholung |
| Fahrerportal | `/fahrer/registrieren` | `fahrer` | nur wer mit Kuerzel + Vor- und Nachname im PERSONALSTAMM steht | Personalstamm-Kuerzel (`zeichen`), immer GROSS | keine | eigenes Portalpasswort mit Wiederholung |

Das Mitarbeiterformular fragt genau zwei Felder ab: Loginname und Passwort. Keine
E-Mail-Adresse -- REGISTRIERUNG hat kein E-Mail-Feld, und ohne Adresse gibt es auch
kein `ADRESSEN.email`, in dem sie landen koennte. Grundsatz: **kein Formular fragt
Daten ab, die nirgends gespeichert werden.**

Die Schalter in `PORTALE` sind bewusst einzeln und nicht an `adressdaten`
gekoppelt -- das Fahrerportal braucht Namensfelder OHNE Adresse:

| Schalter | `kunde` | `mitarbeiter` | `fahrer` |
|---|---|---|---|
| `userspruefung` -- USERS-Nachweis in PHP | nein | ja | nein (der Endpunkt prueft selbst) |
| `adressdaten` -- Anrede, Anschrift, Telefon, Kundennummer | ja | nein | nein |
| `namensfelder` -- `name1`, `name2` | ja | nein | **ja** |
| `eigenes_passwort` -- Passwort + Wiederholung | ja | nein | ja |
| `live_pruefung` -- Verfuegbarkeit waehrend der Eingabe | ja | nein | nein |
| `username_aus` | `email` | `loginname` | `zeichen` |

### typ ist Pflicht -- und steuert die Adressbehandlung

`REGISTRIERUNG.typ` haelt fest, aus welchem Portal registriert wurde. Der Endpunkt
`insertregistrierunglocal` laesst laut Delphi-Quelle
(`DataModulRegistrierungClass.pas`) **ausschliesslich `kunde`, `mitarbeiter` und
`fahrer`** zu -- jeder andere sowie ein fehlender `typ` wird abgewiesen
("Ungueltiger typ. Erlaubt sind kunde, mitarbeiter und fahrer."). Am `typ` haengt,
was der Endpunkt prueft und ob eine Adresse entsteht:

- `typ='kunde'` -- REGISTRIERUNG und ADRESSEN in einer Transaktion. `anrede`,
  `name1`, `name2` sind Pflicht, `kennziffer` verknuepft eine bestehende Adresse.
- `typ='fahrer'` -- `username`, `name1` (Vorname) und `name2` (Nachname) sind
  Pflicht. `username` wird grossgeschrieben und muss zusammen mit den Namen einem
  Satz im PERSONALSTAMM entsprechen (`username = zeichen`, `name1`, `name2`).
  Ohne Treffer: "Benutzer ist nicht im Personalstamm vorhanden." KEINE Adresse.
- `typ='mitarbeiter'` -- KEINE Adresse, keine weiteren Pflichtfelder.

Bei `mitarbeiter` und `fahrer` bleibt `kennziffer` `NULL` und die Antwort lautet
`"kennziffer":null`, `"adresse":"keine"`. Die Adressfelder werden dort verworfen --
**mit Ausnahme von `name1`/`name2` bei `fahrer`**: die sind Suchkriterium, kein
Adressinhalt, und muessen mitgesendet werden.

**Jeder Registrierungsvorgang setzt `typ` passend zum Portal** -- ohne den Wert
lehnt der Endpunkt den Aufruf komplett ab.

Welche Felder ein Formular abfragt, steuern die Schalter in `PORTALE[..]` (Tabelle
oben). Ein Formular fragt NIE Felder ab, die der Endpunkt bei diesem `typ`
ohnehin verwirft.

### Fahrer-Registrierung -- Abgleich mit dem PERSONALSTAMM

Anders als beim Mitarbeiterportal prueft **PHP hier gar nichts** -- der
Identitaetsnachweis passiert vollstaendig im Endpunkt. `userspruefung` ist
deshalb `false`, es gibt keine zweite Passworteingabe und keinen `Codec`-Aufruf.

Was PHP beisteuert: Kuerzel grossschreiben (`mb_strtoupper`), Laengen pruefen
(`zeichen` ist `ftstring 15`, `name1`/`name2` je 30) und `name1`/`name2` in den
Request legen.

Live verifiziert am 2026-08-27 gegen den laufenden RATIOserver:

| Eingabe | Ergebnis |
|---|---|
| `dan` / Daniela / . (existiert im PERSONALSTAMM) | angelegt als `username='DAN'`, `typ='fahrer'`, `kennziffer=null` |
| `IVANA` / Falscher / Name | "Benutzer ist nicht im Personalstamm vorhanden." |
| `DAN` erneut | "Für dieses Fahrerkürzel ist bereits ein Portalzugang angelegt." |
| Login mit `dan` (klein) | erfolgreich -- `/login` vergleicht per `UPPER()` |

Die Live-Verfuegbarkeitspruefung ist im Fahrerportal bewusst **aus**
(`live_pruefung => false`): sie wuerde oeffentlich verraten, welche Fahrerkuerzel
bereits einen Zugang haben. Die Dublette meldet erst das abgesendete Formular --
und das faellt unter das Rate-Limit.

### Mitarbeiter-Registrierung -- Nachweis ueber USERS

Die Route ist `['auth' => false]`, der Zugang wird aber NICHT per JWT geregelt,
sondern in `pruefeMitarbeiter()`: registrieren darf sich nur, wer in USERS mit
Loginname und Passwort existiert. `USERS.passwort` liegt codiert vor und wird mit
`Core\Codec::decodieren()` entschluesselt.

Die geprueften USERS-Zugangsdaten sind gleichzeitig die Portal-Zugangsdaten: der
Loginname wird als `REGISTRIERUNG.username` gespeichert, das Passwort gehasht
(`password_hash`) als `REGISTRIERUNG.pwd2`. Deshalb hat das Mitarbeiterformular
KEIN eigenes Passwortfeld, keine Wiederholung und keine E-Mail-Adresse -- der
Mitarbeiter meldet sich am Portal mit genau denselben Daten an, die er ohnehin kennt.

Gegengeprueft: `/login` verifiziert `REGISTRIERUNG.pwd2` per `password_verify` und
akzeptiert einen von PHP geschriebenen `password_hash` -- die Kette funktioniert.

Regeln dabei:

- Jeder Fehlschlag -- unbekannter Loginname, falsches Passwort, gesperrtes Konto --
  ergibt DIESELBE Meldung („Der gewünschte Mitarbeiter ist im System nicht
  angelegt"). Unterschiedliche Meldungen wuerden verraten, welche Loginnamen
  existieren. Ausnahme: ein leeres Passwortfeld bekommt einen eigenen Hinweis --
  das verraet nichts ueber das Konto und wird vor den Zaehlern geprueft.
- Fail-closed: nur ein tatsaechlich passendes Passwort laesst weiterlaufen. Fehlt die
  Route oder ist die Antwort unlesbar, wird abgebrochen -- niemals durchgelassen.
- Die USERS-Eingabe ist KEINE Anmeldung: es wird kein `jwt_token`-Cookie gesetzt.
- Das Formular ist eine oeffentliche Brute-Force-Flaeche auf Mitarbeiterkonten,
  deshalb zwei Zaehler: pro IP und pro Loginname. Der Loginname landet nur als
  SHA-256-Hash in der Zaehlerdatei.

### Rate-Limiting -- core/RateLimit.php

Begrenzt Versuche je Aktion und Schluessel (IP oder Hash eines Namens) in
einem gleitenden Fenster von 1 Stunde (`RateLimit::FENSTER`). Einzige
Umsetzung -- kein Controller zaehlt selbst.

| Aktion | Schluessel | Grenze/Stunde | Was zaehlt | Wo |
|---|---|---|---|---|
| `login_ip` | IP | 20 | nur **Fehlschlaege** | `AuthController` |
| `login_konto` | Hash des Benutzernamens | 10 | nur **Fehlschlaege**, Erfolg setzt zurueck | `AuthController` |
| `registrierung` | IP | 3 | jeder Absendeversuch, auch ungueltige | `RegistrierungController` |
| `usernamecheck` | IP | 30 | jede Live-Pruefung | `RegistrierungController` |
| `mitarbeiterlogin_ip` | IP | 10 | jeder USERS-Nachweis | `RegistrierungController` |
| `mitarbeiterlogin_user` | Hash des Loginnamens | 5 | jeder USERS-Nachweis | `RegistrierungController` |

Zwei Muster:

```php
// 1. Jeder Versuch zaehlt
if (RateLimit::ueberschritten(Anfrage::ip(), 'registrierung', 3)) { ... }

// 2. Nur Fehlschlaege zaehlen: vorab zaehlen, bei Erfolg zuruecknehmen.
//    NICHT "erst pruefen, nach Fehlschlag zaehlen" -- parallele Versuche
//    kaemen sonst alle durch die Luecke dazwischen.
if (RateLimit::ueberschritten($ip, 'login_ip', 20)) { ... }
// ... Versuch ...
if ($erfolg) { RateLimit::zuruecknehmen($ip, 'login_ip'); }
```

- Namen nur ueber `RateLimit::schluessel($name)` -- SHA-256 der Grossschreibung,
  kein Klartext auf Platte, Gross-/Kleinschreibung umgeht das Limit nicht.
- IP nur ueber `Anfrage::ip()` (`REMOTE_ADDR`, nie `X-Forwarded-For` -- frei
  vom Client waehlbar).
- Leerer Schluessel wird nicht begrenzt; ist die Datei nicht beschreibbar,
  wird nicht blockiert (fail-open).
- Beim Login ist ein Systemfehler (Server nicht erreichbar) kein Fehlversuch --
  sonst sperrt ein Backend-Ausfall alle Benutzer aus.
- Die Meldung beim Login ist fuer beide Zaehler gleich und verraet nichts
  ueber das Konto. Bekannte Grenze: ein Angreifer kann ein fremdes Konto
  durch absichtliche Fehlversuche fuer bis zu eine Stunde sperren.

### Rate-Limiting zum Testen abschalten

`index.php` kennt dafuer die Konstante `RATE_LIMIT_AKTIV`:

```php
define('RATE_LIMIT_AKTIV', false);   // nur zum Testen
```

Dann greift keine Grenze und es wird auch nichts gezaehlt -- die Zaehlerdatei
entsteht gar nicht. Das gilt fuer alle Zaehler (Login, Registrierung,
Verfuegbarkeitspruefung, Mitarbeiternachweis).

**Sicherung:** `false` wirkt ausschliesslich zusammen mit `DEBUG = true`. Bleibt es
versehentlich im Deployment stehen, greift das Limit dort trotzdem -- solange `DEBUG`
dort wie vorgesehen `false` ist. Nach dem Testen bitte wieder auf `true` setzen; der
eingecheckte Zustand ist immer `true`.

Alternative ohne Codeaenderung -- nur die Zaehler zuruecksetzen:

```bash
rm -f "$(php -r 'echo sys_get_temp_dir();')/ratiophp_ratelimit.json"
```

Achtung: Apache und die PHP-CLI koennen unterschiedliche Temp-Verzeichnisse haben.
Beim Apache dieser Installation liegt die Datei unter `C:\Windows\Temp`.

**Regel fuer Claude Code:** Kein Schutzmechanismus wird "zum Testen" ersatzlos
entfernt oder aufgeweicht. Wenn er im Test stoert, bekommt er einen ausdruecklichen
Schalter, der in der Produktion nicht greifen kann -- so wie hier.

### core/Codec.php

Sinngemaesse Portierung von `Codieren`/`DeCodieren` aus
`D:\Delphi\RATIOserver\Shared\rechtelib.pas` -- ein positionsabhaengiges XOR
(`Chr(Ord(zeichen) XOR (65 + i))`, 1-basiert), symmetrisch in beide Richtungen;
`DeCodieren` ignoriert ein abschliessendes `.`.

**Aendert sich `rechtelib.pas`, muss `core/Codec.php` nachgezogen werden.**
Nie eine eigene Variante der Rechnung in einen Controller schreiben.

---

## Auth / Token

### Cookie-Strategie

Nach dem Login werden ZWEI Cookies gesetzt:

| Cookie | Inhalt | httponly | Zweck |
|---|---|---|---|
| `jwt_token` | JWT-Token | `true` | API-Authentifizierung, sicher gegen XSS |
| `jwt_user` | Benutzername | `false` | Anzeige im Header (kein sensitiver Inhalt) |

### Das secure-Flag -- niemals mit isset() bestimmen

Beide Cookies bekommen ihr `secure`-Flag aus `BaseController::istHttps()`.
Die Methode steht in `core/BaseController.php` und ist `protected` -- damit
steht sie JEDEM Controller zur Verfuegung, nicht nur dem `AuthController`.
Das ist Absicht: auch andere Controller loeschen die Cookies, etwa nach einer
abgelaufenen Anmeldung.

```php
protected function istHttps(): bool
{
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
}
```

**Grenze der Erkennung:** erkannt wird nur eine direkt in diesem Apache
terminierte TLS-Verbindung. Terminiert spaeter ein vorgelagerter Reverse-Proxy
das TLS und reicht per HTTP weiter, ist `$_SERVER['HTTPS']` leer -- die Methode
liefert `false`, obwohl der Browser ueber HTTPS verbunden ist, und die Cookies
werden ohne `secure` gesetzt. Fuer so ein Setup muss zusaetzlich
`$_SERVER['HTTP_X_FORWARDED_PROTO']` ausgewertet werden, aber NUR bei einem
vertrauenswuerdigen Proxy: der Header ist sonst frei vom Client waehlbar.

```php
// RICHTIG
'secure' => $this->istHttps(),

// FALSCH -- manche Server- und PHP-Konfigurationen setzen HTTPS bei einem
// HTTP-Zugriff auf den String 'off'. isset() ist dann true, das Cookie
// bekaeme secure, und der Browser verwirft es ueber HTTP stillschweigend.
// Die Anmeldung sieht erfolgreich aus, greift aber nicht -- ein Fehlerbild,
// das sich nur auf manchen Zugriffswegen zeigt und schwer zu finden ist.
'secure' => isset($_SERVER['HTTPS']),
```

Die Methode kapselt den Ausdruck bewusst, statt ihn an den vier setcookie-Stellen
zu wiederholen -- genau diese Wiederholung hat den Fehler urspruenglich
entstehen lassen. Braucht ein weiterer Controller dieselbe Pruefung, wandert sie
nach `core/BaseController.php`, statt kopiert zu werden.

```php
// Login -- beide Cookies setzen
setcookie('jwt_token', $token, [
    'expires'  => time() + TOKEN_LIFETIME,
    'path'     => '/',
    'samesite' => 'Strict',
    'secure'   => $this->istHttps(),
    'httponly' => true,
]);
setcookie('jwt_user', $username, [
    'expires'  => time() + TOKEN_LIFETIME,
    'path'     => '/',
    'samesite' => 'Strict',
    'secure'   => $this->istHttps(),
    'httponly' => false,
]);

// Token lesen -- in api_post() und Router
$_COOKIE['jwt_token'] ?? ''

// Benutzername lesen -- in Header-Komponente
$_COOKIE['jwt_user'] ?? ''

// Logout -- beide Cookies loeschen
setcookie('jwt_token', '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Strict', 'secure' => $this->istHttps(), 'httponly' => true]);
setcookie('jwt_user',  '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Strict', 'secure' => $this->istHttps(), 'httponly' => false]);
```

**Spaetere SSO-Integration mit Angular:**
Angular setzt nach Login dieselben zwei Cookies -- PHP-Login entfaellt automatisch:
```typescript
document.cookie = `jwt_token=${token}; path=/; max-age=${900*60}; SameSite=Strict`;
document.cookie = `jwt_user=${username}; path=/; max-age=${900*60}; SameSite=Strict`;
```

### Entwicklung
Der JWT-Token fuer die Entwicklung liegt in:
```
ClaudeCodePatterns/token.local.txt
```

**WICHTIG: Diese Datei existiert NUR in der Entwicklungsumgebung -- NICHT im Deployment.**
Claude Code darf den Dateizugriff auf `token.local.txt` AUSSCHLIESSLICH zum
direkten Testen von Endpunkten per curl/bash verwenden.

In der Anwendung selbst wird der Token IMMER aus dem Cookie gelesen:
```php
// RICHTIG -- Token aus Cookie
$_COOKIE['jwt_token'] ?? ''

// FALSCH -- Session wird nicht verwendet
$_SESSION['jwt_token'] ?? ''

// FALSCH -- Datei nur fuer curl-Tests
file_get_contents('ClaudeCodePatterns/token.local.txt')
```

Die Datei wird NICHT ins Git eingecheckt (.gitignore).

### Login-Endpunkt
```
POST /login
```

Request-Body:
```json
{ "user": "benutzername", "password": "passwort" }
```

Erfolg (HTTP 200):
```json
{ "token": "eyJ0eXAiOiJKV1Qi..." }
```

Fehler (HTTP 401):
```json
{ "status": "error", "message": "Benutzername oder Passwort sind falsch" }
```

### Login-Ablauf im AuthController

Das Login-Formular sitzt in einem Bootstrap-Modal (`views/components/login-modal.php`).
Das Modal ist im `layout.php` eingebunden und wird in JEDEM Portal gerendert.
Der "Anmelden"-Link im Header oeffnet es per `data-bs-toggle="modal"`.

Es gibt keine GET-Route `/login` -- nur POST `/login` fuer den Formular-Submit.
`/login` prueft gegen REGISTRIERUNG (`pwd2` per `password_verify`) -- dort liegen
die Registrierungen aller Portale nebeneinander, unterschieden nur durch `typ`.
Es gibt also nur EINEN Login-Endpunkt fuer alle Portale. Der Benutzername wird
per `UPPER()` auf beiden Seiten verglichen -- Gross-/Kleinschreibung egal.

**Das Weiterleitungsziel kommt aus dem versteckten Feld `portal`:**

| `portal` | nach Login | nach Fehler | nach Logout |
|---|---|---|---|
| `kunde` | `/` | `/?login=1` | `/` |
| `mitarbeiter` | `/mitarbeiter` | `/mitarbeiter?login=1` | `/mitarbeiter` |
| `fahrer` | `/fahrer` | `/fahrer?login=1` | `/fahrer` |

Der `AuthController` bildet den Wert ueber `Core\Portal` ab. Unbekannte, fehlende
oder manipulierte Werte landen im Kundenportal.

**Ausnahme nach ERFOLGREICHEM Login: das Portal des Tokens gewinnt.**
`Auth::portalAusToken($token)` liest `role.typ` aus der frischen Antwort -- wer
sich am Modal eines fremden Portals anmeldet, landet trotzdem in seinem eigenen
Portal. Ohne das wuerde ein Fahrer, der sich ueber das Kundenportal anmeldet,
nach `/` geschickt und von der Portalgrenze im Router mit einer Fehlermeldung
sofort wieder weggeschickt. Das Formular-Portal bleibt Ziel bei Fehlern und als
Fallback, falls der Token kein verwertbares `typ` traegt.

```php
$tokenPortal = Auth::portalAusToken($token);
$this->redirect($tokenPortal !== null ? Portal::start($tokenPortal) : $ziel);
```

Das Cookie kann an dieser Stelle nicht gelesen werden -- `setcookie()` wirkt
erst beim naechsten Request. Deshalb `portalAusToken()` und nicht `portal()`.

```php
// RICHTIG -- nur Portalnamen annehmen, Pfad aus der zentralen Definition
$portal = Portal::name((string)($_POST['portal'] ?? ''));
$ziel   = Portal::start($portal);

// FALSCH -- Pfad aus dem Request ist eine offene Weiterleitung
$ziel = $_POST['redirect_to'] ?? '/';

// FALSCH -- eigene Whitelist im Controller. Sie vergisst jedes neue Portal.
private const PORTAL_ZIELE = ['kunde' => '/', 'mitarbeiter' => '/mitarbeiter'];
```

Vor dem Aufruf von `/login`: Rate-Limit pro IP und pro Konto pruefen
(`login_ip`, `login_konto` -- nur Fehlschlaege zaehlen, siehe Abschnitt
**Rate-Limiting** unter Registrierung).

Nach erfolgreichem Login:
1. Token aus API-Antwort lesen, Fehlversuch-Zaehler zuruecknehmen bzw.
   den Kontozaehler zuruecksetzen
2. Benutzernamen direkt aus `$_POST['user']` -- kein `verifytoken` noetig
3. Beide Cookies setzen (`jwt_token` + `jwt_user`)
4. Auf `/mitarbeiter` weiterleiten

```php
public function login(): void
{
    // Nur POST erlaubt -- kein GET
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $this->redirect('/mitarbeiter');
        return;
    }

    $user     = $_POST['user']     ?? '';
    $password = $_POST['password'] ?? '';

    // Login gegen RATIOserver
    $response = \api_post('/login', [
        'user'     => $user,
        'password' => $password,
    ]);

    if (empty($response['token'])) {
        $this->flashError($response['message'] ?? 'Login fehlgeschlagen');
        $this->redirect('/mitarbeiter?login=1');  // ?login=1 oeffnet das Modal automatisch per JS
        return;
    }

    $token    = $response['token'];
    $username = $_POST['user'] ?? '';

    // Beide Cookies setzen
    setcookie('jwt_token', $token, [
        'expires'  => time() + TOKEN_LIFETIME,
        'path'     => '/',
        'samesite' => 'Strict',
        'secure'   => $this->istHttps(),
        'httponly' => true,
    ]);
    setcookie('jwt_user', $username, [
        'expires'  => time() + TOKEN_LIFETIME,
        'path'     => '/',
        'samesite' => 'Strict',
        'secure'   => $this->istHttps(),
        'httponly' => false,
    ]);

    $this->redirect('/mitarbeiter');
}

public function logout(): void
{
    // Beide Cookies loeschen
    setcookie('jwt_token', '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Strict', 'secure' => $this->istHttps(), 'httponly' => true]);
    setcookie('jwt_user',  '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Strict', 'secure' => $this->istHttps(), 'httponly' => false]);
    $this->redirect('/mitarbeiter');
}
```

### Header-Komponente

Die Navigation liegt in `views/components/header.php` und wird von `views/layout.php`
per `include` eingebunden -- nie direkt im Layout ausgeschrieben, nie in einem View.

Die Komponente wertet `$portal` aus (siehe Abschnitt Portalstruktur). Sie enthaelt
**keine Feature-Links** -- nur Brand, Portal-Label und "Start". Der Benutzerbereich
existiert ausschliesslich im Mitarbeiterportal:

```php
$portal = $portal ?? 'kunde';
$istMitarbeiter = ($portal === 'mitarbeiter');
$portalStart    = $istMitarbeiter ? '/mitarbeiter' : '/';
```

Die Login-Pruefung selbst nutzt `$_COOKIE['jwt_token']` -- NIEMALS `$_SESSION`.

Der Benutzerbereich im Header ist ein **Bootstrap Dropdown-Menue** und wird nur
im `$istMitarbeiter`-Zweig ausgegeben:

```php
<?php if (!empty($_COOKIE['jwt_token'])): ?>
    <!-- Eingeloggt: Dropdown mit Benutzername und Abmelden -->
    <ul class="navbar-nav ms-auto">
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#"
               role="button" data-bs-toggle="dropdown">
                <i class="bi bi-person-circle me-1"></i>
                <?= htmlspecialchars($_COOKIE['jwt_user'] ?? '') ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item" href="<?= APP_BASE ?>/logout">
                        <i class="bi bi-box-arrow-right me-1"></i>Abmelden
                    </a>
                </li>
            </ul>
        </li>
    </ul>
<?php else: ?>
    <!-- Nicht eingeloggt: Anmelden-Button oeffnet Login-Modal -->
    <ul class="navbar-nav ms-auto">
        <li class="nav-item">
            <a class="nav-link" href="#"
               data-bs-toggle="modal" data-bs-target="#loginModal">
                <i class="bi bi-person me-1"></i>Anmelden
            </a>
        </li>
    </ul>
<?php endif; ?>
```

**WICHTIG fuer Claude Code:**
- Navigation IMMER als Komponente `views/components/header.php` -- nie im Layout inline
- KEINE Feature-/Modul-Links im Header -- Zugang nur ueber die Portal-Startseite
- Kein Link vom Kundenportal ins Mitarbeiterportal
- Benutzerbereich IMMER als Bootstrap Dropdown -- kein einfacher Link
- `$_COOKIE['jwt_token']` fuer Login-Pruefung
- `$_COOKIE['jwt_user']` fuer Anzeigename
- Niemals `$_SESSION` verwenden

### Router Auth-Check

Die Pruefung im Router hat zwei Stufen: erst Portalgrenze, dann Login-Zwang.
Details und Zugriffstabelle im Abschnitt **Routenkonventionen und Portalgrenzen**.

Das Ziel des Login-Redirects ist die Startseite des **Portals der Route** --
nicht mehr fest `/mitarbeiter`. Sonst landet ein Kunde auf einer geschuetzten
Kundenseite im Mitarbeiterportal.

```php
// RICHTIG -- Ziel ist die Startseite des Portals der Route,
// ?login=1 oeffnet dort das Modal automatisch per JS
$tokenPortal = Auth::portal();
if ($tokenPortal === null && $route['auth']) {
    header('Location: ' . APP_BASE . Portal::start($route['portal']) . '?login=1');
    exit;
}

// FALSCH -- fest verdrahtetes Portal. Falsch, sobald eine geschuetzte Route
// nicht zum Mitarbeiterportal gehoert.
header('Location: ' . APP_BASE . '/mitarbeiter?login=1');

// FALSCH -- /login existiert nur als POST-Route, das ergibt einen 404
header('Location: ' . APP_BASE . '/login');

// FALSCH -- nur "Cookie vorhanden" geprueft. Sagt nichts ueber das Portal
// und laesst einen Kundentoken auf jede Mitarbeiterseite.
if ($route['auth'] && empty($_COOKIE['jwt_token'])) { ... }

// FALSCH -- Session wird nicht verwendet
if ($route['auth'] && empty($_SESSION['jwt_token'])) { ... }
```

### Token-Validierung

```
POST /verifytoken
```

Prueft Signatur und Ablauf serverseitig und gibt den `role`-Claim zurueck.
Der Token wird im Authorization-Header mitgeschickt.

**Die App ruft diesen Endpunkt nicht auf.** Der Benutzername kommt aus
`$_POST['user']`, das Portal aus dem lokal dekodierten Payload
(`core/Auth.php`). Der Endpunkt ist hier dokumentiert, weil er die Struktur des
`role`-Claims zeigt -- nicht als Aufrufmuster. Kein `/verifytoken` fuer die
Portalpruefung nachruesten (Begruendung im Abschnitt Routenkonventionen).

Achtung bei der Antwort: `user` auf oberster Ebene ist bei einem Login aus PHP
LEER -- `DoLogin` nimmt den Subject aus dem Query-String, PHP sendet die
Anmeldedaten aber im JSON-Body. Der Benutzername steht in `role.username` bzw.
`role.loginname`.

Erfolg (HTTP 200):
```json
{
    "status": "OK",
    "valid": true,
    "user": "SUPERVISOR",
    "role": {
        "loginname": "SUPERVISOR",
        "username": "SUPERVISOR",
        "passwort": "",
        "gruppe": "",
        "zugruppe": "",
        "agenturcode": "",
        "kennziffer": "10086",
        "filiale": "",
        "abteilung": ""
    }
}
```

Fehler (HTTP 500):
```json
{
    "status": "error",
    "message": "Anmeldung ungueltig oder abgelaufen. Bitte neu anmelden"
}
```

Bei HTTP 500 -- beide Cookies loeschen und auf Startseite umleiten:
```php
setcookie('jwt_token', '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Strict', 'secure' => $this->istHttps(), 'httponly' => true]);
setcookie('jwt_user',  '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Strict', 'secure' => $this->istHttps(), 'httponly' => false]);
$this->redirect('/mitarbeiter');
```

### Was Claude Code beim Befehl "Erstelle einen Login" anlegen soll

- Route `/login` in `config/routes.php` -- nur POST, `['auth' => false, 'portal' => Router::ALLE]`
- Route `/logout` in `config/routes.php` mit `['auth' => false, 'portal' => Router::ALLE]`
- `standard/Controllers/AuthController.php` mit `login()` und `logout()` gemaess Muster oben
- `views/components/login-modal.php` -- Login-Formular im gemeinsamen
  Geruest `views/components/modal.php` (`View::komponente('modal', ...)`)
  - `formAction => '/login'` (POST)
  - Felder: `user`, `password`, verstecktes `portal`
  - Kein `required` auf dem Passwort-Feld
  - Benutzerfehler ueber `meldungen.php` im Modal anzeigen
  - `autoOeffnen` wenn URL-Parameter `?login=1` gesetzt (`Portal::login()`)
- `views/layout.php` -- Login-Modal einbinden (direkt vor `</body>`), in BEIDEN
  Portalen ohne Bedingung:
  ```php
  <?php include VIEW_PATH . '/components/login-modal.php'; ?>
  ```
- `views/components/header.php` -- "Anmelden"-Link bzw. Benutzer-Dropdown, in
  jedem Portal. Modal per `data-bs-toggle="modal" data-bs-target="#loginModal"`
- Redirects von `login()` und `logout()` richten sich nach `portal` (siehe Tabelle
  oben) -- immer aus der Whitelist, nie ein Pfad aus dem Request
- Keine eigene View `auth/login.php` -- das Modal ersetzt sie vollstaendig

---

## Konstanten in index.php

```php
define('API_ORIGIN_MANUELL', '');           // leer = automatisch, siehe unten
define('API_ORIGIN', $apiSchema . '://127.0.0.1:' . $apiPort);   // Loopback
define('BASE_URL',   API_ORIGIN . '/ibapi');
define('APP_BASE',  rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'));
define('DEBUG', true);  // Entwicklung: true -- Produktion: false
define('RATE_LIMIT_AKTIV', true);  // nur zum Testen false -- siehe Abschnitt Registrierung
```

### API_ORIGIN und BASE_URL

Volle URL fuer curl -- kein relativer Pfad, da curl absolute URLs benoetigt.

**`BASE_URL` ist ein Loopback und wird NICHT aus dem Request abgeleitet.**
PHP und RATIOserver laufen im selben Apache -- der Aufruf verlaesst die Maschine
also nie und braucht weder DNS-Aufloesung noch ein gueltiges Zertifikat noch
eine Firewall-Freigabe.

Frueher wurde `BASE_URL` aus `$_SERVER['HTTP_HOST']` und `$_SERVER['HTTPS']`
gebaut. Das war der Grund, warum der Login **lokal funktionierte und remote
fehlschlug**: sobald ein Browser von aussen zugriff, rief PHP sich selbst unter
der externen URL auf.

| Zugriff des Browsers | alte BASE_URL | Ergebnis |
|---|---|---|
| `http://localhost/ratiophp` | `http://localhost/ibapi` | funktioniert |
| `https://server.firma.de/ratiophp` | `https://server.firma.de/ibapi` | curl-Fehler 60 -- selbstsigniertes Zertifikat nicht vertrauenswuerdig |
| `http://server.firma.de/ratiophp` | `http://server.firma.de/ibapi` | scheitert, wenn der Server sich selbst unter diesem Namen nicht erreicht |

Der Transportfehler war dabei nicht erkennbar: `curl_exec()` liefert `false`,
`api_post()` gibt `[]` zurueck, und der `AuthController` meldet lediglich
"Login fehlgeschlagen" -- ununterscheidbar von falschen Zugangsdaten.

### Beliebige Ports funktionieren ohne Konfiguration

Schema UND Port stammen aus dem aktuellen Request. Beide setzt Apache selbst --
anders als `HTTP_HOST`, das aus einem Client-Header stammt und deshalb nicht
verwendbar ist. `SERVER_PORT` nennt immer den Port des Sockets, der diesen
Request bedient, und passt damit **immer** zum Schema:

```php
$apiSchema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$apiPort   = (int)($_SERVER['SERVER_PORT'] ?? ($apiSchema === 'https' ? 443 : 80));
```

| Zugriff des Browsers | daraus abgeleitete BASE_URL |
|---|---|
| `http://host/...` | `http://127.0.0.1:80/ibapi` |
| `http://host:8080/...` | `http://127.0.0.1:8080/ibapi` |
| `https://host/...` | `https://127.0.0.1:443/ibapi` |
| `https://host:8443/...` | `https://127.0.0.1:8443/ibapi` |

Der entscheidende Punkt: **der Loopback folgt dem Schema des Requests.** Wuerde
bei einem HTTPS-Zugriff auf HTTP zurueckgefallen, waere der HTTP-Port unbekannt
-- `SERVER_PORT` nennt dann ja den HTTPS-Port. Genau deshalb kein
HTTP-Erzwingen und kein fest verdrahteter Fallback-Port.

Der HTTPS-Loopback laeuft dabei gegen das meist selbstsignierte Zertifikat
desselben Apache. `core/Api.php` schaltet die Zertifikatspruefung deshalb ab --
**ausschliesslich** fuer Loopback-Ziele (`127.0.0.1`, `::1`, `localhost`):

```php
$ziel = parse_url(BASE_URL);
$istLoopback = in_array($ziel['host'] ?? '', ['127.0.0.1', '::1', 'localhost'], true);

if ($istLoopback && ($ziel['scheme'] ?? '') === 'https') {
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
}
```

Wer den Loopback belauschen koennte, sitzt bereits auf dem Server -- die
Abschwaechung kostet dort nichts. Ausserhalb davon bleibt die Pruefung
unangetastet: zeigt `API_ORIGIN_MANUELL` auf einen fremden HTTPS-Host, wird
dessen Zertifikat weiterhin geprueft (live gegengeprueft -- der Aufruf
scheitert dann mit curl-Fehler 60).

### API_ORIGIN_MANUELL -- die Notbremse

```php
define('API_ORIGIN_MANUELL', '');   // leer = automatische Ableitung
```

Nur fuer Sonderfaelle, in denen die Ableitung nicht passt -- praktisch nur,
wenn Apache nicht an `127.0.0.1` gebunden ist (`Listen` mit fester IP).
Dann z.B. `'http://192.168.1.5:8080'`. Solange der Wert leer ist, greift die
automatische Ableitung.

**WICHTIG fuer Claude Code:**
- `BASE_URL` NIEMALS wieder aus `$_SERVER['HTTP_HOST']` bauen -- das ist genau
  der behobene Fehler. `HTTP_HOST` stammt aus einem Client-Header;
  `SERVER_PORT` dagegen von Apache und ist deshalb verwendbar
- In Controllern und Views immer die Konstante `BASE_URL` verwenden -- nie eine
  URL ausschreiben
- `APP_BASE` bleibt dynamisch: das betrifft die Browser-Seite (Links, Redirects),
  nicht den Server-zu-Server-Aufruf

### APP_BASE
**IMMER dynamisch ermitteln -- niemals hardcodieren.**
`APP_BASE` ergibt sich automatisch aus dem tatsaechlichen Installationsverzeichnis:

| Installation unter | APP_BASE |
|---|---|
| `htdocs/app/` | `/app` |
| `htdocs/myapp/` | `/myapp` |
| `htdocs/kundenname/` | `/kundenname` |
| Server-Root | `` (leer) |

**WICHTIG fuer Claude Code:** Taucht irgendwo im generierten Code ein
hardcodierter Pfad wie `/app/...` auf, ist das ein Fehler.
Sofort durch die Konstante `APP_BASE` ersetzen.

### JWT_TOKEN
**Kein `define()` fuer JWT_TOKEN in index.php.**
Der Token steckt im Cookie und kann sich pro Request unterscheiden -- eine Konstante
wuerde einen veralteten Wert festschreiben. Token immer direkt aus dem Cookie lesen:
```php
// RICHTIG -- direkt in Api.php aus dem Cookie lesen
$_COOKIE['jwt_token'] ?? ''

// FALSCH -- Konstante friert den Wert ein
define('JWT_TOKEN', $_COOKIE['jwt_token'] ?? '');

// FALSCH -- die Session wird fuer den Token nicht verwendet
$_SESSION['jwt_token'] ?? ''
```

---

## API-Basisurl

```
BASE_URL = http://127.0.0.1/ibapi
```

Kein externer Hostname, kein abweichender Port -- PHP und RATIOserver laufen im selben Apache.
`BASE_URL` ist deshalb ein fester Loopback und haengt NICHT vom Zugriffsweg des
Browsers ab -- Begruendung und Anpassung siehe Abschnitt **API_ORIGIN und BASE_URL**
bei den Konstanten in index.php.
`BASE_URL` muss eine vollstaendige URL sein, da curl relative Pfade nicht akzeptiert.

---

## HTTP-Zugriff aus PHP

Wichtig: RATIOserver verwendet fuer ALLE Operationen HTTP-POST -- auch fuer lesende
Zugriffe wie Listen oder Einzelsaetze. Es gibt kein GET, PUT oder DELETE.
Der Unterschied zwischen Lesen und Schreiben ergibt sich ausschliesslich aus dem
Endpunkt-Namen (getXxx vs. insertXxx) und dem JSON-Body.

### Fehler werden NICHT verschluckt

`api_post()` reicht jede Antwort an `Core\Fehler::pruefeApiAntwort()` weiter.
Systemfehler (Transport, 401, 403, 404, Datenbank) werden dort gemeldet und
kommen in der ueblichen Form zurueck:

```php
[
    'status'    => 'error',
    'message'   => 'Für diese Funktion fehlt Ihnen die Berechtigung. ...',
    'fehlerart' => 'system',
    'http'      => 403,
]
```

Details und Klassifizierung im Abschnitt **Fehlerbehandlung**.

**Warum das wichtig ist:** ohne diese Behandlung ist ein Verbindungsfehler von
falschen Zugangsdaten nicht zu unterscheiden -- der Benutzer sieht in beiden
Faellen nur "Login fehlgeschlagen". Genau dieses stumme Fehlerbild hat eine
Fehlersuche mehrere Runden gekostet, als `BASE_URL` noch aus dem Request
gebaut wurde.

**Regel:** Diese Behandlung nicht entfernen und nicht in Controller kopieren --
sie gehoert in `api_post()`, damit jeder Endpunkt sie bekommt.

---

## Fehlerbehandlung

Zwei Fehlerarten, getrennt angezeigt -- plus Erfolgsmeldungen, die denselben
Weg nehmen.

| Art | Beispiele | Anzeige | Melden |
|---|---|---|---|
| **Systemfehler** | Server/Port nicht erreichbar, Datenbankfehler, fehlende Rolle auf einen Endpunkt (403), Seite/Endpunkt existiert nicht (404), Token ungueltig (401), PHP-Ausnahme | reservierter Bereich `<div id="app-systemfehler">` direkt unter dem Header -- scrollt nie weg | automatisch durch `\api_post()`, Router und Exception-Handler; sonst `$this->systemFehler($text, $detail)` |
| **Benutzerfehler** | Pflichtfeld leer, Feld zu lang, Zeitraum zu gross, Registrierung vom Endpunkt fachlich abgelehnt | Dialog, oeffnet sich automatisch | Formularfelder: `Core\Pruefung`; sonst `$this->flashError($text)` |
| Erfolg | Registrierung angelegt | oben im Inhaltsbereich | `$this->flashSuccess($text)` |

### Bausteine -- jeder genau einmal

| Datei | Aufgabe |
|---|---|
| `core/Meldungen.php` | EINZIGER Speicher (Session) fuer alle Meldungsarten. `ARTEN` legt Farbe, Icon und Praefix je Art fest. Niemand sonst greift auf die Session-Schluessel zu. |
| `core/Fehler.php` | Klassifizierung der API-Antworten, `Fehler::ok()`, `Fehler::istSystem()`, `Fehler::system()`, Fehlerseite, Exception-Handler |
| `core/Pruefung.php` | Formularpruefung aus Feld-Definitionen -- erzeugt alle Benutzerfehler-Texte einheitlich ("Feld: Problem.") und die HTML-Attribute (`maxlength`, `minlength`, `required`) |
| `core/Anfrage.php` | Routenpfad des Requests (`pfad()`, `normalisiere()`) und `umleiten()` -- einziges `header('Location: ...')` |
| `views/components/meldungen.php` | EINZIGE Darstellung von Meldungen, fuer alle Arten und Orte |
| `views/components/modal.php` | EINZIGES Modal-Geruest inkl. Auto-Oeffnen -- Login-Modal und Fehler-Dialog bauen darauf auf |
| `views/components/systemfehler.php` | reservierter Bereich -- Aufruf von `meldungen.php` |
| `views/components/fehler-dialog.php` | Dialog -- `modal.php` + `meldungen.php` |
| `views/components/flash.php` | Erfolg -- Aufruf von `meldungen.php` |

Komponenten mit eigenen Variablen werden mit `View::komponente('name', [...])`
gerendert.

Meldungen liegen in der Session und ueberleben damit einen Redirect. Gleiche
Texte werden zusammengefasst, ihre Details gesammelt.

**Regel fuer Modals:** Ein Modal, das beim Laden ohnehin aufgeht, zeigt die
Benutzerfehler selbst an und holt sie damit ab -- so erscheint falsches
Passwort im Login-Modal (`Portal::login()`, also `?login=1`) und nicht
zusaetzlich im Fehler-Dialog. Deshalb steht `fehler-dialog.php` im Layout
nach allen anderen Modals.

### Formularpruefung mit Core\Pruefung

Jedes Formularfeld hat EINE Definition im Controller. Daraus entstehen Label,
`maxlength`/`required` im View UND die serverseitige Pruefung samt
Fehlertext -- keine Grenze und kein Label steht doppelt. Muster:
`RegistrierungController::FELDER`.

```php
// Controller
private const FELDER = [
    'name1'    => ['bezeichnung' => 'Vorname', 'pflicht' => true, 'max_zeichen' => 30],
    'password' => ['bezeichnung' => 'Passwort', 'pflicht' => true, 'min_bytes' => 6, 'max_bytes' => 72, 'geheim' => true],
];

$werte = Pruefung::werteAusPost(self::FELDER);
if (!Pruefung::formular(self::FELDER, $werte)->melde()) {
    // alle Fehler stehen gesammelt im Dialog -- Formular erneut zeigen,
    // Eingaben ohne Passwoerter: Pruefung::ohneGeheime(self::FELDER, $werte)
}

// View -- Definitionen kommen per render() als $felder
<input name="name1" <?= Pruefung::htmlAttribute($felder, 'name1') ?>>
```

Schluessel: `bezeichnung`, `pflicht`, `max_zeichen` (mb_strlen), `min_bytes`/
`max_bytes` (strlen, Passwort), `email`, `ganzzahl` `[min, max]`, `auswahl`,
`gleich` (Wiederholungsfeld), `geheim` (nicht trimmen, nicht zurueckgeben),
`gross` (Grossbuchstaben). Beschreibung in `core/Pruefung.php`.

### Klassifizierung der RATIOserver-Antworten

Verifiziert gegen `WebModuleUnit1.pas` (`DefActionHandler`, `WebModuleException`):

| Antwort | Art | Herkunft im Backend |
|---|---|---|
| curl-Fehler | System | Server/Port nicht erreichbar |
| HTTP 401 | System -- **ausser bei `/login`** (falsches Passwort) | `DoVerifyToken` |
| HTTP 403 "Keine Berechtigung für diesen Endpunkt." | System | Rollenpruefung `HasRouteAccess` gegen `role.rollen` |
| HTTP 404 | System | Pfad im Router nicht gefunden |
| HTTP 400 bzw. Meldung beginnt mit `[FireDAC]` | System | `EFDDBEngineException` -- SQL-/Verbindungsfehler |
| keine JSON-Antwort mit HTTP >= 400 | System | Absturz / falsches Ziel |
| HTTP 500 mit `message` | **Benutzer** | fachliche Ablehnung, z.B. "Benutzer ist nicht im Personalstamm vorhanden." |

Die Rollenpruefung: `role.rollen` (Komma-Liste, Blaupausen wie `@mitarbeiter`
aufgeloest) muss `supervisor`, eine Rolle der Route oder den Endpunkt selbst
(`/dispo/*`) enthalten. Leere Rollen = keine Einschraenkung.

### Regeln fuer Claude Code

- Erfolg eines Endpunkts mit `Fehler::ok($antwort)` pruefen -- nie
  `($antwort['status'] ?? '') === 'OK'` ausschreiben.
- Fehler einer `\api_post()`-Antwort mit `$this->apiFehler($antwort, 'Ersatztext')`
  melden -- Systemfehler sind dann schon gemeldet und erscheinen NICHT doppelt
  als Dialog. Alternativ `Fehler::istSystem($antwort)` pruefen.
- Systemfehler NIE per `flashError()` melden, Benutzerfehler NIE per
  `systemFehler()`.
- Formularfelder IMMER ueber Feld-Definitionen und `Core\Pruefung` pruefen --
  keine handgeschriebenen `if (mb_strlen(..) > ..) flashError(..)`-Bloecke.
- Keine eigene Meldungs- oder Modal-Darstellung: `meldungen.php` bzw.
  `modal.php` verwenden. Kein `alert alert-...` und kein `modal fade` in Views.
- Kein direkter Zugriff auf `$_SESSION` fuer Meldungen -- nur ueber
  `Core\Meldungen` (bzw. `flashError()`/`flashSuccess()`/`Fehler::system()`).
- Umleitungen nur ueber `redirect()` bzw. `Anfrage::umleiten()`, Login-Aufforderung
  ueber `Portal::login($portal)` -- `?login=1` nie selbst anhaengen.
- Technische Details (Endpunkt, HTTP-Status, Servermeldung, Datei:Zeile) nur
  als `$detail` -- sichtbar ausschliesslich bei `DEBUG`.
- Der Tag `#app-systemfehler` wird immer ausgegeben (leer mit `hidden`) und ist
  ausschliesslich fuer Systemfehler reserviert.
- Unbekannte Routen, Konfigurationsfehler und nicht abgefangene Ausnahmen
  rendern ueber `Fehler::seite()` eine Fehlerseite im Layout des passenden
  Portals (`standard/Views/fehler/index.php`) -- keine nackten `<h1>404</h1>`.

```php
function api_post(string $endpoint, array $data): array
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, BASE_URL . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . ($_COOKIE['jwt_token'] ?? ''),
        'Content-Type: application/json',
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}
```

---

## API-Konventionen

### Endpunkt-Namensschema

Claude leitet Endpunkte eigenstaendig aus dem Namensschema ab.
`Xxx` ist IMMER der exakte Tabellenname in RATIOserver -- keine Singular/Plural-Ableitung.

Bei Unklarheit: Endpunkt mit Token aus `ClaudeCodePatterns/token.local.txt` direkt testen.

**Endpunkt-Pfad:** Der Pfad vor dem Endpunkt-Namen entspricht dem Controller-Namen in RATIOserver.
Dieser muss nicht identisch mit dem Tabellennamen sein -- es kann ein abweichender
Primaerpfad verwendet werden.

Im Prompt IMMER den vollstaendigen Pfad angeben:

```
-- Einfach: Tabellenname = Controllerpfad
Tabelle ADRESSEN -> /adressen/getAdressen

-- Mit Primaerpfad: Controllerpfad weicht vom Tabellennamen ab
Tabelle EINSATZ, Controllerpfad "dispo" -> /dispo/getEinsatz
```

Beispiel-Prompt mit Primaerpfad:
```
Lege eine Seite mit Tabelle fuer die Tabelle EINSATZ an.
Der API-Pfad lautet /dispo/getEinsatz (nicht /einsatz/getEinsatz).
```

| Muster | Zweck |
|---|---|
| `getXxx` | Liste -- alle Datensaetze |
| `getXxxFiltered` | Liste mit parametrisiertem Filter |
| `getXxxById` | Einzelsatz -- fuer Edit-Formulare |
| `insertXxx` | Neuen Datensatz anlegen |
| `updateXxx` | Datensatz aktualisieren |
| `deleteXxx` | Datensatz loeschen |
| `getXxxKey` | Naechsten Wert fuer Sequence ermitteln |

Beispiel fuer Tabelle `ADRESSEN` (Controllerpfad = Tabellenname):
```
/adressen/getAdressen
/adressen/getAdressenById
/adressen/insertAdressen
/adressen/updateAdressen
/adressen/deleteAdressen
```

Beispiel fuer Tabelle `EINSATZ` mit Primaerpfad `dispo`:
```
/dispo/getEinsatz
/dispo/getEinsatzById
/dispo/insertEinsatz
/dispo/updateEinsatz
/dispo/deleteEinsatz
```

### Request-Format (immer POST, JSON-Body)

**WICHTIG: `fields` muss immer ein JSON-Array sein -- niemals der String `"*"`.**

Unbekannte Felder ermitteln: Endpunkt einmalig mit `"fields":"*"` (ohne limit/offset)
testen -- die Antwort enthaelt einen `header`-Block mit allen Feldnamen und Typen:
```json
{
  "header": { "kennziffer": "ftinteger", "name2": "ftstring 30", ... },
  "data": [ ... ]
}
```

```php
// Liste ohne Pagination
\api_post('/controller/getXxx', [
    'fields'  => ['feld1', 'feld2'],
    'orderby' => 'feld1',
]);

// Liste MIT Pagination (orderby ist Pflicht)
\api_post('/controller/getXxx', [
    'fields'  => ['feld1', 'feld2'],
    'orderby' => 'feld1',
    'limit'   => 20,
    'offset'  => 0,
]);

// Gefilterter Select
\api_post('/controller/getXxxFiltered', [
    'fields'    => ['feld1', 'feld2'],
    'filterfeld' => 'wert',
]);

// Einzelsatz
\api_post('/controller/getXxxById', [
    'kennziffer' => 42,
    'fields'     => ['feld1', 'feld2'],
]);

// Insert
\api_post('/controller/insertXxx', ['feld1' => 'wert1', 'feld2' => 'wert2']);

// Update
\api_post('/controller/updateXxx', ['kennziffer' => 42, 'feld1' => 'wert1']);

// Delete
\api_post('/controller/deleteXxx', ['kennziffer' => 42]);
```

### Sortierung

`orderby` akzeptiert nur den Feldnamen -- kein `DESC`, kein `ASC`:
```php
// RICHTIG
['orderby' => 'name2']

// FALSCH -- wird von RATIOserver abgelehnt
['orderby' => 'name2 DESC']
['orderby' => 'name2 desc']
```

### Pagination

RATIOserver unterstuetzt Pagination ueber InterBase `ROWS x TO y` Syntax.
PHP schickt `limit` und `offset`, RATIOserver rechnet intern um:
- Erster Datensatz der Seite: `offset + 1`
- Letzter Datensatz der Seite: `offset + limit`

**Pagination ist NUR in Kombination mit `orderby` erlaubt.**
Ohne Sortierung ist die Reihenfolge nicht garantiert -- Seiten liefern
inkonsistente oder doppelte Datensaetze.

```php
// RICHTIG -- mit orderby
\api_post('/controller/getXxx', [
    'fields'  => ['feld1', 'feld2'],
    'orderby' => 'feld1',
    'limit'   => 20,
    'offset'  => 0,    // Seite 1: Datensaetze 1-20
]);

\api_post('/controller/getXxx', [
    'fields'  => ['feld1', 'feld2'],
    'orderby' => 'feld1',
    'limit'   => 20,
    'offset'  => 20,   // Seite 2: Datensaetze 21-40
]);

// FALSCH -- kein orderby bei Pagination
\api_post('/controller/getXxx', [
    'fields' => ['feld1', 'feld2'],
    'limit'  => 20,
    'offset' => 0,
]);
```

Antwort mit Pagination:
```json
{
  "total": 255,
  "limit": 20,
  "offset": 0,
  "data": {
    "header": { "kennziffer": "ftinteger", "name2": "ftstring 30", ... },
    "data": [ { "kennziffer": 1, "name2": "..." }, ... ]
  }
}
```

**WICHTIG:** Bei Pagination sind die Datensaetze in `data.data` verschachtelt --
nicht direkt in `data`. Der Controller muss entsprechend zugreifen:

```php
// RICHTIG -- bei Pagination
'adressen' => $result['data']['data'] ?? [],
'total'    => $result['total'] ?? 0,

// FALSCH -- liefert den header-Block statt der Datensaetze
'adressen' => $result['data'] ?? [],
```

Antwort ohne Pagination:
```json
{ "data": [ ... ] }
```

#### Controller-Muster mit Pagination

Die aktuelle Seite kommt als GET-Parameter `page` (0-basiert):

```php
public function index(): void
{
    $limit  = 20;
    $page   = max(0, (int)($_GET['page'] ?? 0));
    $offset = $page * $limit;

    $result = \api_post('/adressen/getAdressen', [
        'fields'  => ['kennziffer', 'name2', 'name1', 'ort'],
        'orderby' => 'name2',
        'limit'   => $limit,
        'offset'  => $offset,
    ]);

    $this->render('adressen/index', [
        'adressen'      => $result['data']['data'] ?? [],  // Bei Pagination: data.data
        'total'         => $result['total'] ?? 0,
        'limit'         => $limit,
        'offset'        => $offset,
        'page'          => $page,
        'seiten_gesamt' => ceil(($result['total'] ?? 0) / $limit),
    ]);
}
```

#### Pagination-Komponente im View

**PFLICHT wenn Pagination verwendet wird:**
`ClaudeCodePatterns/pagination-component.php` IMMER 1:1 nach `views/components/pagination.php` kopieren -- niemals neu generieren, niemals weglassen.

Sie erwartet die Variablen: `$page`, `$seiten_gesamt`, `$base_url`.

```php
// Am Ende des Views einbinden:
include VIEW_PATH . '/components/pagination.php';
```

Die Komponente wird beim ersten Einsatz von Pagination angelegt.
Sie erzeugt Bootstrap-Pagination-Links mit APP_BASE:

```php
// views/components/pagination.php
<?php if ($seiten_gesamt > 1): ?>
<nav class="mt-4">
    <ul class="pagination">
        <li class="page-item <?= $page <= 0 ? 'disabled' : '' ?>">
            <a class="page-link"
               href="<?= APP_BASE . $base_url ?>?page=<?= $page - 1 ?>">
               &laquo;
            </a>
        </li>
        <?php for ($i = 0; $i < $seiten_gesamt; $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
            <a class="page-link"
               href="<?= APP_BASE . $base_url ?>?page=<?= $i ?>">
               <?= $i + 1 ?>
            </a>
        </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $seiten_gesamt - 1 ? 'disabled' : '' ?>">
            <a class="page-link"
               href="<?= APP_BASE . $base_url ?>?page=<?= $page + 1 ?>">
               &raquo;
            </a>
        </li>
    </ul>
</nav>
<?php endif; ?>
```

Einbindung im View mit `$base_url`:
```php
<?php
$base_url = '/adressen';
include VIEW_PATH . '/components/pagination.php';
?>
```

---

## /select -- Direkter SQL-Endpunkt

**WICHTIG: `/select` NUR verwenden wenn Entwickler es im Prompt explizit angibt.**
Kein `getXxx`, `getXxxById`, `getXxxFiltered` durch `/select` ersetzen -- niemals.

### Endpunkt

```php
\api_post('/select', [
    'sql'    => 'SELECT ... FROM ... WHERE ...',
    'params' => [ ... ],  // optional -- nur wenn Parameter vorhanden
]);
```

PHP und RATIOserver laufen im selben Apache -- `/select` ist nur vom localhost erreichbar,
fuer PHP aber problemlos nutzbar. Auth laeuft normal ueber JWT-Cookie wie bei allen anderen
Endpunkten.

### Parameter-Notation im Prompt

Harry gibt Parameter mit einer speziellen Notation an. Claude Code ersetzt sie automatisch
durch den passenden PHP-Ausdruck:

| Notation im Prompt | Quelle | PHP-Ausdruck |
|---|---|---|
| `:[tabellenname.feld]` | Ergebnis einer vorherigen Abfrage | `$tabellenname['data'][0]['feld'] ?? ''` |
| `:[GET.param]` | URL-Parameter | `$_GET['param'] ?? ''` |
| `:[POST.param]` | POST-Body | `$_POST['param'] ?? ''` |
| `:["wert"]` | Konstanter Wert | `'wert'` |

Konstante Werte immer in `"` -- egal ob String oder Zahl. RATIOserver konvertiert intern.

### Beispiel-Prompt (mehrere Parameter, alle Varianten)

```
Erstelle eine Seite "Auftragsdetail".

Abfrage 1 -- Kundendaten:
/select
SELECT kennziffer, name1, name2 FROM adressen WHERE kennziffer = :[GET.id]

Abfrage 2 -- Auftraege zum Kunden:
/select
SELECT auftrnr, datum, betrag FROM auftraege
WHERE kundenid = :[adressen.kennziffer]
AND land = :["DE"]
AND status = :[POST.status]
```

### Generierter PHP-Code

```php
// Abfrage 1 -- Parameter aus URL
$adressen = \api_post('/select', [
    'sql'    => 'SELECT kennziffer, name1, name2 FROM adressen WHERE kennziffer = :kennziffer',
    'params' => [
        'kennziffer' => $_GET['id'] ?? '',
    ],
]);

// Abfrage 2 -- Parameter aus vorheriger Abfrage, Konstante, POST
$auftraege = \api_post('/select', [
    'sql'    => 'SELECT auftrnr, datum, betrag FROM auftraege WHERE kundenid = :kundenid AND land = :land AND status = :status',
    'params' => [
        'kundenid' => $adressen['data'][0]['kennziffer'] ?? '',
        'land'     => 'DE',
        'status'   => $_POST['status'] ?? '',
    ],
]);
```

### Antwort-Struktur

Gleiche Struktur wie `getXxx` ohne Pagination:
```json
{ "data": [ { "feld1": "wert1", ... }, ... ] }
```

Zugriff im Controller:
```php
$rows = $result['data'] ?? [];
```

### Abfrage ohne Parameter

**`params` ist PFLICHT -- auch wenn der SQL keine Platzhalter enthaelt.**
Dann wird ein leeres Objekt gesendet:

```php
// RICHTIG -- leeres params mitgeben
\api_post('/select', [
    'sql'    => 'SELECT count(*) AS anzahl FROM adressen',
    'params' => new \stdClass(),   // wird zu {} statt []
]);

// FALSCH -- der Endpunkt antwortet mit
// {"status":"error","message":"Fehler beim Parsen des JSON: Wert 'params' nicht gefunden"}
\api_post('/select', [
    'sql' => 'SELECT count(*) AS anzahl FROM adressen',
]);
```

Achtung bei der Schreibweise: ein leeres PHP-Array wird von `json_encode()` zu
`[]` kodiert, nicht zu `{}`. Deshalb `new \stdClass()` -- oder
`json_encode(..., JSON_FORCE_OBJECT)`. Sobald mindestens ein Parameter
enthalten ist, genuegt ein normales assoziatives Array.

Live verifiziert am 2026-09-14 gegen den laufenden RATIOserver.

---

## Dataset-Verknuepfung

Notation, Regeln und PHP-Muster sind vollstaendig dokumentiert in:
```
ClaudeCodePatterns/dataset-verknuepfung.md
```

**Claude Code liest diese Datei IMMER wenn:**
- der Prompt Pfeil-Notation enthaelt (`-->`, `-->*`, `<--`)
- der Prompt Felder aus mehreren Tabellen kombiniert
- der Prompt Begriffe wie "zugehoerig", "je ... den ...", "mit ...", "dazugehoerig" enthaelt
- Daten aus mehr als einem API-Endpunkt benoetigt werden

**In allen diesen Faellen: Datei zuerst lesen -- dann implementieren. Niemals eigene Loesungen erfinden.**

---

## Feldlaengen und Eingabepruefung

Jedes Eingabefeld hat **zwei** Grenzen: `maxlength` im HTML (Bedienerfuehrung)
und eine serverseitige Pruefung im Controller (die verbindliche). `maxlength`
allein genuegt NIE -- es ist mit jedem HTTP-Client umgehbar.

### Zeichen oder Bytes? -- Zeichen.

Die Datenbank laeuft mit `charset NONE` (`RDB$CHARACTER_SET_ID = 0`), dort ist
`RDB$FIELD_LENGTH` gleich `RDB$CHARACTER_LENGTH`. FireDAC konvertiert beim
Schreiben von UTF-8 in die ANSI-Codepage und beim Lesen zurueck -- ein Umlaut
belegt in der Spalte also **ein** Byte.

Live verifiziert am 2026-08-27: 30-mal `ü` (= 60 UTF-8-Bytes) in
`ADRESSEN.name1` (30 Zeichen) wird vollstaendig gespeichert und unveraendert
zurueckgelesen.

**Konsequenz: `mb_strlen()` ist die richtige Funktion** -- sie zaehlt Zeichen,
genau wie die Spalte. `strlen()` waere hier falsch und wuerde Umlautnamen
grundlos ablehnen.

Einzige Ausnahme ist das Passwort: dort zaehlt `strlen()` (Bytes), weil die
Bcrypt-Grenze in Bytes gilt -- siehe unten.

### Feldlaengen laut Datenbank

Abgefragt ueber den `header`-Block (`"fields":"*"`) bzw. `RDB$RELATION_FIELDS`:

| Tabelle | Spalte | Laenge |
|---|---|---|
| ADRESSEN | anrede, titel | 20 |
| ADRESSEN | name1, name2, strasse, ort | 30 |
| ADRESSEN | plz | 15 |
| ADRESSEN | telefon1 | 25 |
| ADRESSEN | email | 60 |
| REGISTRIERUNG | username | 120 |
| REGISTRIERUNG | pwd2 | 255 |
| REGISTRIERUNG | typ | 30 |
| REGISTRIERUNG | email | 60 |
| USERS | loginname | 20 |
| USERS | passwort | 20 |
| PERSONALSTAMM | zeichen | 15 |
| PERSONALSTAMM | name1, name2 | 30 |
| EINSATZ | fahrer1, fahrzeug | 30 |
| EINSATZ | bezeichnung | 120 |
| EINSATZ | dienstnr | 10 |

### Abgleich Formularfeld -> Spalte

Alle Registrierungsgrenzen stehen in `RegistrierungController::FELDER`
(Abweichungen je Portal in `PORTALE[..]['felder']`) und werden von
`Core\Pruefung` geprueft:

| Feld | Grenze | Woher | Schluessel in FELDER |
|---|---|---|---|
| `name1`, `name2` | 30 | ADRESSEN bzw. PERSONALSTAMM | `max_zeichen` |
| `strasse`, `ort` | 30 | ADRESSEN | `max_zeichen` |
| `plz` | 15 | ADRESSEN | `max_zeichen` |
| `telefon1` | 25 | ADRESSEN | `max_zeichen` |
| `username` (Kunde) | 60 | ADRESSEN.email -- **nicht** REGISTRIERUNG.username (120), die E-Mail landet in beiden Feldern, die kleinere Grenze bindet | `max_zeichen`, `email` |
| `username` (Fahrer) | 15 | PERSONALSTAMM.zeichen | `max_zeichen`, `gross` (Abweichung in PORTALE) |
| `loginname` | 20 | USERS.loginname | `max_zeichen` -- geprueft in `pruefeMitarbeiter()` |
| `login_password` | 20 | USERS.passwort | `max_zeichen` -- geprueft in `pruefeMitarbeiter()` |
| `password`, `password_wdh` | 72 Bytes | Bcrypt | `min_bytes`, `max_bytes`, `gleich` |
| `kennziffer` | 10 Ziffern | ADRESSEN.kennziffer (ftinteger) | `ganzzahl`, `max_zeichen` |
| `anrede` | Whitelist | `ANREDEN` (max 7 Zeichen < 20) | `auswahl` |
| Einsatz-Filter | 61 / 30 / 120 | durchsuchte EINSATZ-Spalten | keine -- reine Anzeigefilter, kein Insert |

### Warum das Passwort bei 72 Bytes endet

`password_hash()` mit `PASSWORD_DEFAULT` (Bcrypt) verarbeitet nur die ersten
**72 Bytes** und ignoriert alles danach stillschweigend. Ohne Grenze koennte
sich jemand mit einem 200 Zeichen langen Passwort registrieren und sich
anschliessend mit den ersten 72 anmelden. Deshalb wird abgelehnt statt
abgeschnitten -- und mit `strlen()` geprueft, nicht `mb_strlen()`, weil die
Grenze in Bytes gilt (ein Umlaut zaehlt doppelt).

Die Zielspalte `REGISTRIERUNG.pwd2` (255) ist dabei unkritisch: dort landet nur
der 60 Zeichen lange Hash, nie das Passwort selbst.

### Regeln fuer Claude Code

- Jedes neue Eingabefeld bekommt `maxlength` **und** eine serverseitige Pruefung
- Die Grenze wird aus der Zielspalte abgeleitet -- nie geraten. Feldlaengen
  ermitteln: Endpunkt einmal mit `"fields":"*"` aufrufen, der `header`-Block
  nennt Typ und Laenge (`"name1":"ftstring 30"`)
- Schreibt ein Feld in MEHRERE Spalten (wie die E-Mail in `username` und
  `email`), gilt die **kleinste** Grenze
- Laengen in der Feld-Definition des Controllers, nie als Zahl im View --
  der View bekommt die Definitionen ueber `render()` und erzeugt die
  Attribute mit `Pruefung::htmlAttribute()`
- `mb_strlen()` fuer Textfelder, `strlen()` nur fuer Passwoerter

---

## Theming

Alle Farben der App sind als CSS-Variablen in `public/css/app.css` definiert (`:root`-Block).
Fuer einen neuen Kunden nur diese Variablen anpassen -- kein CSS in Views aendern.

Die Variablen sind bewusst nach Bootstrap-Farbmanagement benannt (`primary`,
kein Farbname wie "gold"), damit Name und tatsaechliche Farbe nie auseinanderlaufen --
egal welches Kundenthema gerade aktiv ist.

### Farb-Variablen

```css
:root {
    --primary-color:       #3A7CC4;   /* Hauptfarbe -- Header, Buttons, Akzente */
    --primary-color-dark:  #1F5A96;   /* Hover, Links, Labels, Icons auf weiss */
    --primary-color-light: #CFE4F7;   /* Tabellenkoepfe, Badges, dezente Flaechen */
    --on-primary:          #FFFFFF;   /* Text/Icons AUF farbigem Hintergrund (Buttons, Header) */
    --text-color:          #0D2438;   /* Text auf weissem/hellem Hintergrund */
    --border-color:        #D9E8F5;   /* Trennlinien, Tabellenrahmen */
    --surface-muted:       #F2F8FD;   /* Toolbar-Hintergrund, Tabellenzeilen-Hover */
}
```

**WICHTIG -- `--on-primary` vs. `--text-color` nicht verwechseln:**
- `--on-primary` ist der Text/Icon-Farbe *auf* `--primary-color`-Hintergrund (Buttons, Header, aktiver Pager) --
  bei einer dunklen/kraeftigen Primaerfarbe fast immer Weiss.
- `--text-color` ist die normale Text-/Ueberschriftenfarbe *auf weissem* Hintergrund -- unabhaengig vom Theme
  meist ein dunkler Ton.

Diese zwei Variablen NIE vertauschen -- sonst entstehen Buttons mit schlechtem Kontrast
(z.B. dunkler Text auf dunklem Button-Hintergrund).

### Was die Variablen steuern

| Variable | Verwendet in |
|---|---|
| `--primary-color` | Header-Hintergrund, Buttons (`.btn-app-primary`), aktiver Pager, Footer-Bordertop |
| `--primary-color-dark` | Links, Labels, Icons, Hover-Zustand auf weissem Hintergrund |
| `--primary-color-light` | Tabellenkoepfe, Badges, Toolbar-Hintergrund |
| `--on-primary` | Text/Icons AUF `--primary-color`-Flaechen (Header, Buttons) |
| `--text-color` | Normaler Text/Ueberschriften auf weissem Hintergrund |
| `--border-color` | Tabellenlinien, Trennlinien, Toolbar-Border |
| `--surface-muted` | Toolbar-Hintergrund, Tabellenzeilen-Hover |

### Zentrale Button-Klasse

Statt wiederholter Inline-Styles gibt es die Klasse `.btn-app-primary` in `app.css`
fuer alle primaeren Aktions-Buttons (Anmelden, Filtern, Speichern, ...):

```html
<!-- RICHTIG -->
<button type="submit" class="btn btn-sm fw-semibold btn-app-primary">Filtern</button>

<!-- FALSCH -- Inline-Style statt Klasse -->
<button type="submit" class="btn btn-sm fw-semibold"
        style="background:var(--primary-color);color:var(--on-primary);border:none;">Filtern</button>
```

`.btn-app-primary` setzt automatisch `background: var(--primary-color)`,
`color: var(--on-primary)` und den Hover-Zustand -- ein neues Farbschema
aendert damit alle primaeren Buttons zentral, ohne dass in Views etwas
angepasst werden muss.

### Neues Farbschema definieren

Nur den `:root` Block in `public/css/app.css` anpassen -- sonst nichts.
Beispiel fuer ein goldenes Theme:

```css
:root {
    --primary-color:       #C8960C;   /* Gold */
    --primary-color-dark:  #A07808;   /* Dunkelgold */
    --primary-color-light: #F5C842;   /* Hellgold */
    --on-primary:          #2C1C00;   /* Dunkler Text -- noetig weil Hellgold zu wenig Kontrast zu Weiss hat */
    --text-color:          #2C1C00;
    --border-color:        #F0E8CC;
    --surface-muted:       #FFFDF0;
}
```

Bei einer hellen/kraeftig-warmen Primaerfarbe (Gold, Gelb) `--on-primary` auf einen
dunklen Ton setzen -- Weiss auf Gold hat zu wenig Kontrast. Bei einer dunklen/kalten
Primaerfarbe (Blau, Gruen, Violett) bleibt `--on-primary` Weiss.

### Regeln fuer Claude Code

- Farben in Views IMMER ueber CSS-Variablen -- nie hardcodierte Hex-Werte
- `style="background:#3A7CC4"` ist FALSCH -- `style="background:var(--primary-color)"` ist RICHTIG
- Primaere Aktions-Buttons IMMER mit der Klasse `.btn-app-primary` -- kein
  `style="background:var(--primary-color);color:...;border:none;"` wiederholen
- Text auf `--primary-color`-Hintergrund IMMER `var(--on-primary)` -- niemals `var(--text-color)`
- Neue Farben die nicht in den Variablen sind gehoeren nicht in Views
- Bootstrap-Farben (`btn-primary`, `text-success` usw.) duerfen weiterhin verwendet werden

---

## Layout

### Dateien

| Datei | Herkunft |
|---|---|
| `public/css/app.css` | 1:1 aus `ClaudeCodePatterns/app.css` -- nie neu generieren |
| `views/layout.php` | Basis `ClaudeCodePatterns/layout.php` **plus Portal-Anpassung** (siehe unten) |
| `views/components/header.php` | projektspezifisch -- kein Pattern-Pendant |

Fuer Details siehe `ClaudeCodePatterns/layout-pattern.md`.

**Abweichung vom Pattern -- bewusst und nicht zurueckzubauen:**
`views/layout.php` weicht in fuenf Punkten von `ClaudeCodePatterns/layout.php` ab,
weil das Pattern die Portalstruktur und die Fehlerbehandlung nicht kennt:

1. Der Header-Block ist durch `include VIEW_PATH . '/components/header.php'` ersetzt.
2. Das Login-Modal ist ohne Bedingung eingebunden -- jedes Portal hat ein Login,
   die Komponente wertet `$portal` selbst aus.
3. Der Benutzername im Footer erscheint in jedem Portal, sobald angemeldet.
4. Direkt nach dem Header: `include VIEW_PATH . '/components/systemfehler.php'`
   (reservierter Systemfehler-Bereich).
5. Nach dem Login-Modal: `include VIEW_PATH . '/components/fehler-dialog.php'`
   -- muss das letzte Modal sein (siehe Abschnitt Fehlerbehandlung).

Ebenso weicht `core/Router.php` deutlich ab -- das Pattern kennt keine Portale:

1. `add()` kennt die Option `portal`, Default aus dem Routen-Praefix.
2. `dispatch()` prueft die Portalgrenze (`pruefePortal()`): Praefix der Route
   gegen `role.typ` aus dem Token.
3. Der Auth-Redirect zeigt auf die Startseite des Portals der Route mit
   `?login=1` -- nicht auf `/login`.
4. Routen ohne Portalangabe werden abgewiesen statt durchgelassen.

Dazu kommt `core/Auth.php` -- eine Datei, die es im Pattern nicht gibt.

Beim Uebernehmen einer neuen Pattern-Version diese fuenf Layout-Punkte und alle
vier Router-Punkte erneut einarbeiten -- nicht das Pattern blind ueberkopieren.

### Layout-Variablen

`core/View.php` reicht diese Werte ans Layout durch:

| Variable | Pflicht | Bedeutung |
|---|---|---|
| `page_title` | ja | Seitentitel im `<title>` |
| `content` | ja | Haupt-Inhalt (vom View gerendert) |
| `portal` | faktisch ja | `'kunde'` (Default) oder `'mitarbeiter'` -- steuert Header und Login-Modal |
| `page_header` | nein | HTML des Seitentitel-Blocks |
| `toolbar` | nein | HTML der Toolbar |
| `pager` | nein | HTML des Pagers |

### Zonen

Das Layout hat 5 Zonen -- Header und Footer immer, Toolbar und Pager optional:

```
┌─────────────────────────────────┐  immer fest -- nie wegscrollbar
│  HEADER                         │
├─────────────────────────────────┤  optional -- weglassen wenn keine Filter
│  TOOLBAR                        │
├─────────────────────────────────┤
│  MAIN CONTENT                   │  einziger scrollbarer Bereich
├─────────────────────────────────┤  optional -- weglassen wenn kein Paging
│  PAGER                          │
├─────────────────────────────────┤  immer fest -- nie wegscrollbar
│  FOOTER                         │
└─────────────────────────────────┘
```

Nur `.app-main` scrollt -- horizontal UND vertikal.
Header, Toolbar, Pager und Footer sind auf allen Devices immer sichtbar.

### CSS-Regel

- `app.css` -- NUR fuer das Layout-Geruest (die 5 Zonen oben)
- Bootstrap 5 -- fuer alle Komponenten (Tabellen, Buttons, Badges, Formulare, Grid)
- Kein eigenes CSS in Views -- nur Bootstrap-Klassen und `app.css`-Klassen

### Konstanten

```php
define('APP_NAME', 'RATIOonline');   // App-Name in Header und Footer -- anpassen pro Kunde
```

### WICHTIGE REGEL fuer Views

**Views enthalten NUR reinen Content-HTML -- kein Layout-Include, kein DOCTYPE, kein html/head/body.**
`layout.php` wird ausschliesslich von `BaseController::render()` eingebunden -- nie in einem View.

```php
// RICHTIG -- View enthaelt nur Content
<div class="container">
    <h1>Adressen</h1>
    ...
</div>

// FALSCH -- View bindet layout.php selbst ein
<?php include VIEW_PATH . '/layout.php'; ?>
```

Es gibt keine eigene Login-Seite und kein `renderPage()`.
Das Login laeuft ueber ein Bootstrap-Modal -- verfuegbar auf jeder Seite des
Mitarbeiterportals, im Kundenportal gar nicht.

Toolbar und Pager sind optionale Layout-Zonen und werden als fertiges HTML
an `render()` uebergeben:

```php
// Ohne Toolbar und Pager -- Startseite, Detailansicht
$this->render('home/index', [
    'page_title' => 'Kundenportal',
    'portal'     => 'kunde',
]);

// Mit Toolbar (Filter + Aktionen) und Pager
$this->render('adressen/index', [
    'page_title' => 'Adressen',
    'portal'     => 'mitarbeiter',
    'toolbar'    => '...HTML...',
    'pager'      => '...HTML...',
]);
```

### Toolbar-Struktur

Bootstrap Grid -- Filter links, Aktions-Button rechts:

```php
$toolbar = '
<div class="row g-2 align-items-end">
    <div class="col-12 col-sm-6 col-lg-auto">
        <label class="filter-label">Von</label>
        <input type="date" class="form-control form-control-sm" name="von">
    </div>
    <div class="col-12 col-sm-6 col-lg-auto">
        <label class="filter-label">Bis</label>
        <input type="date" class="form-control form-control-sm" name="bis">
    </div>
    <div class="col-12 col-sm-6 col-lg-auto ms-lg-auto">
        <button class="btn btn-sm fw-semibold w-100 btn-app-primary">
            <i class="bi bi-plus-lg me-1"></i>Neuer Eintrag
        </button>
    </div>
</div>
';
```

Breakpoints fuer Controls:
- `col-12`      -- unter 576px: 1 Control pro Zeile
- `col-sm-6`    -- ab 576px:    2 Controls nebeneinander
- `col-lg-auto` -- ab 992px:    alle nebeneinander

### Seitentypen

| Seitentyp | toolbar | pager |
|---|---|---|
| Startseite, Detailansicht | leer | leer |
| Liste ohne Pagination | gesetzt | leer |
| Liste mit Pagination | gesetzt | gesetzt |

---

## View-Komponenten

**Grundregel: Kein Code wird kopiert. Was mehr als einmal benoetigt wird, wird zur Komponente.**

Wiederverwendbare Bausteine liegen in `views/components/` und werden per `include` eingebunden:

```php
include VIEW_PATH . '/components/mein-baustein.php';
```

Typische Beispiele fuer Komponenten (nicht abschliessend):
- Navigation / Header
- Footer
- Fehler- und Erfolgsmeldungen
- Pagination
- Tabellen-Layouts
- Formular-Elemente
- Statusanzeigen / Badges

Claude Code legt automatisch eine neue Komponente in `views/components/` an,
sobald ein Baustein in mehr als einem View benoetigt wird -- ohne Rueckfrage.

---

## Routing

`index.php` liegt direkt im Projektstamm.
`public/` enthaelt ausschliesslich statische Assets (CSS, JS, Images).

**Beim ersten Anlegen:** `core/Router.php` wird 1:1 aus
`ClaudeCodePatterns/router-implementation.php` kopiert -- nie neu generiert.
Im laufenden Projekt weicht die Datei allerdings deutlich vom Pattern ab, weil
das Pattern die Portalstruktur nicht kennt: Route-Option `portal` mit Default aus
dem Praefix, Portalgrenze gegen `role.typ`, Auth-Redirect zur Startseite des
Portals der Route, Abweisung von Routen ohne Praefix. Siehe Abschnitte
**Routenkonventionen und Portalgrenzen** sowie **Router Auth-Check** -- beim
Uebernehmen einer neuen Pattern-Version erneut einarbeiten.

Der Router laedt beide Routen-Dateien -- Standard zuerst, dann Custom:
```php
require 'config/routes.php';
require 'config/routes.custom.php';
```

Custom-Routen koennen Standard-Routen ueberschreiben (spaeteres Laden gewinnt).

### Router-Signatur

```php
$router->add(string $path, string $controller, string $action, array $options = []): void
```

### Authentifizierung und Portal pro Route

`$options['auth']` -- Default: `true`.
`$options['portal']` -- Default: aus dem Routen-Praefix abgeleitet.

```php
// Kundenportal -- oeffentlicher Default-Einstieg
$router->add('/', 'Standard\Controllers\HomeController', 'index', ['auth' => false]);
$router->add('/kunde/registrieren', 'Standard\Controllers\RegistrierungController', 'index', ['auth' => false]);

// Mitarbeiterportal -- Einstieg per URL, kein auth-Check, Login laeuft per Modal
$router->add('/mitarbeiter', 'Standard\Controllers\HomeController', 'mitarbeiter', ['auth' => false]);

// Fahrerportal -- ebenso; die Identitaetspruefung macht der Registrierungs-
// Endpunkt selbst (PERSONALSTAMM), nicht der Router
$router->add('/fahrer', 'Standard\Controllers\HomeController', 'fahrer', ['auth' => false]);
$router->add('/fahrer/registrieren', 'Standard\Controllers\RegistrierungController', 'fahrer', ['auth' => false]);

// Portaluebergreifend -- nur Login und Logout
$router->add('/login',  'Standard\Controllers\AuthController', 'login',  ['auth' => false, 'portal' => Router::ALLE]);
$router->add('/logout', 'Standard\Controllers\AuthController', 'logout', ['auth' => false, 'portal' => Router::ALLE]);

// Geschuetzte Seiten (Default auth: true) -- Portal kommt aus dem Praefix
$router->add('/mitarbeiter/adressen', 'Standard\Controllers\AdressenController', 'index');

// FALSCH -- kein Praefix. Der Router weist die Route ab (DEBUG: 500 mit
// Klartext, sonst 404), statt sie ungeschuetzt durchzulassen.
$router->add('/adressen', 'Standard\Controllers\AdressenController', 'index');
```

`['auth' => false]` nur setzen wenn der Prompt explizit "ohne Login", "Gastseite"
oder "oeffentlich" erwaehnt.

`config/routes.php` ist nach Portalen gruppiert -- neue Routen in den passenden
Block einsortieren, nicht einfach unten anhaengen. Fuer `Router::ALLE` braucht
die Routen-Datei `use Core\Router;` am Dateianfang.

---

## Update-Strategie

| Verzeichnis | Bei Update |
|---|---|
| `core/` | wird ersetzt |
| `standard/` | wird ersetzt |
| `views/components/` | wird ersetzt |
| `views/layout.php` | wird ersetzt -- Portal-Anpassungen danach erneut einarbeiten (siehe Abschnitt Layout) |
| `custom/` | bleibt unangetastet |
| `config/routes.custom.php` | bleibt unangetastet |
| `ClaudeCodePatterns/` | bleibt unangetastet |

---

## Bekannte Probleme

### api_post() in Controller nicht gefunden

Zwei haeufige Fehlerquellen:

**1. core/Api.php nicht eingebunden**
`core/Api.php` muss in `index.php` explizit eingebunden sein:
```php
require_once 'core/Api.php';
```
Ohne dieses `require_once` ist `api_post()` nirgends bekannt -- auch nicht nach Autoloader.

**2. Namespace-Problem in Controllern**
Controller liegen in einem Namespace (`Standard\Controllers`, `Custom\Controllers`).
PHP sucht `api_post()` dann zuerst im aktuellen Namespace -- und findet es nicht.
Loesung: fuehrendes `\` erzwingt den globalen Namespace:

```php
// RICHTIG -- globaler Namespace
$result = \api_post('/adressen/getAdressen', ['fields' => ['kennziffer', 'name2']]);

// FALSCH -- PHP sucht Standard\Controllers\api_post() -- existiert nicht
$result = api_post('/adressen/getAdressen', ['fields' => ['kennziffer', 'name2']]);
```

**Claude Code verwendet in Controllern IMMER `\api_post()` mit fuehrendem Backslash.**
Token kommt aus `$_COOKIE['jwt_token']` -- niemals aus `$_SESSION`.

---

### Redirects landen auf falschem Pfad
Die App kann unter einem beliebigen Unterverzeichnis laufen -- der Name ist
nicht festgelegt (/app, /myapp, /kundenname, ...).
Rohe `header('Location: /irgendwas')`-Aufrufe ignorieren dieses Unterverzeichnis.

**Loesung:** Immer `$this->redirect('/')` oder `$this->redirect('/irgendwas')` verwenden.
`BaseController::redirect()` haengt `APP_BASE` automatisch voran:
```php
protected function redirect(string $path): void
{
    header('Location: ' . APP_BASE . $path);
    exit;
}
```

Form-Actions und alle href-Links im View ebenfalls mit APP_BASE:
```html
<form method="POST" action="<?= APP_BASE ?>/login">
<a href="<?= APP_BASE ?>/logout">Abmelden</a>
<a href="<?= APP_BASE ?>/">Start</a>
```

**Claude Code prueft bei JEDEM generierten View und Controller:**
- Kein hardcodierter Pfad wie `/app/...` oder `/myapp/...`
- Alle Links und Redirects verwenden `APP_BASE`

---

### .htaccess wird nicht angelegt
Claude Code legt Dateien mit fuehrendem Punkt gelegentlich nicht an.
Falls `.htaccess` fehlt, explizit anfordern:
```
Bitte lege die Datei .htaccess im Projektstamm an
```

Inhalt der `.htaccess`:
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

---

## Vokabular im Prompt

| Begriff im Prompt | Bedeutung |
|---|---|
| "Seite" | Neue HTML-View ohne Eingabefelder |
| "Seite mit Tabelle" | View mit Bootstrap-Tabelle / Liste |
| "Seite mit Tabelle und Pagination" | View mit Tabelle + Pagination-Komponente |
| "Formular" oder "Eingabemaske" | View mit `<form>`-Elementen |
| "Komponente" | Wiederverwendbarer Baustein in `views/components/` |
| "Kundenportal" | Oeffentlicher Bereich unter `/` -- `'portal' => 'kunde'`, kein Login |
| "Mitarbeiterportal" | Interner Bereich unter `/mitarbeiter` -- `'portal' => 'mitarbeiter'`, Login per Modal |

Kein `<form>` anlegen ausser der Prompt enthaelt explizit "Formular" oder "Eingabemaske".
Keine Pagination anlegen ausser der Prompt enthaelt explizit "Pagination" oder "Blaettern".

---

## Modulzuordnung

Neue Module immer in `standard/` anlegen, ausser der Prompt enthaelt explizit das Wort "custom".

Beispiele:
- "Erstelle einen AdressenController" -> `standard/`
- "Erstelle einen custom AdressenController" -> `custom/`

---

## Coding-Konventionen

- Views verwenden Bootstrap 5 (CDN) fuer Komponenten -- Layout-CSS kommt aus app.css
- Layout IMMER aus `ClaudeCodePatterns/layout.php` kopieren -- nie neu generieren
- `app.css` IMMER aus `ClaudeCodePatterns/app.css` kopieren -- nie neu generieren
- `core/Api.php` IMMER aus `ClaudeCodePatterns/Api.php` kopieren -- nie neu
  generieren. **Abweichung vom Pattern, bewusst und nicht zurueckzubauen:**
  `api_post()` behandelt Transportfehler (siehe Abschnitt **HTTP-Zugriff aus
  PHP**). Beim Uebernehmen einer neuen Pattern-Version erneut einarbeiten
- `views/components/debug.php` IMMER aus `ClaudeCodePatterns/debug.php` kopieren -- nie neu generieren
- `views/components/pagination.php` bei Pagination IMMER aus `ClaudeCodePatterns/pagination-component.php` kopieren -- niemals neu generieren
- `views/layout.php` muss folgende Zeile enthalten -- direkt nach `<?= $content ?? '' ?>`:
  `<?php include VIEW_PATH . '/components/debug.php'; ?>`
- Tabellen IMMER mit Klasse `app-table` -- kein Bootstrap `table-striped`, `table-bordered`, `table-hover`
- Tabellen OHNE `<div class="table-responsive">` Wrapper -- `.app-main` scrollt bereits horizontal
- Tabellen OHNE jeden anderen Wrapper-Div -- direkt in `.app-main`
- Aktions-Buttons (Bearbeiten, Loeschen) IMMER als erste Spalte -- nie rechts oder weggelassen

```html
<!-- FALSCH -->
<div class="table-responsive">
  <table class="table table-hover table-bordered">

<!-- RICHTIG -->
<table class="app-table" style="min-width:700px;">
    <thead>
        <tr>
            <th>Aktionen</th>
            <th>Nr.</th>
            <th>Name</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="act">
                <button class="btn btn-sm btn-outline-secondary" style="padding:.2rem .4rem;margin-right:.2rem;">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" style="padding:.2rem .4rem;">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
            <td class="dim">10001</td>
            <td>Mustermann</td>
        </tr>
    </tbody>
</table>
```

Der goldene Header-Hintergrund kommt automatisch aus `app.css` -- kein style auf `thead` noetig.
- PHP-Kommentare ohne Umlaute (keine ae/oe/ue/ss in `//` und `/* */` Kommentaren)
- HTML-Output und sichtbare Texte in Views IMMER mit korrekten Umlauten oder HTML-Entities:
  `ä` = `&auml;` / direkt `ä`, `ö` = `&ouml;` / direkt `ö`, `ü` = `&uuml;` / direkt `ü`
- Niemals Umlaute in HTML-Ausgaben durch ae/oe/ue ersetzen
- Wiederverwendbare Bausteine immer als Komponente -- nie kopieren
- Custom-Controller erben von `core/BaseController.php`
- Kein hardcodierter Verzeichnisname -- immer APP_BASE verwenden
- Kein `required` Attribut auf dem Passwort-Feld im Login-Modal -- Validierung erfolgt serverseitig durch RATIOserver
- Jedes Eingabefeld hat `maxlength` UND eine serverseitige Laengenpruefung --
  die Grenze stammt aus der Zielspalte (siehe Abschnitt Feldlaengen)
- Textlaengen mit `mb_strlen()` pruefen (die DB zaehlt Zeichen, nicht Bytes) --
  `strlen()` nur beim Passwort, wo die Bcrypt-Grenze in Bytes gilt
- Token IMMER aus `$_COOKIE['jwt_token']` lesen -- niemals aus `$_SESSION`
- `\api_post()` in Controllern immer mit fuehrendem Backslash
- Jeder `render()`-Aufruf gibt sein `'portal'` mit -- `'kunde'`, `'mitarbeiter'`
  oder `'fahrer'` (Schluessel aus `core/Portal.php`)
- Portalpfade, -labels und Registrierungslinks IMMER ueber `Core\Portal` --
  nie ein Ternaeroperator wie `$portal === 'mitarbeiter' ? '/mitarbeiter' : '/'`
  in Views oder Controllern. Mit drei Portalen ist so ein Ausdruck bereits falsch.
- Navigation nur in `views/components/header.php` aendern -- nie im Layout, nie in einem View
- Header verlinkt keine Features -- neue Module bekommen eine Kachel auf der Portal-Startseite
- Kein Link, Button oder Hinweis der vom Kundenportal in ein internes Portal fuehrt
- Jede neue Route liegt unter dem Praefix ihres Portals (`/mitarbeiter/...`,
  `/kunde/...`, `/fahrer/...`) -- eine Route ohne Praefix wird abgewiesen.
  `['portal' => Router::ALLE]` nur fuer `/login` und `/logout`
- Modul-URLs aus `Portal::praefix()` bauen -- kein Praefix in Views oder
  Controllern ausschreiben
- Router-Auth-Redirect (geschuetzte Routen) immer auf die Startseite des Portals
  DER ROUTE mit `?login=1` (`Portal::start($route['portal'])`) -- niemals fest
  `/mitarbeiter`, niemals `/login` (existiert nur als POST-Route)
- Zugriffsentscheidungen ausschliesslich ueber `Auth::portal()` /
  `Portal::ausTyp()` -- niemals `Portal::name()` auf `role.typ` anwenden und
  niemals `role.typ` selbst aus dem Token parsen
- Login-/Logout-Redirects richten sich nach dem Feld `portal`, abgebildet ueber
  `Portal::name()` -- unbekannte Werte landen im Kundenportal. Nach
  erfolgreichem Login gewinnt das Portal des Tokens
  (`Auth::portalAusToken()`)
- Jeder Logout-Link enthaelt `?portal=..` mit dem eigenen Portalnamen
- Keine JavaScript-Standard-Dialoge (`alert()`, `confirm()`, `prompt()`) --
  immer als wiederverwendbare Bootstrap-Modal-Komponente in `views/components/`
