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
}
