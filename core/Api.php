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

        // Kommt der Request per HTTPS herein, laeuft auch der Loopback ueber
        // HTTPS -- nur so passt SERVER_PORT zum Schema und beliebige Ports
        // funktionieren ohne Konfiguration (siehe index.php).
        //
        // Dabei steht am anderen Ende derselbe Apache mit seinem in aller Regel
        // selbstsignierten Zertifikat. Die Pruefung wird deshalb abgeschaltet --
        // aber AUSSCHLIESSLICH, wenn das Ziel nachweislich die eigene Maschine
        // ist. Ein Angreifer, der den Loopback belauschen koennte, sitzt bereits
        // auf dem Server; ausserhalb davon bleibt die Pruefung unangetastet.
        //
        // Zeigt API_ORIGIN_MANUELL auf einen fremden Host, greift dieser Zweig
        // NICHT -- dort wird das Zertifikat weiterhin geprueft.
        $ziel = parse_url(BASE_URL);
        $istLoopback = in_array($ziel['host'] ?? '', ['127.0.0.1', '::1', 'localhost'], true);

        if ($istLoopback && ($ziel['scheme'] ?? '') === 'https') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        // Zeitmessung fuer Debug-Panel
        $start     = microtime(true);
        $response  = curl_exec($ch);
        $ms        = (int) round((microtime(true) - $start) * 1000);
        $http      = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        $decoded = json_decode(is_string($response) ? $response : '', true);
        $decoded = is_array($decoded) ? $decoded : [];

        // Transportfehler NICHT verschlucken. Ohne diesen Block gibt die
        // Funktion bei einem nicht erreichbaren RATIOserver ein leeres Array
        // zurueck -- der Aufrufer sieht dann nur "Login fehlgeschlagen" und
        // kann einen Verbindungsfehler nicht von falschen Zugangsdaten
        // unterscheiden. Genau das hat eine Fehlersuche schon mehrere Runden
        // gekostet, als BASE_URL noch aus dem Request gebaut wurde.
        //
        // Der Rueckgabewert behaelt die uebliche Form mit message, damit die
        // Meldung ohne Sonderbehandlung in den Controllern ankommt.
        if ($curlErrno !== 0 || $response === false) {
            $klartext = 'RATIOserver nicht erreichbar unter ' . BASE_URL . $endpoint
                      . ' -- curl-Fehler ' . $curlErrno . ': ' . $curlError;

            if (defined('DEBUG') && DEBUG) {
                error_log($klartext);
            }

            return [
                'status'  => 'error',
                // In der Produktion keine internen Adressen und Ports preisgeben
                'message' => (defined('DEBUG') && DEBUG)
                    ? $klartext
                    // Echter Umlaut, keine HTML-Entity: die Meldung laeuft in
                    // views/components/flash.php durch htmlspecialchars()
                    : 'Der Server ist derzeit nicht erreichbar. Bitte versuchen Sie es später erneut.',
            ];
        }

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
