<?php
/**
 * standard/Controllers/RegistrierungController.php
 *
 * Selbstregistrierung in der Tabelle REGISTRIERUNG -- fuer beide Portale:
 *
 *   Kundenportal        /kunde/registrieren        typ = 'kunde'
 *                                                  oeffentlich, jeder darf
 *   Mitarbeiterportal   /mitarbeiter/registrieren  typ = 'mitarbeiter'
 *                                                  nur wer in USERS existiert
 *
 * Beide Varianten laufen durch dieselbe Verarbeitung (verarbeite()) und
 * denselben View -- Unterschiede stehen ausschliesslich in der Konstante
 * PORTALE. Zwei getrennte Formulare wuerden mit der Zeit auseinanderdriften.
 *
 * Insert laeuft ueber den dedizierten Endpunkt /registrierung/
 * insertregistrierunglocal (Vorgabe des Entwicklers). Der Endpunkt legt
 * BEIDES in einem Aufruf an: einen Adressdatensatz (ADRESSEN) und die
 * zugehoerige Registrierung (REGISTRIERUNG). Er ist ohne JWT-Token
 * aufrufbar -- im Gegensatz zu insertRegistrierung/getRegistrierungFiltered.
 *
 * Live verifizierte Schnittstelle (Feld-Whitelists laut Delphi-Quelle
 * DataModulRegistrierungClass.pas, insertRegistrierungLocal):
 *
 *   POST /registrierung/insertregistrierunglocal
 *     REGISTRIERUNG: username, pwd2, typ
 *     ADRESSEN:      anrede, name1, name2, strasse, plz, ort, telefon1, email
 *     dazu:          kennziffer (Zuordnung zu bestehender Adresse)
 *     Ignoriert:     titel, gruppe (ADRESSEN.gruppe ist immer 1)
 *     Erfolg: {"status":"OK","nr":..,"kennziffer":..,"adresse":".."}
 *     Fehler: {"status":"error","message":".."}
 *     Der Endpunkt lehnt bereits vergebene Benutzernamen selbst ab
 *     (Gross-/Kleinschreibung wird dabei ignoriert).
 *
 *     Die Adressbehandlung haengt am typ (live verifiziert):
 *       typ='kunde'   -- REGISTRIERUNG und ADRESSEN in einer Transaktion.
 *                        anrede, name1, name2 sind dann Pflicht, sonst
 *                        "Die Felder anrede, name1 und name2 sind bei
 *                        typ=kunde Pflichtfelder."
 *       jeder andere typ (z.B. 'mitarbeiter') und fehlender typ --
 *                        KEINE Adresse, kennziffer bleibt NULL, die
 *                        Adressfelder werden ignoriert. Antwort dann
 *                        "kennziffer":null und "adresse":"keine".
 *     Deshalb fragt das Mitarbeiterformular keine Adressdaten ab -- gesteuert
 *     ueber PORTALE[..]['adressdaten'].
 *
 *   POST /registrierung/checkusernamelocal
 *     Pflicht: username
 *     Frei:   {"status":"OK","username":"..","frei":true,"nr":null}
 *     Belegt: {"status":"error","username":"..","frei":false,"message":"..","nr":12140}
 *
 *     Kennziffer (Kundennummer) -- live verifiziertes Verhalten:
 *       - kennziffer passend, d.h. name1 UND name2 stimmen mit der
 *         bestehenden Adresse ueberein (Gross-/Kleinschreibung wird
 *         ignoriert, anrede ist irrelevant):
 *         {"status":"OK",..,"adresse":"gefunden"} -- die Registrierung haengt
 *         an der bestehenden Adresse, diese wird NICHT geaendert.
 *       - kennziffer unbekannt oder Name passt nicht:
 *         {"status":"error","message":"Diese Kennziffer existiert nicht für
 *         diesen Namen."} -- es wird NICHTS geschrieben, weder Adresse noch
 *         Registrierung. Der normale Fehlerpfad in verarbeite() bricht damit
 *         den Vorgang ab und zeigt die Meldung an.
 *       - kennziffer leer oder 0: neue Adresse ("adresse":"neu").
 *
 * Mitarbeiter-Registrierung -- Identitaetsnachweis ueber USERS:
 *   Registrieren darf sich nur, wer in USERS mit Loginname und Passwort
 *   bereits angelegt ist. USERS.passwort liegt codiert vor; entschluesselt
 *   wird mit Core\Codec (Portierung von DeCodieren aus rechtelib.pas).
 *   Die USERS-Eingabe ist ausdruecklich KEINE Anmeldung: es wird kein
 *   jwt_token-Cookie gesetzt.
 *
 *   ERWARTETE ROUTE -- von RATIOserver noch NICHT bereitgestellt (Stand
 *   2026-08-22 antwortet sie HTTP 401; in WebModuleUnit1.pas existiert
 *   ueberhaupt keine AddRoute-Zeile fuer "users"):
 *
 *     POST /users/getuserlocal        Auth=false, LocalOnly=true
 *       Request:  {"loginname":"ABL"}
 *       Treffer:  {"status":"OK","loginname":"ABL","username":"..",
 *                  "passwort":"<codiert>","gesperrt":"NEIN"}
 *       Unbekannt:{"status":"error","gefunden":false}
 *
 *     Das ausdrueckliche "gefunden":false ist wichtig: nur daran erkennt PHP
 *     einen unbekannten Loginnamen. Eine fehlende oder nicht erreichbare
 *     Route antwortet ebenfalls mit status "error" -- ohne den Marker waere
 *     sie von "Mitarbeiter existiert nicht" nicht zu unterscheiden.
 *
 *     LocalOnly=true ist zwingend: die Route gibt das codierte Passwort
 *     heraus, und die Codierung ist ein XOR mit bekanntem Schluessel -- also
 *     praktisch eine Klartextauskunft. Sie darf nie nach aussen gelangen.
 *
 *   Solange die Route fehlt, verhaelt sich pruefeMitarbeiter() fail-closed:
 *   Abbruch mit technischer Meldung, kein Insert.
 *
 *   Das gepruefte USERS-Passwort ist gleichzeitig das Portalpasswort: es wird
 *   gehasht in REGISTRIERUNG.pwd2 abgelegt. Als REGISTRIERUNG.username dient
 *   der USERS-Loginname. Das Mitarbeiterformular fragt deshalb NUR Loginname
 *   und Passwort ab (dazu freiwillig eine E-Mail-Adresse fuer
 *   REGISTRIERUNG.email) -- kein eigenes Portalpasswort, keine Wiederholung.
 *   Der Mitarbeiter meldet sich am Portal also mit denselben Zugangsdaten an,
 *   die er ohnehin kennt.
 *
 * Das Passwort wird NIE im Klartext an RATIOserver gesendet -- es wird per
 * password_hash() (Bcrypt) gehasht und ausschliesslich der Hash in PWD2
 * gespeichert (Vorgabe des Entwicklers).
 *
 * Missbrauchsschutz (rein serverseitig, kein externer Dienst):
 *   - Honeypot-Feld "homepage" -- im View per Inline-Style ausserhalb des
 *     sichtbaren Bereichs positioniert. Echte Nutzer sehen und befuellen es
 *     nie, einfache Formular-Bots aber schon. Ist es befuellt, wird KEIN
 *     Insert ausgefuehrt, dem Absender aber ein Erfolg vorgetaeuscht --
 *     verraet dem Bot nicht, dass er erkannt wurde.
 *   - Rate-Limiting pro IP-Adresse, getrennt je Aktion:
 *       'registrierung'  -- max. RATE_LIMIT_MAX Versuche pro Stunde. Zaehlt
 *         JEDEN echten (nicht per Honeypot abgefangenen) Absendeversuch,
 *         auch mit ungueltigen Eingaben, damit ein Bot nicht durch Senden
 *         ungueltiger Daten am Limit vorbeikommt.
 *       'usernamecheck' -- max. RATE_LIMIT_CHECK_MAX Pruefungen pro Stunde.
 *         Die Verfuegbarkeitspruefung verraet, ob eine E-Mail-Adresse
 *         registriert ist. Ohne Limit liesse sich damit ein fremder
 *         Adressbestand durchprobieren -- deshalb auch hier eine Grenze.
 *       'mitarbeiterlogin_ip' / 'mitarbeiterlogin_user' -- das
 *         Mitarbeiterformular nimmt USERS-Zugangsdaten an und ist damit eine
 *         oeffentliche Brute-Force-Flaeche auf Mitarbeiterkonten. Deshalb
 *         zwei Zaehler: pro IP und pro Loginname. Der Loginname wird nur als
 *         SHA-256-Hash abgelegt -- keine Klartext-Logins auf Platte.
 *     Umsetzung und Ablage: core/RateLimit.php.
 */

