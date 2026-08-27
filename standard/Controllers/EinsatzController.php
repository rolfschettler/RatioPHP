<?php
/**
 * standard/Controllers/EinsatzController.php
 *
 * Einsatz-Uebersicht mit Filterfunktion. Server-rendered Port der
 * Angular-Logik (displayEinsaetze).
 *
 * Aufgabenteilung:
 *   - Datum/Zeit (von, bis, timemode) filtert der RATIOserver ueber
 *     /dispo/getEinsatzFiltered.
 *   - Fahrer, Fahrzeug, Begriff, Gruppe und Einsatzart-Typen werden hier
 *     im Speicher gefiltert -- genau wie das Angular-computed.
 *
 * Alle Endpunkte liegen unter dem Primaerpfad /dispo/ (weicht vom
 * Tabellennamen ab). Token kommt aus $_COOKIE['jwt_token'] -- automatisch
 * ueber \api_post(). Antworten werden defensiv ausgepackt (data ODER
 * paginiert data.data).
 */

namespace Standard\Controllers;

use Core\BaseController;
use Core\Portal;

class EinsatzController extends BaseController
{
    /** Portal des Moduls -- bestimmt Layout, Navigation und Routen-Praefix. */
    private const PORTAL = 'mitarbeiter';

    /** Pfad des Moduls unterhalb des Portal-Praefix. */
    private const MODUL = '/einsatz';

    /**
     * Maximale Anzahl Einsaetze, die vom Server geholt werden.
     * Es wird bewusst 1 mehr als das Nutz-Maximum (2000) angefordert:
     * Kommen >= LIMIT-1 Saetze zurueck, ist der Zeitraum vermutlich zu gross
     * und das Ergebnis abgeschnitten -- dann wird eine Warnung angezeigt.
     */
    private const EINSATZ_LIMIT = 20000;

