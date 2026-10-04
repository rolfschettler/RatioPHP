<?php
/**
 * core/Auth.php -- Portal des angemeldeten Benutzers aus dem Token lesen
 *
 * Der RATIOserver legt beim Login die komplette REGISTRIERUNG-Zeile als
 * role-Claim in den JWT -- inklusive typ ('kunde', 'mitarbeiter', 'fahrer');
 * nur pwd2 wird ausgefiltert (DataModulLoginClass.login). Dieses typ ist das
 * Portal des Benutzers.
 *
 * Gelesen wird LOKAL aus dem JWT-Payload -- KEIN Aufruf von /verifytoken.
 * Bewusste Entscheidung: die Portalpruefung soll ohne Roundtrip und ohne
 * Abhaengigkeit von der Erreichbarkeit des Backends laufen.
 *
 * Was das bedeutet: die Signatur wird NICHT geprueft, das Secret liegt nur im
 * RATIOserver. Ein selbst gebautes Cookie kommt also durch diese Pruefung und
 * die Portalseite rendert -- jeder Datenzugriff bleibt aber leer, weil
 * RATIOserver den Token bei jedem api_post() ablehnt. Dasselbe gilt fuer einen
 * abgelaufenen Token. Diese Klasse weist Portale zu; die Zugriffskontrolle auf
 * Daten sitzt im Backend.
 *
 * exp wird bewusst nicht ausgewertet. Merkposten, falls das jemand nachruestet:
 * exp und iat sind KEINE UTC-Zeitstempel. Delphi setzt
 * Expiration := IncMinute(Now, ..) mit lokaler Zeit, die JOSE-Bibliothek
 * schreibt sie als UTC -- der Wert ist um den Zeitzonen-Offset verschoben. Ein
 * Vergleich muesste gegen lokale Zeit laufen, nicht gegen time()/gmdate().
 */

namespace Core;

class Auth
{
    /** role-Claim des Cookie-Tokens -- pro Request nur einmal dekodiert. */
    private static bool $gelesen = false;
    private static ?array $rolle = null;

    /**
     * Portal des angemeldeten Benutzers.
     *
     * @return string|null Portalname, oder null wenn kein Token vorliegt, der
     *                     Payload nicht lesbar ist oder typ zu keinem Portal
     *                     gehoert. Jeder Zweifelsfall ergibt null.
     */
    public static function portal(): ?string
    {
        $rolle = self::rolleAusCookie();

        return $rolle === null ? null : Portal::ausTyp((string)($rolle['typ'] ?? ''));
    }

    /**
     * Kennziffer (ADRESSEN.kennziffer) des angemeldeten Benutzers -- der Wert
     * aus REGISTRIERUNG.kennziffer, wie er beim Login im role-Claim stand.
     *
     * Einzige zulaessige Quelle, wenn ein Controller die EIGENE Adresse
     * liest oder schreibt: eine Kennziffer aus dem Request waere frei
     * waehlbar. Die Signatur prueft RATIOserver beim naechsten api_post() --
     * ein selbst gebautes Cookie bekommt dort keine Daten.
     *
     * @return int|null null ohne Token, ohne Kennziffer (typ mitarbeiter und
     *                  fahrer) oder bei einem unbrauchbaren Wert
     */
    public static function kennziffer(): ?int
    {
        $wert = (string)(self::rolleAusCookie()['kennziffer'] ?? '');

        return ctype_digit($wert) && (int)$wert > 0 ? (int)$wert : null;
    }

    /**
     * Portal eines beliebigen Tokens -- fuer den Moment direkt nach dem Login,
     * in dem das Cookie noch nicht gelesen werden kann (setcookie() wirkt erst
     * beim naechsten Request). Der AuthController leitet damit in das Portal
     * weiter, zu dem der Anmelder tatsaechlich gehoert, und nicht in das
     * Portal, dessen Login-Modal er benutzt hat.
     *
     * Jeder Schritt bricht bei Unstimmigkeit mit null ab -- fail-closed.
     */
    public static function portalAusToken(string $token): ?string
    {
        $rolle = self::rolleAusToken($token);

        return $rolle === null ? null : Portal::ausTyp((string)($rolle['typ'] ?? ''));
    }

    /** role-Claim des Cookie-Tokens, pro Request nur einmal dekodiert. */
    private static function rolleAusCookie(): ?array
    {
        if (!self::$gelesen) {
            self::$gelesen = true;
            self::$rolle   = self::rolleAusToken((string)($_COOKIE['jwt_token'] ?? ''));
        }

        return self::$rolle;
    }

    /**
     * role-Claim eines Tokens -- die REGISTRIERUNG-Zeile des Anmelders
     * (ohne pwd2).
     *
     * Jeder Schritt bricht bei Unstimmigkeit mit null ab -- fail-closed.
     */
    private static function rolleAusToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        // JWT besteht aus header.payload.signature -- alles andere ist kaputt
        $teile = explode('.', $token);
        if (count($teile) !== 3) {
            return null;
        }

        $payload = self::base64UrlDecode($teile[1]);
        if ($payload === null) {
            return null;
        }

        $claims = json_decode($payload, true);
        if (!is_array($claims)) {
            return null;
        }

        // Der role-Claim ist ein JSON-STRING, kein Objekt: uJWTUtils haengt ihn
        // per Token.Claims.JSON.AddPair('role', ARole) als Zeichenkette an.
        // Deshalb ein zweites json_decode.
        $role = $claims['role'] ?? null;
        if (is_string($role)) {
            $role = json_decode($role, true);
        }

        return is_array($role) ? $role : null;
    }

    /**
     * Dekodiert einen base64url-kodierten Wert (JWT-Alphabet: - und _ statt
     * + und /, Padding weggelassen).
     *
     * @return string|null null wenn der Wert kein gueltiges Base64 ist
     */
    private static function base64UrlDecode(string $wert): ?string
    {
        $wert = strtr($wert, '-_', '+/');
        $wert .= str_repeat('=', (4 - strlen($wert) % 4) % 4);

        $roh = base64_decode($wert, true);

        return is_string($roh) ? $roh : null;
    }
}
