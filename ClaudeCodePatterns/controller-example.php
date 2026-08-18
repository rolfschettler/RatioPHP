<?php
/**
 * Controller-Muster fuer RATIOserver PHP-Frontend
 *
 * Zeigt wie Controller implementiert werden.
 * Namespace: Standard\Controllers oder Custom\Controllers
 */

namespace Standard\Controllers;

use Core\BaseController;

// ============================================================================
// AUTH-CONTROLLER (oeffentliche Routes -- auth: false)
// ============================================================================

class AuthController extends BaseController
{
    /**
     * GET /login  -- Login-Formular anzeigen
     * POST /login -- Login verarbeiten
     * Route: auth: false (kein Token erforderlich)
     */
    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $user     = $_POST['user']     ?? '';
            $password = $_POST['password'] ?? '';

            // API-Call: Token anfordern
            // Endpunkt: /login (NICHT /ibapi/login -- BASE_URL enthaelt bereits /ibapi)
            $response = \api_post('/login', [
                'user'     => $user,
                'password' => $password,
            ]);

            if (empty($response['token'])) {
                $this->flashError($response['message'] ?? 'Login fehlgeschlagen');
                $this->redirect('/login');
                return;
            }

            $token = $response['token'];

            // Benutzername direkt aus POST -- kein verifytoken-Aufruf noetig
            $username = $user;

            // Token als Cookie setzen (httponly -- sicher gegen XSS)
            setcookie('jwt_token', $token, [
                'expires'  => time() + TOKEN_LIFETIME,
                'path'     => '/',
                'samesite' => 'Strict',
                'secure'   => isset($_SERVER['HTTPS']),
                'httponly' => true,
            ]);

            // Benutzername als Cookie (nicht httponly -- fuer Header-Anzeige)
            setcookie('jwt_user', $username, [
                'expires'  => time() + TOKEN_LIFETIME,
                'path'     => '/',
                'samesite' => 'Strict',
                'secure'   => isset($_SERVER['HTTPS']),
                'httponly' => false,
            ]);

            $this->redirect('/');
        } else {
            // GET: Formular anzeigen
            // Kein required auf Passwort-Feld -- Validierung durch RATIOserver
            $this->renderPage('auth/login', ['page_title' => 'Anmelden']);
        }
    }

    /**
     * GET/POST /logout -- Abmelden
     * Route: auth: false (auch mit abgelaufenem Token erreichbar)
     */
    public function logout(): void
    {
        // Beide Cookies loeschen
        setcookie('jwt_token', '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Strict', 'secure' => isset($_SERVER['HTTPS']), 'httponly' => true]);
        setcookie('jwt_user',  '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Strict', 'secure' => isset($_SERVER['HTTPS']), 'httponly' => false]);
        $this->redirect('/login');
    }
}

// ============================================================================
// HOME-CONTROLLER (geschuetzte Route)
// ============================================================================

class HomeController extends BaseController
{
    /**
     * GET / -- Startseite
     * Router prueft Token VOR Aufruf -- keine redundante Pruefung noetig.
     */
    public function index(): void
    {
        $this->render('home/index', [
            'page_title'  => 'Start',
            'page_header' => '<h1 class="h5 fw-bold mb-0">Willkommen</h1>',
            'content'     => '<p class="p-3">Willkommen bei ' . htmlspecialchars(APP_NAME) . '.</p>',
        ]);
    }
}

// ============================================================================
// CRUD-CONTROLLER -- Muster fuer Listen, Formular, Insert, Update, Delete
// Xxx = exakter Tabellenname (z.B. Adressen, Einsatz)
// ============================================================================

class AdressenController extends BaseController
{
    /**
     * GET /adressen -- Liste aller Adressen
     */
    public function index(): void
    {
        $result = \api_post('/adressen/getAdressen', [
            'fields'  => ['kennziffer', 'name2', 'name1', 'strasse', 'ort', 'email'],
            'orderby' => 'name2',
        ]);

        $rows = '';
        foreach ($result['data'] ?? [] as $row) {
            $rows .= '<tr>
                <td class="act">
                    <a href="' . APP_BASE . '/adressen/bearbeiten?kennziffer=' . (int)$row['kennziffer'] . '"
                       class="btn btn-sm btn-outline-secondary" style="padding:.2rem .4rem;margin-right:.2rem;">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <form method="POST" action="' . APP_BASE . '/adressen/loeschen" class="d-inline">
                        <input type="hidden" name="kennziffer" value="' . (int)$row['kennziffer'] . '">
                        <button type="submit" class="btn btn-sm btn-outline-danger" style="padding:.2rem .4rem;">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </td>
                <td class="dim">' . htmlspecialchars($row['kennziffer']) . '</td>
                <td>' . htmlspecialchars($row['name2'] ?? '') . '</td>
                <td>' . htmlspecialchars($row['name1'] ?? '') . '</td>
                <td>' . htmlspecialchars($row['strasse'] ?? '') . '</td>
                <td>' . htmlspecialchars($row['ort'] ?? '') . '</td>
                <td>' . htmlspecialchars($row['email'] ?? '') . '</td>
            </tr>';
        }

        $content = '
        <table class="app-table" style="min-width:900px;">
            <thead>
                <tr>
                    <th>Aktionen</th>
                    <th>Nr.</th>
                    <th>Name</th>
                    <th>Vorname</th>
                    <th>Strasse</th>
                    <th>Ort</th>
                    <th>E-Mail</th>
                </tr>
            </thead>
            <tbody>' . $rows . '</tbody>
        </table>';

        $this->render('adressen/index', [
            'page_title'  => 'Adressen',
            'page_header' => '
                <h1 class="h5 fw-bold mb-0" style="color:var(--app-text-dark);">Adressen</h1>
                <span class="badge rounded-pill"
                      style="background:var(--app-gold-light);color:var(--app-text-dark);">
                    ' . count($result['data'] ?? []) . ' Eintraege
                </span>',
            'toolbar'     => '
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-sm-6 col-lg-auto ms-lg-auto">
                        <a href="' . APP_BASE . '/adressen/neu"
                           class="btn btn-sm fw-semibold w-100"
                           style="background:var(--app-gold);color:var(--app-text-dark);border:none;">
                            <i class="bi bi-plus-lg me-1"></i>Neue Adresse
                        </a>
                    </div>
                </div>',
            'content'     => $content,
        ]);
    }

