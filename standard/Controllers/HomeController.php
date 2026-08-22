<?php
/**
 * standard/Controllers/HomeController.php
 *
 * Startseiten der beiden Portale -- beide oeffentlich (auth: false).
 *
 *   index()       GET /            -- Kundenportal (Default-Einstieg)
 *   mitarbeiter() GET /mitarbeiter -- Mitarbeiterportal, Login per Modal
 *
 * Das Mitarbeiterportal ist bewusst nur direkt per URL erreichbar --
 * es gibt keinen Link vom Kundenportal dorthin.
 */

namespace Standard\Controllers;

use Core\BaseController;

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
     * GET /mitarbeiter -- Mitarbeiterportal: Login und Modulauswahl.
     */
    public function mitarbeiter(): void
    {
        $this->render('home/mitarbeiter', [
            'page_title' => 'Mitarbeiterportal',
            'portal'     => 'mitarbeiter',
        ]);
    }
}