    /**
     * GET /mitarbeiter/einsatz -- Gefilterte und sortierte Liste der Einsaetze.
     *
     * Filter-Parameter (GET):
     *   von, bis    Zeitraum (datetime-local) -- filtert der Server
     *   timemode    '1' = ueberlappende Zeitraeume (optional, Query-Param)
     *   fahrer      Teilstring in Fahrer-Kuerzel ODER aufgeloestem Namen
     *   fahrzeug    Teilstring im Fahrzeug
     *   begriff     Teilstring in Bezeichnung ODER Dienst-Nr.
     *   gruppe      Fahrergruppen-Nr -- wird zu Set von Kuerzeln aufgeloest
     *   typen[]     Mehrfachauswahl der Einsatzart-Codes (leer = alle)
     *   sortcol     Spalte fuer die In-Memory-Sortierung (Default: von)
     *   sortdir     'asc' oder 'desc'
     */
    public function index(): void
    {
        // 1. Filterwerte aus der URL lesen (= die Angular-Signals)
        //    Von/Bis sind reine Datumswerte (YYYY-MM-DD) -- ohne Uhrzeit.
        //    Default: erster bis letzter Tag des aktuellen Monats.
        $von      = substr(($_GET['von'] ?? '') ?: date('Y-m-01'), 0, 10);
        $bis      = substr(($_GET['bis'] ?? '') ?: date('Y-m-t'),  0, 10);
        $timemode = trim((string)($_GET['timemode'] ?? ''));

        $fFahrer   = strtolower(trim((string)($_GET['fahrer']   ?? '')));
        $fFahrzeug = strtolower(trim((string)($_GET['fahrzeug'] ?? '')));
        $fBegriff  = strtolower(trim((string)($_GET['begriff']  ?? '')));
        $fGruppe   = (string)($_GET['gruppe'] ?? '');
        $fTypen    = array_values(array_filter((array)($_GET['typen'] ?? []), 'strlen'));

        $sortCol = (string)($_GET['sortcol'] ?? 'von');
        $sortDir = (($_GET['sortdir'] ?? 'asc') === 'desc') ? -1 : 1;

        // 2. Daten holen -- Datum/Zeit filtert der SERVER
        //    timemode sicherheitshalber BEIDE Wege: als Query-Parameter UND
        //    im Body. Der RATIOserver akzeptiert beide Varianten -- so greift
        //    der Filter unabhaengig davon, welche der Server auswertet.
        $endpoint = '/dispo/getEinsatzFiltered'
                  . ($timemode !== '' ? '?timemode=' . urlencode($timemode) : '');

        // Reine Datumswerte um die vollen Tagesgrenzen ergaenzen, damit der
        // Server den kompletten Tag einschliesst (Bis = bis 23:59).
        $body = [
            'fields'  => ['nr', 'von', 'bis', 'bezeichnung', 'dienstnr',
                          'fahrer1', 'fahrer2', 'fahrzeug', 'typ'],
            'von'     => $von . ' 00:00',
            'bis'     => $bis . ' 23:59',
            'orderby' => 'von',
            'limit'   => self::EINSATZ_LIMIT,
        ];
        if ($timemode !== '') {
            $body['timemode'] = $timemode;
        }

        $einsaetze = $this->unwrapList(\api_post($endpoint, $body));

        // Limit-Pruefung auf der ROHEN Satzzahl vor der In-Memory-Filterung.
        // Ist das Limit erreicht, ist der Zeitraum zu gross und das Ergebnis
        // unvollstaendig -- dann werden KEINE Datensaetze angezeigt, nur die
        // Warnung. Der Benutzer muss den Zeitraum eingrenzen.
        $warnung = '';
        if (count($einsaetze) >= self::EINSATZ_LIMIT - 1) {
            $warnung = 'Der Zeitraum liefert mehr als ' . (self::EINSATZ_LIMIT - 1)
                . ' Eins&auml;tze &ndash; es werden keine Ergebnisse angezeigt. '
                . 'Bitte den Zeitraum eingrenzen.';
            $einsaetze = [];   // nichts anzeigen
        }

        $personal = $this->unwrapList(\api_post('/dispo/getPersonalstamm', [
            'fields'  => ['nr', 'zeichen', 'name1', 'name2'],
            'orderby' => 'zeichen',
        ]));

        $fahrergruppen = $this->unwrapList(\api_post('/dispo/getFahrergruppen', [
            'fields'  => ['nr', 'name', 'ids'],
            'orderby' => 'name',
        ]));

        $einsatzarten = $this->unwrapList(\api_post('/dispo/getEinsatzarten', [
            'fields'  => ['code', 'beschreibung'],
            'orderby' => 'code',
        ]));

        // 3. Filtern + Sortieren im Speicher (= displayEinsaetze computed)

        // 3a. personalMap: Kuerzel (zeichen) -> Datensatz
        $personalMap = [];
        foreach ($personal as $p) {
            $personalMap[$p['zeichen'] ?? ''] = $p;
        }

        // 3b. Gewaehlte Fahrergruppe -> Set von Kuerzeln (zeichen)
        $gruppeZeichen = $this->resolveGruppe($fGruppe, $fahrergruppen, $personal);

        // 3c. Namen anreichern + Marker bereinigen (= .map)
        foreach ($einsaetze as &$e) {
            $p1 = $personalMap[$e['fahrer1'] ?? ''] ?? null;
            $p2 = $personalMap[$e['fahrer2'] ?? ''] ?? null;
            $fz = (string)($e['fahrzeug'] ?? '');

            $e['fahrzeug']    = (strpos($fz, '~#') !== false) ? '?' : $fz;
            $e['fahrer1Name'] = $this->cleanMarkers(
                $p1 ? trim(($p1['name1'] ?? '') . ' ' . ($p1['name2'] ?? '')) : (string)($e['fahrer1'] ?? '')
            );
            $e['fahrer2Name'] = $this->cleanMarkers(
                $p2 ? trim(($p2['name1'] ?? '') . ' ' . ($p2['name2'] ?? '')) : (string)($e['fahrer2'] ?? '')
            );
        }
        unset($e);

        // 3d. Filtern (= .filter)
        $rows = array_filter($einsaetze, function ($e) use (
            $fFahrer, $fFahrzeug, $fBegriff, $gruppeZeichen, $fTypen
        ) {
            if ($fFahrer !== '') {
                $f1 = strtolower(($e['fahrer1'] ?? '') . ' ' . ($e['fahrer1Name'] ?? ''));
                $f2 = strtolower(($e['fahrer2'] ?? '') . ' ' . ($e['fahrer2Name'] ?? ''));
                if (strpos($f1, $fFahrer) === false && strpos($f2, $fFahrer) === false) {
                    return false;
                }
            }
            if ($fFahrzeug !== ''
                && stripos((string)($e['fahrzeug'] ?? ''), $fFahrzeug) === false) {
                return false;
            }
            if ($fBegriff !== ''
                && stripos((string)($e['bezeichnung'] ?? ''), $fBegriff) === false
                && stripos((string)($e['dienstnr']    ?? ''), $fBegriff) === false) {
                return false;
            }
            if ($gruppeZeichen !== null
                && !isset($gruppeZeichen[$e['fahrer1'] ?? ''])
                && !isset($gruppeZeichen[$e['fahrer2'] ?? ''])) {
                return false;
            }
            if (!empty($fTypen) && !in_array((string)($e['typ'] ?? ''), $fTypen, true)) {
                return false;
            }
            return true;
        });

        // 3e. Sortieren (= .sort) -- numerisch bzw. localeCompare/strcoll
        usort($rows, function ($a, $b) use ($sortCol, $sortDir) {
            $av = $a[$sortCol] ?? '';
            $bv = $b[$sortCol] ?? '';
            if (is_numeric($av) && is_numeric($bv)) {
                return (($av <=> $bv)) * $sortDir;
            }
            return strcoll((string)$av, (string)$bv) * $sortDir;
        });

        // 4. Toolbar (Filter) aufbauen
        $toolbar = $this->buildToolbar(
            $von, $bis, $timemode, $fGruppe, $fTypen, $fahrergruppen, $einsatzarten
        );

        // 5. Rendern -- Filterwerte fuers Highlighting an den View geben
        $this->render('einsatz/index', [
            'page_title'  => 'Einsatz-Uebersicht',
            'portal'      => self::PORTAL,
            // Basis der Sortier-Links im View -- kein Portalpfad im View
            'modul_url'   => Portal::praefix(self::PORTAL) . self::MODUL,
            'page_header' => '
                <h1 class="h5 fw-bold mb-0" style="color:var(--text-color);">Einsatz-&Uuml;bersicht</h1>
                <span class="badge rounded-pill"
                      style="background:var(--primary-color-light);color:var(--text-color);">
                    ' . count($rows) . ' Eins&auml;tze
                </span>',
            'toolbar'   => $toolbar,
            'warnung'   => $warnung,
            'rows'      => $rows,
            'fFahrer'   => $fFahrer,
            'fFahrzeug' => $fFahrzeug,
            'fBegriff'  => $fBegriff,
            'sortCol'   => $sortCol,
            'sortDir'   => $sortDir,
        ]);
    }

