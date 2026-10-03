<?php
/**
 * standard/Controllers/RegistrierungsverwaltungController.php
 *
 * Verwaltung der Portalzugaenge in der Tabelle REGISTRIERUNG -- Mitarbeiterportal.
 *
 *   index()      Liste mit Typ-Filter und Suche (Benutzername / E-Mail)
 *   speichern()  E-Mail, Sperre und Rollen einer Registrierung aendern
 *   loeschen()   Registrierung loeschen
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
 * Portalzugaenge. Sie werden nicht gelistet und koennen hier weder geaendert
 * noch geloescht werden -- auch nicht per manipuliertem POST. Als Rolle
 * zuweisbar sind sie dagegen: der Login loest '@XYZ' in die Rollen des
 * Blaupausen-Satzes auf (ResolveRoleBlueprints in DataModulLoginClass.pas).
 *
 * Rollen (REGISTRIERUNG.rollen, Blob):
 *   - Lesen liefert base64, Schreiben nimmt Klartext. Eine Rolle je Zeile.
 *   - Das Backend trennt an Zeilenumbruch, Komma und Semikolon und vergleicht
 *     ohne Gross-/Kleinschreibung (NormalizeRoleList in webUtils.pas).
 *   - LEERE Rollen bedeuten im Backend VOLLEN Zugriff (HasRouteAccess).
 *     Deshalb laesst speichern() keine leere Rollenliste zu.
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

    /** REGISTRIERUNG.gesperrt: 'JA' sperrt (Login: SameText 'JA'), sonst offen. */
    private const GESPERRT = ['NEIN' => 'Aktiv', 'JA' => 'Gesperrt'];

    /**
     * Suchfeld -- durchsucht username (120) und email (60), die groessere
     * Spalte bestimmt die Grenze. Reiner Anzeigefilter, kein Insert.
     */
    private const SUCHE_MAX = 120;

    /**
     * Gueltiger Rollenname: optional '@' (Blaupause), sonst Buchstaben,
     * Ziffern und die Zeichen eines Endpunkts ('/dispo/*'). Keine Trenner
     * (Zeilenumbruch, Komma, Semikolon) -- die zerlegen im Backend die Liste.
     */
    private const ROLLE_MUSTER = '~^@?[A-Za-z0-9_./*-]{1,100}$~';

    /** Immer anbietbar, auch wenn noch kein Zugang sie hat -- darf alles. */
    private const ROLLE_SUPERVISOR = 'supervisor';

    /**
     * Felder des Bearbeiten-Dialogs. Die Rollen-Checkboxen (rollen[]) sind
     * eine Mehrfachauswahl und werden in pruefeRollen() geprueft.
     * Die Auswahl fuer gesperrt setzt felder().
     */
    private const FELDER = [
        'nr'             => ['bezeichnung' => 'Nr.', 'pflicht' => true, 'ganzzahl' => [1, 2147483647], 'max_zeichen' => 10],
        'email'          => ['bezeichnung' => 'E-Mail', 'email' => true, 'max_zeichen' => 60],
        'gesperrt'       => ['bezeichnung' => 'Status', 'pflicht' => true],
        'rollen_weitere' => ['bezeichnung' => 'Weitere Rollen', 'max_zeichen' => 500],
    ];

    /**
     * GET /mitarbeiter/registrierungen
     *
     * Filter-Parameter (GET):
     *   typ    Portalname, TYP_OHNE oder leer (= alle)
     *   suche  Teilstring in Benutzername ODER E-Mail, ohne Gross-/Kleinschreibung
     */
    public function index(): void
    {
        $fTyp   = (string)($_GET['typ'] ?? '');
        $fSuche = mb_substr(trim((string)($_GET['suche'] ?? '')), 0, self::SUCHE_MAX);

        if ($fTyp !== self::TYP_OHNE && !in_array($fTyp, Portal::alle(), true)) {
            $fTyp = '';
        }

        $antwort = \api_post('/registrierung/getregistrierung', [
            'fields'  => self::LISTENFELDER,
            'orderby' => 'username',
        ]);

        // Bekannte Rollen fuer die Auswahl im Dialog: Blaupausen, alle bereits
        // vergebenen Rollen und supervisor. Schluessel klein -- das Backend
        // vergleicht ohne Gross-/Kleinschreibung.
        $bekannt = [self::ROLLE_SUPERVISOR => self::ROLLE_SUPERVISOR];

        $alle = [];
        foreach ($antwort['data'] ?? [] as $r) {
            $r['rollen_liste'] = $this->rollen($r['rollen'] ?? null);

            if ($this->istBlaupause($r)) {
                $name = mb_strtoupper((string)$r['username']);
                $bekannt[mb_strtolower($name)] = $name;
                continue;
            }
            foreach ($r['rollen_liste'] as $rolle) {
                $bekannt[mb_strtolower($rolle)] ??= $rolle;
            }
            $alle[] = $r;
        }

        // Blaupausen zuerst, dann alphabetisch
        uksort($bekannt, static fn($a, $b) =>
            [!str_starts_with($a, '@'), $a] <=> [!str_starts_with($b, '@'), $b]);

        $rows = $this->filtere($alle, $fTyp, $fSuche);

        $this->render('registrierungsverwaltung/index', [
            'page_title'    => 'Registrierungen',
            'portal'        => self::PORTAL,
            'page_header'   => '
                <h1 class="h5 fw-bold mb-0" style="color:var(--text-color);">Registrierungen</h1>
                <span class="badge rounded-pill"
                      style="background:var(--primary-color-light);color:var(--text-color);">
                    ' . count($rows) . ' von ' . count($alle) . '
                </span>',
            'toolbar'       => $this->buildToolbar($fTyp, $fSuche),
            'rows'          => $rows,
            'fSuche'        => $fSuche,
            'typen'         => $this->typLabels(),
            'gesperrtWerte' => self::GESPERRT,
            'bekannteRollen'=> $bekannt,
            'felder'        => $this->felder(),
            'modul_url'     => Portal::praefix(self::PORTAL) . self::MODUL,
            'eigener'       => mb_strtoupper((string)($_COOKIE['jwt_user'] ?? '')),
        ]);
    }

    /**
     * POST /mitarbeiter/registrierungen/speichern -- E-Mail, Sperre, Rollen.
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
        $rollen   = $this->pruefeRollen($werte['rollen_weitere'], $pruefung);
        if (!$pruefung->melde()) {
            $this->redirect($zurueck);
        }

        $satz = $this->ladeSatz((int)$werte['nr']);
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

        $antwort = \api_post('/registrierung/updateregistrierung', [
            'nr'       => (int)$werte['nr'],
            // Leere E-Mail als NULL -- wie bei Saetzen, die nie eine hatten
            'email'    => $werte['email'] !== '' ? $werte['email'] : null,
            'gesperrt' => $werte['gesperrt'],
            // Klartext, eine Rolle je Zeile (gelesen wird base64)
            'rollen'   => implode("\r\n", $rollen),
        ]);

        if (Fehler::ok($antwort)) {
            $this->flashSuccess('Registrierung „' . ($satz['username'] ?? '') . '“ wurde gespeichert.');
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

        $felder = ['nr' => self::FELDER['nr']];
        $werte  = Pruefung::werteAusPost($felder);
        if (!Pruefung::formular($felder, $werte)->melde()) {
            $this->redirect($zurueck);
        }

        $satz = $this->ladeSatz((int)$werte['nr']);
        if ($satz === null) {
            $this->redirect($zurueck);
        }

        if ($this->istEigener($satz)) {
            $this->flashError('Den eigenen Zugang können Sie nicht löschen.');
            $this->redirect($zurueck);
        }

        $antwort = \api_post('/registrierung/deleteregistrierung', ['nr' => (int)$werte['nr']]);

        if (Fehler::ok($antwort)) {
            $this->flashSuccess('Registrierung „' . ($satz['username'] ?? '') . '“ wurde gelöscht.');
        } else {
            $this->apiFehler($antwort, 'Die Registrierung konnte nicht gelöscht werden.');
        }
        $this->redirect($zurueck);
    }

    // ------------------------------------------------------------------
    // Hilfsfunktionen
    // ------------------------------------------------------------------

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
     * Rollen aus den Checkboxen (rollen[]) und dem Freitextfeld zusammenfuehren
     * und pruefen. Fehler landen in $pruefung.
     *
     * @return string[] bereinigte Rollen, ohne Dubletten (Gross-/Kleinschreibung egal)
     */
    private function pruefeRollen(string $weitere, Pruefung $pruefung): array
    {
        $eingaben = array_merge(
            array_map('strval', array_filter((array)($_POST['rollen'] ?? []), 'is_string')),
            preg_split('/[\r\n,;]+/', $weitere)
        );

        $rollen = [];
        foreach ($eingaben as $rolle) {
            $rolle = trim($rolle);
            if ($rolle === '') {
                continue;
            }
            if (!preg_match(self::ROLLE_MUSTER, $rolle)) {
                $pruefung->fehler('Rollen: „' . mb_substr($rolle, 0, 40) . '“ ist kein gültiger Rollenname '
                    . '(erlaubt sind Buchstaben, Ziffern und _ . / * -, optional mit @ am Anfang).');
                continue;
            }
            $rollen[mb_strtolower($rolle)] ??= $rolle;
        }

        // Leer hiesse im Backend "keine Einschraenkung" -- also voller Zugriff
        if ($rollen === []) {
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

    /**
     * Laedt einen Satz fuer speichern()/loeschen() und meldet, wenn er fehlt
     * oder eine Rollen-Blaupause ist. null = abbrechen.
     */
    private function ladeSatz(int $nr): ?array
    {
        $antwort = \api_post('/registrierung/getregistrierungbyid', [
            'nr'     => $nr,
            'fields' => ['nr', 'username', 'rollen'],
        ]);
        if (Fehler::istSystem($antwort)) {
            return null;
        }

        $satz = $antwort['data'][0] ?? null;
        if ($satz === null || $this->istBlaupause($satz)) {
            $this->flashError('Die Registrierung wurde nicht gefunden.');
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
     * Ruecksprung zur Liste mit den Filtern, die das Formular mitschickt.
     * Nur die bekannten Parameter -- kein Pfad aus dem Request.
     */
    private function zurueck(): string
    {
        $query = http_build_query(array_filter([
            'typ'   => (string)($_POST['f_typ'] ?? ''),
            'suche' => (string)($_POST['f_suche'] ?? ''),
        ], 'strlen'));

        return Portal::praefix(self::PORTAL) . self::MODUL . ($query !== '' ? '?' . $query : '');
    }

    /**
     * Filter-Toolbar als HTML (GET-Form). Typ wird beim Wechsel sofort
     * angewendet, die Suche per Enter oder Button.
     */
    private function buildToolbar(string $fTyp, string $fSuche): string
    {
        $action = APP_BASE . Portal::praefix(self::PORTAL) . self::MODUL;
        $sucheV = htmlspecialchars($fSuche, ENT_QUOTES);
        $max    = self::SUCHE_MAX;

        $optionen = ['' => 'Alle Typen'] + $this->typLabels() + [self::TYP_OHNE => 'Ohne gültigen Typ'];
        $typOptions = '';
        foreach ($optionen as $wert => $label) {
            $sel = ((string)$wert === $fTyp) ? 'selected' : '';
            $typOptions .= '<option value="' . htmlspecialchars((string)$wert, ENT_QUOTES) . '" ' . $sel . '>'
                         . htmlspecialchars($label) . '</option>';
        }

        return <<<HTML
<form method="get" action="{$action}" class="w-100">
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
