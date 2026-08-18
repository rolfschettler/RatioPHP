<?php
/**
 * standard/Controllers/AnmietimportController.php
 *
 * Anmietimport -- liest eine hochgeladene JSON-Datei ein und importiert
 * die Eintraege in die Tabellen ANMIET und ANMIETPOS.
 *
 * Verwendet ausschliesslich die lokalen SQL-CRUD-Endpunkte des RATIOservers
 * (/select, /insert, /update, /delete) statt der getXxx/insertXxx-Endpunkte --
 * fuer ANMIETPOS existiert kein dediziertes REST-Pendant. Request-Formate
 * live gegen den Server verifiziert:
 *
 *   /select              POST {"sql": "...", "params": {...}}   -- params ist PFLICHT, auch wenn leer
 *   /insert?table=Xxx     POST {feld1: wert1, feld2: wert2, ...} (flach, keine Huelle)
 *                         Antwort: {"status":"OK","keyname":"nr","keyvalue":"123"}
 *   /update?table=Xxx&key=schluesselfeld  POST {schluesselfeld: wert, feld: wert, ...}
 *   /delete?table=Xxx     POST {feld: wert, ...} -- als WHERE-Bedingung
 *
 * Erwartetes JSON-Format der Importdatei:
 * {
 *   "anmiet": [
 *     { ...ANMIET-Felder..., "anmietpos": [ {...ANMIETPOS-Felder...}, ... ] },
 *     ...
 *   ]
 * }
 *
 * Schluessel-Handling (per Firebird-Systemtabellen und Testinsert verifiziert):
 *   - ANMIET.NR ist der Integer-Primaerschluessel. Generator laut Vorgabe:
 *     GEN_ID(ANMIETNR, 1). Wird hier explizit per /select geholt und im
 *     insert mitgeschickt (statt sich auf den impliziten DB-Trigger zu
 *     verlassen).
 *   - ANMIET.VORGANG ist ein unabhaengiges String-Geschaeftsfeld (ftstring 20)
 *     -- KEIN Fremdschluessel fuer ANMIETPOS. Wird NICHT selbst gesetzt --
 *     RATIOserver vergibt es automatisch, sofern die uebrigen Pflichtfelder
 *     (insbesondere VART) belegt sind.
 *   - ANMIET.VART wird immer fest auf 'FAHRTAUFTRAG' gesetzt (Vorgabe) --
 *     unabhaengig vom Wert in der Importdatei. Ohne VART unterbleibt die
 *     automatische VORGANG-Vergabe und der Insert schlaegt mit einer
 *     Unique-Key-Verletzung auf ANMIET_VORGANG_KEY fehl (live beobachtet).
 *   - ANMIETPOS.NR ist der Fremdschluessel auf ANMIET.NR (kein eigener
 *     Generator fuer ANMIETPOS.NR vorhanden -- RDB$GENERATORS enthaelt nur
 *     ANMIETNR und ANMIETPOS_LFDNR_GEN, keinen ANMIETPOSNR).
 *   - ANMIETPOS.POSITIONSNR wird in Zehnerschritten vergeben (10, 20, 30, ...)
 *     -- entspricht dem in echten Datensaetzen beobachteten Muster.
 *   - ANMIETPOS.LFDNR bleibt unbelegt (in echten Datensaetzen ebenfalls null).
 *
 * Ueberspringen leerer Datensaetze (Vorgabe):
 *   Ein ANMIET-Eintrag, bei dem ALLE Felder (ausser der verschachtelten
 *   anmietpos-Liste) null sind, wird komplett uebersprungen -- kein
 *   /insert fuer ANMIET, keine Positionen, kein GEN_ID-Aufruf. Die Pruefung
 *   erfolgt VOR dem Erzwingen von vart='FAHRTAUFTRAG', damit ein wirklich
 *   leerer Datensatz nicht durch die vart-Vorgabe kuenstlich "belegt" wird.
 *   Erscheint im Ergebnis mit Status 'uebersprungen' und zaehlt in der
 *   Sammelmeldung getrennt von echten Fehlern.
 *
 *   Dieselbe Regel gilt je einzelner ANMIETPOS-Position: eine Position, bei
 *   der ALLE Felder null sind, wird ebenfalls uebersprungen (kein Insert,
 *   keine positionsnr-Vergabe) -- taucht in der Meldung des zugehoerigen
 *   ANMIET-Datensatzes als "X Position(en) uebersprungen (leer)" auf.
 *
 * UPDATEDISPO-Aufruf nach dem Anlegen des Anmietsatzes (Vorgabe):
 *   RATIOserver dokumentiert keinen eigenen Endpoint fuer Stored Procedures
 *   (kein /procedure, /sp/<name>). Verifiziert (Postman-Collections +
 *   lesbarer Delphi-Quellcode TDataModulSQL.execute) ist stattdessen der
 *   generische Endpunkt POST /execute {"sql": "...", "params": {...}} --
 *   fuehrt beliebiges SQL per ExecSQL aus, gleiches Zugriffsmuster wie
 *   /select. Firebird akzeptiert darin auch EXECUTE PROCEDURE name(:param).
 *   Direkt nach erfolgreichem insertAnmiet wird damit
 *   EXECUTE PROCEDURE UPDATEDISPO(:nr) aufgerufen (nr = ANMIET.NR). Ein
 *   Fehlschlag macht den bereits angelegten ANMIET-Datensatz nicht ungueltig
 *   -- Status faellt auf 'teilweise' (gleiches Soft-Fail-Muster wie bei
 *   fehlgeschlagenen Positionen), die Meldung nennt "UPDATEDISPO fehlgeschlagen".
 *
 * Kein Session-Zwischenspeicher (Vorgabe):
 *   hochladen() rendert das Ergebnis (Statuszeilen + nachgeladene ANMIET/
 *   ANMIETPOS-Daten) direkt selbst, statt es in $_SESSION abzulegen und auf
 *   /anmietimport umzuleiten. index() zeigt bei direktem Aufruf nur das
 *   leere Formular. Konsequenz: kein Post-Redirect-Get -- ein Neuladen der
 *   Seite nach einem Upload fragt den Browser-Dialog zum erneuten Absenden
 *   ab. Bewusst in Kauf genommen, da die importierten Datensaetze nirgends
 *   zwischengespeichert werden sollen.
 *
 * Schutz gegen versehentlichen Doppelimport (Refresh/erneutes Absenden):
 *   Ohne Post-Redirect-Get wuerde ein Browser-Refresh nach dem Upload exakt
 *   denselben POST-Body (inkl. Datei) erneut senden -- ohne Schutz wuerden
 *   dieselben Vorgaenge ein zweites Mal angelegt. Daher bekommt jedes
 *   Formular ein Einmal-Token (import_token): zeigeSeite() erzeugt ein neues
 *   Token und legt es in $_SESSION['anmietimport_token'] ab -- NICHT die
 *   Datensaetze selbst, nur diese eine Zufallszeichenkette. hochladen()
 *   prueft das Token und macht es SOFORT ungueltig, bevor der eigentliche
 *   Import beginnt. Ein Refresh sendet das bereits verbrauchte Token erneut
 *   -- der Import wird dann abgelehnt, bevor irgendein API-Call erfolgt.
 */

