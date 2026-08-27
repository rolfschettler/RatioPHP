<?php
/**
 * standard/Controllers/HomeController.php
 *
 * Startseiten der Portale -- alle oeffentlich (auth: false), der Login laeuft
 * jeweils ueber das Modal.
 *
 *   index()       GET /            -- Kundenportal (Default-Einstieg)
 *   kunde()       GET /kunde       -- Weiterleitung auf /
 *   mitarbeiter() GET /mitarbeiter -- Mitarbeiterportal
 *   fahrer()      GET /fahrer      -- Fahrerportal
 *
 * Mitarbeiter- und Fahrerportal sind bewusst nur direkt per URL erreichbar --
 * es gibt keinen Link vom Kundenportal dorthin.
 *
 * Die Portalnamen selbst sind in core/Portal.php definiert.
 */

namespace Standard\Controllers;

use Core\BaseController;
use Core\Portal;

class HomeController extends BaseController
{
    /**
     * GET / -- Kundenportal: Einstieg mit Registrierung.
     */
    public function index(): void
    {
        $this->render('home/index', [
            'page_title' => 'Kundenportal',
            'portal'     => 'kunde',
        ]);
    }

    /**
     * GET /kunde -- Weiterleitung auf die Startseite.
     *
     * Das Kundenportal beginnt auf '/', sein Routen-Praefix ist aber '/kunde'
     * (die uebrigen Kundenseiten liegen darunter). Einstieg und Praefix fallen
     * hier also auseinander -- anders als bei /mitarbeiter und /fahrer, wo
     * beides derselbe Pfad ist. Damit die Praefix-URL nicht als 404 endet,
     * zeigt sie auf die Startseite. Bewusst eine Weiterleitung und keine
     * zweite Ausgabe derselben Seite: das Kundenportal hat genau eine
     * Startadresse, und die steht in core/Portal.php.
     */
    public function kunde(): void
    {
        $this->redirect(Portal::start('kunde'));
    }

    /**
     * GET /mitarbeiter -- Mitarbeiterportal: Login und Modulauswahl.
     */
    public function mitarbeiter(): void
    {
        $this->render('home/mitarbeiter', [
            'page_title' => 'Mitarbeiterportal',
            'portal'     => 'mitarbeiter',
        ]);
    }

    /**
     * GET /fahrer -- Fahrerportal: Login und Registrierung.
     * Noch ohne Fachmodule -- Kacheln kommen, sobald feststeht, welche Daten
     * Fahrer sehen sollen.
     */
    public function fahrer(): void
    {
        $this->render('home/fahrer', [
            'page_title' => 'Fahrerportal',
            'portal'     => 'fahrer',
        ]);
    }
}