namespace Standard\Controllers;

use Core\Anfrage;
use Core\BaseController;
use Core\Codec;
use Core\Fehler;
use Core\Portal;
use Core\Pruefung;
use Core\RateLimit;

class RegistrierungController extends BaseController
{
    // Grenzen je Zeitfenster (Core\RateLimit::FENSTER, 1 Stunde)
    private const RATE_LIMIT_MAX           = 3;
    private const RATE_LIMIT_CHECK_MAX     = 30;
    private const RATE_LIMIT_MITARB_IP     = 10;
    private const RATE_LIMIT_MITARB_USER   = 5;

    /** Zulaessige Anreden -- entsprechen den Werten im Adressbestand. */
    private const ANREDEN = ['Frau', 'Herr', 'Firma', 'Familie'];

    /**
     * Die Formularfelder -- EINZIGE Quelle fuer Beschriftung, Feldlaenge und
     * Pruefregeln (Schluessel siehe core/Pruefung.php). Daraus entstehen die
     * Labels und maxlength/required im View UND die serverseitige Pruefung
     * samt Fehlertexten. Abweichungen einer Portalvariante stehen in
     * PORTALE[..]['felder'].
     *
     * Grenzen aus den Zielspalten (CLAUDE.md, Abschnitt Feldlaengen):
     *   loginname, login_password  USERS (ftstring 20) -- laenger kann ein
     *                              hinterlegter Wert gar nicht sein
     *   kennziffer                 ADRESSEN.kennziffer (ftinteger)
     *   name1, name2, strasse, ort ADRESSEN bzw. PERSONALSTAMM, je 30
     *   plz 15, telefon1 25        ADRESSEN
     *   username 60                ADRESSEN.email -- REGISTRIERUNG.username
     *                              hat 120, die E-Mail landet in beiden, die
     *                              kleinere Grenze bindet
     *   password 72 BYTES          Bcrypt verarbeitet nur die ersten 72 Bytes
     *                              und ignoriert den Rest stillschweigend --
     *                              lieber ablehnen als still abschneiden. Die
     *                              Zielspalte pwd2 (255) nimmt nur den Hash auf.
     */
    private const FELDER = [
        'loginname'      => ['bezeichnung' => 'Loginname', 'pflicht' => true, 'max_zeichen' => 20],
        'login_password' => ['bezeichnung' => 'Passwort', 'pflicht' => true, 'max_zeichen' => 20, 'geheim' => true],
        'kennziffer'     => ['bezeichnung' => 'Kundennummer', 'ganzzahl' => [1, 2147483647], 'max_zeichen' => 10],
        'anrede'         => ['bezeichnung' => 'Anrede', 'pflicht' => true, 'auswahl' => self::ANREDEN],
        'name1'          => ['bezeichnung' => 'Vorname', 'pflicht' => true, 'max_zeichen' => 30],
        'name2'          => ['bezeichnung' => 'Nachname / Firma', 'pflicht' => true, 'max_zeichen' => 30],
        'strasse'        => ['bezeichnung' => 'Straße und Hausnummer', 'max_zeichen' => 30],
        'plz'            => ['bezeichnung' => 'PLZ', 'max_zeichen' => 15],
        'ort'            => ['bezeichnung' => 'Ort', 'max_zeichen' => 30],
        'telefon1'       => ['bezeichnung' => 'Telefon', 'max_zeichen' => 25],
        'username'       => ['bezeichnung' => 'E-Mail-Adresse', 'pflicht' => true, 'email' => true, 'max_zeichen' => 60],
        'email'          => ['bezeichnung' => 'E-Mail-Adresse', 'email' => true, 'max_zeichen' => 60],
        'password'       => ['bezeichnung' => 'Passwort', 'pflicht' => true, 'min_bytes' => 6, 'max_bytes' => 72, 'geheim' => true],
        'password_wdh'   => ['bezeichnung' => 'Passwort wiederholen', 'pflicht' => true, 'gleich' => 'password', 'geheim' => true],
    ];

