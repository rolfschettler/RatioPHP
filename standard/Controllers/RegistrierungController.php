<?php
/**
 * standard/Controllers/RegistrierungController.php
 *
 * Selbstregistrierung in der Tabelle REGISTRIERUNG -- fuer beide Portale:
 *
 *   Kundenportal        /registrieren              typ = 'kunde'
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
 *   und Passwort ab -- kein eigenes Portalpasswort, keine Wiederholung und
 *   keine E-Mail-Adresse: REGISTRIERUNG hat kein E-Mail-Feld, und ohne Adresse
 *   gibt es auch kein ADRESSEN.email, in dem sie landen koennte.
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
 *     Ablage in einer JSON-Datei im System-Temp-Verzeichnis
 *     (sys_get_temp_dir()) -- bewusst NICHT im Web-Root, keine Datenbank-
 *     Verbindung noetig (PHP macht laut Vorgabe keine direkte DB-Anbindung).
 */

namespace Standard\Controllers;

use Core\BaseController;
use Core\Codec;

class RegistrierungController extends BaseController
{
    private const MIN_PASSWORT_LAENGE      = 6;
    private const RATE_LIMIT_MAX           = 3;
    private const RATE_LIMIT_CHECK_MAX     = 30;
    private const RATE_LIMIT_MITARB_IP     = 10;
    private const RATE_LIMIT_MITARB_USER   = 5;
    private const RATE_LIMIT_FENSTER       = 3600;   // 1 Stunde in Sekunden
    private const RATE_LIMIT_DATEI         = 'ratiophp_registrierung_ratelimit.json';

    /** Maximale Laenge von USERS.loginname (ftstring 20). */
    private const MAX_LOGINNAME = 20;

    /**
     * Die beiden Portalvarianten. Alles was sich zwischen Kunden- und
     * Mitarbeiterregistrierung unterscheidet, steht hier -- und nur hier.
     */
    private const PORTALE = [
        'kunde' => [
            'portal'        => 'kunde',
            'typ'           => 'kunde',
            'titel'         => 'Registrieren',
            'untertitel'    => 'Legen Sie ein neues Konto an.',
            'formular'      => '/registrieren',
            'aktion'        => '/registrieren/absenden',
            'nach_erfolg'   => '/',
            'zurueck'       => '/',
            'zurueck_text'  => 'Zurück zur Startseite',
            'userspruefung' => false,
            'adressdaten'   => true,
            // Woraus REGISTRIERUNG.username entsteht -- 'email' fragt eine
            // E-Mail-Adresse ab, 'loginname' uebernimmt den USERS-Loginnamen.
            'username_aus'  => 'email',
            'dublette'      => 'Diese E-Mail-Adresse ist bereits registriert.',
        ],
        'mitarbeiter' => [
            'portal'        => 'mitarbeiter',
            'typ'           => 'mitarbeiter',
            'titel'         => 'Mitarbeiter registrieren',
            'untertitel'    => 'Legen Sie Ihren Portalzugang an.',
            'formular'      => '/mitarbeiter/registrieren',
            'aktion'        => '/mitarbeiter/registrieren/absenden',
            'nach_erfolg'   => '/mitarbeiter',
            'zurueck'       => '/mitarbeiter',
            'zurueck_text'  => 'Zurück zum Mitarbeiterportal',
            'userspruefung' => true,
            'adressdaten'   => false,
            // Der Mitarbeiter meldet sich am Portal mit seinem USERS-Loginnamen
            // an -- eine E-Mail-Adresse wird nicht abgefragt: REGISTRIERUNG hat
            // kein E-Mail-Feld, und ohne Adresse gibt es auch kein
            // ADRESSEN.email, in dem sie landen koennte.
            'username_aus'  => 'loginname',
            'dublette'      => 'Für diesen Loginnamen ist bereits ein Portalzugang angelegt.',
        ],
    ];

    /** Zulaessige Anreden -- entsprechen den Werten im Adressbestand. */
    private const ANREDEN = ['Frau', 'Herr', 'Firma', 'Familie'];

