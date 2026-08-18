<?php
/**
 * core/Api.php -- HTTP-Client fuer RATIOserver
 *
 * Globale Funktion api_post() -- in Controllern IMMER mit fuehrendem
 * Backslash aufrufen: \api_post(...).
 *
 * RATIOserver verwendet fuer ALLE Operationen HTTP-POST -- auch fuer
 * lesende Zugriffe. Der Token kommt IMMER aus dem Cookie jwt_token --
 * niemals aus der Session.
 */

if (!function_exists('api_post')) {
    /**
     * Sendet einen POST-Request mit JSON-Body an den RATIOserver.
     *
     * @param string $endpoint Endpunkt-Pfad, z.B. '/adressen/getAdressen'
     * @param array  $data     Request-Body als assoziatives Array
     * @return array           Dekodierte JSON-Antwort (leeres Array bei Fehler)
     */
    function api_post(string $endpoint, array $data): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, BASE_URL . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . ($_COOKIE['jwt_token'] ?? ''),
            'Content-Type: application/json',
        ]);

        // Zeitmessung fuer Debug-Panel
        $start    = microtime(true);
        $response = curl_exec($ch);
        $ms       = (int) round((microtime(true) - $start) * 1000);
        $http     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);
        $decoded = is_array($decoded) ? $decoded : [];

        // Debug-Logging -- nur wenn DEBUG aktiv. Das Debug-Panel
        // (views/components/debug.php) liest aus $GLOBALS['_api_debug_log'].
        if (defined('DEBUG') && DEBUG && isset($_GET['debug'])) {
            // Anzahl Datensaetze ermitteln -- Pagination (data.data) und
            // einfache Liste (data) beruecksichtigen
            $count = null;
            if (isset($decoded['data']['data']) && is_array($decoded['data']['data'])) {
                $count = count($decoded['data']['data']);
            } elseif (isset($decoded['data']) && is_array($decoded['data'])) {
                $count = count($decoded['data']);
            }

            $GLOBALS['_api_debug_log'][] = [
                'endpoint' => $endpoint,
                'request'  => $data,
                'response' => $decoded,
                'http'     => $http,
                'ms'       => $ms,
                'count'    => $count,
            ];
        }

        return $decoded;
    }
}
