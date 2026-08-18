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
│   ├── Router.php                  <- URL-Matching + Dispatch
│   ├── Api.php                     <- api_post() + Token-Handling
│   ├── BaseController.php          <- Basis fuer alle Controller
│   └── View.php                    <- render() / layout()
│
├── standard/                       <- Standardmodule (gleich fuer alle Kunden)
│   ├── Controllers/
│   │   └── BeispielController.php
│   └── Views/
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
│   ├── layout.php                  <- Haupt-Layout (aus ClaudeCodePatterns kopiert)
│   └── components/
│       ├── flash.php               <- Fehler- und Erfolgsmeldungen
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

## Auth / Token

### Cookie-Strategie

Nach dem Login werden ZWEI Cookies gesetzt:

| Cookie | Inhalt | httponly | Zweck |
|---|---|---|---|
| `jwt_token` | JWT-Token | `true` | API-Authentifizierung, sicher gegen XSS |
| `jwt_user` | Benutzername | `false` | Anzeige im Header (kein sensitiver Inhalt) |

```php
// Login -- beide Cookies setzen
setcookie('jwt_token', $token, [
    'expires'  => time() + TOKEN_LIFETIME,
    'path'     => '/',
    'samesite' => 'Strict',
    'secure'   => isset($_SERVER['HTTPS']),
    'httponly' => true,
]);
setcookie('jwt_user', $username, [
    'expires'  => time() + TOKEN_LIFETIME,
    'path'     => '/',
    'samesite' => 'Strict',
    'secure'   => isset($_SERVER['HTTPS']),
    'httponly' => false,
]);

// Token lesen -- in api_post() und Router
$_COOKIE['jwt_token'] ?? ''

// Benutzername lesen -- in Header-Komponente
$_COOKIE['jwt_user'] ?? ''

// Logout -- beide Cookies loeschen
setcookie('jwt_token', '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Strict', 'secure' => isset($_SERVER['HTTPS']), 'httponly' => true]);
setcookie('jwt_user',  '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Strict', 'secure' => isset($_SERVER['HTTPS']), 'httponly' => false]);
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
Das Modal ist im `layout.php` eingebunden -- damit auf jeder Seite verfuegbar.
Der "Anmelden"-Link im Header oeffnet das Modal per `data-bs-toggle="modal"`.

Es gibt keine GET-Route `/login` -- nur POST `/login` fuer den Formular-Submit.
Bei Fehler: Flash-Message setzen und zurueck auf `/` (Modal oeffnet sich erneut via `?login=1`).
Bei Erfolg: redirect auf `/`.

Nach erfolgreichem Login:
1. Token aus API-Antwort lesen
2. Benutzernamen direkt aus `$_POST['user']` -- kein `verifytoken` noetig
3. Beide Cookies setzen (`jwt_token` + `jwt_user`)
4. Auf Startseite weiterleiten

```php
public function login(): void
{
    // Nur POST erlaubt -- kein GET
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $this->redirect('/');
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
        $this->redirect('/?login=1');  // ?login=1 oeffnet das Modal automatisch per JS
        return;
    }

    $token    = $response['token'];
    $username = $_POST['user'] ?? '';

    // Beide Cookies setzen
    setcookie('jwt_token', $token, [
        'expires'  => time() + TOKEN_LIFETIME,
        'path'     => '/',
        'samesite' => 'Strict',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
    ]);
    setcookie('jwt_user', $username, [
        'expires'  => time() + TOKEN_LIFETIME,
        'path'     => '/',
        'samesite' => 'Strict',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => false,
    ]);

    $this->redirect('/');
}

public function logout(): void
{
    // Beide Cookies loeschen
    setcookie('jwt_token', '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Strict', 'secure' => isset($_SERVER['HTTPS']), 'httponly' => true]);
    setcookie('jwt_user',  '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Strict', 'secure' => isset($_SERVER['HTTPS']), 'httponly' => false]);
    $this->redirect('/');
}
```

### Header-Komponente

Die Header-Komponente prueft `$_COOKIE['jwt_token']` -- NIEMALS `$_SESSION`.

Der Benutzerbereich im Header ist ein **Bootstrap Dropdown-Menue**:

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
- Benutzerbereich IMMER als Bootstrap Dropdown -- kein einfacher Link
- `$_COOKIE['jwt_token']` fuer Login-Pruefung
- `$_COOKIE['jwt_user']` fuer Anzeigename
- Niemals `$_SESSION` verwenden

### Router Auth-Check

```php
// RICHTIG -- nicht eingeloggte User landen auf der Startseite (mit Anmelden-Button)
if ($route['auth'] && empty($_COOKIE['jwt_token'])) {
    header('Location: ' . APP_BASE . '/');
    exit;
}

// FALSCH -- Session wird nicht verwendet
if ($route['auth'] && empty($_SESSION['jwt_token'])) { ... }
```

### Token-Validierung

```
POST /verifytoken
```

Wird nach dem Login aufgerufen um den Benutzernamen zu ermitteln.
Der Token wird im Authorization-Header mitgeschickt.

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
setcookie('jwt_token', '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Strict', 'secure' => isset($_SERVER['HTTPS']), 'httponly' => true]);
setcookie('jwt_user',  '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Strict', 'secure' => isset($_SERVER['HTTPS']), 'httponly' => false]);
$this->redirect('/');
```

### Was Claude Code beim Befehl "Erstelle einen Login" anlegen soll