    /**
     * GET /adressen/neu -- Formular fuer neue Adresse
     */
    public function neu(): void
    {
        $this->render('adressen/form', [
            'page_title'  => 'Neue Adresse',
            'page_header' => '<h1 class="h5 fw-bold mb-0">Neue Adresse</h1>',
            'adresse'     => null,
            'action'      => APP_BASE . '/adressen/speichern',
        ]);
    }

    /**
     * POST /adressen/speichern -- Neue Adresse speichern
     */
    public function speichern(): void
    {
        $response = \api_post('/adressen/insertAdressen', [
            'name2'   => $_POST['name2']   ?? '',
            'name1'   => $_POST['name1']   ?? '',
            'strasse' => $_POST['strasse'] ?? '',
            'ort'     => $_POST['ort']     ?? '',
            'email'   => $_POST['email']   ?? '',
        ]);

        if (($response['status'] ?? '') === 'OK') {
            $this->flashSuccess('Adresse gespeichert.');
        } else {
            $this->flashError($response['message'] ?? 'Fehler beim Speichern.');
        }
        $this->redirect('/adressen');
    }

    /**
     * GET /adressen/bearbeiten?kennziffer=42 -- Formular zum Bearbeiten
     */
    public function bearbeiten(): void
    {
        $kennziffer = (int)($_GET['kennziffer'] ?? 0);
        if (!$kennziffer) { $this->redirect('/adressen'); return; }

        $result = \api_post('/adressen/getAdressenById', [
            'kennziffer' => $kennziffer,
            'fields'     => ['kennziffer', 'name2', 'name1', 'strasse', 'ort', 'email'],
        ]);

        if (empty($result['data'][0])) {
            $this->flashError('Adresse nicht gefunden.');
            $this->redirect('/adressen');
            return;
        }

        $this->render('adressen/form', [
            'page_title'  => 'Adresse bearbeiten',
            'page_header' => '<h1 class="h5 fw-bold mb-0">Adresse bearbeiten</h1>',
            'adresse'     => $result['data'][0],
            'action'      => APP_BASE . '/adressen/aktualisieren',
        ]);
    }

    /**
     * POST /adressen/aktualisieren -- Adresse aktualisieren
     */
    public function aktualisieren(): void
    {
        $kennziffer = (int)($_POST['kennziffer'] ?? 0);
        if (!$kennziffer) { $this->redirect('/adressen'); return; }

        $response = \api_post('/adressen/updateAdressen', [
            'kennziffer' => $kennziffer,
            'name2'      => $_POST['name2']   ?? '',
            'name1'      => $_POST['name1']   ?? '',
            'strasse'    => $_POST['strasse'] ?? '',
            'ort'        => $_POST['ort']     ?? '',
            'email'      => $_POST['email']   ?? '',
        ]);

        if (($response['status'] ?? '') === 'OK') {
            $this->flashSuccess('Adresse gespeichert.');
        } else {
            $this->flashError($response['message'] ?? 'Fehler beim Speichern.');
        }
        $this->redirect('/adressen');
    }

    /**
     * POST /adressen/loeschen -- Adresse loeschen
     */
    public function loeschen(): void
    {
        $kennziffer = (int)($_POST['kennziffer'] ?? 0);
        if (!$kennziffer) { $this->redirect('/adressen'); return; }

        $response = \api_post('/adressen/deleteAdressen', [
            'kennziffer' => $kennziffer,
        ]);

        if (($response['status'] ?? '') === 'OK') {
            $this->flashSuccess('Adresse geloescht.');
        } else {
            $this->flashError($response['message'] ?? 'Fehler beim Loeschen.');
        }
        $this->redirect('/adressen');
    }
}

/*
 * BEST PRACTICES:
 *
 * 1. Token aus Cookie -- NICHT aus Session
 *    $_COOKIE['jwt_token'] -- nie $_SESSION['jwt_token']
 *
 * 2. Endpunkte: /login, /verifytoken (ohne /ibapi/ -- BASE_URL enthaelt das bereits)
 *
 * 3. Tabellenname in Endpunkten: exakter DB-Tabellenname
 *    ADRESSEN -> getAdressen, insertAdressen, updateAdressen, deleteAdressen
 *
 * 4. fields immer als Array -- nie als String "*"
 *
 * 5. \api_post() mit fuehrendem Backslash -- Namespace-Problem
 *
 * 6. Kein required auf Passwort-Feld im Login-Formular
 *
 * 7. Redirects mit $this->redirect() -- haengt APP_BASE automatisch an
 *
 * 8. Layout-Variablen: page_title, page_header, toolbar, content, pager
 */
