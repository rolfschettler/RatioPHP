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
    /** Ergebnis wird pro Request nur einmal ermittelt. */
    private static bool $gelesen = false;
    private static ?string $portal = null;

    /**
     * Portal des angemeldeten Benutzers.
     *
     * @return string|null Portalname, oder null wenn kein Token vorliegt, der
     *                     Payload nicht lesbar ist oder typ zu keinem Portal
     *                     gehoert. Jeder Zweifelsfall ergibt null.
     */
    public static function portal(): ?string
    {
        if (!self::$gelesen) {
            self::$gelesen = true;
            self::$portal  = self::portalAusToken((string)($_COOKIE['jwt_token'] ?? ''));
        }

        return self::$portal;
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
        if (!is_array($role)) {
            return null;
        }

        return Portal::ausTyp((string)($role['typ'] ?? ''));
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
