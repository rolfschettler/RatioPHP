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

// BASE_URL: IMMER dynamisch -- niemals hardcodieren
// $scheme erkennt HTTP und HTTPS automatisch
// HTTP_HOST enthaelt Host UND Port (z.B. localhost:8080)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
define('BASE_URL', $scheme . '://' . $_SERVER['HTTP_HOST'] . '/ibapi');

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
