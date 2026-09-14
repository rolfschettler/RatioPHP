<?php
/**
 * core/BaseController.php -- Basis fuer alle Controller
 *
 * Stellt render(), redirect() und Flash-Messages bereit.
 * Custom- und Standard-Controller erben von dieser Klasse.
 *
 * Token kommt IMMER aus $_COOKIE['jwt_token'] -- niemals aus $_SESSION.
 * \api_post() wird in Controllern mit fuehrendem Backslash aufgerufen.
 */

namespace Core;

class BaseController
{
    /**
     * Rendert einen View ins Layout und gibt ihn aus.
     *
     * @param string $view Pfad ohne Endung, z.B. 'home/index'
     * @param array  $data Variablen fuer View und Layout
     */
    protected function render(string $view, array $data = []): void
    {
        echo View::render($view, $data);
    }

    /**
     * Leitet auf einen Pfad innerhalb der App um.
     * APP_BASE wird automatisch vorangestellt -- funktioniert in jedem
     * Installationsverzeichnis.
     */
    protected function redirect(string $path): void
    {
        header('Location: ' . APP_BASE . $path);
        exit;
    }

    /**
     * Setzt eine Fehler-Flash-Message fuer den naechsten Request.
     */
    protected function flashError(string $message): void
    {
        $_SESSION['flash_error'] = $message;
    }

    /**
     * Setzt eine Erfolgs-Flash-Message fuer den naechsten Request.
     */
    protected function flashSuccess(string $message): void
    {
        $_SESSION['flash_success'] = $message;
    }

    /**
     * Laeuft der aktuelle Request ueber HTTPS?
     *
     * Steuert das secure-Flag der Login-Cookies. Steht hier und nicht im
     * AuthController, weil auch andere Controller die Cookies loeschen --
     * etwa nach einer abgelaufenen Anmeldung.
     *
     * Bewusst NICHT isset($_SERVER['HTTPS']): manche Server- und
     * PHP-Konfigurationen setzen die Variable bei HTTP-Zugriffen auf den
     * String 'off'. isset() waere dann true, das Cookie bekaeme secure --
     * und der Browser verwirft es ueber HTTP stillschweigend. Die Anmeldung
     * sieht erfolgreich aus, greift aber nicht.
     *
     * GRENZE: erkennt nur eine direkt in diesem Apache terminierte
     * TLS-Verbindung. Terminiert spaeter ein vorgelagerter Reverse-Proxy das
     * TLS und reicht per HTTP weiter, ist $_SERVER['HTTPS'] LEER -- die
     * Methode liefert dann false, obwohl der Browser ueber HTTPS verbunden
     * ist, und die Cookies werden ohne secure gesetzt. Fuer so ein Setup muss
     * hier zusaetzlich $_SERVER['HTTP_X_FORWARDED_PROTO'] ausgewertet werden
     * -- aber NUR, wenn dem Proxy vertraut werden kann: der Header ist sonst
     * frei vom Client waehlbar.
     */
    protected function istHttps(): bool
    {
        return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    }
}
