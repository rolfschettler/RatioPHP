<?php
/**
 * config/routes.php -- Routen der Standardmodule
 *
 * Wird vor routes.custom.php geladen. Custom-Routen koennen diese
 * ueberschreiben (spaeteres Laden gewinnt).
 *
 * $router->add(path, controller, action, options)
 *   options['auth']   => false          -- oeffentliche Route ohne Token
 *   options['portal'] => Router::ALLE   -- portaluebergreifende Route
 *
 * PRAEFIX-KONVENTION -- jede Portalseite liegt unter dem Praefix ihres Portals
 * (Definition der Portale und Praefixe: core/Portal.php):
 *
 *   /                  Kundenportal, oeffentlicher Einstieg (praefixlos)
 *   /kunde/...         Kundenportal
 *   /mitarbeiter/...   Mitarbeiterportal
 *   /fahrer/...        Fahrerportal
 *
 * Aus dem Praefix leitet der Router das Portal der Route ab und vergleicht es
 * mit typ aus dem Token. Eine Route OHNE Praefix (ausser den Startseiten) hat
 * kein Portal und wird abgewiesen -- die Angabe kann also nicht vergessen
 * werden. Ausnahme sind /login und /logout: sie gehoeren zu allen Portalen und
 * bekommen dafuer ausdruecklich ['portal' => Router::ALLE].
 */

use Core\Router;

// ---------------------------------------------------------------------------
// Kundenportal -- oeffentlicher Default-Einstieg
// ---------------------------------------------------------------------------

$router->add('/', 'Standard\Controllers\HomeController', 'index', ['auth' => false]);

// Die Praefix-URL selbst: das Kundenportal beginnt auf '/', nicht auf '/kunde'
// (Einstieg und Praefix fallen nur hier auseinander). Damit /kunde nicht als
// 404 endet, leitet es auf die Startseite um.
$router->add('/kunde', 'Standard\Controllers\HomeController', 'kunde', ['auth' => false]);

// Registrierung -- oeffentlich, legt Adresse (ADRESSEN) und Registrierung
// (REGISTRIERUNG) ueber /registrierung/insertregistrierunglocal an.
// username-pruefen liefert JSON fuer die Live-Verfuegbarkeitspruefung im
// Formular (bedient /registrierung/checkusernamelocal).
$router->add('/kunde/registrieren',                  'Standard\Controllers\RegistrierungController', 'index',           ['auth' => false]);
$router->add('/kunde/registrieren/absenden',         'Standard\Controllers\RegistrierungController', 'speichern',       ['auth' => false]);
$router->add('/kunde/registrieren/username-pruefen', 'Standard\Controllers\RegistrierungController', 'usernamePruefen', ['auth' => false]);

// Testseite "Hallo Welt" -- geschuetzt (Default auth: true), keine Fachfunktion.
// Dient zum Durchklicken des Kundenportals und uebt die Portalgrenze aus:
// eine geschuetzte Route, die NICHT zum Mitarbeiterportal gehoert.
// Zum Entfernen genuegt diese Zeile plus DemoController und View.
$router->add('/kunde/demo', 'Standard\Controllers\DemoController', 'index');

// ---------------------------------------------------------------------------
// Mitarbeiterportal -- Einstieg per URL, Login laeuft ueber das Modal
// ---------------------------------------------------------------------------

$router->add('/mitarbeiter', 'Standard\Controllers\HomeController', 'mitarbeiter', ['auth' => false]);

// Mitarbeiter-Registrierung -- oeffentliche Route, aber KEIN offener Zugang:
// der Controller laesst nur durch, wer sich mit Loginname und Passwort aus der
// Tabelle USERS ausweist. Der Zugang wird also nicht per JWT geregelt, sondern
// in pruefeMitarbeiter() -- deshalb hier auth => false.
$router->add('/mitarbeiter/registrieren',          'Standard\Controllers\RegistrierungController', 'mitarbeiter',          ['auth' => false]);
$router->add('/mitarbeiter/registrieren/absenden', 'Standard\Controllers\RegistrierungController', 'mitarbeiterSpeichern', ['auth' => false]);

// EINSATZ-Liste -- geschuetzt (Default auth: true), Endpunkte unter /dispo/
$router->add('/mitarbeiter/einsatz', 'Standard\Controllers\EinsatzController', 'index');

// ANMIETIMPORT -- geschuetzt, Upload + Import nach ANMIET/ANMIETPOS ueber /anmiet/
$router->add('/mitarbeiter/anmietimport',           'Standard\Controllers\AnmietimportController', 'index');
$router->add('/mitarbeiter/anmietimport/hochladen', 'Standard\Controllers\AnmietimportController', 'hochladen');

// ---------------------------------------------------------------------------
// Fahrerportal -- Einstieg per URL, Login laeuft ueber das Modal
// ---------------------------------------------------------------------------

$router->add('/fahrer', 'Standard\Controllers\HomeController', 'fahrer', ['auth' => false]);

// Fahrer-Registrierung -- oeffentliche Route. Der Identitaetsnachweis passiert
// nicht in PHP, sondern im Endpunkt selbst: insertregistrierunglocal gleicht
// bei typ=fahrer username (= PERSONALSTAMM.zeichen), name1 und name2 gegen den
// PERSONALSTAMM ab und lehnt Unbekannte ab.
$router->add('/fahrer/registrieren',          'Standard\Controllers\RegistrierungController', 'fahrer',          ['auth' => false]);
$router->add('/fahrer/registrieren/absenden', 'Standard\Controllers\RegistrierungController', 'fahrerSpeichern', ['auth' => false]);

// ---------------------------------------------------------------------------
// Portaluebergreifend
// ---------------------------------------------------------------------------

// Login (nur POST) und Logout -- oeffentlich und portaluebergreifend. Ein
// Endpunkt fuer alle Portale; das Ziel bestimmt das Feld "portal" bzw.
// ?portal=.. (Whitelist Core\Portal). Router::ALLE ist hier Pflicht: die Pfade
// tragen kein Portal-Praefix, und Abmelden muss auch mit einem Token
// funktionieren, dessen typ zu keinem Portal gehoert.
$router->add('/login',  'Standard\Controllers\AuthController', 'login',  ['auth' => false, 'portal' => Router::ALLE]);
$router->add('/logout', 'Standard\Controllers\AuthController', 'logout', ['auth' => false, 'portal' => Router::ALLE]);
