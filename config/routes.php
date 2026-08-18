<?php
/**
 * config/routes.php -- Routen der Standardmodule
 *
 * Wird vor routes.custom.php geladen. Custom-Routen koennen diese
 * ueberschreiben (spaeteres Laden gewinnt).
 *
 * $router->add(path, controller, action, options)
 *   options['auth'] => false  -- oeffentliche Route ohne Token
 */

// Oeffentliche Startseite -- kein Auth-Check, Login laeuft per Modal
$router->add('/', 'Standard\Controllers\HomeController', 'index', ['auth' => false]);

// Login (nur POST) und Logout -- oeffentlich
$router->add('/login',  'Standard\Controllers\AuthController', 'login',  ['auth' => false]);
$router->add('/logout', 'Standard\Controllers\AuthController', 'logout', ['auth' => false]);

// EINSATZ-Liste -- geschuetzt (Default auth: true), Endpunkte unter /dispo/
$router->add('/einsatz', 'Standard\Controllers\EinsatzController', 'index');

// ANMIETIMPORT -- geschuetzt, Upload + Import nach ANMIET/ANMIETPOS ueber /anmiet/
$router->add('/anmietimport', 'Standard\Controllers\AnmietimportController', 'index');
$router->add('/anmietimport/hochladen', 'Standard\Controllers\AnmietimportController', 'hochladen');