    /**
     * Packt eine API-Antwort defensiv aus.
     * Antwort ist entweder { data:[...] } ODER paginiert
     * { data:{ total, limit, offset, data:[...] } }.
     */
    private function unwrapList(array $json): array
    {
        $d = $json['data'] ?? [];
        if (is_array($d) && array_is_list($d)) {
            return $d;
        }
        return $d['data'] ?? [];
    }

    /**
     * Bereinigt RATIOserver-Marker im Text.
     * "~#2" -> "??", "~#1" -> "?".
     */
    private function cleanMarkers(?string $t): string
    {
        if ($t === null || $t === '') {
            return $t ?? '';
        }
        if (strpos($t, '~#2') !== false) {
            return '??';
        }
        if (strpos($t, '~#1') !== false) {
            return '?';
        }
        return $t;
    }

    /**
     * Loest eine Fahrergruppen-Nr in ein Set von Personal-Kuerzeln auf.
     * Rueckgabe: assoziatives Array kuerzel => true, oder null wenn keine
     * Gruppe gewaehlt/gefunden wurde (= kein Gruppenfilter).
     *
     * gruppe['ids'] ist ein Komma-String von Personal-Nr (teils mit "" ),
     * z.B. '"10","12","17"'. Diese werden ueber PERSONALSTAMM.NR auf das
     * Kuerzel (zeichen) gemappt.
     */
    private function resolveGruppe(string $fGruppe, array $fahrergruppen, array $personal): ?array
    {
        if ($fGruppe === '') {
            return null;
        }

        $gruppe = null;
        foreach ($fahrergruppen as $g) {
            if ((string)($g['nr'] ?? '') === $fGruppe) {
                $gruppe = $g;
                break;
            }
        }
        if ($gruppe === null) {
            return null;
        }

        // Personal-Nr -> Kuerzel
        $nrToZeichen = [];
        foreach ($personal as $p) {
            $nrToZeichen[(string)($p['nr'] ?? '')] = $p['zeichen'] ?? '';
        }

        $ids = array_filter(
            array_map(
                static fn($s) => (int)trim(str_replace('"', '', $s)),
                explode(',', (string)($gruppe['ids'] ?? ''))
            ),
            static fn($n) => $n > 0
        );

        $gruppeZeichen = [];
        foreach ($ids as $id) {
            $z = $nrToZeichen[(string)$id] ?? '';
            if ($z !== '') {
                $gruppeZeichen[$z] = true;
            }
        }
        return $gruppeZeichen;
    }

