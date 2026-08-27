<?php
/**
 * core/Router.php -- URL-Matching, Portalgrenze und Dispatch
 *
 * Basis ist ClaudeCodePatterns/router-implementation.php. Diese Datei weicht
 * bewusst davon ab, weil das Pattern die Portalstruktur nicht kennt:
 *   - jede Route kennt ihr Portal (Option 'portal', Default aus dem Praefix)
 *   - der Auth-Redirect fuehrt zur Startseite des Portals der Route,
 *     nicht fest nach /login
 *   - Portalgrenze: Praefix der Route gegen typ aus dem Token (core/Auth.php)
 * Beim Uebernehmen einer neuen Pattern-Version diese Punkte erneut einarbeiten.
 *
 * Praefix-Konvention (Definition der Portale: core/Portal.php):
 *   /                    Kundenportal, oeffentlicher Einstieg (praefixlos)
 *   /kunde/...           Kundenportal
 *   /mitarbeiter/...     Mitarbeiterportal
 *   /fahrer/...          Fahrerportal
 *   /login, /logout      portaluebergreifend -- ['portal' => Router::ALLE]
 *
 * Zugriffsregel:
 *   typ='mitarbeiter'    darf jede Route besuchen
 *   alle anderen         nur Routen ihres eigenen Portals
 *   typ unbekannt/leer   nur portaluebergreifende und oeffentliche Routen
 */

namespace Core;

class Router
{
    /**
     * Portalwert fuer Routen, die zu allen Portalen gehoeren.
     * Ausschliesslich fuer /login und /logout -- Abmelden muss auch mit
     * kaputtem Token funktionieren.
     */
    public const ALLE = 'alle';

    private array $routes = [];

    /**
     * Registriert eine Route mit optionalen Meta-Daten.
     *
     * @param string $path       URI-Pfad, z.B. '/mitarbeiter/einsatz'
     * @param string $controller Vollqualifizierte Controller-Klasse
     * @param string $action     Name der Action-Methode im Controller
     * @param array  $options    ['auth' => false]        oeffentliche Route
     *                           ['portal' => Router::ALLE] portaluebergreifend
     */
    public function add(string $path, string $controller, string $action, array $options = []): void
    {
        $this->routes[$path] = [
            'controller' => $controller,
            'action'     => $action,
            'auth'       => $options['auth'] ?? true,     // Default: Auth erforderlich
            // Default aus dem Routen-Praefix -- eine Portalangabe kann nicht
            // vergessen werden, solange der Pfad die Konvention einhaelt.
            'portal'     => $options['portal'] ?? Portal::ausPfad($path),
        ];
    }

    /**
     * Dispatcht den Request zum entsprechenden Controller.
     *
     * Workflow:
     * 1. URI aus REQUEST_URI extrahieren und normalisieren
     * 2. APP_BASE bereinigen (Unterverzeichnis)
     * 3. Route nachschlagen
     * 4. Portalgrenze pruefen (Praefix der Route gegen typ aus dem Token)
     * 5. Controller instanziieren und Action aufrufen
     */
    public function dispatch(): void
    {
        // URI extrahieren
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // APP_BASE (Unterverzeichnis wie '/app', '/myapp') bereinigen
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }

        // Normalisieren: fuehrender Slash, Trailing Slash entfernen
        $uri = '/' . ltrim($uri, '/');
        $uri = ($uri !== '/' ? rtrim($uri, '/') : '/');

        // Route nachschlagen
        if (isset($this->routes[$uri])) {
            $route = $this->routes[$uri];

            // Portalgrenze -- beendet den Request selbst, falls nicht erlaubt
            $this->pruefePortal($uri, $route);

            // Controller laden und Action aufrufen
            $controller = new $route['controller']();
            $controller->{$route['action']}();
        } else {
            // Route nicht gefunden
            http_response_code(404);
            echo '<h1>404 Not Found</h1>';
        }
    }

    /**
     * Prueft, ob der Token die Route besuchen darf. Kehrt nur zurueck, wenn
     * der Zugriff erlaubt ist -- sonst wird umgeleitet bzw. abgewiesen und der
     * Request mit exit beendet.
     */
    private function pruefePortal(string $uri, array $route): void
    {
        $routePortal = $route['portal'];

        // Konventionsverstoss: Route ohne Portal-Praefix. Nie durchlassen --
        // sonst haette ein vergessenes Praefix stillschweigend keinen Schutz.
        if ($routePortal === null) {
            $this->konfigurationsfehler($uri);
        }

        // Portal des Anmelders -- lokal aus dem Token, kein Backend-Aufruf.
        // null = kein Token, kaputter Payload oder typ ohne Portal.
        $tokenPortal = Auth::portal();

        if ($tokenPortal === null) {
            if ($route['auth']) {
                // Login des Zielportals anbieten; ?login=1 oeffnet das Modal
                $ziel = Portal::start($routePortal === self::ALLE ? Portal::DEFAULT : $routePortal);
                $this->weiter($ziel . (str_contains($ziel, '?') ? '&' : '?') . 'login=1');
            }

            // Oeffentliche Route ohne Token -- normal rendern
            return;
        }

        // Portaluebergreifende Routen stehen jedem Angemeldeten offen
        if ($routePortal === self::ALLE) {
            return;
        }

        // Mitarbeiter duerfen jede Route besuchen
        if (Portal::darfAlles($tokenPortal)) {
            return;
        }

        if ($tokenPortal !== $routePortal) {
            // Der Router hat kein flashError() -- das sitzt im BaseController.
            // views/components/flash.php zeigt die Meldung auf der Zielseite.
            $_SESSION['flash_error'] = 'Diese Seite gehört nicht zu Ihrem Portal.';
            $this->weiter(Portal::start($tokenPortal));
        }
    }

    /**
     * Leitet innerhalb der App um (APP_BASE davor) und beendet den Request.
     */
    private function weiter(string $pfad): void
    {
        header('Location: ' . APP_BASE . $pfad);
        exit;
    }

    /**
     * Route ohne Portalangabe -- ein Fehler in config/routes.php.
     * In der Entwicklung im Klartext, in der Produktion als 404.
     */
    private function konfigurationsfehler(string $uri): void
    {
        if (defined('DEBUG') && DEBUG) {
            http_response_code(500);
            echo '<h1>500 Konfigurationsfehler</h1>';
            echo '<p>Die Route <code>' . htmlspecialchars($uri) . '</code> hat kein '
               . 'Portal-Pr&auml;fix und keine Option <code>portal</code>.</p>';
            echo '<p>Erlaubt sind die Pr&auml;fixe ';
            foreach (Portal::alle() as $i => $portalName) {
                echo ($i > 0 ? ', ' : '') . '<code>' . htmlspecialchars(Portal::praefix($portalName)) . '</code>';
            }
            echo ' sowie <code>\'portal\' =&gt; Router::ALLE</code> f&uuml;r '
               . 'portal&uuml;bergreifende Routen.</p>';
        } else {
            http_response_code(404);
            echo '<h1>404 Not Found</h1>';
        }
        exit;
    }
}
