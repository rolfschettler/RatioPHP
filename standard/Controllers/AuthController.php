<?php
/**
 * standard/Controllers/AuthController.php
 *
 * Login laeuft ueber ein Bootstrap-Modal (views/components/login-modal.php).
 * Es gibt KEINE GET-Route /login -- nur POST /login fuer den Formular-Submit.
 * Token kommt nach Login aus dem Cookie jwt_token -- niemals aus $_SESSION.
 */

namespace Standard\Controllers;

use Core\BaseController;

class AuthController extends BaseController
{
    /**
     * POST /login -- Login gegen RATIOserver verarbeiten.
     * Bei Erfolg: beide Cookies setzen, zurueck auf Startseite.
     * Bei Fehler: Flash-Message + zurueck auf /?login=1 (Modal oeffnet sich erneut).
     */
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

        $this->redirect('/');
    }

    /**
     * GET/POST /logout -- Abmelden, beide Cookies loeschen.
     */
    public function logout(): void
    {
        setcookie('jwt_token', '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Strict', 'secure' => isset($_SERVER['HTTPS']), 'httponly' => true]);
        setcookie('jwt_user',  '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Strict', 'secure' => isset($_SERVER['HTTPS']), 'httponly' => false]);
        $this->redirect('/');
    }
}
