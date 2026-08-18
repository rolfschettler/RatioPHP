<?php
/**
 * standard/Controllers/HomeController.php
 *
 * Oeffentliche Startseite (auth: false). Der Login laeuft per Modal --
 * eingeloggte Nutzer sehen einen Willkommensbereich mit Abmelden-Button.
 */

namespace Standard\Controllers;

use Core\BaseController;

class HomeController extends BaseController
{
    /**
     * GET / -- Startseite mit HERO-Bereich.
     */
    public function index(): void
    {
        $this->render('home/index', [
            'page_title' => 'Start',
        ]);
    }
}
