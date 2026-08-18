<?php
/**
 * core/View.php -- View-Renderer
 *
 * Loest einen Modul-View (standard/Views oder custom/Views) auf, rendert
 * ihn in $content und bettet das Ergebnis in views/layout.php ein.
 *
 * Views enthalten NUR reinen Content-HTML -- kein DOCTYPE, kein html/head/body.
 * layout.php wird ausschliesslich hier eingebunden -- nie in einem View.
 */

namespace Core;

class View
{
    /**
     * Rendert einen View und gibt das fertige HTML zurueck.
     *
     * Custom-Views ueberschreiben gleichnamige Standard-Views.
     *
     * @param string $view Pfad ohne Endung, z.B. 'home/index'
     * @param array  $data Variablen fuer View und Layout
     */
    public static function render(string $view, array $data = []): string
    {
        // Custom gewinnt vor Standard
        $custom   = ROOT_PATH . '/custom/Views/'   . $view . '.php';
        $standard = ROOT_PATH . '/standard/Views/' . $view . '.php';
        $viewFile = is_file($custom) ? $custom : $standard;

        // Content aus View-Datei rendern -- oder direkt aus $data['content']
        if (is_file($viewFile)) {
            $content = self::capture($viewFile, $data);
        } else {
            $content = $data['content'] ?? '';
        }

        // Layout-Variablen mit Defaults
        $layoutData = [
            'page_title'  => $data['page_title']  ?? APP_NAME,
            'page_header' => $data['page_header'] ?? '',
            'toolbar'     => $data['toolbar']     ?? '',
            'pager'       => $data['pager']       ?? '',
            'content'     => $content,
        ];

        return self::capture(VIEW_PATH . '/layout.php', $layoutData);
    }

    /**
     * Bindet eine PHP-Datei in isoliertem Scope ein und faengt die Ausgabe ab.
     */
    private static function capture(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return ob_get_clean();
    }
}
