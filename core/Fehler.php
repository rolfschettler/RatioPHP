<?php
/**
 * core/Fehler.php -- Zentrale Fehlerbehandlung
 *
 * Unterscheidet zwei Fehlerarten, die getrennt angezeigt werden:
 *
 *   SYSTEMFEHLER   -- der Benutzer kann nichts dagegen tun: RATIOserver nicht
 *                     erreichbar, Datenbankfehler, fehlende Berechtigung auf
 *                     einen Endpunkt (HTTP 403), Seite oder Endpunkt existiert
 *                     nicht (404), abgelaufene Anmeldung (401), PHP-Ausnahme.
 *                     Meldung: Fehler::system()
 *
 *   BENUTZERFEHLER -- der Benutzer kann es selbst beheben: Pflichtfeld leer,
 *                     Feld zu lang, Registrierung abgelehnt, falsches Passwort.
 *                     Meldung: BaseController::flashError() bzw. Core\Pruefung
 *
 * Gespeichert und angezeigt werden beide ueber Core\Meldungen -- dort steht
 * auch, wo welche Art erscheint.
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
    /**
     * Endpunkte, bei denen HTTP 401 eine falsche Eingabe des Benutzers
     * bedeutet und keinen Systemfehler.
     */
    private const ANMELDE_ENDPUNKTE = ['/login'];

    /** Text fuer jeden nicht abgefangenen PHP-Fehler. */
    private const INTERNER_FEHLER = 'Es ist ein interner Fehler aufgetreten.';

    /**
     * Meldet einen Systemfehler fuer den reservierten Bereich und schreibt
     * die Details ins Fehlerprotokoll.
     *
     * @param string $meldung Text fuer den Benutzer -- ohne interne Details
     * @param string $detail  Technische Details, nur bei DEBUG sichtbar
     */
    public static function system(string $meldung, string $detail = ''): void
    {
        Meldungen::melde(Meldungen::SYSTEM, $meldung, $detail);
        error_log('Systemfehler: ' . $meldung . ($detail !== '' ? ' -- ' . $detail : ''));
    }

    // ------------------------------------------------------------------
    // API-Antworten
    // ------------------------------------------------------------------

    /**
     * Hat der Endpunkt den Vorgang ausgefuehrt? (status "OK")
     */
    public static function ok(array $antwort): bool
    {
        return ($antwort['status'] ?? '') === 'OK';
    }

    /**
     * Ist die Antwort von api_post() ein Systemfehler? Dann wurde er bereits
     * gemeldet -- der Controller darf ihn NICHT noch einmal als Dialog zeigen.
     */
    public static function istSystem(array $antwort): bool
    {
        return ($antwort['fehlerart'] ?? '') === Meldungen::SYSTEM;
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
            'fehlerart' => Meldungen::SYSTEM,
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
        set_exception_handler(static function (\Throwable $e): void {
            self::seite(500, self::INTERNER_FEHLER,
                get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        });

        // Fatale Fehler (Parse-Fehler, Speicher, Laufzeit) erreichen den
        // Exception-Handler nicht -- sie werden beim Beenden abgefangen
        register_shutdown_function(static function (): void {
            $f = error_get_last();
            if ($f !== null && ($f['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
                self::seite(500, self::INTERNER_FEHLER, $f['message'] . ' in ' . $f['file'] . ':' . $f['line']);
            }
        });
    }

    /**
     * Gibt eine vollstaendige Fehlerseite im Layout des passenden Portals aus
     * und beendet den Request. Die Meldung steht im reservierten
     * Systemfehler-Bereich, der Inhalt bietet nur den Weg zurueck.
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

        try {
            echo View::render('fehler/index', [
                'page_title' => $meldung,
                'portal'     => Portal::ausPfad(Anfrage::pfad()) ?? Portal::DEFAULT,
                'http'       => $httpStatus,
            ]);
        } catch (\Throwable $e) {
            // Letzte Rueckfallebene: auch das Layout ist kaputt
            error_log((string)$e);
            echo '<h1>' . $httpStatus . '</h1><p>' . htmlspecialchars($meldung) . '</p>';
        }
        exit;
    }
}