    /**
     * Maximale Feldlaengen laut RATIOserver-Header von ADRESSEN.
     * username ist in REGISTRIERUNG 120 Zeichen lang, wird aber zusaetzlich
     * als ADRESSEN.email gespeichert -- daher gilt die kleinere Grenze 60.
     */
    private const MAX_LAENGE = [
        'name1'    => 30,
        'name2'    => 30,
        'strasse'  => 30,
        'plz'      => 15,
        'ort'      => 30,
        'telefon1' => 25,
        'username' => 60,
    ];

    /** Groesstmoeglicher Wert fuer ADRESSEN.kennziffer (ftinteger). */
    private const MAX_KENNZIFFER = 2147483647;

    /** Beschriftungen fuer Fehlermeldungen zu Ueberlaengen. */
    private const BEZEICHNUNG = [
        'name1'    => 'Vorname',
        'name2'    => 'Nachname bzw. Firma',
        'strasse'  => 'Straße',
        'plz'      => 'PLZ',
        'ort'      => 'Ort',
        'telefon1' => 'Telefon',
        'username' => 'E-Mail-Adresse',
    ];

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

    /** GET /registrieren -- leeres Formular, Kundenportal. */
    public function index(): void
    {
        $this->zeigeFormular('kunde');
    }

    /** POST /registrieren/absenden -- Kundenportal. */
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

    // ------------------------------------------------------------------
    // Gemeinsame Verarbeitung
    // ------------------------------------------------------------------

    /**
     * Rendert das leere Formular der angegebenen Portalvariante.
     */
    private function zeigeFormular(string $variante): void
    {
        $this->render('registrierung/index', $this->viewDaten($variante));
    }

    /**
     * Prueft die Eingaben und legt die Registrierung an.
     */
    private function verarbeite(string $variante): void
    {
        $konfig = self::PORTALE[$variante];

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect($konfig['formular']);
            return;
        }

        $honeypot = trim((string)($_POST['homepage'] ?? ''));

        // Honeypot befuellt -- Bot erkannt. Kein Insert, aber Erfolg
        // vortaeuschen (verraet dem Bot nicht, woran es lag).
        if ($honeypot !== '') {
            $this->flashSuccess('Registrierung erfolgreich.');
            $this->redirect($konfig['nach_erfolg']);
            return;
        }

        // Eingaben einsammeln -- alle Werte fliessen bei einem Fehler zurueck
        // in das Formular, ausser den Passwoertern.
        $eingaben = [
            'anrede'     => trim((string)($_POST['anrede']     ?? '')),
            'name1'      => trim((string)($_POST['name1']      ?? '')),
            'name2'      => trim((string)($_POST['name2']      ?? '')),
            'strasse'    => trim((string)($_POST['strasse']    ?? '')),
            'plz'        => trim((string)($_POST['plz']        ?? '')),
            'ort'        => trim((string)($_POST['ort']        ?? '')),
            'telefon1'   => trim((string)($_POST['telefon1']   ?? '')),
            'username'   => trim((string)($_POST['username']   ?? '')),
            'kennziffer' => trim((string)($_POST['kennziffer'] ?? '')),
            'loginname'  => trim((string)($_POST['loginname']  ?? '')),
        ];

        $passwort           = (string)($_POST['password']       ?? '');
        $passwortWiederholt = (string)($_POST['password_wdh']   ?? '');
        $usersPasswort      = (string)($_POST['login_password'] ?? '');

        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        if ($ip !== '' && $this->rateLimitUeberschritten($ip, 'registrierung', self::RATE_LIMIT_MAX)) {
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
            $fehler = $this->pruefeMitarbeiter($eingaben['loginname'], $usersPasswort, $ip);
            if ($fehler !== null) {
                $this->zeigeMitFehler($fehler, $eingaben, $variante);
                return;
            }
        }

