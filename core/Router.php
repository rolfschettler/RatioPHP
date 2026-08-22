<?php
/**
 * Router-Implementierung (core/Router.php)
 *
 * Diese Datei 1:1 nach core/Router.php kopieren -- nie neu generieren.
 */

namespace Core;

class Router
{
    private array $routes = [];

    /**
     * Registriert eine Route mit optionalen Meta-Daten.
     *
     * @param string $path       URI-Pfad, z.B. '/login', '/dashboard'
     * @param string $controller Vollqualifizierte Controller-Klasse
     * @param string $action     Name der Action-Methode im Controller
     * @param array  $options    Optional: ['auth' => false] fuer oeffentliche Routes
     */
    public function add(string $path, string $controller, string $action, array $options = []): void
    {
        $this->routes[$path] = [
            'controller' => $controller,
            'action'     => $action,
            'auth'       => $options['auth'] ?? true  // Default: Auth erforderlich
        ];
    }

    /**
     * Dispatcht den Request zum entsprechenden Controller.
     *
     * Workflow:
     * 1. URI aus REQUEST_URI extrahieren und normalisieren
     * 2. APP_BASE bereinigen (Unterverzeichnis)
     * 3. Route nachschlagen
     * 4. Falls Auth erforderlich: Token-Pruefung via Cookie
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

            // Auth-Check: Token kommt aus Cookie -- NICHT aus Session
            if ($route['auth'] && empty($_COOKIE['jwt_token'])) {
                header('Location: ' . APP_BASE . '/mitarbeiter?login=1');
                exit;
            }

            // Controller laden und Action aufrufen
            $controller = new $route['controller']();
            $controller->{$route['action']}();
        } else {
            // Route nicht gefunden
            http_response_code(404);
            echo '<h1>404 Not Found</h1>';
        }
    }
}
