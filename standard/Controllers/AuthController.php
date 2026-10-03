<?php
/**
 * standard/Controllers/AuthController.php
 *
 * Login laeuft ueber ein Bootstrap-Modal (views/components/login-modal.php).
 * Es gibt KEINE GET-Route /login -- nur POST /login fuer den Formular-Submit.
 * Token kommt nach Login aus dem Cookie jwt_token -- niemals aus $_SESSION.
 *
 * ALLE Portale melden sich hier an -- Kunden-, Mitarbeiter- und Fahrerportal.
 * Der RATIOserver-Endpunkt /login prueft gegen REGISTRIERUNG (pwd2 per
 * password_verify), dort liegen die Registrierungen aller Portale
 * nebeneinander, unterschieden nur durch das Feld typ. Der Vergleich des
 * Benutzernamens laeuft per UPPER() auf beiden Seiten (DataModulLoginClass.pas)
 * -- Gross-/Kleinschreibung spielt beim Anmelden also keine Rolle.
 *
 * Unterschieden wird hier nur, wohin nach Login bzw. Logout weitergeleitet
 * wird: das Formular schickt ein verstecktes Feld "portal" mit, der
 * Logout-Link haengt ?portal=.. an.
 *
 * Nach ERFOLGREICHEM Login gewinnt allerdings das Portal des Tokens
 * (Core\Auth::portalAusToken) -- wer sich am falschen Modal anmeldet, landet
 * trotzdem in seinem eigenen Portal statt an der Portalgrenze im Router.
 * Das Formular-Portal bleibt Ziel bei Fehlern und als Fallback.
 *
 * Das Ziel kommt IMMER aus Core\Portal -- niemals ein Pfad aus dem Request,
 * sonst entsteht eine offene Weiterleitung.
 */

namespace Standard\Controllers;

use Core\Auth;
use Core\BaseController;
use Core\Fehler;
use Core\Portal;

class AuthController extends BaseController
{
    /**
     * POST /login -- Login gegen RATIOserver verarbeiten.
     * Bei Erfolg: beide Cookies setzen, zurueck zur Portal-Startseite.
     * Bei Fehler: Flash-Message + zurueck auf <portal>?login=1
     * (das Modal oeffnet sich dann automatisch per JS).
     */
    public function login(): void
    {
        $portal = Portal::name((string)($_POST['portal'] ?? ''));
        $ziel   = Portal::start($portal);

        // Nur POST erlaubt -- kein GET
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect($ziel);
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
            // Systemfehler (Server nicht erreichbar, ...) stehen bereits im
            // reservierten Bereich -- das Modal bleibt zu, damit er sichtbar ist
            if (Fehler::istSystem($response)) {
                $this->redirect($ziel);
                return;
            }

            // Falsche Zugangsdaten -- Meldung erscheint im Login-Modal
            $this->flashError($response['message'] ?? 'Login fehlgeschlagen');
            // ?login=1 oeffnet das Modal automatisch per JS
            $this->redirect($ziel . (str_contains($ziel, '?') ? '&' : '?') . 'login=1');
            return;
        }

        $token    = $response['token'];
        $username = $user;  // Benutzername direkt aus POST -- kein verifytoken noetig

        // Token-Cookie (httponly -- sicher gegen XSS)
        setcookie('jwt_token', $token, [
            'expires'  => time() + TOKEN_LIFETIME,
            'path'     => '/',
            'samesite' => 'Strict',
            'secure'   => $this->istHttps(),
            'httponly' => true,
        ]);

        // Benutzername-Cookie (nicht httponly -- fuer Header-Anzeige)
        setcookie('jwt_user', $username, [
            'expires'  => time() + TOKEN_LIFETIME,
            'path'     => '/',
            'samesite' => 'Strict',
            'secure'   => $this->istHttps(),
            'httponly' => false,
        ]);

        // Ziel ist das Portal des ANMELDERS (typ aus dem Token), nicht das
        // Portal, dessen Modal benutzt wurde. Ein Fahrer, der sich ueber das
        // Modal im Kundenportal anmeldet, landet sonst auf / -- und wird von
        // der Portalpruefung im Router sofort wieder weggeschickt.
        // Fallback auf das Formular-Portal, falls der Token kein typ traegt.
        $tokenPortal = Auth::portalAusToken($token);
        $this->redirect($tokenPortal !== null ? Portal::start($tokenPortal) : $ziel);
    }

    /**
     * GET/POST /logout -- Abmelden, beide Cookies loeschen.
     * Zurueck zur Startseite des Portals, aus dem abgemeldet wurde.
     */
    public function logout(): void
    {
        setcookie('jwt_token', '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Strict', 'secure' => $this->istHttps(), 'httponly' => true]);
        setcookie('jwt_user',  '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Strict', 'secure' => $this->istHttps(), 'httponly' => false]);

        $portal = Portal::name((string)($_GET['portal'] ?? ''));
        $this->redirect(Portal::start($portal));
    }
}
