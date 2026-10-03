<?php
// ============================================================================
// Front Controller -- einziger Einstiegspunkt der Anwendung
// ============================================================================

// ============================================================================
// 1. Session initialisieren (VOR Token-Zugriff!)
// ============================================================================

session_start();

// ============================================================================
// 2. Konstanten definieren
// ============================================================================

define('ROOT_PATH', __DIR__);
define('VIEW_PATH', ROOT_PATH . '/views');

// App-Name in Header und Footer -- pro Kunde anpassen
define('APP_NAME', 'RATIOonline');

// Entwicklung: true -- Produktion: false
define('DEBUG', true);

// Rate-Limiting der Registrierung. Zum Testen auf false setzen -- dann zaehlt
// kein Versuch und keine Grenze greift.
// Sicherung: false wirkt NUR zusammen mit DEBUG = true. Ein vergessenes false
// kann die Produktion also nicht schwaechen, solange DEBUG dort false ist.
define('RATE_LIMIT_AKTIV', true);

// API_ORIGIN: Woher PHP den RATIOserver aufruft -- ein reiner Loopback.
// PHP und RATIOserver laufen im SELBEN Apache, der Aufruf verlaesst die
// Maschine also nie. Bewusst NICHT aus $_SERVER['HTTP_HOST'] gebaut: sonst
// haengt der Server-zu-Server-Aufruf am Zugriffsweg des Browsers und
// scheitert, sobald von aussen zugegriffen wird -- am selbstsignierten
// Zertifikat bei HTTPS, an DNS oder Firewall bei einem fremden Hostnamen.
//
// Schema UND Port stammen aus dem aktuellen Request. Beide Werte setzt Apache
// selbst -- anders als HTTP_HOST, das aus einem Client-Header stammt und
// deshalb nicht verwendbar ist. SERVER_PORT nennt immer den Port des Sockets,
// der diesen Request bedient, und passt damit IMMER zum Schema:
//
//   Zugriff per HTTP  auf Port 8080  ->  http://127.0.0.1:8080/ibapi
//   Zugriff per HTTPS auf Port 8443  ->  https://127.0.0.1:8443/ibapi
//
// Dadurch funktionieren beliebige Ports ohne Konfiguration. Der HTTPS-Loopback
// laeuft gegen ein selbstsigniertes Zertifikat -- core/Api.php schaltet die
// Zertifikatspruefung deshalb gezielt fuer Loopback-Ziele ab (nur dort).
$apiSchema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$apiPort   = (int)($_SERVER['SERVER_PORT'] ?? ($apiSchema === 'https' ? 443 : 80));

// Notbremse fuer Sonderfaelle, in denen die Ableitung nicht passt -- etwa wenn
// Apache nicht an 127.0.0.1 gebunden ist (Listen mit fester IP). Leer lassen,
// solange nichts dagegen spricht. Beispiel: 'http://192.168.1.5:8080'
define('API_ORIGIN_MANUELL', '');

define('API_ORIGIN', API_ORIGIN_MANUELL !== ''
    ? API_ORIGIN_MANUELL
    : $apiSchema . '://127.0.0.1:' . $apiPort);
define('BASE_URL', API_ORIGIN . '/ibapi');

// APP_BASE: Unterverzeichnis der App (z.B. /app, /myapp, /ratiophp)
// Dynamisch -- funktioniert in jedem Installationsverzeichnis
define('APP_BASE', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'));

// TOKEN_LIFETIME: Muss mit RATIOserver-Konfiguration uebereinstimmen (900 Minuten)
define('TOKEN_LIFETIME', 900 * 60);

// ============================================================================
// 3. Autoloader und Framework laden
// ============================================================================

require ROOT_PATH . '/vendor/autoload.php';
require ROOT_PATH . '/core/Api.php';

// Nicht abgefangene Ausnahmen und fatale PHP-Fehler als Systemfehler im
// Layout anzeigen statt als weisse Seite -- siehe core/Fehler.php
Core\Fehler::registriereHandler();

// ============================================================================
// 4. Router instanziieren
// ============================================================================

use Core\Router;

$router = new Router();

// ============================================================================
// 5. Routes registrieren -- Standard zuerst, dann Custom
// ============================================================================

require ROOT_PATH . '/config/routes.php';
require ROOT_PATH . '/config/routes.custom.php';

// ============================================================================
// 6. Request dispatchen
// ============================================================================

$router->dispatch();