namespace Standard\Controllers;

use Core\BaseController;

class AnmietimportController extends BaseController
{
    private const UPLOAD_MAX_BYTES = 5 * 1024 * 1024;

    /**
     * GET /anmietimport -- Upload-Formular, ohne Ergebnis eines frueheren Imports.
     * Das Ergebnis wird ausschliesslich direkt nach dem Upload angezeigt
     * (siehe hochladen()) -- es wird nirgends zwischengespeichert.
     */
    public function index(): void
    {
        $this->zeigeSeite([], []);
    }

    /**
     * POST /anmietimport/hochladen -- Datei entgegennehmen, parsen, importieren.
     */
    public function hochladen(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/anmietimport');
            return;
        }

        // Je Vorgang mehrere sequentielle API-Aufrufe (Schluessel holen,
        // insertAnmiet, UPDATEDISPO, je Position ein insertAnmietpos) --
        // bei groesseren Dateien (z.B. 200 Vorgaenge x 2-3 Positionen =
        // rund 1200-1400 Aufrufe) reicht selbst ein hoeheres PHP-Limit von
        // 60 Sekunden (php.ini max_execution_time) nicht mehr aus.
        set_time_limit(600);

        // Einmal-Token pruefen -- verhindert Doppelimport durch Browser-
        // Refresh/erneutes Absenden desselben POST-Requests. Token SOFORT
        // ungueltig machen, noch bevor die Datei ueberhaupt gelesen wird --
        // ein zweiter Request mit demselben (bereits verbrauchten) Token
        // kommt so nie bis zum eigentlichen Import.
        $eingereichtesToken = $_POST['import_token'] ?? '';
        $erwartetesToken    = $_SESSION['anmietimport_token'] ?? '';
        unset($_SESSION['anmietimport_token']);

