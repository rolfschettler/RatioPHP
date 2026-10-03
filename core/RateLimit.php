<?php
/**
 * core/RateLimit.php -- Begrenzung von Versuchen pro Zeitfenster
 *
 * Schutz oeffentlicher Formulare gegen Bots und Brute-Force: Login,
 * Registrierung, Verfuegbarkeitspruefung, Mitarbeiternachweis.
 *
 * Gezaehlt wird je AKTION (z.B. 'login_ip') und SCHLUESSEL (z.B. die IP oder
 * der Hash eines Loginnamens). Das Zeitfenster gleitet: es zaehlen die
 * Versuche der letzten FENSTER Sekunden, kein fester Reset zur vollen Stunde.
 *
 * Zwei Muster:
 *
 *   1. Jeder Versuch zaehlt (Registrierung):
 *
 *        if (RateLimit::ueberschritten(Anfrage::ip(), 'registrierung', 3)) { ... }
 *
 *   2. Nur Fehlschlaege zaehlen (Login): VORAB zaehlen, bei Erfolg
 *      zuruecknehmen. Pruefen und Zaehlen passieren atomar in einem Schritt --
 *      bei "erst pruefen, nach Fehlschlag zaehlen" kaemen parallel abgesetzte
 *      Versuche alle durch die Luecke dazwischen.
 *
 *        if (RateLimit::ueberschritten($ip, 'login_ip', 20)) { ... }
 *        ... Login ...
 *        if ($erfolg) { RateLimit::zuruecknehmen($ip, 'login_ip'); }
 *
 * Ablage: JSON-Datei im System-Temp-Verzeichnis (sys_get_temp_dir()) --
 * bewusst NICHT im Web-Root, keine Datenbank (PHP hat laut Vorgabe keine
 * direkte DB-Anbindung). Zugriff per flock, damit parallele Requests sich
 * nicht gegenseitig umgehen. Kann die Datei nicht geoeffnet werden, wird
 * NICHT blockiert (fail-open) -- ein Dateiproblem soll Login und
 * Registrierung nicht lahmlegen.
 *
 * Zum Testen abschaltbar ueber RATE_LIMIT_AKTIV = false in index.php --
 * wirkt AUSSCHLIESSLICH zusammen mit DEBUG = true.
 */

namespace Core;

class RateLimit
{
    /** Laenge des Zeitfensters in Sekunden. */
    public const FENSTER = 3600;

    private const DATEI = 'ratiophp_ratelimit.json';

    /**
     * Prueft und zaehlt einen Versuch in einem Schritt.
     *
     * @param string $schluessel Wer -- IP oder schluessel() eines Namens.
     *                           Leer = nicht zuordenbar, wird nicht begrenzt.
     * @param string $aktion     Zaehler-Gruppe, z.B. 'login_ip'
     * @param int    $max        Erlaubte Versuche im Zeitfenster
     * @return bool true, wenn die Grenze bereits erreicht ist (Versuch NICHT
     *              gezaehlt) -- false, wenn der Versuch erlaubt war (gezaehlt)
     */
    public static function ueberschritten(string $schluessel, string $aktion, int $max): bool
    {
        $ueberschritten = false;

        self::bearbeite($schluessel, $aktion, static function (array $zeitpunkte) use ($max, &$ueberschritten): array {
            $ueberschritten = count($zeitpunkte) >= $max;
            if (!$ueberschritten) {
                $zeitpunkte[] = time();
            }
            return $zeitpunkte;
        });

        return $ueberschritten;
    }

    /**
     * Nimmt den zuletzt gezaehlten Versuch zurueck -- fuer Muster 2, wenn der
     * Versuch erfolgreich war und deshalb nicht zaehlen soll.
     */
    public static function zuruecknehmen(string $schluessel, string $aktion): void
    {
        self::bearbeite($schluessel, $aktion, static function (array $zeitpunkte): array {
            array_pop($zeitpunkte);
            return $zeitpunkte;
        });
    }

    /**
     * Loescht alle Versuche eines Schluessels -- z.B. den Zaehler eines
     * Kontos nach erfolgreicher Anmeldung.
     */
    public static function zuruecksetzen(string $schluessel, string $aktion): void
    {
        self::bearbeite($schluessel, $aktion, static fn(array $zeitpunkte): array => []);
    }

    /**
     * Schluessel fuer einen Namen (Loginname, Benutzername): SHA-256 der
     * Grossschreibung. Kein Klartext-Login auf Platte, und Gross-/
     * Kleinschreibung umgeht das Limit nicht -- der Login vergleicht per
     * UPPER().
     */
    public static function schluessel(string $name): string
    {
        return $name === '' ? '' : hash('sha256', mb_strtoupper($name));
    }

    /**
     * Ist das Rate-Limiting eingeschaltet?
     *
     * RATE_LIMIT_AKTIV = false wirkt nur zusammen mit DEBUG = true. Bleibt das
     * false versehentlich im Deployment stehen, greift das Limit dort
     * trotzdem -- vorausgesetzt DEBUG ist wie vorgesehen false. Fehlt die
     * Konstante ganz, ist das Limit aktiv.
     */
    public static function aktiv(): bool
    {
        $abgeschaltet = defined('RATE_LIMIT_AKTIV') && RATE_LIMIT_AKTIV === false;
        $entwicklung  = defined('DEBUG') && DEBUG === true;

        return !($abgeschaltet && $entwicklung);
    }

    /**
     * Liest die Zeitpunkte eines Zaehlers unter Dateisperre, laesst sie von
     * $aendern bearbeiten und schreibt das Ergebnis zurueck. Abgelaufene
     * Eintraege ALLER Schluessel der Aktion werden dabei entfernt -- sonst
     * waechst die Datei unbegrenzt.
     *
     * @param callable(int[]): int[] $aendern
     */
    private static function bearbeite(string $schluessel, string $aktion, callable $aendern): void
    {
        // Abgeschaltet oder nicht zuordenbar -- nichts pruefen, nichts zaehlen
        if (!self::aktiv() || $schluessel === '') {
            return;
        }

        $handle = fopen(sys_get_temp_dir() . '/' . self::DATEI, 'c+');
        if ($handle === false) {
            return;
        }

        flock($handle, LOCK_EX);

        $daten   = json_decode((string)stream_get_contents($handle), true);
        $daten   = is_array($daten) ? $daten : [];
        $grenze  = time() - self::FENSTER;
        $zaehler = [];

        foreach ((array)($daten[$aktion] ?? []) as $einSchluessel => $ts) {
            $ts = array_values(array_filter((array)$ts, static fn($t) => $t > $grenze));
            if ($ts) {
                $zaehler[$einSchluessel] = $ts;
            }
        }

        $neu = $aendern($zaehler[$schluessel] ?? []);
        if ($neu) {
            $zaehler[$schluessel] = array_values($neu);
        } else {
            unset($zaehler[$schluessel]);
        }
        $daten[$aktion] = $zaehler;

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($daten));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
