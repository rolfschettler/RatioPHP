<?php
/**
 * standard/Controllers/RegistrierungsverwaltungController.php
 *
 * Verwaltung der Tabelle REGISTRIERUNG -- Mitarbeiterportal. Zwei Ansichten,
 * umgeschaltet ueber Reiter im Seitenkopf (?ansicht=):
 *
 *   Zugaenge (Default)
 *     index()            Liste mit Typ-Filter und Suche (Benutzername / E-Mail)
 *     speichern()        E-Mail, Sperre, Rollen und optional Passwort aendern
 *     loeschen()         Registrierung loeschen
 *
 *   Rollenvorlagen (?ansicht=vorlagen)
 *     index()            Liste der Rollen-Blaupausen mit Verwendung
 *     vorlageAnlegen()   neue Blaupause '@NAME' anlegen
 *     vorlageSpeichern() Rollen einer Blaupause aendern
 *     vorlageLoeschen()  eigene, unbenutzte Blaupause loeschen
 *
 * Datenquelle ist ausschliesslich REGISTRIERUNG (Endpunkte unter
 * /registrierung/). Die E-Mail steht dort in der eigenen Spalte email.
 *
 * typ wird NICHT geaendert -- ein Trigger in der Datenbank verbietet
 * Typaenderungen. Der Typ ist im Dialog nur Anzeige.
 *
 * Filter und Suche laufen im Speicher: getregistrierungfiltered kennt nur
 * Gleichheitsbedingungen, die Suche braucht aber Teilstrings. Die Tabelle ist
 * klein (ein Satz je Portalzugang), ein einziger Abruf genuegt.
 *
 * Rollen-Blaupausen (username beginnt mit '@', z.B. '@MITARBEITER') sind keine
 * Portalzugaenge (typ NULL, kein Passwort). Die Zugangs-Aktionen lehnen sie ab,
 * die Vorlagen-Aktionen umgekehrt alles andere -- auch per manipuliertem POST.
 * Der Login loest '@XYZ' in die Rollen des Blaupausen-Satzes auf
 * (ResolveRoleBlueprints in DataModulLoginClass.pas):
 *   - keine Verschachtelung -- '@'-Eintraege IN einer Blaupause wirken nicht,
 *     deshalb werden sie hier abgelehnt
 *   - '@XYZ' bleibt selbst in der Liste -- eine leere oder fehlende Blaupause
 *     ergibt also KEINE Rechte (nicht "voller Zugriff"), leer ist erlaubt
 *   - insertregistrierung(local) weist neuen Zugaengen '@' + UPPER(typ) zu.
 *     Diese Systemvorlagen (je Portal eine) sind daher nicht loeschbar.
 *
 * Rollen (REGISTRIERUNG.rollen, Blob):
 *   - Lesen liefert base64, Schreiben nimmt Klartext. Eine Rolle je Zeile.
 *   - Das Backend trennt an Zeilenumbruch, Komma und Semikolon und vergleicht
 *     ohne Gross-/Kleinschreibung (NormalizeRoleList in webUtils.pas).
 *   - LEERE Rollen eines ZUGANGS bedeuten im Backend VOLLEN Zugriff
 *     (HasRouteAccess). Deshalb laesst speichern() keine leere Rollenliste zu.
 *   - Ein Rollenfilter fuer die Liste ist eine weitere Bedingung in filtere().
 */

namespace Standard\Controllers;

use Core\BaseController;
use Core\Fehler;
use Core\Portal;
use Core\Pruefung;

class RegistrierungsverwaltungController extends BaseController
{
    /** Portal des Moduls -- bestimmt Layout, Navigation und Routen-Praefix. */
    private const PORTAL = 'mitarbeiter';

    /** Pfad des Moduls unterhalb des Portal-Praefix. */
    private const MODUL = '/registrierungen';

    /** Wert von ?ansicht= fuer die Rollenvorlagen; leer = Zugaenge. */
    private const ANSICHT_VORLAGEN = 'vorlagen';

    /** Maximale Breite der Seite (Kopf, Toolbar, Inhalt) in Pixel, zentriert. */
    private const BREITE_MAX = 1600;

    /** Spalten der Liste. */
    private const LISTENFELDER = [
        'nr', 'kennziffer', 'username', 'email', 'typ', 'rollen',
        'gesperrt', 'erstellt', 'letzter_login',
    ];

    /**
     * Typ-Filterwert fuer Saetze ohne gueltigen typ (NULL, Legacy 'Standard').
     * Bewusst kein Portalname -- kann mit keinem kollidieren.
     */
    private const TYP_OHNE = '-';

    /**
     * Sortierbare Spalten der Benutzerliste (?sort=) => Art des Sortiersymbols
     * ('alpha' oder 'numeric', Bootstrap-Icons bi-sort-<art>-...), dazu die
     * Richtungen (?richtung=). Default: erste Spalte aufsteigend.
     * letzter_login kommt als 'Y-m-d H:i:s' -- als Text schon chronologisch.
     */
    private const SORTIERBAR = ['username' => 'alpha', 'typ' => 'alpha', 'letzter_login' => 'numeric'];
    private const RICHTUNGEN = ['auf', 'ab'];

    /** REGISTRIERUNG.gesperrt: 'JA' sperrt (Login: SameText 'JA'), sonst offen. */
    private const GESPERRT = ['NEIN' => 'Aktiv', 'JA' => 'Gesperrt'];

    /**
     * Suchfeld -- durchsucht username (120) und email (60), die groessere
     * Spalte bestimmt die Grenze. Reiner Anzeigefilter, kein Insert.
     */
    private const SUCHE_MAX = 120;