    /**
     * Welche Felder ein Schalter in PORTALE einschaltet. username kommt dazu,
     * wenn username_aus nicht 'loginname' ist.
     */
    private const FELDGRUPPEN = [
        'userspruefung'    => ['loginname', 'login_password'],
        'adressdaten'      => ['kennziffer', 'anrede', 'strasse', 'plz', 'ort', 'telefon1'],
        'namensfelder'     => ['name1', 'name2'],
        'eigenes_passwort' => ['password', 'password_wdh'],
        'email_optional'   => ['email'],
    ];

    /**
     * Felder, die NICHT Core\Pruefung prueft, sondern pruefeMitarbeiter() --
     * mit einheitlicher Meldung, damit das Formular nicht verraet, welche
     * Loginnamen existieren.
     */
    private const FELDER_MITARBEITERNACHWEIS = ['loginname', 'login_password'];

    /**
     * Die Portalvarianten. Alles was sich zwischen den Registrierungen
     * unterscheidet, steht hier -- und nur hier. Eine vierte Variante ist ein
     * weiterer Eintrag, kein zweites Formular: kopierte Formulare driften mit
     * der Zeit auseinander.
     *
     * Die Schalter im Einzelnen:
     *   userspruefung    Identitaetsnachweis in PHP gegen USERS
     *                    (pruefeMitarbeiter). Nur das Mitarbeiterportal.
     *   adressdaten      Anrede, Anschrift, Telefon und Kundennummer abfragen.
     *                    Nur typ=kunde -- nur dort legt der Endpunkt eine
     *                    Adresse an, bei jedem anderen typ verwirft er sie.
     *   namensfelder     name1 (Vorname) und name2 (Nachname) abfragen.
     *                    Bei typ=kunde Teil der Adresse, bei typ=fahrer der
     *                    Abgleich mit dem PERSONALSTAMM.
     *   eigenes_passwort Portalpasswort mit Wiederholung abfragen. Im
     *                    Mitarbeiterportal nicht: dort IST das gepruefte
     *                    USERS-Passwort das Portalpasswort.
     *   email_optional   Zusaetzliches, freiwilliges E-Mail-Feld. Landet in
     *                    REGISTRIERUNG.email (60). Nicht beim Kunden: dort IST
     *                    der Benutzername die E-Mail-Adresse.
     *   live_pruefung    Verfuegbarkeit des Benutzernamens schon waehrend der
     *                    Eingabe per fetch pruefen.
     *   username_aus     Woraus REGISTRIERUNG.username entsteht:
     *                    'email'     -- eingegebene E-Mail-Adresse (Kunde)
     *                    'loginname' -- USERS-Loginname (Mitarbeiter)
     *                    'zeichen'   -- Personalstamm-Kuerzel (Fahrer),
     *                                   wird grossgeschrieben gespeichert
     *   erfolg           Erfolgsmeldung -- bei mitarbeiter/fahrer mit Hinweis auf
     *                    die Freischaltung (der Endpunkt legt sie gesperrt an)
     *   felder           Abweichungen von FELDER fuer diese Variante
     *
     * Welche Felder abgefragt und geprueft werden, folgt aus den Schaltern
     * (FELDGRUPPEN) -- ein Feld, das der Endpunkt bei diesem typ verwirft,
     * wird weder abgefragt noch geprueft.
     *
     * Startseite, Portal-Label und der Pfad des Formulars kommen aus
     * core/Portal.php -- sie stehen bewusst nicht noch einmal hier.
     */
    private const PORTALE = [
        'kunde' => [
            'portal'           => 'kunde',
            'typ'              => 'kunde',
            'titel'            => 'Registrieren',
            'untertitel'       => 'Legen Sie ein neues Konto an.',
            'zurueck_text'     => 'Zurück zur Startseite',
            'userspruefung'    => false,
            'adressdaten'      => true,
            'namensfelder'     => true,
            'eigenes_passwort' => true,
            'email_optional'   => false,
            'live_pruefung'    => true,
            'username_aus'     => 'email',
            'dublette'         => 'Diese E-Mail-Adresse ist bereits registriert.',
            'erfolg'           => 'Registrierung erfolgreich.',
        ],
        'mitarbeiter' => [
            'portal'           => 'mitarbeiter',
            'typ'              => 'mitarbeiter',
            'titel'            => 'Mitarbeiter registrieren',
            'untertitel'       => 'Legen Sie Ihren Portalzugang an.',
            'zurueck_text'     => 'Zurück zum Mitarbeiterportal',
            'userspruefung'    => true,
            'adressdaten'      => false,
            'namensfelder'     => false,
            'eigenes_passwort' => false,
            'email_optional'   => true,
            'live_pruefung'    => false,
            // Der Mitarbeiter meldet sich am Portal mit seinem USERS-Loginnamen
            // an. Eine E-Mail-Adresse kann er freiwillig angeben -- sie landet
            // in REGISTRIERUNG.email, eine Adresse entsteht nicht.
            'username_aus'     => 'loginname',
            'dublette'         => 'Für diesen Loginnamen ist bereits ein Portalzugang angelegt.',
            'erfolg'           => self::ERFOLG_FREISCHALTUNG,
        ],
        'fahrer' => [
            'portal'           => 'fahrer',
            'typ'              => 'fahrer',
            'titel'            => 'Fahrer registrieren',
            'untertitel'       => 'Legen Sie Ihren Portalzugang an.',
            'zurueck_text'     => 'Zurück zum Fahrerportal',
            // Kein USERS-Nachweis in PHP: den Abgleich macht der Endpunkt
            // selbst gegen den PERSONALSTAMM (zeichen + name1 + name2).
            'userspruefung'    => false,
            'adressdaten'      => false,
            'namensfelder'     => true,
            'eigenes_passwort' => true,
            'email_optional'   => true,
            // Bewusst aus: eine Live-Pruefung wuerde oeffentlich verraten,
            // welche Fahrerkuerzel bereits einen Zugang haben.
            'live_pruefung'    => false,
            'username_aus'     => 'zeichen',
            'dublette'         => 'Für dieses Fahrerkürzel ist bereits ein Portalzugang angelegt.',
            'erfolg'           => self::ERFOLG_FREISCHALTUNG,
            // Kuerzel aus PERSONALSTAMM.zeichen (ftstring 15), immer gross --
            // der Endpunkt normalisiert ebenso, der Anmeldevergleich laeuft
            // per UPPER(). name2 ist hier kein Firmenname.
            'felder'           => [
                'username' => ['bezeichnung' => 'Fahrerkürzel', 'email' => false, 'max_zeichen' => 15, 'gross' => true],
                'name2'    => ['bezeichnung' => 'Nachname'],
            ],
        ],
    ];

