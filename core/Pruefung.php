<?php
/**
 * core/Pruefung.php -- Generische Pruefung von Formulareingaben
 *
 * Erzeugt BENUTZERFEHLER in einheitlicher Form "<Feld>: <Problem>." und
 * sammelt ALLE Fehler eines Formulars -- der Benutzer sieht sie gemeinsam im
 * Dialog statt einen nach dem anderen.
 *
 * Bevorzugter Weg -- eine Feld-Definition je Formularfeld, aus der sowohl die
 * Pruefung als auch die HTML-Attribute entstehen (keine Grenze steht doppelt):
 *
 *   private const FELDER = [
 *       'name1'    => ['bezeichnung' => 'Vorname', 'pflicht' => true, 'max_zeichen' => 30],
 *       'password' => ['bezeichnung' => 'Passwort', 'pflicht' => true, 'min_bytes' => 6, 'max_bytes' => 72, 'geheim' => true],
 *       'pwd_wdh'  => ['bezeichnung' => 'Passwort wiederholen', 'pflicht' => true, 'gleich' => 'password', 'geheim' => true],
 *   ];
 *
 *   $werte = Pruefung::werteAusPost(self::FELDER);           // Controller
 *   if (!Pruefung::formular(self::FELDER, $werte)->melde()) { ... }
 *
 *   <input name="name1" <?= Pruefung::htmlAttribute($felder['name1']) ?>>   // View
 *
 * Schluessel einer Feld-Definition:
 *   bezeichnung  Label im Formular und Feldname in der Fehlermeldung
 *   pflicht      bool         -- required
 *   max_zeichen  int          -- maxlength, mb_strlen (Zeichen, wie die DB)
 *   min_bytes    int          -- minlength, strlen (Passwoerter)
 *   max_bytes    int          -- maxlength, strlen (Bcrypt-Grenze)
 *   email        bool         -- gueltige E-Mail-Adresse
 *   ganzzahl     [min, max]   -- nur Ziffern im Bereich
 *   auswahl      string[]     -- einer der Werte
 *   gleich       string       -- muss dem Wert dieses Feldes entsprechen
 *                               (Wiederholung); alle weiteren Regeln gelten
 *                               ueber das Bezugsfeld
 *   geheim       bool         -- nicht trimmen, nie ins Formular zurueckgeben
 *   gross        bool         -- in Grossbuchstaben umwandeln
 *
 * Einzelne Regeln lassen sich auch direkt verketten:
 *
 *   $pruefung->feld('Vorname', $wert)->pflicht()->maxZeichen(30);
 *
 * Regeln:
 *   - Pro Feld zaehlt nur der ERSTE Fehler, weitere Regeln werden uebergangen.
 *   - Ein leeres Feld ist nur fuer pflicht() ein Fehler. Alle anderen Regeln
 *     gelten fuer optionale Felder nur, wenn etwas eingegeben wurde.
 *   - maxZeichen() zaehlt Zeichen (mb_strlen) -- die Datenbank zaehlt Zeichen.
 *     min/maxBytes() zaehlen Bytes (strlen) -- nur fuer Passwoerter (Bcrypt).
 */

namespace Core;

class Pruefung
{
    /** @var string[] Fehlertexte in Reihenfolge der Felder */
    private array $fehler = [];

    private string $bezeichnung = '';
    private string $wert        = '';
    private bool   $feldFehler  = false;

    // ------------------------------------------------------------------
    // Feld-Definitionen
    // ------------------------------------------------------------------

    /**
     * Liest die Werte aller definierten Felder aus $_POST: getrimmt, ausser
     * bei 'geheim', und in Grossbuchstaben bei 'gross'.
     *
     * @return array<string, string>
     */
    public static function werteAusPost(array $definitionen): array
    {
        $werte = [];
        foreach ($definitionen as $feld => $def) {
            $wert = (string)($_POST[$feld] ?? '');
            if (empty($def['geheim'])) {
                $wert = trim($wert);
            }
            if (!empty($def['gross'])) {
                $wert = mb_strtoupper($wert);
            }
            $werte[$feld] = $wert;
        }
        return $werte;
    }