        // --- Benutzername der Registrierung ---------------------------------
        // Kundenportal: die eingegebene E-Mail-Adresse.
        // Mitarbeiterportal: der USERS-Loginname -- er ist oben schon geprueft
        // (vorhanden, maximal MAX_LOGINNAME Zeichen, Passwort passt).

        if ($konfig['username_aus'] === 'loginname') {
            $benutzername = $eingaben['loginname'];
        } else {
            $benutzername = $eingaben['username'];

            if ($benutzername === '' || !filter_var($benutzername, FILTER_VALIDATE_EMAIL)) {
                $this->zeigeMitFehler('Bitte eine gültige E-Mail-Adresse angeben.', $eingaben, $variante);
                return;
            }
            if (mb_strlen($benutzername) > self::MAX_LAENGE['username']) {
                $this->zeigeMitFehler(
                    self::BEZEICHNUNG['username'] . ': maximal ' . self::MAX_LAENGE['username'] . ' Zeichen.',
                    $eingaben,
                    $variante
                );
                return;
            }
        }

        // --- Adress- und Passwortfelder -- nur wo eine Adresse entsteht -----
        // Bei typ != 'kunde' legt der Endpunkt keine Adresse an und ignoriert
        // anrede/name1/name2/kennziffer. Diese Felder werden dann gar nicht
        // erst abgefragt, also auch nicht geprueft.

        if ($konfig['adressdaten']) {
            if (!in_array($eingaben['anrede'], self::ANREDEN, true)) {
                $this->zeigeMitFehler('Bitte eine Anrede auswählen.', $eingaben, $variante);
                return;
            }

            // name1 und name2 sind bei typ=kunde Pflicht -- der Endpunkt
            // lehnt leere Werte ab.
            if ($eingaben['name1'] === '') {
                $this->zeigeMitFehler('Bitte einen Vornamen angeben.', $eingaben, $variante);
                return;
            }
            if ($eingaben['name2'] === '') {
                $this->zeigeMitFehler('Bitte einen Nachnamen bzw. Firmennamen angeben.', $eingaben, $variante);
                return;
            }

            // Kundennummer ist optional -- wenn angegeben, muss sie eine Zahl sein.
            if ($eingaben['kennziffer'] !== ''
                && (!ctype_digit($eingaben['kennziffer'])
                    || (int)$eingaben['kennziffer'] < 1
                    || (float)$eingaben['kennziffer'] > self::MAX_KENNZIFFER)) {
                $this->zeigeMitFehler('Bitte eine gültige Kundennummer angeben (nur Ziffern).', $eingaben, $variante);
                return;
            }

            foreach (self::MAX_LAENGE as $feld => $max) {
                if (mb_strlen($eingaben[$feld]) > $max) {
                    $this->zeigeMitFehler(
                        self::BEZEICHNUNG[$feld] . ': maximal ' . $max . ' Zeichen.',
                        $eingaben,
                        $variante
                    );
                    return;
                }
            }

            // Eigenes Portalpasswort -- nur hier, mit Wiederholung.
            if (strlen($passwort) < self::MIN_PASSWORT_LAENGE) {
                $this->zeigeMitFehler(
                    'Das Passwort muss mindestens ' . self::MIN_PASSWORT_LAENGE . ' Zeichen lang sein.',
                    $eingaben,
                    $variante
                );
                return;
            }
            if ($passwort !== $passwortWiederholt) {
                $this->zeigeMitFehler('Die Passwörter stimmen nicht überein.', $eingaben, $variante);
                return;
            }
        }

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
        $klartextPasswort = $konfig['adressdaten'] ? $passwort : $usersPasswort;

        $daten = [
            'username' => $benutzername,
            'pwd2'     => password_hash($klartextPasswort, PASSWORD_DEFAULT),
            'typ'      => $konfig['typ'],
        ];