    /**
     * Erfolgsmeldung fuer typ=mitarbeiter und typ=fahrer: insertregistrierunglocal
     * legt beide mit REGISTRIERUNG.gesperrt='JA' an, freigeschaltet wird in der
     * Registrierungsverwaltung. Ohne diesen Hinweis wirkt die Sperrmeldung
     * beim ersten Login wie ein Fehler.
     */
    private const ERFOLG_FREISCHALTUNG = 'Registrierung erfolgreich. Ihr Zugang wird nach der Freischaltung aktiv.';

    /**
     * Einheitliche Meldung fuer jeden Fehlschlag der USERS-Pruefung.
     * Bewusst nicht unterscheiden zwischen "Loginname unbekannt",
     * "Passwort falsch" und "gesperrt" -- sonst verraet das oeffentliche
     * Formular, welche Loginnamen im System existieren.
     */
    private const FEHLER_MITARBEITER = 'Der gewünschte Mitarbeiter ist im System nicht angelegt';

    // ------------------------------------------------------------------
    // Actions
    // ------------------------------------------------------------------

    /** GET /kunde/registrieren -- leeres Formular, Kundenportal. */
    public function index(): void
    {
        $this->zeigeFormular('kunde');
    }

    /** POST /kunde/registrieren/absenden -- Kundenportal. */
    public function speichern(): void
    {
        $this->verarbeite('kunde');
    }

