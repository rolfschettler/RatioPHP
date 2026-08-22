<?php
/**
 * standard/Controllers/AuthController.php
 *
 * Login laeuft ueber ein Bootstrap-Modal (views/components/login-modal.php).
 * Es gibt KEINE GET-Route /login -- nur POST /login fuer den Formular-Submit.
 * Token kommt nach Login aus dem Cookie jwt_token -- niemals aus $_SESSION.
 *
 * BEIDE Portale melden sich hier an -- Kunden- und Mitarbeiterportal. Der
 * RATIOserver-Endpunkt /login prueft gegen REGISTRIERUNG (pwd2 per
 * password_verify), dort liegen sowohl Kunden- als auch Mitarbeiter-
 * Registrierungen. Unterschieden wird nur, wohin nach Login bzw. Logout
 * weitergeleitet wird: das Formular schickt ein verstecktes Feld "portal"
 * mit, der Logout-Link haengt ?portal=.. an.
 *
 * Das Ziel wird IMMER gegen PORTAL_ZIELE geprueft -- niemals ein Pfad aus
 * dem Request uebernehmen, sonst entsteht eine offene Weiterleitung.
 */

namespace Standard\Controllers;

use Core\BaseController;

class AuthController extends BaseController
{
    /**
     * Startseite je Portal. Nur diese Werte sind als Ziel zulaessig.
     */
    private const PORTAL_ZIELE = [
        'kunde'       => '/',
        'mitarbeiter' => '/mitarbeiter',
    ];

    /**
     * Portal aus dem Request lesen und auf einen erlaubten Wert abbilden.
     * Unbekannte oder fehlende Angaben landen im Kundenportal -- das ist der
     * oeffentliche Default-Einstieg. Ein Fehlgriff wuerde einen Kunden sonst
     * ins Mitarbeiterportal schicken, das er gar nicht sehen soll.
     */
    private function portal(string $wert): string
    {
        return isset(self::PORTAL_ZIELE[$wert]) ? $wert : 'kunde';
    }

    /**
     * POST /login -- Login gegen RATIOserver verarbeiten.
     * Bei Erfolg: beide Cookies setzen, zurueck zur Portal-Startseite.
     * Bei Fehler: Flash-Message + zurueck auf <portal>?login=1
     * (das Modal oeffnet sich dann automatisch per JS).
     */
    public function login(): void
    {
        $portal = $this->portal((string)($_POST['portal'] ?? ''));
        $ziel   = self::PORTAL_ZIELE[$portal];

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
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
        ]);

        // Benutzername-Cookie (nicht httponly -- fuer Header-Anzeige)
        setcookie('jwt_user', $username, [
            'expires'  => time() + TOKEN_LIFETIME,
            'path'     => '/',
            'samesite' => 'Strict',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => false,
        ]);

        $this->redirect($ziel);
    }

    /**
     * GET/POST /logout -- Abmelden, beide Cookies loeschen.
     * Zurueck zur Startseite des Portals, aus dem abgemeldet wurde.
     */
    public function logout(): void
    {
        setcookie('jwt_token', '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Strict', 'secure' => isset($_SERVER['HTTPS']), 'httponly' => true]);
        setcookie('jwt_user',  '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Strict', 'secure' => isset($_SERVER['HTTPS']), 'httponly' => false]);

        $portal = $this->portal((string)($_GET['portal'] ?? ''));
        $this->redirect(self::PORTAL_ZIELE[$portal]);
    }
}
