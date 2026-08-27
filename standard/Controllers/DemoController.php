<?php
/**
 * standard/Controllers/DemoController.php
 *
 * Testseite des Kundenportals -- "Hallo Welt". Kein Fachmodul, keine
 * Datenbank, kein API-Aufruf: die Seite dient ausschliesslich dazu, das
 * Kundenportal von Hand durchzuklicken.
 *
 * Die Route ist GESCHUETZT (Default auth: true) und liegt unter dem Praefix
 * des Kundenportals. Damit uebt sie genau den Fall aus, den es sonst nirgends
 * gibt: eine geschuetzte Route, die NICHT zum Mitarbeiterportal gehoert.
 *
 * Erwartetes Verhalten:
 *   nicht angemeldet     -> Weiterleitung auf / mit ?login=1 (Modal oeffnet)
 *   angemeldet als kunde -> Seite wird angezeigt
 *   angemeldet als fahrer-> Weiterleitung auf /fahrer mit Fehlermeldung
 *   angemeldet als mitarbeiter -> Seite wird angezeigt (darf alles)
 *
 * Zum Entfernen genuegt es, die Route in config/routes.php zu loeschen --
 * Controller und View haengen an nichts anderem.
 */

namespace Standard\Controllers;

use Core\Auth;
use Core\BaseController;
use Core\Portal;

class DemoController extends BaseController
{
    /** Portal des Moduls -- bestimmt Layout, Navigation und Routen-Praefix. */
    private const PORTAL = 'kunde';

    /** Pfad des Moduls unterhalb des Portal-Praefix. */
    private const MODUL = '/demo';

    /**
     * GET /kunde/demo -- Hallo Welt plus der Kontext, der zeigt, dass die
     * Seite im richtigen Portal und mit dem richtigen Token gelandet ist.
     */
    public function index(): void
    {
        $this->render('demo/index', [
            'page_title'   => 'Hallo Welt',
            'portal'       => self::PORTAL,
            'modul_url'    => Portal::praefix(self::PORTAL) . self::MODUL,
            // Portal der Route gegen Portal des Tokens -- muessen bei einem
            // Kundenkonto beide 'kunde' sein. Bei einem Mitarbeiter steht hier
            // 'mitarbeiter': er darf jede Route besuchen.
            'route_portal' => self::PORTAL,
            'token_portal' => Auth::portal(),
            'benutzer'     => $_COOKIE['jwt_user'] ?? '',
        ]);
    }
}
