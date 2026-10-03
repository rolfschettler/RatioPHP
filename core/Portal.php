<?php
/**
 * core/Portal.php -- zentrale Definition der Portale
 *
 * Ein Portal ist ein eigener Einstieg mit eigener Startseite, eigenem
 * Portal-Label im Header, eigenem Routen-Praefix und eigener Registrierung.
 * Alles, was ein Portal ausmacht, steht HIER -- nicht verstreut in
 * Ternaeroperatoren in Header, Login-Modal, Router und AuthController.
 *
 * Ein viertes Portal ist damit ein Eintrag in PORTALE plus:
 *   - Action in HomeController + View standard/Views/home/<portal>.php
 *   - Routen in config/routes.php (unter dem Praefix des Portals)
 *   - Variante in RegistrierungController::PORTALE (nur falls es eine
 *     Registrierung geben soll)
 *
 * WICHTIG -- zwei Abbildungen, die nicht verwechselt werden duerfen:
 *
 *   name()   bildet einen Wert aus einem REQUEST auf ein Portal ab und faellt
 *            bei Unbekanntem auf DEFAULT (Kundenportal) zurueck. Fail-safe
 *            nach aussen: ein Fehlgriff schickt niemanden in einen internen
 *            Bereich. Niemals einen Pfad aus dem Request uebernehmen -- das
 *            waere eine offene Weiterleitung.
 *
 *   ausTyp() bildet REGISTRIERUNG.typ aus dem Token auf ein Portal ab und
 *            gibt bei Unbekanntem NULL. Hier waere ein Rueckfall auf 'kunde'
 *            das Gegenteil von sicher: Legacy-Saetze mit typ='Standard' oder
 *            typ=NULL bekaemen Kundenrechte. Fuer Zugriffsentscheidungen
 *            deshalb IMMER ausTyp(), nie name().
 */

namespace Core;

class Portal
{
    /** Portal fuer unbekannte oder fehlende Angaben aus einem Request. */
    public const DEFAULT = 'kunde';

    /**
     * start         -- Startseite des Portals (Brand-Link, Ziel nach Login/Logout)
     * praefix       -- Routen-Praefix aller Seiten des Portals. Faellt nur beim
     *                  Kundenportal von start ab: '/' ist der oeffentliche
     *                  Einstieg, die uebrigen Kundenseiten liegen unter /kunde.
     * label         -- Anzeige neben dem Brand im Header
     * registrierung -- Pfad des Registrierungsformulars, null = keine Registrierung
     * darf_alles    -- true = dieses Portal darf auch die Routen aller anderen
     *                  Portale besuchen (Mitarbeiter sehen alles)
     */
    private const PORTALE = [
        'kunde' => [
            'start'         => '/',
            'praefix'       => '/kunde',
            'label'         => 'Kundenportal',
            'registrierung' => '/kunde/registrieren',
            'darf_alles'    => false,
        ],
        'mitarbeiter' => [
            'start'         => '/mitarbeiter',
            'praefix'       => '/mitarbeiter',
            'label'         => 'Mitarbeiterportal',
            'registrierung' => '/mitarbeiter/registrieren',
            'darf_alles'    => true,
        ],
        'fahrer' => [
            'start'         => '/fahrer',
            'praefix'       => '/fahrer',
            'label'         => 'Fahrerportal',
            'registrierung' => '/fahrer/registrieren',
            'darf_alles'    => false,
        ],
    ];

    /**
     * Bildet einen beliebigen Wert AUS EINEM REQUEST auf ein bekanntes Portal
     * ab. Alles Unbekannte wird zu DEFAULT -- fail-safe nach aussen.
     * Fuer REGISTRIERUNG.typ aus dem Token stattdessen ausTyp() verwenden.
     */
    public static function name(string $wert): string
    {
        return isset(self::PORTALE[$wert]) ? $wert : self::DEFAULT;
    }

    /** Startseite des Portals, z.B. '/mitarbeiter'. */
    public static function start(string $portal): string
    {
        return self::PORTALE[self::name($portal)]['start'];
    }

    /**
     * Startseite mit geoeffnetem Login-Modal, z.B. '/mitarbeiter?login=1'.
     * Ziel fuer "bitte anmelden" -- Router-Auth-Redirect und fehlgeschlagener
     * Login. Der Parameter wird nirgends sonst zusammengesetzt.
     */
    public static function login(string $portal): string
    {
        return self::start($portal) . '?login=1';
    }

    /**
     * Routen-Praefix des Portals, z.B. '/mitarbeiter'.
     * Basis fuer jede Modul-URL in Views und Controllern:
     *   Portal::praefix('mitarbeiter') . '/einsatz'
     */
    public static function praefix(string $portal): string
    {
        return self::PORTALE[self::name($portal)]['praefix'];
    }

    /** Portal-Label fuer den Header, z.B. 'Fahrerportal'. */
    public static function label(string $portal): string
    {
        return self::PORTALE[self::name($portal)]['label'];
    }

    /** Pfad der Registrierung, oder null wenn das Portal keine hat. */
    public static function registrierung(string $portal): ?string
    {
        return self::PORTALE[self::name($portal)]['registrierung'];
    }

    /**
     * Darf dieses Portal die Routen aller anderen Portale besuchen?
     * Trifft heute nur auf das Mitarbeiterportal zu.
     */
    public static function darfAlles(string $portal): bool
    {
        return self::PORTALE[self::name($portal)]['darf_alles'];
    }

    /**
     * Leitet das Portal aus einem ROUTENPFAD ab -- die Grundlage der
     * Praefix-Konvention. Zuerst die Startseiten (deckt '/' ab, das kein
     * Praefix traegt), danach das laengste passende Praefix.
     *
     * Rueckgabe null bedeutet: der Pfad gehoert zu keinem Portal. Das ist ein
     * Konventionsverstoss -- der Router weist solche Routen ab statt zu raten.
     */
    public static function ausPfad(string $pfad): ?string
    {
        $pfad = Anfrage::normalisiere($pfad);

        foreach (self::PORTALE as $portalName => $konfig) {
            if ($pfad === $konfig['start']) {
                return $portalName;
            }
        }

        $treffer = null;
        foreach (self::PORTALE as $portalName => $konfig) {
            $praefix = $konfig['praefix'];
            if ($pfad !== $praefix && !str_starts_with($pfad, $praefix . '/')) {
                continue;
            }
            if ($treffer === null
                || strlen($praefix) > strlen(self::PORTALE[$treffer]['praefix'])) {
                $treffer = $portalName;
            }
        }

        return $treffer;
    }

    /**
     * Bildet REGISTRIERUNG.typ (aus dem role-Claim des Tokens) auf ein Portal
     * ab. Strikt: nur die exakten Portalnamen zaehlen, alles andere ergibt
     * null und damit keinen Zugriff.
     *
     * In REGISTRIERUNG stehen Legacy-Saetze mit typ='Standard' und typ=NULL --
     * die bekommen so kein Portal, statt stillschweigend als Kunde zu gelten.
     */
    public static function ausTyp(string $typ): ?string
    {
        $typ = trim($typ);

        return isset(self::PORTALE[$typ]) ? $typ : null;
    }

    /** Alle Portalnamen -- fuer Whitelists und Schleifen. */
    public static function alle(): array
    {
        return array_keys(self::PORTALE);
    }
}
