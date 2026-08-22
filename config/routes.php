<?php
/**
 * config/routes.php -- Routen der Standardmodule
 *
 * Wird vor routes.custom.php geladen. Custom-Routen koennen diese
 * ueberschreiben (spaeteres Laden gewinnt).
 *
 * $router->add(path, controller, action, options)
 *   options['auth'] => false  -- oeffentliche Route ohne Token
 *
 * Portalstruktur:
 *   /            Kundenportal      -- Default-Einstieg, oeffentlich
 *   /mitarbeiter Mitarbeiterportal -- nur direkt per URL erreichbar
 */

// ---------------------------------------------------------------------------
// Kundenportal -- oeffentlicher Default-Einstieg
// ---------------------------------------------------------------------------

$router->add('/', 'Standard\Controllers\HomeController', 'index', ['auth' => false]);

// Registrierung -- oeffentlich, legt Adresse (ADRESSEN) und Registrierung
// (REGISTRIERUNG) ueber /registrierung/insertregistrierunglocal an.
// username-pruefen liefert JSON fuer die Live-Verfuegbarkeitspruefung im
// Formular (bedient /registrierung/checkusernamelocal).
$router->add('/registrieren',                  'Standard\Controllers\RegistrierungController', 'index',           ['auth' => false]);
$router->add('/registrieren/absenden',         'Standard\Controllers\RegistrierungController', 'speichern',       ['auth' => false]);
$router->add('/registrieren/username-pruefen', 'Standard\Controllers\RegistrierungController', 'usernamePruefen', ['auth' => false]);

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

// Login (nur POST) und Logout -- oeffentlich
$router->add('/login',  'Standard\Controllers\AuthController', 'login',  ['auth' => false]);
$router->add('/logout', 'Standard\Controllers\AuthController', 'logout', ['auth' => false]);

// EINSATZ-Liste -- geschuetzt (Default auth: true), Endpunkte unter /dispo/
$router->add('/einsatz', 'Standard\Controllers\EinsatzController', 'index');

// ANMIETIMPORT -- geschuetzt, Upload + Import nach ANMIET/ANMIETPOS ueber /anmiet/
$router->add('/anmietimport', 'Standard\Controllers\AnmietimportController', 'index');
$router->add('/anmietimport/hochladen', 'Standard\Controllers\AnmietimportController', 'hochladen');