    /**
     * Baut die Filter-Toolbar als HTML-String (GET-Form).
     * Die Werte ueberleben den Reload ueber die URL.
     */
    private function buildToolbar(
        string $von,
        string $bis,
        string $timemode,
        string $fGruppe,
        array $fTypen,
        array $fahrergruppen,
        array $einsatzarten
    ): string {
        $action   = APP_BASE . Portal::praefix(self::PORTAL) . self::MODUL;
        $vonEsc   = htmlspecialchars($von, ENT_QUOTES);
        $bisEsc   = htmlspecialchars($bis, ENT_QUOTES);
        $checked  = ($timemode !== '') ? 'checked' : '';

        // Jahr + Monat aus dem aktuellen Von-Datum ableiten (Helfer-Felder
        // ohne name -- landen nicht in der URL, steuern nur Von/Bis per JS).
        $ts       = strtotime($von . ' 00:00') ?: time();
        $aktJahr  = (int)date('Y', $ts);
        $aktMonat = (int)date('n', $ts);
        $monate   = [
            1 => 'Januar',  2 => 'Februar', 3 => 'M&auml;rz',   4 => 'April',
            5 => 'Mai',     6 => 'Juni',    7 => 'Juli',        8 => 'August',
            9 => 'September',10 => 'Oktober',11 => 'November',  12 => 'Dezember',
        ];
        $monatOptions = '';
        foreach ($monate as $mnum => $mname) {
            $sel = ($mnum === $aktMonat) ? 'selected' : '';
            $monatOptions .= "<option value=\"{$mnum}\" {$sel}>{$mname}</option>";
        }
        $fahrerV  = htmlspecialchars((string)($_GET['fahrer']   ?? ''), ENT_QUOTES);
        $fzV      = htmlspecialchars((string)($_GET['fahrzeug'] ?? ''), ENT_QUOTES);
        $begriffV = htmlspecialchars((string)($_GET['begriff']  ?? ''), ENT_QUOTES);

        // Gruppen-Optionen
        $gruppeOptions = '';
        foreach ($fahrergruppen as $g) {
            $nr  = htmlspecialchars((string)($g['nr'] ?? ''), ENT_QUOTES);
            $sel = ((string)($g['nr'] ?? '') === $fGruppe) ? 'selected' : '';
            $nam = htmlspecialchars((string)($g['name'] ?? ''));
            $gruppeOptions .= "<option value=\"{$nr}\" {$sel}>{$nam}</option>";
        }

        // Einsatzart-Checkboxen
        $typItems = '';
        foreach ($einsatzarten as $a) {
            $code    = (string)($a['code'] ?? '');
            $codeEsc = htmlspecialchars($code, ENT_QUOTES);
            $label   = htmlspecialchars(($a['beschreibung'] ?? '') !== '' ? $a['beschreibung'] : $code);
            $isCheck = in_array($code, $fTypen, true) ? 'checked' : '';
            $typItems .= <<<ITEM
<li>
    <label class="dropdown-item d-flex align-items-center gap-2 py-1">
        <input type="checkbox" class="form-check-input flex-shrink-0 mt-0"
               name="typen[]" value="{$codeEsc}" onchange="this.form.submit()" {$isCheck}>
        {$label}
    </label>
</li>
ITEM;
        }

        $typAnzahl = count($fTypen);
        if ($typAnzahl === 0) {
            $typLabel = 'Alle Einsatzarten';
            $typBtn   = 'btn-outline-secondary';
        } elseif ($typAnzahl === 1) {
            $typLabel = htmlspecialchars($fTypen[0]);
            $typBtn   = 'btn-outline-secondary';
        } else {
            $typLabel = $typAnzahl . ' Arten gew&auml;hlt';
            $typBtn   = 'btn-outline-secondary';
        }

        return <<<HTML
<form method="get" action="{$action}" class="w-100" id="einsatzFilterForm">
<div class="row g-2 align-items-end">
    <div class="col-6 col-sm-4 col-lg-auto">
        <label class="filter-label" for="filterJahr">Jahr</label>
        <input type="number" class="form-control form-control-sm" id="filterJahr"
               value="{$aktJahr}" style="max-width:6rem;" onchange="einsatzApplyMonat()">
    </div>
    <div class="col-6 col-sm-4 col-lg-auto">
        <label class="filter-label" for="filterMonat">Monat</label>
        <select id="filterMonat" class="form-select form-select-sm" onchange="einsatzApplyMonat()">
            {$monatOptions}
        </select>
    </div>
    <div class="col-6 col-sm-4 col-lg-auto">
        <label class="filter-label" for="filterVon">Von</label>
        <input type="date" class="form-control form-control-sm" id="filterVon" name="von" value="{$vonEsc}">
    </div>
    <div class="col-6 col-sm-4 col-lg-auto">
        <label class="filter-label" for="filterBis">Bis</label>
        <input type="date" class="form-control form-control-sm" id="filterBis" name="bis" value="{$bisEsc}">
    </div>
    <!-- maxlength je Filter aus der Laenge der durchsuchten Spalten:
         fahrer   -> EINSATZ.fahrer1 (30) oder aufgeloester Name aus
                     PERSONALSTAMM name1+' '+name2 (30+1+30)
         fahrzeug -> EINSATZ.fahrzeug (30)
         begriff  -> EINSATZ.bezeichnung (120) oder dienstnr (10)
         Reine Anzeigefilter -- laengere Eingaben koennen nie treffen. -->
    <div class="col-12 col-sm-6 col-lg-auto">
        <label class="filter-label" for="filterFahrer">Fahrer</label>
        <input type="text" class="form-control form-control-sm" id="filterFahrer" name="fahrer" value="{$fahrerV}" maxlength="61" placeholder="Fahrer">
    </div>
    <div class="col-12 col-sm-6 col-lg-auto">
        <label class="filter-label" for="filterFahrzeug">Fahrzeug</label>
        <input type="text" class="form-control form-control-sm" id="filterFahrzeug" name="fahrzeug" value="{$fzV}" maxlength="30" placeholder="Fahrzeug">
    </div>
    <div class="col-12 col-sm-6 col-lg-auto">
        <label class="filter-label" for="filterBegriff">Begriff</label>
        <input type="text" class="form-control form-control-sm" id="filterBegriff" name="begriff" value="{$begriffV}" maxlength="120" placeholder="Bezeichnung / Dienst-Nr.">
    </div>
    <div class="col-12 col-sm-6 col-lg-auto">
        <label class="filter-label" for="filterGruppe">Gruppe</label>
        <select id="filterGruppe" class="form-select form-select-sm" name="gruppe">
            <option value="">Alle Gruppen</option>
            {$gruppeOptions}
        </select>
    </div>
    <div class="col-12 col-sm-6 col-lg-auto">
        <label class="filter-label">Einsatzarten</label>
        <div class="dropdown">
            <button class="btn btn-sm {$typBtn} dropdown-toggle w-100" type="button"
                    data-bs-toggle="dropdown" data-bs-auto-close="outside">
                {$typLabel}
            </button>
            <ul class="dropdown-menu p-1" style="max-height:300px;overflow:auto;">
                {$typItems}
            </ul>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-auto">
        <div class="form-check">
            <input type="checkbox" class="form-check-input" id="filterTimemode" name="timemode" value="overlaps_range" {$checked}>
            <label class="form-check-label" for="filterTimemode">&Uuml;berlappende Zeitr&auml;ume</label>
        </div>
    </div>
    <div class="col-6 col-lg-auto">
        <button type="submit" class="btn btn-sm fw-semibold w-100 btn-app-primary">
            <i class="bi bi-funnel me-1"></i>Filtern
        </button>
    </div>
    <div class="col-6 col-lg-auto">
        <a href="{$action}" class="btn btn-sm btn-outline-secondary w-100">
            <i class="bi bi-x-lg me-1"></i>Zur&uuml;cksetzen
        </a>
    </div>
</div>
</form>
<script>
// Setzt Von/Bis aus Jahr + Monat und aktualisiert sofort.
// Bewusst ohne $-Zeichen geschrieben (PHP-Heredoc).
function einsatzApplyMonat() {
    var y = document.getElementById('filterJahr').value;
    var m = parseInt(document.getElementById('filterMonat').value, 10);
    if (!y || !m) { return; }
    var p = function (n) { return String(n).padStart(2, '0'); };
    var last = new Date(y, m, 0).getDate();   // Tag 0 des Folgemonats = letzter Tag
    document.getElementById('filterVon').value = y + '-' + p(m) + '-01';
    document.getElementById('filterBis').value = y + '-' + p(m) + '-' + p(last);
    document.getElementById('einsatzFilterForm').submit();
}
</script>
HTML;
    }
}