    /**
     * Werte, die nach einem Fehler ins Formular zurueckgegeben werden
     * duerfen -- alles ausser den geheimen Feldern (Passwoerter).
     */
    public static function ohneGeheime(array $definitionen, array $werte): array
    {
        return array_filter(
            $werte,
            static fn($feld) => empty($definitionen[$feld]['geheim']),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Prueft alle Felder nach ihren Definitionen.
     */
    public static function formular(array $definitionen, array $werte): self
    {
        $pruefung = new self();

        foreach ($definitionen as $feld => $def) {
            $pruefung->feld($def['bezeichnung'] ?? $feld, (string)($werte[$feld] ?? ''));

            if (!empty($def['pflicht'])) {
                $pruefung->pflicht();
            }
            if (isset($def['gleich'])) {
                $bezug = $def['gleich'];
                $pruefung->gleich((string)($werte[$bezug] ?? ''), $definitionen[$bezug]['bezeichnung'] ?? $bezug);
                continue;
            }
            if (isset($def['auswahl'])) {
                $pruefung->auswahl($def['auswahl']);
            }
            if (!empty($def['email'])) {
                $pruefung->email();
            }
            if (isset($def['ganzzahl'])) {
                $pruefung->ganzzahl(...$def['ganzzahl']);
            }
            if (isset($def['max_zeichen'])) {
                $pruefung->maxZeichen($def['max_zeichen']);
            }
            if (isset($def['min_bytes'])) {
                $pruefung->minBytes($def['min_bytes']);
            }
            if (isset($def['max_bytes'])) {
                $pruefung->maxBytes($def['max_bytes']);
            }
        }

        return $pruefung;
    }

    /**
     * HTML-Gegenstueck der Regeln fuer das <input>: maxlength, minlength,
     * required. Bedienerfuehrung -- verbindlich ist die Pruefung im Controller.
     * Bei 'gleich' gelten die Laengen des Bezugsfeldes.
     *
     * @param array $definitionen Alle Definitionen (fuer 'gleich')
     */
    public static function htmlAttribute(array $definitionen, string $feld): string
    {
        $def    = $definitionen[$feld] ?? [];
        $laenge = isset($def['gleich']) ? ($definitionen[$def['gleich']] ?? []) : $def;
        $attr   = '';

        $max = $laenge['max_zeichen'] ?? $laenge['max_bytes'] ?? null;
        if ($max !== null) {
            $attr .= ' maxlength="' . (int)$max . '"';
        }
        if (isset($laenge['min_bytes'])) {
            $attr .= ' minlength="' . (int)$laenge['min_bytes'] . '"';
        }
        if (!empty($def['pflicht'])) {
            $attr .= ' required';
        }
        return $attr;
    }

    // ------------------------------------------------------------------
    // Einzelregeln
    // ------------------------------------------------------------------

    /**
     * Beginnt die Pruefung eines Feldes. Alle folgenden Regeln beziehen sich
     * auf dieses Feld, bis feld() erneut aufgerufen wird.
     */
    public function feld(string $bezeichnung, string $wert): self
    {
        $this->bezeichnung = $bezeichnung;
        $this->wert        = $wert;
        $this->feldFehler  = false;
        return $this;
    }

    public function pflicht(): self
    {
        return $this->regel(fn() => $this->wert !== '', 'Pflichtfeld', true);
    }

    public function maxZeichen(int $max): self
    {
        return $this->regel(fn() => mb_strlen($this->wert) <= $max, 'maximal ' . $max . ' Zeichen');
    }

    public function minBytes(int $min): self
    {
        return $this->regel(fn() => strlen($this->wert) >= $min, 'mindestens ' . $min . ' Zeichen');
    }

    public function maxBytes(int $max): self
    {
        return $this->regel(
            fn() => strlen($this->wert) <= $max,
            'höchstens ' . $max . ' Zeichen (Umlaute zählen doppelt)'
        );
    }

    public function email(): self
    {
        return $this->regel(
            fn() => filter_var($this->wert, FILTER_VALIDATE_EMAIL) !== false,
            'keine gültige E-Mail-Adresse'
        );
    }

    public function ganzzahl(int $min, int $max): self
    {
        return $this->regel(
            fn() => ctype_digit($this->wert) && (float)$this->wert >= $min && (float)$this->wert <= $max,
            'bitte eine gültige Zahl (nur Ziffern) angeben'
        );
    }

    /** @param string[] $erlaubt */
    public function auswahl(array $erlaubt): self
    {
        return $this->regel(fn() => in_array($this->wert, $erlaubt, true), 'bitte einen Eintrag auswählen', true);
    }

    public function gleich(string $anderer, string $andereBezeichnung): self
    {
        return $this->regel(fn() => $this->wert === $anderer, 'stimmt nicht mit ' . $andereBezeichnung . ' überein', true);
    }

    /**
     * Fuegt einen Fehler hinzu, der sich nicht als Feldregel ausdruecken
     * laesst (z.B. Benutzername bereits vergeben).
     */
    public function fehler(string $text): self
    {
        $this->fehler[] = $text;
        return $this;
    }

    /** Keine Fehler? */
    public function ok(): bool
    {
        return $this->fehler === [];
    }

    /** @return string[] */
    public function alle(): array
    {
        return $this->fehler;
    }

    /**
     * Meldet alle Fehler als Benutzerfehler (Dialog).
     *
     * @return bool true, wenn KEIN Fehler vorlag
     */
    public function melde(): bool
    {
        foreach ($this->fehler as $text) {
            Meldungen::melde(Meldungen::BENUTZER, $text);
        }
        return $this->ok();
    }

    /**
     * Wendet eine Regel auf das aktuelle Feld an.
     *
     * @param bool $auchLeer Regel auch bei leerem Wert anwenden
     */
    private function regel(callable $gueltig, string $problem, bool $auchLeer = false): self
    {
        if ($this->feldFehler || (!$auchLeer && $this->wert === '')) {
            return $this;
        }
        if (!$gueltig()) {
            $this->fehler[]   = $this->bezeichnung . ': ' . $problem . '.';
            $this->feldFehler = true;
        }
        return $this;
    }
}
