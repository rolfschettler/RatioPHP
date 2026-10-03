<?php
/**
 * core/Fehler.php -- Zentrale Fehlerbehandlung
 *
 * Unterscheidet zwei Fehlerarten, die getrennt angezeigt werden:
 *
 *   SYSTEMFEHLER  -- der Benutzer kann nichts dagegen tun: RATIOserver nicht
 *                    erreichbar, Datenbankfehler, fehlende Berechtigung auf
 *                    einen Endpunkt (HTTP 403), Seite oder Endpunkt existiert
 *                    nicht (404), abgelaufene Anmeldung (401), PHP-Ausnahme.
 *                    Anzeige im reservierten Bereich unter dem Header
 *                    (views/components/systemfehler.php, id="app-systemfehler").
 *
 *   BENUTZERFEHLER -- der Benutzer kann es selbst beheben: Pflichtfeld leer,
 *                    Feld zu lang, Registrierung abgelehnt, falsches Passwort.
 *                    Anzeige als Dialog (views/components/fehler-dialog.php).
 *
 * Beide Arten liegen in der Session, damit sie einen Redirect ueberleben.
 * Benutzerfehler unter dem bisherigen Schluessel flash_error -- das
 * Login-Modal zeigt ihn bei ?login=1 selbst an.
 *
 * Klassifizierung der RATIOserver-Antworten (verifiziert gegen
 * D:\Delphi\RATIOserver\Shared\WebModuleUnit1.pas):
 *
 *   curl-Fehler              System   -- Server/Port nicht erreichbar
 *   HTTP 401                 System   -- Token fehlt/ungueltig/abgelaufen
 *                                        Ausnahme /login: falsches Passwort
 *   HTTP 403                 System   -- Rollenpruefung: "Keine Berechtigung
 *                                        fuer diesen Endpunkt."
 *   HTTP 404                 System   -- Endpunkt existiert nicht
 *   HTTP 400                 System   -- Datenbankfehler (EFDDBEngineException)
 *                                        bzw. Konfigurationsfehler im Server
 *   Meldung "[FireDAC]..."   System   -- Datenbankfehler, egal welcher Status
 *   keine JSON-Antwort       System   -- Server abgestuerzt / falsches Ziel
 *   HTTP 500 mit Meldung     Benutzer -- fachliche Ablehnung des Endpunkts,
 *                                        z.B. "Benutzer ist nicht im
 *                                        Personalstamm vorhanden."
 */

namespace Core;

class Fehler
{
    public const SYSTEM   = 'system';
    public const BENUTZER = 'benutzer';

    private const SESSION_SYSTEM   = 'systemfehler';
    private const SESSION_BENUTZER = 'flash_error';

    /**
     * Endpunkte, bei denen HTTP 401 eine falsche Eingabe des Benutzers
     * bedeutet und keinen Systemfehler.
     */
    private const ANMELDE_ENDPUNKTE = ['/login'];

    // ------------------------------------------------------------------
    // Melden
    // ------------------------------------------------------------------

    /**
     * Meldet einen Systemfehler fuer den reservierten Bereich.
     *
     * @param string $meldung Text fuer den Benutzer -- ohne interne Details
     * @param string $detail  Technische Details, nur bei DEBUG sichtbar
     */
    public static function system(string $meldung, string $detail = ''): void
    {
        $liste = $_SESSION[self::SESSION_SYSTEM] ?? [];

        // Dieselbe Meldung nur einmal -- die Details werden gesammelt.
        // Eine Seite mit vier gesperrten Endpunkten zeigt also EINE Zeile.
        if (!isset($liste[$meldung])) {
            $liste[$meldung] = [];
        }
        if ($detail !== '' && !in_array($detail, $liste[$meldung], true)) {
            $liste[$meldung][] = $detail;
        }

        $_SESSION[self::SESSION_SYSTEM] = $liste;

        if ($detail !== '') {
            error_log('Systemfehler: ' . $meldung . ' -- ' . $detail);
        }
    }

    /**
     * Meldet einen Benutzerfehler fuer den Fehler-Dialog.
     * Mehrere Meldungen im selben Request werden untereinander angezeigt.
     */
    public static function benutzer(string $meldung): void
    {
        $bisher = (string)($_SESSION[self::SESSION_BENUTZER] ?? '');
        $_SESSION[self::SESSION_BENUTZER] = $bisher === '' ? $meldung : $bisher . "\n" . $meldung;
    }

    // ------------------------------------------------------------------
    // Auslesen (fuer die View-Komponenten)
    // ------------------------------------------------------------------

    /**
     * Liefert die Systemfehler und loescht sie.
     *
     * @return array<string, string[]> Meldung => Details
     */
    public static function holeSystem(): array
    {
        $liste = $_SESSION[self::SESSION_SYSTEM] ?? [];
        unset($_SESSION[self::SESSION_SYSTEM]);
        return is_array($liste) ? $liste : [];
    }

    /**
     * Liefert den Benutzerfehler und loescht ihn -- '' wenn keiner vorliegt.
     */
    public static function holeBenutzer(): string
    {
        $meldung = (string)($_SESSION[self::SESSION_BENUTZER] ?? '');
        unset($_SESSION[self::SESSION_BENUTZER]);
        return $meldung;
    }

    // ------------------------------------------------------------------
    // API-Antworten
    // ------------------------------------------------------------------

    /**
     * Ist die Antwort von api_post() ein Systemfehler? Dann wurde er bereits
     * gemeldet -- der Controller darf ihn NICHT noch einmal als Dialog zeigen.
     */
    public static function istSystem(array $antwort): bool
    {
        return ($antwort['fehlerart'] ?? '') === self::SYSTEM;
    }