        // Adressfelder nur bei typ=kunde -- bei jedem anderen typ legt der
        // Endpunkt keine Adresse an und ignoriert sie ohnehin.
        // email wird mit dem Benutzernamen befuellt -- der Benutzername IST
        // die E-Mail-Adresse, ein zweites Feld dafuer waere redundant.
        if ($konfig['adressdaten']) {
            $daten += [
                'anrede'   => $eingaben['anrede'],
                'name1'    => $eingaben['name1'],
                'name2'    => $eingaben['name2'],
                'strasse'  => $eingaben['strasse'],
                'plz'      => $eingaben['plz'],
                'ort'      => $eingaben['ort'],
                'telefon1' => $eingaben['telefon1'],
                'email'    => $benutzername,
            ];

            // Kundennummer nur mitsenden wenn angegeben -- 0 oder Leerstring
            // wuerde der Endpunkt ohnehin verwerfen.
            if ($eingaben['kennziffer'] !== '') {
                $daten['kennziffer'] = (int)$eingaben['kennziffer'];
            }
        }

        $insertResponse = \api_post('/registrierung/insertregistrierunglocal', $daten);

        if (($insertResponse['status'] ?? '') !== 'OK') {
            $this->zeigeMitFehler(
                $insertResponse['message'] ?? 'Registrierung fehlgeschlagen -- bitte später erneut versuchen.',
                $eingaben,
                $variante
            );
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
        $this->flashSuccess('Registrierung erfolgreich.');
        $this->redirect($konfig['nach_erfolg']);
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
        if ($loginname === '' || mb_strlen($loginname) > self::MAX_LOGINNAME) {
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
        if ($ip !== ''
            && $this->rateLimitUeberschritten($ip, 'mitarbeiterlogin_ip', self::RATE_LIMIT_MITARB_IP)) {
            return 'Zu viele Versuche von dieser IP-Adresse -- bitte später erneut versuchen.';
        }

        $loginSchluessel = hash('sha256', mb_strtoupper($loginname));
        if ($this->rateLimitUeberschritten($loginSchluessel, 'mitarbeiterlogin_user', self::RATE_LIMIT_MITARB_USER)) {
            return 'Zu viele Versuche für diesen Loginnamen -- bitte später erneut versuchen.';
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
     * POST /registrieren/username-pruefen -- Verfuegbarkeitspruefung fuer das
     * Formular, wird per fetch() aufgerufen sobald die E-Mail-Adresse
     * eingegeben ist. Dient beiden Portalen.
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

        $username = trim((string)($_POST['username'] ?? ''));

        if ($username === '' || !filter_var($username, FILTER_VALIDATE_EMAIL)) {
            $this->json(['frei' => null, 'message' => '']);
            return;
        }
        if (mb_strlen($username) > self::MAX_LAENGE['username']) {
            $this->json([
                'frei'    => null,
                'message' => 'E-Mail-Adresse: maximal ' . self::MAX_LAENGE['username'] . ' Zeichen.',
            ]);
            return;
        }

        // Die Pruefung verraet, ob eine Adresse registriert ist -- Limit
        // gegen das Durchprobieren fremder Adressen.
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        if ($ip !== '' && $this->rateLimitUeberschritten($ip, 'usernamecheck', self::RATE_LIMIT_CHECK_MAX)) {
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
            $this->json(['frei' => false, 'message' => 'Diese E-Mail-Adresse ist bereits registriert.']);
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
     * Zeigt das Formular erneut mit Fehlermeldung und den bisherigen Eingaben
     * (ausser Passwoertern -- die werden nie zurueckgegeben).
     */
    private function zeigeMitFehler(string $fehler, array $eingaben, string $variante): void
    {
        $this->flashError($fehler);
        $this->render('registrierung/index', $this->viewDaten($variante, $eingaben));
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
            'formular_action'      => $konfig['aktion'],
            'zurueck_link'         => $konfig['zurueck'],
            'zurueck_text'         => $konfig['zurueck_text'],
            'mitarbeiter_pruefung' => $konfig['userspruefung'],
            'adressdaten'          => $konfig['adressdaten'],
            'email_feld'           => $konfig['username_aus'] === 'email',
            'anreden'              => self::ANREDEN,
            'max_laenge'           => self::MAX_LAENGE,
            'max_loginname'        => self::MAX_LOGINNAME,
            'min_pwd'              => self::MIN_PASSWORT_LAENGE,
            'eingaben'             => $eingaben,
        ];
    }

    /**
     * Ist das Rate-Limiting eingeschaltet?
     *
     * Abschalten geht nur in der Entwicklung: RATE_LIMIT_AKTIV = false wirkt
     * ausschliesslich zusammen mit DEBUG = true. Bleibt das false versehentlich
     * im Deployment stehen, greift das Limit dort trotzdem -- vorausgesetzt
     * DEBUG ist wie vorgesehen false.
     *
     * Fehlt die Konstante ganz (aeltere index.php), ist das Limit aktiv.
     */
    private function rateLimitAktiv(): bool
    {
        $abgeschaltet = defined('RATE_LIMIT_AKTIV') && RATE_LIMIT_AKTIV === false;
        $entwicklung  = defined('DEBUG') && DEBUG === true;

        return !($abgeschaltet && $entwicklung);
    }

    /**
     * Prueft und zaehlt einen Versuch fuer den angegebenen Schluessel und die
     * angegebene Aktion. Atomar per Dateisperre (flock) -- Pruefung und
     * Zaehlung in einem Aufwasch, damit parallele Requests sich nicht
     * gegenseitig umgehen.
     *
     * @param string $schluessel Zaehler-Schluessel, z.B. IP oder Login-Hash
     * @param string $aktion     Zaehler-Gruppe, z.B. 'registrierung'
     * @param int    $max        Maximale Versuche im Zeitfenster
     * @return bool true, wenn das Limit bereits erreicht ist (Versuch NICHT
     *              gezaehlt) -- false, wenn der Versuch noch erlaubt war
     *              (wurde gezaehlt).
     */
    private function rateLimitUeberschritten(string $schluessel, string $aktion, int $max): bool
    {
        // Zum Testen abgeschaltet -- es wird nichts geprueft und nichts
        // gezaehlt, die Zaehlerdatei bleibt unberuehrt.
        if (!$this->rateLimitAktiv()) {
            return false;
        }

        $pfad = sys_get_temp_dir() . '/' . self::RATE_LIMIT_DATEI;

        $handle = fopen($pfad, 'c+');
        if ($handle === false) {
            // Datei nicht verfuegbar -- Limit kann nicht geprueft werden,
            // Registrierung deswegen nicht blockieren.
            return false;
        }

        flock($handle, LOCK_EX);

        $inhalt = stream_get_contents($handle);
        $daten  = json_decode((string)$inhalt, true);
        $daten  = is_array($daten) ? $daten : [];

        $jetzt      = time();
        $grenze     = $jetzt - self::RATE_LIMIT_FENSTER;
        $zaehler    = is_array($daten[$aktion] ?? null) ? $daten[$aktion] : [];
        $zeitpunkte = array_values(array_filter((array)($zaehler[$schluessel] ?? []), static fn($t) => $t > $grenze));

        $ueberschritten = count($zeitpunkte) >= $max;

        if (!$ueberschritten) {
            $zeitpunkte[]         = $jetzt;
            $zaehler[$schluessel] = $zeitpunkte;

            // Alle Schluessel ohne Versuche im aktuellen Zeitfenster
            // entfernen -- sonst waechst die Datei unbegrenzt.
            foreach ($zaehler as $einSchluessel => $ts) {
                $ts = array_values(array_filter((array)$ts, static fn($t) => $t > $grenze));
                if (empty($ts)) {
                    unset($zaehler[$einSchluessel]);
                } else {
                    $zaehler[$einSchluessel] = $ts;
                }
            }

            $daten[$aktion] = $zaehler;

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($daten));
            fflush($handle);
        }

        flock($handle, LOCK_UN);
        fclose($handle);

        return $ueberschritten;
    }
}
