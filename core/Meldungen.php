<?php
/**
 * core/Meldungen.php -- Einziger Speicher fuer alle Meldungen an den Benutzer
 *
 * Jede Meldung hat eine Art. Die Art legt fest, WO und WIE sie erscheint --
 * das steht ausschliesslich in ARTEN, nicht in Controllern oder Views:
 *
 *   SYSTEM    Systemfehler   -> reservierter Bereich unter dem Header
 *                               (views/components/systemfehler.php)
 *   BENUTZER  Benutzerfehler -> Dialog (views/components/fehler-dialog.php),
 *                               bzw. im gerade geoeffneten Modal (Login)
 *   ERFOLG    Erfolgsmeldung -> oben im Inhaltsbereich (views/components/flash.php)
 *
 * Gerendert werden alle Arten von derselben Komponente
 * views/components/meldungen.php.
 *
 * Meldungen liegen in der Session und ueberleben damit einen Redirect.
 * Gleiche Texte werden zusammengefasst, ihre Details gesammelt.
 *
 * Niemand ausser dieser Klasse greift auf die Session-Schluessel zu.
 */

namespace Core;

class Meldungen
{
    public const SYSTEM   = 'system';
    public const BENUTZER = 'benutzer';
    public const ERFOLG   = 'erfolg';

    /**
     * Darstellung je Art -- Bootstrap-Farbe, Icon und optionaler Praefix.
     * Neue Art = neuer Eintrag hier plus eine Stelle, die sie ausgibt.
     */
    public const ARTEN = [
        self::SYSTEM => [
            'farbe'   => 'danger',
            'icon'    => 'bi-exclamation-octagon-fill',
            'praefix' => 'Systemfehler:',
        ],
        self::BENUTZER => [
            'farbe'   => 'warning',
            'icon'    => 'bi-exclamation-triangle-fill',
            'praefix' => '',
        ],
        self::ERFOLG => [
            'farbe'   => 'success',
            'icon'    => 'bi-check-circle-fill',
            'praefix' => '',
        ],
    ];

    private const SESSION = 'meldungen';

    /**
     * Legt eine Meldung ab.
     *
     * @param string $art    Eine der Konstanten SYSTEM, BENUTZER, ERFOLG
     * @param string $text   Text fuer den Benutzer -- ohne interne Details
     * @param string $detail Technische Details, nur bei DEBUG sichtbar
     */
    public static function melde(string $art, string $text, string $detail = ''): void
    {
        if (!isset(self::ARTEN[$art])) {
            throw new \InvalidArgumentException('Unbekannte Meldungsart: ' . $art);
        }

        $details = $_SESSION[self::SESSION][$art][$text] ?? [];
        if ($detail !== '' && !in_array($detail, $details, true)) {
            $details[] = $detail;
        }
        $_SESSION[self::SESSION][$art][$text] = $details;
    }

    /**
     * Liegen Meldungen dieser Art vor?
     */
    public static function hat(string $art): bool
    {
        return !empty($_SESSION[self::SESSION][$art]);
    }

    /**
     * Liefert die Meldungen einer Art und loescht sie -- jede Meldung wird
     * genau einmal angezeigt.
     *
     * @return array<string, string[]> Text => Details
     */
    public static function hole(string $art): array
    {
        $liste = $_SESSION[self::SESSION][$art] ?? [];
        unset($_SESSION[self::SESSION][$art]);
        return is_array($liste) ? $liste : [];
    }
}