- Route `/login` in `config/routes.php` -- nur POST, `['auth' => false]`
- Route `/logout` in `config/routes.php` mit `['auth' => false]`
- `standard/Controllers/AuthController.php` mit `login()` und `logout()` gemaess Muster oben
- `views/components/login-modal.php` -- Bootstrap-Modal mit Login-Formular
  - `action="<?= APP_BASE ?>/login"` method POST
  - Felder: `user`, `password`
  - Kein `required` auf dem Passwort-Feld
  - Flash-Fehlermeldung im Modal anzeigen
  - JS-Snippet: Modal automatisch oeffnen wenn URL-Parameter `?login=1` gesetzt
- `views/layout.php` -- Login-Modal einbinden (direkt vor `</body>`):
  `<?php include VIEW_PATH . '/components/login-modal.php'; ?>`
- Header-Komponente: "Anmelden"-Link oeffnet Modal per `data-bs-toggle="modal" data-bs-target="#loginModal"`
- Keine eigene View `auth/login.php` -- das Modal ersetzt sie vollstaendig

---

## Konstanten in index.php

```php
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
define('BASE_URL', $scheme . '://' . $_SERVER['HTTP_HOST'] . '/ibapi');
define('APP_BASE',  rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'));
define('DEBUG', true);  // Entwicklung: true -- Produktion: false
```

### BASE_URL
Volle URL fuer curl -- kein relativer Pfad, da curl absolute URLs benoetigt.
**IMMER dynamisch ermitteln -- niemals hardcodieren.**
`$_SERVER['HTTP_HOST']` liefert automatisch den richtigen Host und Port.
`$_SERVER['HTTPS']` erkennt ob HTTP oder HTTPS verwendet wird.

| Umgebung | BASE_URL |
|---|---|
| Lokal HTTP Port 80 | `http://localhost/ibapi` |
| Lokal HTTP Port 8080 | `http://localhost:8080/ibapi` |
| Produktiv HTTPS | `https://meinserver.de/ibapi` |

**WICHTIG fuer Claude Code:**
- Niemals `http://localhost/ibapi` hardcodieren
- Niemals `https://` oder `http://` hardcodieren
- Immer die Konstante `BASE_URL` verwenden

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
Der Token ist beim Start von index.php noch nicht verfuegbar -- die Session
wird erst spaeter gestartet. Token immer direkt aus der Session lesen:
```php
// RICHTIG -- direkt in Api.php aus Session lesen
$_SESSION['jwt_token'] ?? ''

// FALSCH -- Session ist beim define() noch nicht gestartet
define('JWT_TOKEN', $_SESSION['jwt_token'] ?? '');
```

---

## API-Basisurl

```
BASE_URL = http://<HTTP_HOST>/ibapi
```

Kein externer Hostname, kein abweichender Port -- PHP und RATIOserver laufen im selben Apache.
`BASE_URL` wird dynamisch aus `$_SERVER['HTTP_HOST']` ermittelt -- siehe Konstanten in index.php.
`BASE_URL` muss eine vollstaendige URL sein, da curl relative Pfade nicht akzeptiert.

---

## HTTP-Zugriff aus PHP

Wichtig: RATIOserver verwendet fuer ALLE Operationen HTTP-POST -- auch fuer lesende
Zugriffe wie Listen oder Einzelsaetze. Es gibt kein GET, PUT oder DELETE.
Der Unterschied zwischen Lesen und Schreiben ergibt sich ausschliesslich aus dem
Endpunkt-Namen (getXxx vs. insertXxx) und dem JSON-Body.

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

Wenn der SQL keine Platzhalter enthaelt, `params` weglassen:
```php
\api_post('/select', [
    'sql' => 'SELECT count(*) AS anzahl FROM adressen',
]);
```

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

Das Layout besteht aus zwei Dateien die 1:1 aus `ClaudeCodePatterns/` kopiert werden -- nie neu generieren:

| Quelle | Ziel |
|---|---|
| `ClaudeCodePatterns/layout.php` | `views/layout.php` |
| `ClaudeCodePatterns/app.css` | `public/css/app.css` |

Fuer Details siehe `ClaudeCodePatterns/layout-pattern.md`.

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
Das Login laeuft ueber ein Bootstrap-Modal das auf jeder Seite verfuegbar ist.

// Mit Toolbar (Filter + Aktionen):
$toolbar = '...HTML...';

// Mit Pager:
$pager = '...HTML...';
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

### Authentifizierung pro Route

`$options['auth']` -- Default: `true`.

```php
// Gastseiten
$router->add('/login',  'Standard\Controllers\AuthController', 'login',  ['auth' => false]);
$router->add('/logout', 'Standard\Controllers\AuthController', 'logout', ['auth' => false]);

// Oeffentliche Startseite -- kein auth-Check, Login laeuft per Modal
$router->add('/', 'Standard\Controllers\HomeController', 'index', ['auth' => false]);

// Geschuetzte Seiten (Default)
$router->add('/adressen', 'Standard\Controllers\AdressenController', 'index');
```

`['auth' => false]` nur setzen wenn der Prompt explizit "ohne Login", "Gastseite"
oder "oeffentlich" erwaehnt.

---

## Update-Strategie

| Verzeichnis | Bei Update |
|---|---|
| `core/` | wird ersetzt |
| `standard/` | wird ersetzt |
| `views/components/` | wird ersetzt |
| `views/layout.php` | wird ersetzt |
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
- `core/Api.php` IMMER aus `ClaudeCodePatterns/Api.php` kopieren -- nie neu generieren
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
- Token IMMER aus `$_COOKIE['jwt_token']` lesen -- niemals aus `$_SESSION`
- `\api_post()` in Controllern immer mit fuehrendem Backslash
- Keine JavaScript-Standard-Dialoge (`alert()`, `confirm()`, `prompt()`) --
  immer als wiederverwendbare Bootstrap-Modal-Komponente in `views/components/`
