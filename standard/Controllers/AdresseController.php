<?php
/**
 * standard/Controllers/AdresseController.php
 *
 * Eigene Adresse bearbeiten -- Kundenportal, geschuetzt (auth: true).
 *
 *   index()      GET  /kunde/adresse           -- Formular mit den aktuellen Daten
 *   speichern()  POST /kunde/adresse/speichern -- /adressen/updateadressen
 *
 * Bearbeitet wird ausschliesslich die Adresse, deren Kennziffer in
 * REGISTRIERUNG.kennziffer des angemeldeten Benutzers steht. Die Kennziffer
 * kommt aus dem Token (Core\Auth::kennziffer()) -- NIE aus dem Formular,
 * sonst koennte jeder eine fremde Adresse ueberschreiben. Zugaenge ohne
 * Kennziffer (typ mitarbeiter, fahrer, Kunden ohne Adresse) bekommen einen
 * Hinweis statt eines Formulars.
 *
 * Endpunkte (DataModulAdressenClass.pas):
 *   /adressen/getadressenbyid   Body { kennziffer, fields }
 *   /adressen/updateadressen    Body { kennziffer, <Felder> } -- Whitelist
 *                               gruppe, anrede, titel, name1, name2, strasse,
 *                               plz, ort, telefon1, email
 *
 * Beide Endpunkte verlangen die passende Rolle (@KUNDE in REGISTRIERUNG) --
 * fehlt sie, meldet api_post() den 403 als Systemfehler.
 */

namespace Standard\Controllers;

use Core\Auth;
use Core\BaseController;
use Core\Fehler;
use Core\Portal;
use Core\Pruefung;

class AdresseController extends BaseController
{
    /** Portal des Moduls -- bestimmt Layout, Navigation und Routen-Praefix. */
    private const PORTAL = 'kunde';

    /** Pfad des Moduls unterhalb des Portal-Praefix. */
    private const MODUL = '/adresse';

    /**
     * Bearbeitbare ADRESSEN-Spalten. Beschriftung, Pflicht und Grenzen kommen
     * aus RegistrierungController::FELDER -- dieselben Spalten wie bei der
     * Registrierung, deshalb keine zweite Definition.
     */
    private const SPALTEN = ['anrede', 'name1', 'name2', 'strasse', 'plz', 'ort'];

    /**
     * GET /kunde/adresse -- Formular mit der aktuellen Adresse.
     */
    public function index(): void
    {
        $kennziffer = Auth::kennziffer();
        if ($kennziffer === null) {
            $this->zeige([], 'Ihrem Zugang ist keine Adresse zugeordnet.');
            return;
        }

        $adresse = $this->ladeAdresse($kennziffer);
        if ($adresse === null) {
            // Fehler ist bereits gemeldet -- kein Formular ohne Daten zeigen
            $this->zeige([], 'Ihre Adresse kann im Moment nicht angezeigt werden.');
            return;
        }
        if ($adresse === []) {
            $this->zeige([], 'Die Ihrem Zugang zugeordnete Adresse wurde nicht gefunden.');
            return;
        }

        $this->zeige($adresse);
    }

    /**
     * POST /kunde/adresse/speichern -- schreibt die Adresse ueber
     * /adressen/updateadressen. Bei Fehlern erscheint das Formular erneut
     * mit den Eingaben.
     */
    public function speichern(): void
    {
        $basis = Portal::praefix(self::PORTAL) . self::MODUL;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect($basis);
        }

        $kennziffer = Auth::kennziffer();
        if ($kennziffer === null) {
            $this->redirect($basis);
        }

        $felder = self::felder();
        $werte  = Pruefung::werteAusPost($felder);

        if (!Pruefung::formular($felder, $werte)->melde()) {
            $this->zeige($werte);
            return;
        }

        $antwort = \api_post('/adressen/updateadressen', ['kennziffer' => $kennziffer] + $werte);

        if (!Fehler::ok($antwort)) {
            $this->apiFehler($antwort, 'Ihre Adresse konnte nicht gespeichert werden.');
            $this->zeige($werte);
            return;
        }

        $this->flashSuccess('Ihre Adresse wurde gespeichert.');
        $this->redirect($basis);
    }

    /**
     * Liest die Adresse. null, wenn der Endpunkt einen Fehler meldet (bereits
     * gemeldet) -- ein leeres Array, wenn es zur Kennziffer keinen Satz gibt.
     */
    private function ladeAdresse(int $kennziffer): ?array
    {
        $antwort = \api_post('/adressen/getadressenbyid', [
            'kennziffer' => $kennziffer,
            'fields'     => self::SPALTEN,
        ]);

        if (($antwort['status'] ?? '') === 'error') {
            $this->apiFehler($antwort, 'Ihre Adresse konnte nicht geladen werden.');
            return null;
        }

        $satz = $antwort['data'][0] ?? [];

        // Werte als Strings -- NULL-Spalten ergeben leere Felder
        return array_map(static fn($wert) => (string)($wert ?? ''), $satz);
    }

    /**
     * Rendert die Seite.
     *
     * @param array       $eingaben Werte fuer die Felder (aus der DB oder dem POST)
     * @param string|null $hinweis  Statt des Formulars anzeigen -- wenn es
     *                              nichts zu bearbeiten gibt
     */
    private function zeige(array $eingaben, ?string $hinweis = null): void
    {
        $this->render('adresse/index', [
            'page_title'      => 'Meine Adresse',
            'portal'          => self::PORTAL,
            'felder'          => self::felder(),
            'eingaben'        => $eingaben,
            'hinweis'         => $hinweis,
            'formular_action' => Portal::praefix(self::PORTAL) . self::MODUL . '/speichern',
        ]);
    }

    /** Feld-Definitionen der bearbeitbaren Spalten, in Formularreihenfolge. */
    private static function felder(): array
    {
        return array_intersect_key(RegistrierungController::FELDER, array_flip(self::SPALTEN));
    }
}