    /**
     * Gueltiger Rollenname: Buchstaben, Ziffern und die Zeichen eines
     * Endpunkts ('/dispo/*'). Keine Trenner (Zeilenumbruch, Komma, Semikolon)
     * -- die zerlegen im Backend die Liste. Bei Zugaengen ist zusaetzlich ein
     * '@' am Anfang erlaubt (Verweis auf eine Blaupause), in Blaupausen nicht.
     */
    private const ROLLE_MAX           = 100;
    private const ROLLE_ZEICHEN       = '[A-Za-z0-9_./*-]{1,' . self::ROLLE_MAX . '}';
    private const ROLLE_MUSTER        = '~^@?' . self::ROLLE_ZEICHEN . '$~';
    private const VORLAGE_ROLLE_MUSTER = '~^' . self::ROLLE_ZEICHEN . '$~';

    /** Immer anbietbar, auch wenn noch kein Zugang sie hat -- darf alles. */
    private const ROLLE_SUPERVISOR = 'supervisor';

    /** Name einer Blaupause ohne das fuehrende '@' (wird grossgeschrieben). */
    private const VORLAGE_NAME_MUSTER = '~^[A-Z0-9_-]+$~';

    /**
     * Felder des Bearbeiten-Dialogs. Die Rollen (rollen[], eine Mehrfachauswahl
     * aus der Autocomplete-Liste) prueft pruefeRollen().
     * Die Auswahl fuer gesperrt setzt felder().
     *
     * passwort_neu ist optional -- leer = Passwort bleibt. Grenzen wie bei der
     * Registrierung (RegistrierungController::FELDER): Bcrypt verarbeitet nur
     * 72 Bytes, deshalb ablehnen statt still abschneiden.
     */
    private const FELDER = [
        'nr'               => ['bezeichnung' => 'Nr.', 'pflicht' => true, 'ganzzahl' => [1, 2147483647], 'max_zeichen' => 10],
        'email'            => ['bezeichnung' => 'E-Mail', 'email' => true, 'max_zeichen' => 60],
        'gesperrt'         => ['bezeichnung' => 'Status', 'pflicht' => true],
        'passwort_neu'     => ['bezeichnung' => 'Neues Passwort', 'min_bytes' => 6, 'max_bytes' => 72, 'geheim' => true],
        'passwort_neu_wdh' => ['bezeichnung' => 'Passwort wiederholen', 'gleich' => 'passwort_neu', 'geheim' => true],
    ];

    /**
     * Name im Neu-Dialog -- ohne '@', das steht fest davor.
     * REGISTRIERUNG.username hat 120 Zeichen, eines davon belegt das '@'.
     */
    private const VORLAGE_FELDER = [
        'name' => ['bezeichnung' => 'Name', 'pflicht' => true, 'max_zeichen' => 119, 'gross' => true],
    ];

    /**
     * GET /mitarbeiter/registrierungen
     *
     * Parameter (GET):
     *   ansicht  ''|ANSICHT_VORLAGEN
     *   typ      Portalname, TYP_OHNE oder leer (= alle)        -- nur Zugaenge
     *   suche    Teilstring in Benutzername ODER E-Mail          -- nur Zugaenge
     *   sort     Spalte aus SORTIERBAR, Default username          -- nur Zugaenge
     *   richtung 'auf'|'ab', Default 'auf'                        -- nur Zugaenge
     */
    public function index(): void
    {
        // Systemfehler ist bereits gemeldet -- dann eben eine leere Liste
        $alle = $this->ladeAlle(self::LISTENFELDER) ?? [];

        $vorlagen = [];
        $zugaenge = [];
        $vergeben = [];
        foreach ($alle as $r) {
            array_push($vergeben, ...$r['rollen_liste']);
            if ($this->istBlaupause($r)) {
                $vorlagen[] = $r;
            } else {
                $zugaenge[] = $r;
            }
        }

        if (($_GET['ansicht'] ?? '') === self::ANSICHT_VORLAGEN) {
            $this->zeigeVorlagen($vorlagen, $zugaenge, $vergeben);
            return;
        }

        $fTyp   = (string)($_GET['typ'] ?? '');
        $fSuche = mb_substr(trim((string)($_GET['suche'] ?? '')), 0, self::SUCHE_MAX);

        if ($fTyp !== self::TYP_OHNE && !in_array($fTyp, Portal::alle(), true)) {
            $fTyp = '';
        }

        [$fSort, $fRichtung] = $this->sortierung($_GET['sort'] ?? '', $_GET['richtung'] ?? '');

        $rows = $this->sortiere($this->filtere($zugaenge, $fTyp, $fSuche), $fSort, $fRichtung);

        $this->render('registrierungsverwaltung/index', [
            'page_title'    => 'Registrierungen',
            'portal'        => self::PORTAL,
            'page_header'   => $this->buildKopf('', count($rows) . ' von ' . count($zugaenge)),
            'toolbar'       => $this->begrenzt($this->buildToolbar($fTyp, $fSuche, $fSort, $fRichtung)),
            'breiteMax'     => self::BREITE_MAX,
            'rows'          => $rows,
            'fSuche'        => $fSuche,
            'fSort'         => $fSort,
            'fRichtung'     => $fRichtung,
            'sortLinks'     => $this->sortLinks($fTyp, $fSuche, $fSort, $fRichtung),
            'typen'         => $this->typLabels(),
            'gesperrtWerte' => self::GESPERRT,
            'rollenVorschlaege' => $this->rollenVorschlaege(
                array_map(fn($v) => mb_strtoupper((string)$v['username']), $vorlagen),
                $vergeben
            ),
            'rolleMax'      => self::ROLLE_MAX,
            // Dasselbe Muster fuer die Eingabehilfe im Browser, ohne Begrenzer
            'rolleMuster'   => substr(self::ROLLE_MUSTER, 1, -1),
            'felder'        => $this->felder(),
            'modul_url'     => Portal::praefix(self::PORTAL) . self::MODUL,
            'eigener'       => mb_strtoupper((string)($_COOKIE['jwt_user'] ?? '')),
        ]);
    }