    /** GET /mitarbeiter/registrieren -- leeres Formular, Mitarbeiterportal. */
    public function mitarbeiter(): void
    {
        $this->zeigeFormular('mitarbeiter');
    }

    /** POST /mitarbeiter/registrieren/absenden -- Mitarbeiterportal. */
    public function mitarbeiterSpeichern(): void
    {
        $this->verarbeite('mitarbeiter');
    }

    /** GET /fahrer/registrieren -- leeres Formular, Fahrerportal. */
    public function fahrer(): void
    {
        $this->zeigeFormular('fahrer');
    }

    /** POST /fahrer/registrieren/absenden -- Fahrerportal. */
    public function fahrerSpeichern(): void
    {
        $this->verarbeite('fahrer');
    }

    // ------------------------------------------------------------------
    // Gemeinsame Verarbeitung
    // ------------------------------------------------------------------

    /**
     * Rendert das Formular der angegebenen Portalvariante -- leer oder mit
     * den bisherigen Eingaben (ohne Passwoerter).
     */
    private function zeigeFormular(string $variante, array $eingaben = []): void
    {
        $this->render('registrierung/index', $this->viewDaten($variante, $eingaben));
    }

    /**
     * Prueft die Eingaben und legt die Registrierung an.
     */
    private function verarbeite(string $variante): void
    {
        $konfig = self::PORTALE[$variante];

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(Portal::registrierung($konfig['portal']));
            return;
        }

        $honeypot = trim((string)($_POST['homepage'] ?? ''));

        // Honeypot befuellt -- Bot erkannt. Kein Insert, aber Erfolg
        // vortaeuschen (verraet dem Bot nicht, woran es lag).
        if ($honeypot !== '') {
            $this->flashSuccess($konfig['erfolg']);
            $this->redirect(Portal::start($konfig['portal']));
            return;
        }

        // Eingaben einsammeln -- nur die Felder dieser Variante (FELDGRUPPEN).
        // Bei einem Fehler fliessen alle Werte ausser den Passwoertern
        // ('geheim') zurueck in das Formular.
        $felder   = $this->felder($variante);
        $werte    = Pruefung::werteAusPost($felder);
        $eingaben = Pruefung::ohneGeheime($felder, $werte);

        $ip = Anfrage::ip();
        if (RateLimit::ueberschritten($ip, 'registrierung', self::RATE_LIMIT_MAX)) {
            $this->zeigeMitFehler(
                'Zu viele Registrierungen von dieser IP-Adresse -- bitte später erneut versuchen.',
                $eingaben,
                $variante
            );
            return;
        }

        // --- Identitaetsnachweis im Mitarbeiterportal -----------------------
        // Zuerst -- wer sich nicht als Mitarbeiter ausweisen kann, soll gar
        // nicht erfahren, ob die restlichen Eingaben in Ordnung waeren.

        if ($konfig['userspruefung']) {
            $fehler = $this->pruefeMitarbeiter($werte['loginname'], $werte['login_password'], $ip);
            if ($fehler !== null) {
                $this->zeigeMitFehler($fehler, $eingaben, $variante);
                return;
            }
        }

        // --- Feldpruefung ---------------------------------------------------
        // Alle Fehler auf einmal -- Texte, Pflichtfelder und Grenzen kommen aus
        // FELDER. Die Felder des Mitarbeiternachweises sind oben bereits
        // geprueft (mit einheitlicher Meldung).

        $pruefung = Pruefung::formular(
            array_diff_key($felder, array_flip(self::FELDER_MITARBEITERNACHWEIS)),
            $werte
        );
        if (!$pruefung->melde()) {
            $this->zeigeFormular($variante, $eingaben);
            return;
        }

        // Benutzername der Registrierung:
        //   Kundenportal      -- die E-Mail-Adresse
        //   Mitarbeiterportal -- der oben gepruefte USERS-Loginname
        //   Fahrerportal      -- das Personalstamm-Kuerzel, durch 'gross'
        //                        bereits grossgeschrieben
        $benutzername = $werte[$konfig['username_aus'] === 'loginname' ? 'loginname' : 'username'];

