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

use Core\Anfrage;
use Core\Auth;
use Core\BaseController;
use Core\Fehler;
use Core\Portal;
use Core\RateLimit;

class AuthController extends BaseController
{
    /**
     * Fehlgeschlagene Anmeldungen je Zeitfenster (Core\RateLimit::FENSTER,
     * 1 Stunde). Erfolgreiche Anmeldungen zaehlen nicht.
     *
     * Pro IP grosszuegiger: hinter einem Firmen-NAT teilen sich viele
     * Mitarbeiter eine Adresse. Pro Konto knapper -- dort zielt Brute-Force
     * hin. Nicht zu knapp: ein Angreifer kann ein fremdes Konto durch
     * absichtliche Fehlversuche fuer den Rest des Fensters sperren.
     */
    private const LOGIN_MAX_IP    = 20;
    private const LOGIN_MAX_KONTO = 10;

    /**
     * POST /login -- Login gegen RATIOserver verarbeiten.
     * Bei Erfolg: beide Cookies setzen, zurueck zur Portal-Startseite.
     * Bei Fehler: Benutzerfehler + zurueck auf Portal::login() -- das Modal
     * oeffnet sich und zeigt die Meldung.
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

        $user     = (string)($_POST['user']     ?? '');
        $password = (string)($_POST['password'] ?? '');

        // Brute-Force-Schutz: zwei Zaehler, pro IP und pro Konto. Es zaehlen
        // nur FEHLSCHLAEGE -- der Versuch wird vorab gezaehlt (atomar, damit
        // parallele Versuche nicht durchrutschen) und bei Erfolg oder
        // Systemfehler wieder zurueckgenommen.
        $ip    = Anfrage::ip();
        $konto = RateLimit::schluessel($user);

        if (RateLimit::ueberschritten($ip, 'login_ip', self::LOGIN_MAX_IP)
            || RateLimit::ueberschritten($konto, 'login_konto', self::LOGIN_MAX_KONTO)) {
            // Dieselbe Meldung fuer beide Zaehler -- verraet nichts ueber das Konto
            $this->flashError('Zu viele fehlgeschlagene Anmeldeversuche -- bitte später erneut versuchen.');
            $this->redirect(Portal::login($portal));
        }

        // Login gegen RATIOserver
        $response = \api_post('/login', [
            'user'     => $user,
            'password' => $password,
        ]);

        // Systemfehler (Server nicht erreichbar, ...) ist kein Fehlversuch --
        // sonst sperrt ein Ausfall des Backends alle Benutzer aus
        if (!empty($response['token']) || Fehler::istSystem($response)) {
            RateLimit::zuruecknehmen($ip, 'login_ip');
            RateLimit::zuruecknehmen($konto, 'login_konto');
        }

        if (empty($response['token'])) {
            // Falsche Zugangsdaten erscheinen im wieder geoeffneten Login-Modal.
            // Ein Systemfehler steht bereits im reservierten Bereich -- dann
            // bleibt das Modal zu, damit er sichtbar ist.
            $this->apiFehler($response, 'Login fehlgeschlagen');
            $this->redirect(Fehler::istSystem($response) ? $ziel : Portal::login($portal));
        }

        // Erfolgreich angemeldet -- fruehere Fehlversuche dieses Kontos
        // verfallen, ein Tippfehler von gestern zaehlt nicht weiter mit
        RateLimit::zuruecksetzen($konto, 'login_konto');

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