        if ($eingereichtesToken === '' || $erwartetesToken === ''
            || !hash_equals($erwartetesToken, $eingereichtesToken)) {
            $this->flashError('Dieser Import wurde bereits verarbeitet oder das Formular ist abgelaufen -- '
                . 'bitte Datei erneut auswählen und hochladen.');
            $this->zeigeSeite([], []);
            return;
        }

        $file = $_FILES['jsonfile'] ?? null;
        if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) {
            $this->flashError('Keine gültige Datei hochgeladen.');
            $this->redirect('/anmietimport');
            return;
        }
        if ($file['size'] > self::UPLOAD_MAX_BYTES) {
            $this->flashError('Datei ist zu groß (max. 5 MB).');
            $this->redirect('/anmietimport');
            return;
        }

        $json = json_decode((string)file_get_contents($file['tmp_name']), true);

        if (!is_array($json) || !isset($json['anmiet']) || !is_array($json['anmiet'])) {
            $this->flashError('Ungültiges JSON-Format -- Schlüssel "anmiet" fehlt oder ist kein Array.');
            $this->redirect('/anmietimport');
            return;
        }

        $ergebnisse          = [];
        $importierteNrs      = [];
        $fehlerAnzahl        = 0;
        $uebersprungenAnzahl = 0;

        foreach ($json['anmiet'] as $eintrag) {
            $positionen = $eintrag['anmietpos'] ?? [];
            unset($eintrag['anmietpos']);
            if (!is_array($positionen)) {
                $positionen = [];
            }

            $bezeichnung = (string)($eintrag['ziel'] ?? '(ohne Bezeichnung)');

            // Nur belegte Felder senden -- null-Werte werden nicht mitgeschickt
            $felder = array_filter($eintrag, static fn($v) => $v !== null);

            // Datensatz ohne jeden belegten Wert -- ueberspringen, kein Insert
            if (empty($felder)) {
                $ergebnisse[] = [
                    'bezeichnung' => $bezeichnung,
                    'status'      => 'uebersprungen',
                    'meldung'     => 'Alle Felder leer -- Datensatz übersprungen.',
                ];
                $uebersprungenAnzahl++;
                continue;
            }

            // vart immer fest auf FAHRTAUFTRAG setzen (Vorgabe) -- unabhaengig
            // vom Wert in der Importdatei
            $felder['vart'] = 'FAHRTAUFTRAG';

            $nr = $this->naechsteAnmietNr();
            if ($nr === null) {
                $ergebnisse[] = [
                    'bezeichnung' => $bezeichnung,
                    'status'      => 'fehler',
                    'meldung'     => 'Kein Schlüsselwert von GEN_ID(ANMIETNR, 1) erhalten.',
                ];
                $fehlerAnzahl++;
                continue;
            }

            $felder['nr'] = $nr;

            $insertResponse = \api_post('/insert?table=ANMIET', $felder);
            if (($insertResponse['status'] ?? '') !== 'OK') {
                $ergebnisse[] = [
                    'bezeichnung' => $bezeichnung,
                    'status'      => 'fehler',
                    'meldung'     => $insertResponse['message'] ?? 'Fehler beim Insert in ANMIET.',
                ];
                $fehlerAnzahl++;
                continue;
            }

            $importierteNrs[] = $nr;

            $dispoOk = $this->aktualisiereDispo($nr);

            $posFehler       = 0;
            $posAnzahl       = 0;
            $posUebersprungen = 0;
            $posNr           = 0;
            foreach ($positionen as $pos) {
                if (!is_array($pos)) {
                    continue;
                }

                // Position ohne jeden belegten Wert -- ueberspringen, kein Insert
                $posFelder = array_filter($pos, static fn($v) => $v !== null);
                if (empty($posFelder)) {
                    $posUebersprungen++;
                    continue;
                }

                $posNr += 10;
                $posAnzahl++;

                $posFelder['nr']          = $nr;
                $posFelder['positionsnr'] = $posNr;

                $posResponse = \api_post('/insert?table=ANMIETPOS', $posFelder);
                if (($posResponse['status'] ?? '') !== 'OK') {
                    $posFehler++;
                }
            }

            $status  = ($posFehler > 0 || !$dispoOk) ? 'teilweise' : 'erfolgreich';
            $meldung = 'ANMIET Nr. ' . $nr . ' angelegt -- ' . $posAnzahl . ' Position(en)';
            if ($posUebersprungen > 0) {
                $meldung .= ', ' . $posUebersprungen . ' Position(en) übersprungen (leer)';
            }
            if ($posFehler > 0) {
                $meldung .= ', ' . $posFehler . ' Position(en) fehlgeschlagen';
                $fehlerAnzahl++;
            }
            if (!$dispoOk) {
                $meldung .= ', UPDATEDISPO fehlgeschlagen';
                $fehlerAnzahl++;
            }

            $ergebnisse[] = [
                'bezeichnung' => $bezeichnung,
                'status'      => $status,
                'meldung'     => $meldung,
            ];
        }

        $gesamt      = count($ergebnisse);
        $importiert  = $gesamt - $fehlerAnzahl - $uebersprungenAnzahl;

        $zusatz = [];
        if ($uebersprungenAnzahl > 0) {
            $zusatz[] = $uebersprungenAnzahl . ' übersprungen (leer)';
        }
        if ($fehlerAnzahl > 0) {
            $zusatz[] = $fehlerAnzahl . ' mit Fehlern';
        }
        $zusatzText = $zusatz ? ' -- ' . implode(', ', $zusatz) . '.' : '.';

        if ($fehlerAnzahl > 0) {
            $this->flashError($importiert . ' von ' . $gesamt . ' Vorgängen importiert' . $zusatzText);
        } else {
            $this->flashSuccess($importiert . ' Vorgang/Vorgänge erfolgreich importiert' . $zusatzText);
        }

        // Direkt anzeigen -- keine Zwischenspeicherung der Datensaetze noetig,
        // die eben importierten NR-Werte sind hier im Speicher bereits bekannt
        $anmietDaten = !empty($importierteNrs) ? $this->ladeImportierteDaten($importierteNrs) : [];
        $this->zeigeSeite($ergebnisse, $anmietDaten);
    }

    /**
     * Rendert das Upload-Formular zusammen mit optionalem Importergebnis.
     */
    private function zeigeSeite(array $ergebnisse, array $anmietDaten): void
    {
        // Verhindert, dass der Browser diese Seite (insbesondere die
        // POST-Antwort von hochladen()) aus dem Cache oder Back-Forward-Cache
        // bedient -- sonst erscheinen bereits verarbeitete Importe erneut,
        // ohne dass der Server neu gerendert hat.
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        // Neues Einmal-Token fuer das Formular -- macht das vorherige
        // (falls eines existierte) implizit ungueltig, da es ueberschrieben wird.
        $token = bin2hex(random_bytes(16));
        $_SESSION['anmietimport_token'] = $token;

        // debug.php prueft ausschliesslich $_GET['debug'] -- bei einem
        // POST-Formular kommt das sonst nie an. Deshalb ?debug=1 direkt in
        // die Form-Action haengen, wenn der aktuelle Request (GET auf
        // /anmietimport ODER die vorherige POST-Antwort) debug=1 gesetzt hat.
        $debugQuery = !empty($_GET['debug']) ? '?debug=1' : '';

        $toolbar = '
        <form method="POST" action="' . APP_BASE . '/anmietimport/hochladen' . $debugQuery . '"
              enctype="multipart/form-data" class="row g-2 align-items-end" id="anmietimportForm">
            <input type="hidden" name="import_token" value="' . htmlspecialchars($token, ENT_QUOTES) . '">
            <div class="col-12 col-sm-6 col-lg-auto">
                <label class="filter-label" for="jsonfile">JSON-Datei</label>
                <input type="file" class="form-control form-control-sm" id="jsonfile"
                       name="jsonfile" accept=".json,application/json" required>
            </div>
            <div class="col-12 col-sm-6 col-lg-auto">
                <button type="submit" class="btn btn-sm fw-semibold w-100 btn-app-primary" id="anmietimportSubmit">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="anmietimportSpinner"
                          role="status" aria-hidden="true"></span>
                    <i class="bi bi-upload me-1" id="anmietimportIcon"></i>
                    <span id="anmietimportLabel">Importieren</span>
                </button>
            </div>
        </form>
        <script>
        (function () {
            var form = document.getElementById("anmietimportForm");
            if (!form) { return; }
            form.addEventListener("submit", function () {
                document.getElementById("anmietimportSpinner").classList.remove("d-none");
                document.getElementById("anmietimportIcon").classList.add("d-none");
                document.getElementById("anmietimportLabel").textContent = "Importiere...";
                document.getElementById("anmietimportSubmit").disabled = true;
            });
        })();
        </script>
        ';

        $this->render('anmietimport/index', [
            'page_title'  => 'Anmietimport',
            'page_header' => '
                <h1 class="h5 fw-bold mb-0" style="color:var(--text-color);">Anmietimport</h1>',
            'toolbar'     => $toolbar,
            'rows'        => $ergebnisse,
            'anmietDaten' => $anmietDaten,
        ]);
    }

    /**
     * Ruft nach dem Anlegen eines Anmietsatzes die Stored Procedure
     * UPDATEDISPO(nr) auf -- ueber den generischen /execute-Endpunkt
     * (ExecSQL, akzeptiert auch EXECUTE PROCEDURE). Kein eigener
     * REST-Endpunkt fuer Stored Procedures dokumentiert.
     */
    private function aktualisiereDispo(int $nr): bool
    {
        $response = \api_post('/execute', [
            'sql'    => 'EXECUTE PROCEDURE UPDATEDISPO(:nr)',
            'params' => ['nr' => $nr],
        ]);

        return ($response['status'] ?? '') === 'OK';
    }

    /**
     * Holt den naechsten ANMIET.NR-Wert ueber den Generator ANMIETNR.
     */
    private function naechsteAnmietNr(): ?int
    {
        $response = \api_post('/select', [
            'sql'    => 'SELECT GEN_ID(ANMIETNR, 1) AS naechster_wert FROM RDB$DATABASE',
            // (object) statt [] -- json_encode([]) ergibt "[]", der Server
            // erwartet fuer params aber ein JSON-Objekt "{}"
            'params' => (object)[],
        ]);

        $wert = $response['data'][0]['naechster_wert'] ?? null;
        return $wert !== null ? (int)$wert : null;
    }

    /**
     * Laedt ANMIET und ANMIETPOS fuer die uebergebenen NR-Werte und haengt
     * jedem ANMIET-Datensatz sein Array von Positionen an (<-- Muster aus
     * dataset-verknuepfung.md, hier per direktem SQL statt getXxx).
     *
     * @param int[] $nrs
     * @return array Liste von ANMIET-Datensaetzen, je mit 'positionen' => [...]
     */
    private function ladeImportierteDaten(array $nrs): array
    {
        $params       = [];
        $platzhalter  = [];
        foreach (array_values($nrs) as $i => $nr) {
            $platzhalter[]        = ':nr' . $i;
            $params['nr' . $i]    = (int)$nr;
        }
        $inListe = implode(', ', $platzhalter);

        $anmietResponse = \api_post('/select', [
            'sql'    => "SELECT nr, vorgang, von, bis, vonzeit, biszeit, ziel, perszahl, reiseart, "
                      . "eart, kennzeichen "
                      . "FROM ANMIET WHERE nr IN ({$inListe}) ORDER BY nr",
            'params' => $params,
        ]);
        $anmietRows = $anmietResponse['data'] ?? [];

        $posResponse = \api_post('/select', [
            'sql'    => "SELECT nr, positionsnr, bezeichnung, menge, epreis "
                      . "FROM ANMIETPOS WHERE nr IN ({$inListe}) ORDER BY nr, positionsnr",
            'params' => $params,
        ]);
        $posRows = $posResponse['data'] ?? [];

        // Positionen nach nr gruppieren
        $posNachNr = [];
        foreach ($posRows as $p) {
            $posNachNr[$p['nr']][] = $p;
        }

        foreach ($anmietRows as &$a) {
            $a['positionen'] = $posNachNr[$a['nr']] ?? [];
        }
        unset($a);

        return $anmietRows;
    }
}
