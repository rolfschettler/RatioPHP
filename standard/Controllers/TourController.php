<?php
/**
 * standard/Controllers/TourController.php
 *
 * Meine Tour -- Fahrerportal, geschuetzt (auth: true). Die eigenen Einsaetze
 * des angemeldeten Fahrers fuer einen Tag oder die naechsten 7 Tage.
 *
 *   index()  GET /fahrer/tour[?tag=0..6 | ?zeitraum=woche]
 *
 *   tag=N            nur der Tag heute + N (Default: heute)
 *   zeitraum=woche   alle 7 Tage ab heute
 *
 * Geladen werden immer alle 7 Tage -- die Tagesleiste zeigt so auch, an
 * welchen Tagen Einsaetze anstehen.
 *
 * Datenquelle ist /select (Auth: true, LocalOnly: true) mit eigener Abfrage --
 * NICHT /dispo/geteinsatzfiltered: der filtert nur nach Zeitraum, ein Fahrer
 * mit Rechten darauf kaeme an die Einsaetze aller Fahrer. /select ist von
 * aussen nicht erreichbar, das Kuerzel kommt aus dem Token
 * (Core\Auth::personalzeichen()) -- NIE aus dem Request.
 *
 * Das Fahrerportal wird am Handy benutzt: Meldungen sind einfache Saetze.
 */

namespace Standard\Controllers;

use Core\Auth;
use Core\BaseController;
use Core\Portal;

class TourController extends BaseController
{
    /** Portal des Moduls -- bestimmt Layout, Navigation und Routen-Praefix. */
    private const PORTAL = 'fahrer';

    /** Pfad des Moduls unterhalb des Portal-Praefix. */
    private const MODUL = '/tour';

    /** Anzahl Tage ab heute, die geladen und in der Leiste angeboten werden. */
    private const TAGE = 7;

    /** Die Seite laedt sich nach so vielen Sekunden selbst neu (Aenderungen der Dispo). */
    private const NEU_LADEN_SEKUNDEN = 300;

    /**
     * Eigene Einsaetze im Zeitraum. Ueberlappung statt "von im Zeitraum":
     * ein Einsatz ueber Mitternacht erscheint auch am Folgetag.
     */
    private const SQL = 'SELECT e.nr, e.von, e.bis, e.bezeichnung, e.typ, a.beschreibung AS typtext,'
        . ' e.fahrzeug, e.abfahrtsort, e.zielort, e.hinweis'
        . ' FROM einsatz e LEFT JOIN einsatzarten a ON a.code = e.typ'
        . ' WHERE (e.fahrer1 = :zeichen OR e.fahrer2 = :zeichen OR e.fahrer3 = :zeichen)'
        . ' AND e.bis >= :von AND e.von <= :bis AND e.storniert_am IS NULL'
        . ' ORDER BY e.von';