    /**
     * POST /mitarbeiter/registrierungen/speichern -- E-Mail, Sperre, Rollen
     * und, falls eingegeben, ein neues Passwort (auch am eigenen Zugang --
     * das sperrt niemanden aus, der Benutzer kennt das neue Passwort ja).
     */
    public function speichern(): void
    {
        $zurueck = $this->zurueck();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect($zurueck);
        }

        $felder   = $this->felder();
        $werte    = Pruefung::werteAusPost($felder);
        $pruefung = Pruefung::formular($felder, $werte);
        $rollen   = $this->pruefeRollen($pruefung, false);
        if (!$pruefung->melde()) {
            $this->redirect($zurueck);
        }

        $satz = $this->ladeSatz((int)$werte['nr'], false);
        if ($satz === null) {
            $this->redirect($zurueck);
        }

        // Schutz vor dem Aussperren: am eigenen Zugang weder Sperre noch
        // Rollen aendern -- beides griffe beim naechsten Login. Die E-Mail
        // bleibt aenderbar.
        if ($this->istEigener($satz)
            && ($werte['gesperrt'] === 'JA'
                || $this->schluessel($rollen) !== $this->schluessel($this->rollen($satz['rollen'] ?? null)))) {
            $this->flashError('Am eigenen Zugang können Sie weder Sperre noch Rollen ändern.');
            $this->redirect($zurueck);
        }

        $daten = [
            'nr'       => (int)$werte['nr'],
            // Leere E-Mail als NULL -- wie bei Saetzen, die nie eine hatten
            'email'    => $werte['email'] !== '' ? $werte['email'] : null,
            'gesperrt' => $werte['gesperrt'],
            // Klartext, eine Rolle je Zeile (gelesen wird base64)
            'rollen'   => implode("\r\n", $rollen),
        ];

        // Neues Passwort nur, wenn eingegeben. /login prueft pwd2 per
        // password_verify -- derselbe Hash wie bei der Registrierung.
        $neuesPasswort = $werte['passwort_neu'] !== '';
        if ($neuesPasswort) {
            $daten['pwd2'] = password_hash($werte['passwort_neu'], PASSWORD_DEFAULT);
        }

        $antwort = \api_post('/registrierung/updateregistrierung', $daten);

