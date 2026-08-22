<?php
/**
 * core/Codec.php -- Portierung der Passwort-Codierung von RATIOserver
 *
 * Sinngemaesse Nachbildung von Codieren()/DeCodieren() aus
 *   D:\Delphi\RATIOserver\Shared\rechtelib.pas  (Zeile 130 bzw. 150)
 *
 * Original (Delphi):
 *
 *   For i := 1 to l do
 *     key := key + Chr((Ord(s[i - 1]) XOR (65 + i)));
 *
 * Also ein XOR mit einem positionsabhaengigen Schluessel, 1-basiert gezaehlt.
 * Die Rechnung ist symmetrisch -- Codieren und DeCodieren sind dieselbe
 * Operation. Der einzige Unterschied: DeCodieren ignoriert ein abschliessendes
 * '.' ("Sonderzeichen mit . abgeschlossen", Kommentar im Original).
 *
 * Verwendet fuer USERS.passwort (ftstring 20). Bei maximal 20 Zeichen bleibt
 * der Schluessel im Bereich 66..85, das Ergebnis also immer im Byte-Bereich --
 * die Portierung ist fuer dieses Feld exakt.
 *
 * OFFENER PUNKT -- Nicht-ASCII: Delphi rechnet auf UTF-16-Codeeinheiten, PHP
 * hier auf Bytes. Fuer ASCII-Passwoerter ist das identisch. Enthaelt ein
 * Passwort Umlaute, haengt das Ergebnis davon ab, wie RATIOserver den Wert im
 * JSON liefert -- dann ist ein Abgleich mit dem Original noetig.
 *
 * WICHTIG: Aendert sich rechtelib.pas, muss diese Datei nachgezogen werden.
 */

namespace Core;

class Codec
{
    /**
     * Entschluesselt einen codierten Wert (z.B. USERS.passwort).
     * Ein abschliessendes '.' wird ignoriert -- wie im Original.
     */
    public static function decodieren(string $wort): string
    {
        if ($wort === '') {
            return '';
        }

        $laenge = strlen($wort);

        // Sonderzeichen mit . abgeschlossen -- letztes Zeichen gehoert nicht dazu
        if (substr($wort, -1) === '.') {
            $laenge--;
        }

        return self::xorRechnung($wort, $laenge);
    }

    /**
     * Codiert einen Klartextwert. Gegenstueck zu decodieren(), ohne die
     * Sonderbehandlung des abschliessenden '.'.
     */
    public static function codieren(string $wort): string
    {
        if ($wort === '') {
            return '';
        }

        return self::xorRechnung($wort, strlen($wort));
    }

    /**
     * Die gemeinsame Rechnung: jedes Zeichen XOR (65 + Position), 1-basiert.
     */
    private static function xorRechnung(string $wort, int $laenge): string
    {
        $ergebnis = '';

        for ($i = 1; $i <= $laenge; $i++) {
            $ergebnis .= chr((ord($wort[$i - 1]) ^ (65 + $i)) & 0xFF);
        }

        return $ergebnis;
    }
}