    private const WOCHENTAGE = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];

    /**
     * GET /fahrer/tour -- Tagesleiste, darunter die Einsaetze als Karten.
     */
    public function index(): void
    {
        $start = strtotime('today');
        $woche = ($_GET['zeitraum'] ?? '') === 'woche';

        // tag nur als Ziffer 0..TAGE-1 -- alles andere ergibt heute
        $tagWert = (string)($_GET['tag'] ?? '');
        $index   = ctype_digit($tagWert) && (int)$tagWert < self::TAGE ? (int)$tagWert : 0;
        $gewaehlt = strtotime('+' . $index . ' days', $start);

        $zeichen = Auth::personalzeichen();
        if ($zeichen === null) {
            $this->zeige($start, $woche, $gewaehlt, [], 'Ihrem Zugang ist kein Fahrer zugeordnet.');
            return;
        }

        $antwort = \api_post('/select', [
            'sql'    => self::SQL,
            'params' => [
                'zeichen' => $zeichen,
                'von'     => date('Y-m-d', $start) . ' 00:00',
                'bis'     => date('Y-m-d', strtotime('+' . (self::TAGE - 1) . ' days', $start)) . ' 23:59',
            ],
        ]);

        if (($antwort['status'] ?? '') === 'error') {
            $this->apiFehler($antwort, 'Ihre Einsätze können gerade nicht geladen werden. Versuchen Sie es später noch einmal.');
            $this->zeige($start, $woche, $gewaehlt, [], 'Ihre Einsätze können gerade nicht angezeigt werden.');
            return;
        }

        $tage = $this->nachTagen($antwort['data'] ?? [], $start);

        $hinweis = null;
        if ($woche && $tage === []) {
            $hinweis = 'In den nächsten 7 Tagen haben Sie keine Einsätze.';
        } elseif (!$woche && empty($tage[$gewaehlt])) {
            $hinweis = $gewaehlt === $start
                ? 'Heute haben Sie keine Einsätze.'
                : 'Am ' . $this->tagText($gewaehlt) . ' haben Sie keine Einsätze.';
        }

        $this->zeige($start, $woche, $gewaehlt, $tage, $hinweis);
    }

    /**
     * Bereitet die Saetze fuer den View auf und gruppiert sie nach Tag.
     * Ein Einsatz, der vor dem Zeitraum beginnt, zaehlt zum ersten Tag.
     *
     * @return array Zeitstempel des Tages (00:00) => Liste der Karten
     */
    private function nachTagen(array $saetze, int $start): array
    {
        $jetzt    = time();
        $markiert = false;
        $tage     = [];

        foreach ($saetze as $s) {
            $von = strtotime((string)($s['von'] ?? '')) ?: $start;
            $bis = strtotime((string)($s['bis'] ?? '')) ?: $von;
            $tag = max(strtotime('today', $von), $start);

            // Erster noch nicht beendeter Einsatz -- laeuft gerade oder kommt als naechstes
            $markierung = null;
            if (!$markiert && $bis >= $jetzt) {
                $markierung = $von <= $jetzt ? 'Jetzt' : 'Als Nächstes';
                $markiert   = true;
            }

            $tage[$tag][] = [
                'von'         => $von,
                'bis'         => $bis,
                'zeit'        => $this->zeitText($von, $bis, $tag),
                'bezeichnung' => trim((string)($s['bezeichnung'] ?? '')),
                'typ'         => trim((string)($s['typtext'] ?? '')) ?: trim((string)($s['typ'] ?? '')),
                'fahrzeug'    => $this->fahrzeugText((string)($s['fahrzeug'] ?? '')),
                'abfahrtsort' => trim((string)($s['abfahrtsort'] ?? '')),
                'zielort'     => trim((string)($s['zielort'] ?? '')),
                'hinweis'     => trim((string)($s['hinweis'] ?? '')),
                'markierung'  => $markierung,
            ];
        }

        return $tage;
    }

    /** "Heute", "Morgen" oder "Mi, 28.10." */
    private function tagText(int $tag): string
    {
        $heute = strtotime('today');
        if ($tag === $heute) {
            return 'Heute';
        }
        if ($tag === strtotime('+1 day', $heute)) {
            return 'Morgen';
        }
        return self::WOCHENTAGE[(int)date('w', $tag)] . ', ' . date('d.m.', $tag);
    }

    /**
     * Kalenderwoche (ISO) als Text -- "KW 44" oder, wenn der Zeitraum ueber
     * eine Wochengrenze geht, "KW 43 – 44".
     */
    private function kwText(int $von, int $bis): string
    {
        $kwVon = (int)date('W', $von);
        $kwBis = (int)date('W', $bis);

        return 'KW ' . $kwVon . ($kwVon !== $kwBis ? ' – ' . $kwBis : '');
    }

    /**
     * "07:30 – 12:15". Liegt Beginn oder Ende nicht am Tag der Gruppe,
     * steht das Datum davor -- z.B. "24.10. 22:00 – 06:00".
     */
    private function zeitText(int $von, int $bis, int $tag): string
    {
        return $this->uhrzeit($von, $tag) . ' – ' . $this->uhrzeit($bis, $tag);
    }

    /** "07:30" -- liegt der Zeitpunkt nicht am Tag $tag: "26.10. 01:30". */
    private function uhrzeit(int $t, int $tag): string
    {
        return (strtotime('today', $t) === $tag ? '' : date('d.m.', $t) . ' ') . date('H:i', $t);
    }

    /**
     * Erster und letzter Termin eines Tages: Beginn des ersten Einsatzes,
     * Beginn und Ende des letzten. Die Karten sind nach von sortiert.
     */
    private function tagesUebersicht(array $karten, int $tag): array
    {
        $erster  = $karten[0];
        $letzter = $karten[count($karten) - 1];

        return [
            'erster'      => $this->uhrzeit($erster['von'], $tag),
            'letzter'     => $this->uhrzeit($letzter['von'], $tag),
            'letzter_bis' => $this->uhrzeit($letzter['bis'], $tag),
        ];
    }

    /**
     * Fahrzeug fuer die Anzeige. '?' und die RATIOserver-Marker '~#..'
     * bedeuten: noch nicht zugeteilt.
     */
    private function fahrzeugText(string $fahrzeug): string
    {
        $fahrzeug = trim($fahrzeug);
        if ($fahrzeug === '?' || strpos($fahrzeug, '~#') !== false) {
            return 'noch offen';
        }
        return $fahrzeug;
    }

    /**
     * Rendert die Seite.
     *
     * @param int         $start    heute 00:00
     * @param bool        $woche    alle 7 Tage statt eines Tages
     * @param int         $gewaehlt gewaehlter Tag (00:00), wenn nicht $woche
     * @param array       $tage     Tag => Karten, siehe nachTagen()
     * @param string|null $hinweis  Statt der Karten anzeigen
     */
    private function zeige(int $start, bool $woche, int $gewaehlt, array $tage, ?string $hinweis = null): void
    {
        $basis = Portal::praefix(self::PORTAL) . self::MODUL;

        // Tagesleiste: heute + 6 Tage, je mit Anzahl der Einsaetze
        $leiste = [];
        for ($i = 0; $i < self::TAGE; $i++) {
            $tag = strtotime('+' . $i . ' days', $start);
            $leiste[] = [
                'kurz'   => $i === 0 ? 'Heute' : self::WOCHENTAGE[(int)date('w', $tag)],
                'nummer' => date('j', $tag),
                'anzahl' => count($tage[$tag] ?? []),
                'url'    => $basis . ($i === 0 ? '' : '?tag=' . $i),
                'aktiv'  => !$woche && $tag === $gewaehlt,
            ];
        }

        // Anzuzeigende Gruppen: alle Tage oder nur der gewaehlte
        $gruppen = [];
        foreach ($tage as $tag => $karten) {
            if ($woche || $tag === $gewaehlt) {
                $gruppen[] = [
                    'titel'      => $this->tagText($tag),
                    'uebersicht' => $this->tagesUebersicht($karten, $tag),
                    'karten'     => $karten,
                ];
            }
        }

        $ende = strtotime('+' . (self::TAGE - 1) . ' days', $start);

        $this->render('tour/index', [
            'page_title' => 'Meine Tour',
            'portal'     => self::PORTAL,
            'woche'      => $woche,
            'kw'         => $woche ? $this->kwText($start, $ende) : $this->kwText($gewaehlt, $gewaehlt),
            'kw_zusatz'  => $woche
                ? date('d.m.', $start) . ' – ' . date('d.m.', $ende)
                : $this->tagText($gewaehlt) . ($gewaehlt <= strtotime('+1 day', $start) ? ', ' . date('d.m.', $gewaehlt) : ''),
            'leiste'     => $leiste,
            'woche_url'  => $basis . '?zeitraum=woche',
            'gruppen'    => $gruppen,
            'hinweis'    => $hinweis,
            'neu_laden'  => self::NEU_LADEN_SEKUNDEN,
            'stand'      => date('H:i'),
        ]);
    }
}