        // --- Benutzername bereits vergeben? ---------------------------------
        // Vorabpruefung fuer eine verstaendliche Meldung. Der Insert prueft
        // selbst noch einmal -- deckt den Fall ab, dass zwischen Pruefung und
        // Insert derselbe Name von jemand anderem registriert wird.

        $check = \api_post('/registrierung/checkusernamelocal', [
            'username' => $benutzername,
        ]);

        if (($check['frei'] ?? null) === false) {
            $this->zeigeMitFehler($konfig['dublette'], $eingaben, $variante);
            return;
        }

        // --- Anlegen --------------------------------------------------------
        // Im Mitarbeiterportal ist das Portalpasswort dasselbe wie das
        // Mitarbeiterpasswort aus USERS -- es wurde oben gegen USERS geprueft
        // und wird hier gehasht abgelegt. Ein eigenes Portalpasswort gibt es
        // dort nicht.
        $klartextPasswort = $konfig['eigenes_passwort'] ? $werte['password'] : $werte['login_password'];

        $daten = [
            'username' => $benutzername,
            'pwd2'     => password_hash($klartextPasswort, PASSWORD_DEFAULT),
            'typ'      => $konfig['typ'],
        ];

        // name1/name2 gehen bei jeder Variante mit, die sie abfragt -- bei
        // typ=kunde als Adressdaten, bei typ=fahrer als Suchkriterium fuer den
        // PERSONALSTAMM-Abgleich (zeichen + name1 + name2). Ohne sie lehnt der
        // Endpunkt beide Varianten ab.
        if ($konfig['namensfelder']) {
            $daten += [
                'name1' => $werte['name1'],
                'name2' => $werte['name2'],
            ];
        }

        // Freiwillige E-Mail-Adresse (Mitarbeiter, Fahrer) -- nur mitsenden,
        // wenn angegeben. Der Endpunkt schreibt sie bei jedem typ in
        // REGISTRIERUNG.email.
        if ($konfig['email_optional'] && $werte['email'] !== '') {
            $daten['email'] = $werte['email'];
        }

        // Uebrige Adressfelder nur bei typ=kunde -- bei jedem anderen typ legt
        // der Endpunkt keine Adresse an und ignoriert sie ohnehin.
        // email wird mit dem Benutzernamen befuellt -- der Benutzername IST
        // die E-Mail-Adresse, ein zweites Feld dafuer waere redundant.
        if ($konfig['adressdaten']) {
            $daten += [
                'anrede'   => $werte['anrede'],
                'strasse'  => $werte['strasse'],
                'plz'      => $werte['plz'],
                'ort'      => $werte['ort'],
                'telefon1' => $werte['telefon1'],
                'email'    => $benutzername,
            ];

            // Kundennummer nur mitsenden wenn angegeben -- 0 oder Leerstring
            // wuerde der Endpunkt ohnehin verwerfen.
            if ($werte['kennziffer'] !== '') {
                $daten['kennziffer'] = (int)$werte['kennziffer'];
            }
        }

        $insertResponse = \api_post('/registrierung/insertregistrierunglocal', $daten);

        if (!Fehler::ok($insertResponse)) {
            // Fachliche Ablehnung (z.B. nicht im Personalstamm) als Dialog;
            // ein Systemfehler steht bereits im reservierten Bereich. In beiden
            // Faellen bleibt das Formular mit den Eingaben stehen.
            $this->apiFehler($insertResponse, 'Registrierung fehlgeschlagen -- bitte später erneut versuchen.');
            $this->zeigeFormular($variante, $eingaben);
            return;
        }

        // Absicherung: bei uebergebener Kundennummer meldet der Endpunkt
        // entweder status "error" (oben behandelt) oder adresse "gefunden".
        // Alles andere waere eine Verhaltensaenderung im Backend -- die
        // Registrierung ist dann bereits angelegt, deshalb kein Abbruch,
        // aber ein Protokolleintrag fuer die Fehlersuche.
        if ($konfig['adressdaten']
            && $eingaben['kennziffer'] !== ''
            && ($insertResponse['adresse'] ?? '') !== 'gefunden') {
            error_log(
                'Registrierung: Kundennummer ' . (int)$eingaben['kennziffer']
                . ' uebergeben, insertregistrierunglocal meldet aber adresse="'
                . (string)($insertResponse['adresse'] ?? '') . '" (nr='
                . (string)($insertResponse['nr'] ?? '?') . ').'
            );
        }