    /**
     * Bewertet eine RATIOserver-Antwort. Wird ausschliesslich von api_post()
     * aufgerufen -- deshalb bekommt jeder Endpunkt die Behandlung, ohne dass
     * ein Controller etwas tun muss.
     *
     * Bei einem Systemfehler wird er gemeldet und eine Antwort in der
     * ueblichen Form zurueckgegeben (status, message), ergaenzt um
     * fehlerart = 'system'. Sonst kommt die Antwort unveraendert zurueck.
     */
    public static function pruefeApiAntwort(
        string $endpoint,
        int $http,
        mixed $roh,
        ?array $antwort,
        int $curlErrno,
        string $curlError
    ): array {
        $pfad      = (string)parse_url($endpoint, PHP_URL_PATH);
        $nachricht = (string)($antwort['message'] ?? '');
        $meldung   = null;
        $detail    = $endpoint . ' -- HTTP ' . $http . ($nachricht !== '' ? ': ' . $nachricht : '');

        if ($curlErrno !== 0 || $roh === false) {
            $meldung = 'Der Server ist derzeit nicht erreichbar. Bitte versuchen Sie es später erneut.';
            $detail  = 'RATIOserver nicht erreichbar unter ' . BASE_URL . $endpoint
                     . ' -- curl-Fehler ' . $curlErrno . ': ' . $curlError;
        } elseif ($http === 401 && !in_array($pfad, self::ANMELDE_ENDPUNKTE, true)) {
            $meldung = 'Ihre Anmeldung ist ungültig oder abgelaufen. Bitte melden Sie sich neu an.';
        } elseif ($http === 403) {
            $meldung = 'Für diese Funktion fehlt Ihnen die Berechtigung. Bitte wenden Sie sich an Ihren Administrator.';
        } elseif ($http === 404) {
            $meldung = 'Eine benötigte Serverfunktion ist nicht verfügbar.';
        } elseif ($http === 400 || str_starts_with($nachricht, '[FireDAC]')) {
            $meldung = 'Bei der Datenbankabfrage ist ein Fehler aufgetreten.';
        } elseif ($antwort === null && $http >= 400) {
            $meldung = 'Der Server hat eine ungültige Antwort geliefert.';
        }

        if ($meldung === null) {
            return $antwort ?? [];
        }

        self::system($meldung, $detail);

        return [
            'status'    => 'error',
            'message'   => $meldung,
            'fehlerart' => self::SYSTEM,
            'http'      => $http,
        ];
    }

    // ------------------------------------------------------------------
    // Fehlerseite und PHP-Ausnahmen
    // ------------------------------------------------------------------

    /**
     * Registriert die Handler fuer nicht abgefangene Ausnahmen und fatale
     * PHP-Fehler. Aufruf einmal in index.php, direkt nach dem Autoloader.
     */
    public static function registriereHandler(): void
    {
        set_exception_handler([self::class, 'behandleAusnahme']);
        register_shutdown_function([self::class, 'behandleAbbruch']);
    }

    /**
     * Nicht abgefangene Ausnahme -- Fehlerseite mit Systemfehler.
     */
    public static function behandleAusnahme(\Throwable $e): void
    {
        error_log((string)$e);
        self::seite(
            500,
            'Es ist ein interner Fehler aufgetreten.',
            get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
        );
    }

    /**
     * Fatale PHP-Fehler (Parse-Fehler, Speicher, Laufzeit) erreichen den
     * Exception-Handler nicht -- sie werden hier beim Beenden abgefangen.
     */
    public static function behandleAbbruch(): void
    {
        $fehler = error_get_last();
        if ($fehler === null
            || !($fehler['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
            return;
        }

        self::seite(
            500,
            'Es ist ein interner Fehler aufgetreten.',
            $fehler['message'] . ' in ' . $fehler['file'] . ':' . $fehler['line']
        );
    }

    /**
     * Gibt eine vollstaendige Fehlerseite im Layout aus und beendet den
     * Request. Die Meldung steht im reservierten Systemfehler-Bereich, der
     * Inhalt bietet nur den Weg zurueck zur Portal-Startseite.
     */
    public static function seite(int $httpStatus, string $meldung, string $detail = ''): never
    {
        // Halb gerenderte Ausgabe verwerfen -- sonst steht die Fehlerseite
        // mitten in einer abgebrochenen Seite
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code($httpStatus);
        }

        self::system($meldung, $detail);
        $portal = self::portalAusRequest();

        try {
            echo View::render('fehler/index', [
                'page_title' => $meldung,
                'portal'     => $portal,
                'http'       => $httpStatus,
            ]);
        } catch (\Throwable $e) {
            // Letzte Rueckfallebene: auch das Layout ist kaputt
            error_log((string)$e);
            echo '<h1>' . $httpStatus . '</h1><p>' . htmlspecialchars($meldung) . '</p>';
            if (defined('DEBUG') && DEBUG && $detail !== '') {
                echo '<pre>' . htmlspecialchars($detail) . '</pre>';
            }
        }
        exit;
    }

    /**
     * Portal des aktuellen Requests aus dem Pfad -- damit eine Fehlerseite
     * im richtigen Portal (Header, Login, Startseite) erscheint.
     */
    private static function portalAusRequest(): string
    {
        $uri  = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $base = defined('APP_BASE') ? APP_BASE : '';
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $uri = '/' . ltrim($uri, '/');

        return Portal::ausPfad($uri) ?? Portal::DEFAULT;
    }
}