        if (Fehler::ok($antwort)) {
            $this->flashSuccess('Registrierung „' . ($satz['username'] ?? '') . '“ wurde gespeichert'
                . ($neuesPasswort ? ', das Passwort wurde neu gesetzt.' : '.'));
        } else {
            $this->apiFehler($antwort, 'Die Registrierung konnte nicht gespeichert werden.');
        }
        $this->redirect($zurueck);
    }

    /**
     * POST /mitarbeiter/registrierungen/loeschen -- Registrierung loeschen.
     */
    public function loeschen(): void
    {
        $zurueck = $this->zurueck();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect($zurueck);
        }

        $nr = $this->nrAusPost();
        if ($nr === null) {
            $this->redirect($zurueck);
        }

        $satz = $this->ladeSatz($nr, false);
        if ($satz === null) {
            $this->redirect($zurueck);
        }

        if ($this->istEigener($satz)) {
            $this->flashError('Den eigenen Zugang können Sie nicht löschen.');
            $this->redirect($zurueck);
        }

        $antwort = \api_post('/registrierung/deleteregistrierung', ['nr' => $nr]);

        if (Fehler::ok($antwort)) {
            $this->flashSuccess('Registrierung „' . ($satz['username'] ?? '') . '“ wurde gelöscht.');
        } else {
            $this->apiFehler($antwort, 'Die Registrierung konnte nicht gelöscht werden.');
        }
        $this->redirect($zurueck);
    }

    /**
     * POST /mitarbeiter/registrierungen/vorlage-anlegen -- neue Blaupause.
     *
     * insertregistrierung nimmt rollen nicht an (Whitelist), deshalb zwei
     * Schritte: Satz anlegen (ohne typ -- sonst wuerde ihm selbst eine Vorlage
     * zugewiesen), nr ueber den Namen ermitteln, Rollen per Update setzen.
     */
    public function vorlageAnlegen(): void
    {
        $zurueck = $this->zurueck(true);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect($zurueck);
        }

        $werte = Pruefung::werteAusPost(self::VORLAGE_FELDER);
        // Ein mit eingetipptes '@' ist harmlos -- es steht ohnehin fest davor
        $werte['name'] = ltrim($werte['name'], '@');
        $pruefung = Pruefung::formular(self::VORLAGE_FELDER, $werte);
        if ($werte['name'] !== '' && !preg_match(self::VORLAGE_NAME_MUSTER, $werte['name'])) {
            $pruefung->fehler('Name: erlaubt sind nur Buchstaben A–Z, Ziffern, _ und -.');
        }
        $rollen = $this->pruefeRollen($pruefung, true);
        if (!$pruefung->melde()) {
            $this->redirect($zurueck);
        }

        $name = '@' . $werte['name'];

        // Dublette ohne Gross-/Kleinschreibung -- wie /login und die Aufloesung
        $alle = $this->ladeAlle(['nr', 'username']);
        if ($alle === null) {
            $this->redirect($zurueck);
        }
        foreach ($alle as $r) {
            if (mb_strtoupper((string)($r['username'] ?? '')) === $name) {
                $this->flashError('Name: die Vorlage „' . $name . '“ gibt es bereits.');
                $this->redirect($zurueck);
            }
        }

        $antwort = \api_post('/registrierung/insertregistrierung', ['username' => $name]);
        if (!Fehler::ok($antwort)) {
            $this->apiFehler($antwort, 'Die Vorlage konnte nicht angelegt werden.');
            $this->redirect($zurueck);
        }

        $neu = \api_post('/registrierung/getregistrierungfiltered', [
            'fields'   => ['nr'],
            'username' => $name,
        ]);
        $nr = (int)($neu['data'][0]['nr'] ?? 0);

        $antwort = $nr > 0
            ? \api_post('/registrierung/updateregistrierung', ['nr' => $nr, 'rollen' => implode("\r\n", $rollen)])
            : $neu;

        if ($nr > 0 && Fehler::ok($antwort)) {
            $this->flashSuccess('Vorlage „' . $name . '“ wurde angelegt.');
        } else {
            $this->apiFehler($antwort, 'Die Vorlage „' . $name . '“ wurde angelegt, ihre Rollen konnten aber nicht gespeichert werden.');
        }
        $this->redirect($zurueck);
    }

    /**
     * POST /mitarbeiter/registrierungen/vorlage-speichern -- Rollen einer Blaupause.
     */
    public function vorlageSpeichern(): void
    {
        $zurueck = $this->zurueck(true);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect($zurueck);
        }

        $felder   = ['nr' => self::FELDER['nr']];
        $werte    = Pruefung::werteAusPost($felder);
        $pruefung = Pruefung::formular($felder, $werte);
        $rollen   = $this->pruefeRollen($pruefung, true);
        if (!$pruefung->melde()) {
            $this->redirect($zurueck);
        }

        $satz = $this->ladeSatz((int)$werte['nr'], true);
        if ($satz === null) {
            $this->redirect($zurueck);
        }

        // Nur rollen -- username, typ und alles andere bleiben unberuehrt
        $antwort = \api_post('/registrierung/updateregistrierung', [
            'nr'     => (int)$werte['nr'],
            'rollen' => implode("\r\n", $rollen),
        ]);

        if (Fehler::ok($antwort)) {
            $this->flashSuccess('Vorlage „' . ($satz['username'] ?? '') . '“ wurde gespeichert.');
        } else {
            $this->apiFehler($antwort, 'Die Vorlage konnte nicht gespeichert werden.');
        }
        $this->redirect($zurueck);
    }

    /**
     * POST /mitarbeiter/registrierungen/vorlage-loeschen -- eigene Blaupause.
     *
     * Systemvorlagen nie, eigene nur, wenn kein Zugang sie verwendet. Eine
     * geloeschte, aber noch zugewiesene Vorlage gaebe zwar keine Rechte statt
     * vollem Zugriff -- die Zugaenge verloeren ihre Rechte aber stillschweigend.
     */
    public function vorlageLoeschen(): void
    {
        $zurueck = $this->zurueck(true);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect($zurueck);
        }

        $nr = $this->nrAusPost();
        if ($nr === null) {
            $this->redirect($zurueck);
        }

        $satz = $this->ladeSatz($nr, true);
        if ($satz === null) {
            $this->redirect($zurueck);
        }

        $name = mb_strtoupper((string)($satz['username'] ?? ''));
        if (in_array($name, $this->systemVorlagen(), true)) {
            $this->flashError('Die Vorlage „' . $name . '“ ist eine Systemvorlage und kann nicht gelöscht werden.');
            $this->redirect($zurueck);
        }

        $alle = $this->ladeAlle(['nr', 'username', 'rollen']);
        if ($alle === null) {
            $this->redirect($zurueck);
        }
        $anzahl = $this->verwendung(array_filter($alle, fn($r) => !$this->istBlaupause($r)))[$name] ?? 0;
        if ($anzahl > 0) {
            $this->flashError('Die Vorlage „' . $name . '“ wird noch von ' . $anzahl
                . ($anzahl === 1 ? ' Zugang' : ' Zugängen') . ' verwendet und kann nicht gelöscht werden.');
            $this->redirect($zurueck);
        }

        $antwort = \api_post('/registrierung/deleteregistrierung', ['nr' => $nr]);

        if (Fehler::ok($antwort)) {
            $this->flashSuccess('Vorlage „' . $name . '“ wurde gelöscht.');
        } else {
            $this->apiFehler($antwort, 'Die Vorlage konnte nicht gelöscht werden.');
        }
        $this->redirect($zurueck);
    }

    // ------------------------------------------------------------------
    // Ansicht Rollenvorlagen
    // ------------------------------------------------------------------

    /**
     * Rendert die Ansicht Rollenvorlagen. Systemvorlagen stehen vorn, in der
     * Reihenfolge der Portale; fehlt eine in der Datenbank, erscheint sie als
     * Zeile ohne nr ("nicht angelegt").
     *
     * @param array    $vorlagen Blaupausen-Saetze (mit rollen_liste)
     * @param array    $zugaenge alle anderen Saetze (mit rollen_liste)
     * @param string[] $vergeben Rollen aller Saetze
     */
    private function zeigeVorlagen(array $vorlagen, array $zugaenge, array $vergeben): void
    {
        $verwendung = $this->verwendung($zugaenge);
        $system     = $this->systemVorlagen();

        $nachName = [];
        foreach ($vorlagen as $v) {
            $nachName[mb_strtoupper((string)$v['username'])] = $v;
        }

        $rows = [];
        foreach ($system as $name) {
            $rows[] = $this->vorlageZeile($name, $nachName[$name] ?? null, true, $verwendung);
            unset($nachName[$name]);
        }
        ksort($nachName, SORT_NATURAL);
        foreach ($nachName as $name => $v) {
            $rows[] = $this->vorlageZeile($name, $v, false, $verwendung);
        }

        $this->render('registrierungsverwaltung/vorlagen', [
            'page_title'  => 'Rollenvorlagen',
            'portal'      => self::PORTAL,
            'page_header' => $this->buildKopf(self::ANSICHT_VORLAGEN, count($rows) . ' Vorlagen'),
            'toolbar'     => $this->begrenzt($this->buildVorlagenToolbar()),
            'breiteMax'   => self::BREITE_MAX,
            'rows'        => $rows,
            // Ohne Gruppe "Vorlagen" -- in einer Blaupause wirkt '@' nicht
            'rollenVorschlaege' => $this->rollenVorschlaege([], $vergeben),
            'rolleMax'    => self::ROLLE_MAX,
            'rolleMuster' => substr(self::VORLAGE_ROLLE_MUSTER, 1, -1),
            'felder'      => self::VORLAGE_FELDER,
            'modul_url'   => Portal::praefix(self::PORTAL) . self::MODUL,
        ]);
    }

    /** Eine Zeile der Vorlagenliste; $satz null = Systemvorlage fehlt in der DB. */
    private function vorlageZeile(string $name, ?array $satz, bool $system, array $verwendung): array
    {
        return [
            'nr'           => $satz['nr'] ?? null,
            'name'         => $name,
            'rollen_liste' => $satz['rollen_liste'] ?? [],
            'system'       => $system,
            'verwendet'    => $verwendung[$name] ?? 0,
        ];
    }

    /**
     * Systemvorlagen -- je Portal '@' + Portalname gross, so wie
     * insertregistrierung(local) sie neuen Zugaengen zuweist. Aus Portal::alle()
     * abgeleitet: ein neues Portal bringt seine Vorlage automatisch mit.
     *
     * @return string[]
     */
    private function systemVorlagen(): array
    {
        return array_map(static fn($p) => '@' . mb_strtoupper($p), Portal::alle());
    }

    /**
     * Wie viele Zugaenge verwenden welche Vorlage?
     *
     * @param array $zugaenge Saetze mit rollen_liste
     * @return array<string, int> Vorlagenname (gross) => Anzahl Zugaenge
     */
    private function verwendung(array $zugaenge): array
    {
        $anzahl = [];
        foreach ($zugaenge as $r) {
            foreach (array_keys($this->schluessel($r['rollen_liste'] ?? [])) as $rolle) {
                if (str_starts_with($rolle, '@')) {
                    $name = mb_strtoupper($rolle);
                    $anzahl[$name] = ($anzahl[$name] ?? 0) + 1;
                }
            }
        }
        return $anzahl;
    }

    // ------------------------------------------------------------------
    // Hilfsfunktionen
    // ------------------------------------------------------------------

    /**
     * Alle Saetze aus REGISTRIERUNG, jeweils mit rollen_liste. null, wenn der
     * Abruf mit einem Systemfehler scheitert (bereits gemeldet).
     *
     * @param string[] $felder
     */
    private function ladeAlle(array $felder): ?array
    {
        $antwort = \api_post('/registrierung/getregistrierung', [
            'fields'  => $felder,
            'orderby' => 'username',
        ]);
        if (Fehler::istSystem($antwort)) {
            return null;
        }

        $saetze = [];
        foreach ($antwort['data'] ?? [] as $r) {
            $r['rollen_liste'] = $this->rollen($r['rollen'] ?? null);
            $saetze[] = $r;
        }
        return $saetze;
    }

    /**
     * Wendet Typ-Filter und Suche an.
     *
     * Ein Rollenfilter wird hier als weitere Bedingung ergaenzt, z.B.:
     *   if ($fRolle !== '' && !isset($this->schluessel($r['rollen_liste'])[$fRolle])) return false;
     */
    private function filtere(array $rows, string $fTyp, string $fSuche): array
    {
        return array_values(array_filter($rows, static function ($r) use ($fTyp, $fSuche) {
            $typ = (string)($r['typ'] ?? '');

            if ($fTyp === self::TYP_OHNE) {
                if (Portal::ausTyp($typ) !== null) {
                    return false;
                }
            } elseif ($fTyp !== '' && $typ !== $fTyp) {
                return false;
            }

            if ($fSuche !== ''
                && mb_stripos((string)($r['username'] ?? ''), $fSuche) === false
                && mb_stripos((string)($r['email'] ?? ''), $fSuche) === false) {
                return false;
            }
            return true;
        }));
    }

    /**
     * Sortier-Parameter gegen die Whitelist abbilden; Unbekanntes faellt auf
     * den Default (Benutzername aufsteigend) zurueck.
     *
     * @return array{0: string, 1: string} [Spalte, Richtung]
     */
    private function sortierung(mixed $sort, mixed $richtung): array
    {
        return [
            is_string($sort) && isset(self::SORTIERBAR[$sort]) ? $sort : array_key_first(self::SORTIERBAR),
            in_array($richtung, self::RICHTUNGEN, true) ? $richtung : self::RICHTUNGEN[0],
        ];
    }

    /**
     * Sortiert im Speicher -- natuerlich und ohne Gross-/Kleinschreibung.
     * Leere Werte (nie angemeldet, kein Typ) stehen in beiden Richtungen am
     * Ende. Bei Gleichstand entscheidet der Benutzername, immer aufsteigend:
     * die Richtung gilt nur fuer die gewaehlte Spalte.
     */
    private function sortiere(array $rows, string $feld, string $richtung): array
    {
        $faktor = $richtung === 'ab' ? -1 : 1;
        usort($rows, static function ($a, $b) use ($feld, $faktor) {
            $wa = (string)($a[$feld] ?? '');
            $wb = (string)($b[$feld] ?? '');
            if (($wa === '') !== ($wb === '')) {
                return $wa === '' ? 1 : -1;
            }
            $c = strnatcasecmp($wa, $wb) * $faktor;
            return $c !== 0 ? $c : strnatcasecmp((string)($a['username'] ?? ''), (string)($b['username'] ?? ''));
        });
        return $rows;
    }

    /**
     * Links der sortierbaren Spaltenkoepfe. Ein Klick auf die aktive Spalte
     * dreht die Richtung um, auf eine andere sortiert aufsteigend. Typ-Filter
     * und Suche bleiben erhalten.
     *
     * @return array<string, array{href: string, icon: string, aktiv: bool}>
     */
    private function sortLinks(string $fTyp, string $fSuche, string $fSort, string $fRichtung): array
    {
        $links = [];
        foreach (self::SORTIERBAR as $feld => $art) {
            $aktiv = $feld === $fSort;
            $neu   = $aktiv && $fRichtung === 'auf' ? 'ab' : 'auf';
            $query = http_build_query(array_filter([
                'typ'      => $fTyp,
                'suche'    => $fSuche,
                'sort'     => $feld,
                'richtung' => $neu,
            ], 'strlen'));

            $links[$feld] = [
                'href'  => APP_BASE . Portal::praefix(self::PORTAL) . self::MODUL . '?' . $query,
                'icon'  => !$aktiv ? 'bi-arrow-down-up' : 'bi-sort-' . $art . ($fRichtung === 'ab' ? '-up-alt' : '-down'),
                'aktiv' => $aktiv,
            ];
        }
        return $links;
    }

    /**
     * Vorschlaege fuer die Autocomplete-Liste der Rollen, gruppiert:
     *
     *   Vorlagen   Rollen-Blaupausen aus REGISTRIERUNG ('@MITARBEITER')
     *   Rollen     supervisor und alle bereits vergebenen Rollen, die kein
     *              Endpunkt sind
     *   Bereiche   '/<prefix>/*' je Endpunkt-Gruppe -- erlaubt alle Endpunkte
     *              darunter (HasRouteAccess, Platzhalter am Ende)
     *   Endpunkte  einzelne Endpunkte aus /getpublicendpoints
     *
     * Vergebene '@'-Eintraege werden uebergangen: Vorlagen kommen nur aus
     * $vorlagen. So enthaelt die Liste mit $vorlagen = [] gar keine Vorlage
     * (Ansicht Rollenvorlagen -- dort wirkt '@' nicht).
     *
     * Nur Endpunkte mit auth=true: ohne Anmeldung prueft das Backend keine
     * Rollen, ein Eintrag waere wirkungslos. Rollen, die das Backend einer
     * Route zusaetzlich erlaubt (AddRoute-Parameter Roles), liefert der
     * Endpunkt nicht -- sie erscheinen erst, wenn ein Satz sie hat.
     *
     * Faellt /getpublicendpoints aus, ist der Systemfehler bereits gemeldet;
     * die Liste enthaelt dann nur Vorlagen und Rollen.
     *
     * @param string[] $vorlagen Benutzernamen der Blaupausen
     * @param string[] $vergeben Rollen aller Saetze
     * @return array<int, array{wert: string, gruppe: string}>
     */
    private function rollenVorschlaege(array $vorlagen, array $vergeben): array
    {
        $gruppen = ['Vorlagen' => $vorlagen, 'Rollen' => [self::ROLLE_SUPERVISOR], 'Bereiche' => [], 'Endpunkte' => []];

        foreach ($vergeben as $rolle) {
            if (str_starts_with($rolle, '@')) {
                continue;
            }
            $gruppen[str_starts_with($rolle, '/') ? 'Endpunkte' : 'Rollen'][] = $rolle;
        }

        $antwort = \api_post('/getpublicendpoints', []);
        foreach ($antwort['prefixes'] ?? [] as $gruppe) {
            $prefix = (string)($gruppe['prefix'] ?? '');
            $mitAuth = false;
            foreach ($gruppe['endpoints'] ?? [] as $endpunkt) {
                if (!empty($endpunkt['auth'])) {
                    $gruppen['Endpunkte'][] = (string)($endpunkt['path'] ?? '');
                    $mitAuth = true;
                }
            }
            if ($prefix !== '' && $mitAuth) {
                $gruppen['Bereiche'][] = '/' . $prefix . '/*';
            }
        }

        // Dubletten ohne Gross-/Kleinschreibung entfernen -- die erste
        // Gruppe gewinnt (eine vergebene '/adressen/*' bleibt unter Bereiche).
        $vorschlaege = [];
        $gesehen     = [];
        foreach ($gruppen as $name => $werte) {
            sort($werte, SORT_NATURAL | SORT_FLAG_CASE);
            foreach ($werte as $wert) {
                $schluessel = mb_strtolower($wert);
                if ($wert === '' || isset($gesehen[$schluessel]) || !preg_match(self::ROLLE_MUSTER, $wert)) {
                    continue;
                }
                $gesehen[$schluessel] = true;
                $vorschlaege[] = ['wert' => $wert, 'gruppe' => $name];
            }
        }
        return $vorschlaege;
    }

    /**
     * Rollen aus der Auswahl (rollen[]) pruefen. Fehler landen in $pruefung.
     *
     * Freie Eingaben sind erlaubt -- die Vorschlagsliste ist eine Hilfe, keine
     * Whitelist (Rollen wie 'dispo' kennt nur das Backend).
     *
     * @param bool $fuerVorlage Rollen einer Blaupause: kein '@' (wird nicht
     *                          aufgeloest), leere Liste erlaubt (ergibt keine
     *                          Rechte). Bei Zugaengen hiesse leer voller Zugriff.
     * @return string[] bereinigte Rollen, ohne Dubletten (Gross-/Kleinschreibung egal)
     */
    private function pruefeRollen(Pruefung $pruefung, bool $fuerVorlage): array
    {
        $eingaben = array_filter((array)($_POST['rollen'] ?? []), 'is_string');

        $rollen = [];
        foreach ($eingaben as $rolle) {
            $rolle = trim($rolle);
            if ($rolle === '') {
                continue;
            }
            if ($fuerVorlage && str_starts_with($rolle, '@')) {
                $pruefung->fehler('Rollen: „' . mb_substr($rolle, 0, 40) . '“ – eine Vorlage kann keine '
                    . 'andere Vorlage enthalten (wird beim Login nicht aufgelöst).');
                continue;
            }
            if (!preg_match(self::ROLLE_MUSTER, $rolle)) {
                $pruefung->fehler('Rollen: „' . mb_substr($rolle, 0, 40) . '“ ist kein gültiger Rollenname '
                    . '(erlaubt sind Buchstaben, Ziffern und _ . / * -'
                    . ($fuerVorlage ? ').' : ', optional mit @ am Anfang).'));
                continue;
            }
            $rollen[mb_strtolower($rolle)] ??= $rolle;
        }

        // Leer hiesse bei einem Zugang "keine Einschraenkung" -- also voller Zugriff
        if ($rollen === [] && !$fuerVorlage) {
            $pruefung->fehler('Rollen: mindestens eine Rolle ist erforderlich '
                . '(ohne Rolle hätte der Zugang uneingeschränkten Zugriff).');
        }

        return array_values($rollen);
    }

    /**
     * Rollenliste als Vergleichsmenge: Schluessel klein, sortiert.
     *
     * @param string[] $rollen
     */
    private function schluessel(array $rollen): array
    {
        $menge = array_fill_keys(array_map('mb_strtolower', $rollen), true);
        ksort($menge);
        return $menge;
    }

    /** nr aus dem POST pruefen (Loeschen-Dialoge). null = abbrechen, gemeldet. */
    private function nrAusPost(): ?int
    {
        $felder = ['nr' => self::FELDER['nr']];
        $werte  = Pruefung::werteAusPost($felder);
        return Pruefung::formular($felder, $werte)->melde() ? (int)$werte['nr'] : null;
    }

    /**
     * Laedt einen Satz fuer die Aenderungs-Aktionen und meldet, wenn er fehlt
     * oder von der falschen Art ist. null = abbrechen.
     *
     * @param bool $vorlage true = Blaupause erwartet, false = Portalzugang
     */
    private function ladeSatz(int $nr, bool $vorlage): ?array
    {
        $antwort = \api_post('/registrierung/getregistrierungbyid', [
            'nr'     => $nr,
            'fields' => ['nr', 'username', 'rollen'],
        ]);
        if (Fehler::istSystem($antwort)) {
            return null;
        }

        $satz = $antwort['data'][0] ?? null;
        if ($satz === null || $this->istBlaupause($satz) !== $vorlage) {
            $this->flashError($vorlage ? 'Die Rollenvorlage wurde nicht gefunden.' : 'Die Registrierung wurde nicht gefunden.');
            return null;
        }
        return $satz;
    }

    /** Rollen-Blaupause ('@KUNDE' usw.) statt Portalzugang? */
    private function istBlaupause(array $satz): bool
    {
        return str_starts_with((string)($satz['username'] ?? ''), '@');
    }

    /**
     * Gehoert der Satz dem angemeldeten Benutzer? /login vergleicht per
     * UPPER(), deshalb auch hier ohne Gross-/Kleinschreibung.
     */
    private function istEigener(array $satz): bool
    {
        $eigener = mb_strtoupper((string)($_COOKIE['jwt_user'] ?? ''));
        return $eigener !== '' && mb_strtoupper((string)($satz['username'] ?? '')) === $eigener;
    }

    /**
     * REGISTRIERUNG.rollen (Blob, base64) -> Liste der Rollen.
     * Trenner wie im Backend: Zeilenumbruch, Komma, Semikolon.
     *
     * @return string[]
     */
    private function rollen(?string $blob): array
    {
        if ($blob === null || $blob === '') {
            return [];
        }
        $text = base64_decode($blob, true);
        if ($text === false) {
            return [];
        }
        return array_values(array_filter(
            array_map('trim', preg_split('/[\r\n,;]+/', $text)),
            'strlen'
        ));
    }

    /** Portalname => Anzeige fuer Filter, Liste und Dialog. */
    private function typLabels(): array
    {
        $labels = [];
        foreach (Portal::alle() as $name) {
            $labels[$name] = ucfirst($name);
        }
        return $labels;
    }

    /** FELDER mit den Auswahllisten, die erst zur Laufzeit feststehen. */
    private function felder(): array
    {
        $felder = self::FELDER;
        $felder['gesperrt']['auswahl'] = array_keys(self::GESPERRT);
        return $felder;
    }

    /**
     * Ruecksprung zur Liste. Zugaenge mit den Filtern, die das Formular
     * mitschickt; Vorlagen in ihre Ansicht. Nur bekannte Parameter -- kein
     * Pfad aus dem Request.
     */
    private function zurueck(bool $vorlagen = false): string
    {
        $query = http_build_query($vorlagen
            ? ['ansicht' => self::ANSICHT_VORLAGEN]
            : array_filter([
                'typ'      => (string)($_POST['f_typ'] ?? ''),
                'suche'    => (string)($_POST['f_suche'] ?? ''),
                // index() bildet sort/richtung erneut gegen die Whitelist ab
                'sort'     => (string)($_POST['f_sort'] ?? ''),
                'richtung' => (string)($_POST['f_richtung'] ?? ''),
            ], 'strlen'));

        return Portal::praefix(self::PORTAL) . self::MODUL . ($query !== '' ? '?' . $query : '');
    }

    /** Umschliesst HTML mit der zentrierten Breitenbegrenzung der Seite. */
    private function begrenzt(string $html): string
    {
        return '<div class="mx-auto w-100" style="max-width:' . self::BREITE_MAX . 'px;">' . $html . '</div>';
    }

    /**
     * Seitenkopf: Titel mit Zaehler links, Reiter Zugaenge/Rollenvorlagen
     * rechts. Die Reiter sind Links -- jede Ansicht ist eine eigene Seite.
     */
    private function buildKopf(string $ansicht, string $zaehler): string
    {
        $basis  = APP_BASE . Portal::praefix(self::PORTAL) . self::MODUL;
        $reiter = [
            ''                     => ['Benutzer', 'bi-people', $basis],
            self::ANSICHT_VORLAGEN => ['Rollenvorlagen', 'bi-diagram-3', $basis . '?ansicht=' . self::ANSICHT_VORLAGEN],
        ];

        $links = '';
        foreach ($reiter as $wert => [$text, $icon, $href]) {
            $aktiv  = $wert === $ansicht;
            $stil   = $aktiv
                ? 'background:var(--primary-color);color:var(--on-primary);'
                : 'color:var(--primary-color-dark);';
            $links .= '<li class="nav-item"><a class="nav-link py-1 px-3' . ($aktiv ? ' active' : '') . '"'
                    . ($aktiv ? ' aria-current="page"' : '')
                    . ' style="' . $stil . '" href="' . htmlspecialchars($href, ENT_QUOTES) . '">'
                    . '<i class="bi ' . $icon . ' me-1"></i>' . $text . '</a></li>';
        }

        return $this->begrenzt('
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <h1 class="h5 fw-bold mb-0" style="color:var(--text-color);">Registrierungen</h1>
                    <span class="badge rounded-pill"
                          style="background:var(--primary-color-light);color:var(--text-color);">
                        ' . htmlspecialchars($zaehler) . '
                    </span>
                </div>
                <ul class="nav nav-pills gap-1">' . $links . '</ul>
            </div>');
    }

    /** Toolbar der Rollenvorlagen -- nur die Aktion "Neue Rollenvorlage". */
    private function buildVorlagenToolbar(): string
    {
        return <<<HTML
<div class="row g-2 align-items-end">
    <div class="col-12 col-sm-6 col-lg-auto ms-lg-auto">
        <button type="button" class="btn btn-sm fw-semibold w-100 btn-app-primary"
                data-bs-toggle="modal" data-bs-target="#vorlageNeu" data-name="">
            <i class="bi bi-plus-lg me-1"></i>Neue Rollenvorlage
        </button>
    </div>
</div>
HTML;
    }

    /**
     * Filter-Toolbar als HTML (GET-Form). Typ wird beim Wechsel sofort
     * angewendet, die Suche per Enter oder Button.
     */
    private function buildToolbar(string $fTyp, string $fSuche, string $fSort, string $fRichtung): string
    {
        $action = APP_BASE . Portal::praefix(self::PORTAL) . self::MODUL;
        $sucheV = htmlspecialchars($fSuche, ENT_QUOTES);
        $max    = self::SUCHE_MAX;
        // Sortierung beim Filtern beibehalten (Werte stammen aus der Whitelist)
        $sortV     = htmlspecialchars($fSort, ENT_QUOTES);
        $richtungV = htmlspecialchars($fRichtung, ENT_QUOTES);

        $optionen = ['' => 'Alle Typen'] + $this->typLabels() + [self::TYP_OHNE => 'Ohne gültigen Typ'];
        $typOptions = '';
        foreach ($optionen as $wert => $label) {
            $sel = ((string)$wert === $fTyp) ? 'selected' : '';
            $typOptions .= '<option value="' . htmlspecialchars((string)$wert, ENT_QUOTES) . '" ' . $sel . '>'
                         . htmlspecialchars($label) . '</option>';
        }

        return <<<HTML
<form method="get" action="{$action}" class="w-100">
<input type="hidden" name="sort" value="{$sortV}">
<input type="hidden" name="richtung" value="{$richtungV}">
<div class="row g-2 align-items-end">
    <div class="col-12 col-sm-6 col-lg-auto">
        <label class="filter-label" for="filterTyp">Typ</label>
        <select id="filterTyp" class="form-select form-select-sm" name="typ" onchange="this.form.submit()">
            {$typOptions}
        </select>
    </div>
    <div class="col-12 col-sm-6 col-lg-auto">
        <label class="filter-label" for="filterSuche">Suche</label>
        <input type="search" class="form-control form-control-sm" id="filterSuche" name="suche"
               value="{$sucheV}" maxlength="{$max}" placeholder="Benutzername / E-Mail">
    </div>
    <div class="col-6 col-lg-auto">
        <button type="submit" class="btn btn-sm fw-semibold w-100 btn-app-primary">
            <i class="bi bi-search me-1"></i>Suchen
        </button>
    </div>
    <div class="col-6 col-lg-auto">
        <a href="{$action}" class="btn btn-sm btn-outline-secondary w-100">
            <i class="bi bi-x-lg me-1"></i>Zur&uuml;cksetzen
        </a>
    </div>
</div>
</form>
HTML;
    }
}
