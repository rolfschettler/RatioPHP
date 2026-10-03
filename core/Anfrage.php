<?php
/**
 * core/Anfrage.php -- Pfad des aktuellen Requests und Umleitung
 *
 * Einzige Stelle, die REQUEST_URI auswertet und Location-Header setzt.
 * Router, BaseController und Fehlerseite verwenden diese Methoden -- keine
 * eigene Normalisierung, kein eigenes header('Location: ...').
 */

namespace Core;

class Anfrage
{
    /**
     * Bringt einen Pfad in die Form der Routen: fuehrender Slash, kein
     * abschliessender Slash (ausser bei '/').
     */
    public static function normalisiere(string $pfad): string
    {
        $pfad = '/' . ltrim($pfad, '/');
        return $pfad !== '/' ? rtrim($pfad, '/') : '/';
    }

    /**
     * Routenpfad des aktuellen Requests -- ohne Query-String und ohne
     * APP_BASE (Installationsverzeichnis).
     */
    public static function pfad(): string
    {
        $uri = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        if (APP_BASE !== '' && str_starts_with($uri, APP_BASE)) {
            $uri = substr($uri, strlen(APP_BASE));
        }

        return self::normalisiere($uri);
    }

    /**
     * IP-Adresse des Clients -- '' wenn unbekannt.
     *
     * Bewusst REMOTE_ADDR und nicht X-Forwarded-For: der Header ist frei vom
     * Client waehlbar, ein Angreifer koennte damit jedes Rate-Limit umgehen.
     * Hinter einem Reverse-Proxy steht hier dessen Adresse -- dann muss der
     * Header ausgewertet werden, aber NUR fuer diesen vertrauenswuerdigen Proxy.
     */
    public static function ip(): string
    {
        return (string)($_SERVER['REMOTE_ADDR'] ?? '');
    }

    /**
     * Leitet auf einen Pfad innerhalb der App um (APP_BASE wird vorangestellt)
     * und beendet den Request.
     */
    public static function umleiten(string $pfad): never
    {
        header('Location: ' . APP_BASE . $pfad);
        exit;
    }
}