        // Kein automatisches Oeffnen des Anmelden-Modals, kein jwt_token-Cookie
        // -- die REGISTRIERUNG-Anmeldung ist ein eigenes System, unabhaengig
        // vom JWT-Login dieser App. Nur die Erfolgsmeldung anzeigen.
        $this->flashSuccess($konfig['erfolg']);
        $this->redirect(Portal::start($konfig['portal']));
    }

    /**
     * Prueft Loginname und Passwort gegen die Tabelle USERS.
     *
     * USERS.passwort ist codiert abgelegt; entschluesselt wird mit
     * Core\Codec::decodieren() -- der Portierung von DeCodieren aus
     * rechtelib.pas. Gelesen wird ueber die tokenfreie, nur lokal
     * erreichbare Route /users/getuserlocal.
     *
     * Fail-closed: nur ein tatsaechlich passendes Passwort laesst den Vorgang
     * weiterlaufen. Ist die Route nicht erreichbar oder ihre Antwort nicht
     * verwertbar, wird abgebrochen -- niemals durchgelassen.
     *
     * @return string|null Fehlermeldung fuer den Besucher, oder null bei Erfolg.
     */
    private function pruefeMitarbeiter(string $loginname, string $passwort, string $ip): ?string
    {
        if ($loginname === '' || mb_strlen($loginname) > self::FELDER['loginname']['max_zeichen']) {
            return self::FEHLER_MITARBEITER;
        }

        // Leeres Passwort: eigene, verstaendliche Meldung -- und zwar VOR den
        // Zaehlern und dem Endpunktaufruf. Sie verraet nichts ueber das Konto,
        // und ein versehentlich leeres Feld verbraucht keinen Versuch.
        if ($passwort === '') {
            return 'Bitte geben Sie Ihr Mitarbeiter-Passwort ein.';
        }

        // Zwei Zaehler: pro IP und pro Loginname. Der Loginname landet nur als
        // Hash in der Zaehlerdatei -- kein Klartext-Login auf Platte.
        if (RateLimit::ueberschritten($ip, 'mitarbeiterlogin_ip', self::RATE_LIMIT_MITARB_IP)) {
            return 'Zu viele Versuche von dieser IP-Adresse -- bitte später erneut versuchen.';
        }

        if (RateLimit::ueberschritten(RateLimit::schluessel($loginname), 'mitarbeiterlogin_user', self::RATE_LIMIT_MITARB_USER)) {
            return 'Zu viele Versuche für diesen Loginnamen -- bitte später erneut versuchen.';
        }

        // Laenger als USERS.passwort (ftstring 20) kann kein hinterlegtes
        // Passwort sein -- der Vergleich weiter unten koennte nie passen.
        // Bewusst dieselbe neutrale Meldung wie jeder andere Fehlschlag und
        // bewusst NACH den Zaehlern: das ist ein Fehlversuch wie jeder andere.
        if (mb_strlen($passwort) > self::FELDER['login_password']['max_zeichen']) {
            return self::FEHLER_MITARBEITER;
        }

        $antwort = \api_post('/users/getuserlocal', ['loginname' => $loginname]);

        // Loginname existiert nicht -- regulaerer Fall, vorgegebene Meldung.
        // Erkannt wird er ausschliesslich am ausdruecklichen "gefunden":false.
        // Wichtig: NICHT an status "error" festmachen -- eine fehlende oder
        // nicht erreichbare Route antwortet ebenfalls mit status "error"
        // ("Keine Anmeldedaten verfügbar"), und dann waere die Aussage
        // "Mitarbeiter nicht angelegt" schlicht falsch.
        if (($antwort['gefunden'] ?? null) === false) {
            return self::FEHLER_MITARBEITER;
        }

        // Kein verwertbares Ergebnis -- Route fehlt, nicht erreichbar oder
        // liefert etwas Unerwartetes. Fail-closed und protokollieren.
        if (!array_key_exists('passwort', $antwort)) {
            error_log(
                'Mitarbeiter-Registrierung: /users/getuserlocal liefert kein Feld "passwort" -- '
                . 'Antwort-Schluessel: ' . implode(',', array_keys($antwort))
                . ' status=' . (string)($antwort['status'] ?? '?')
            );

            return 'Die Mitarbeiterprüfung ist derzeit nicht möglich -- bitte später erneut versuchen.';
        }

        // Gesperrte Konten duerfen sich nicht registrieren. Bewusst dieselbe
        // Meldung wie bei unbekanntem Loginnamen.
        if (strcasecmp(trim((string)($antwort['gesperrt'] ?? '')), 'JA') === 0) {
            return self::FEHLER_MITARBEITER;
        }

        $klartext = Codec::decodieren(trim((string)$antwort['passwort']));

        // Leeres Passwort in USERS -- kein gueltiger Nachweis, nie durchlassen.
        if ($klartext === '') {
            return self::FEHLER_MITARBEITER;
        }

        if (!hash_equals($klartext, $passwort)) {
            return self::FEHLER_MITARBEITER;
        }

        return null;
    }

    /**
     * POST /kunde/registrieren/username-pruefen -- Verfuegbarkeitspruefung fuer
     * Formular, wird per fetch() aufgerufen sobald die E-Mail-Adresse
     * eingegeben ist. Nur das Kundenportal hat live_pruefung.
     *
     * Antwortet immer JSON: {"frei":true|false|null,"message":".."}
     * frei = null bedeutet "keine Aussage moeglich" -- das Formular macht
     * dann bewusst keine Zusage.
     */
    public function usernamePruefen(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['frei' => null, 'message' => 'Methode nicht erlaubt.'], 405);
            return;
        }

        // Dieselbe Feld-Definition und Pruefung wie beim Absenden
        $feld     = array_intersect_key($this->felder('kunde'), ['username' => true]);
        $username = Pruefung::werteAusPost($feld)['username'];
        $pruefung = Pruefung::formular($feld, ['username' => $username]);

        if (!$pruefung->ok()) {
            $this->json(['frei' => null, 'message' => $pruefung->alle()[0]]);
            return;
        }

        // Die Pruefung verraet, ob eine Adresse registriert ist -- Limit
        // gegen das Durchprobieren fremder Adressen.
        if (RateLimit::ueberschritten(Anfrage::ip(), 'usernamecheck', self::RATE_LIMIT_CHECK_MAX)) {
            $this->json(['frei' => null, 'message' => '']);
            return;
        }

        $check = \api_post('/registrierung/checkusernamelocal', ['username' => $username]);
        $frei  = $check['frei'] ?? null;

        if ($frei === true) {
            $this->json(['frei' => true, 'message' => 'Diese E-Mail-Adresse ist noch frei.']);
            return;
        }
        if ($frei === false) {
            $this->json(['frei' => false, 'message' => self::PORTALE['kunde']['dublette']]);
            return;
        }

        $this->json(['frei' => null, 'message' => '']);
    }

    /**
     * Gibt eine JSON-Antwort aus und beendet den Request.
     * Bewusst hier und nicht in core/BaseController.php -- core wird bei
     * Updates ersetzt.
     */
    private function json(array $daten, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($daten, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Zeigt das Formular erneut mit einem Benutzerfehler (Dialog) und den
     * bisherigen Eingaben (ohne Passwoerter).
     */
    private function zeigeMitFehler(string $fehler, array $eingaben, string $variante): void
    {
        $this->flashError($fehler);
        $this->zeigeFormular($variante, $eingaben);
    }

    /**
     * Feld-Definitionen der Variante: nur die Felder, die ihre Schalter
     * einschalten (FELDGRUPPEN), in der Reihenfolge von FELDER und mit den
     * Abweichungen aus PORTALE[..]['felder'].
     */
    private function felder(string $variante): array
    {
        $konfig = self::PORTALE[$variante];

        $aktiv = $konfig['username_aus'] === 'loginname' ? [] : ['username'];
        foreach (self::FELDGRUPPEN as $schalter => $gruppe) {
            if ($konfig[$schalter]) {
                $aktiv = array_merge($aktiv, $gruppe);
            }
        }

        $felder = array_intersect_key(self::FELDER, array_flip($aktiv));
        foreach ($konfig['felder'] ?? [] as $feld => $abweichung) {
            if (isset($felder[$feld])) {
                $felder[$feld] = array_replace($felder[$feld], $abweichung);
            }
        }
        return $felder;
    }

    /**
     * Ziel des Formular-Submits: Registrierungspfad des Portals + /absenden.
     * Ergibt /registrieren/absenden, /mitarbeiter/registrieren/absenden und
     * /fahrer/registrieren/absenden -- die Routen in config/routes.php.
     */
    private function aktionPfad(string $portal): string
    {
        return Portal::registrierung($portal) . '/absenden';
    }

    /**
     * Baut die View-Variablen einer Portalvariante zusammen.
     */
    private function viewDaten(string $variante, array $eingaben = []): array
    {
        $konfig = self::PORTALE[$variante];

        return [
            'page_title'           => $konfig['titel'],
            'portal'               => $konfig['portal'],
            'titel'                => $konfig['titel'],
            'untertitel'           => $konfig['untertitel'],
            'formular_action'      => $this->aktionPfad($konfig['portal']),
            // Ziel der Live-Verfuegbarkeitspruefung (fetch im View). Liegt wie
            // das Formular unter dem Registrierungspfad des Portals, damit die
            // Route zum Portal-Praefix passt.
            'pruef_url'            => Portal::registrierung($konfig['portal']) . '/username-pruefen',
            'zurueck_link'         => Portal::start($konfig['portal']),
            'zurueck_text'         => $konfig['zurueck_text'],
            'mitarbeiter_pruefung' => $konfig['userspruefung'],
            'adressdaten'          => $konfig['adressdaten'],
            'namensfelder'         => $konfig['namensfelder'],
            'eigenes_passwort'     => $konfig['eigenes_passwort'],
            'live_pruefung'        => $konfig['live_pruefung'],
            'email_optional'       => $konfig['email_optional'],
            'username_aus'         => $konfig['username_aus'],
            // Beschriftung, Grenzen und Pflichtfelder -- dieselben
            // Definitionen, nach denen der Controller prueft
            'felder'               => $this->felder($variante),
            'eingaben'             => $eingaben,
        ];
    }
}
