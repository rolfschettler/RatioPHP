<?php
// standard/Views/einsatz/index.php
// Einsatz-Uebersicht -- reine Tabelle (nur Content-HTML, kein Layout).
// .app-main scrollt bereits horizontal -- kein table-responsive Wrapper.

/** @var array  $rows      Gefilterte und sortierte Einsaetze */
/** @var string $warnung   Hinweis bei abgeschnittenem Ergebnis (HTML, leer = keiner) */
/** @var string $fFahrer   Suchbegriff Fahrer  (lowercase, fuers Highlight) */
/** @var string $fFahrzeug Suchbegriff Fahrzeug */
/** @var string $fBegriff  Suchbegriff Bezeichnung/Dienst-Nr. */
/** @var string $sortCol   Aktuelle Sortierspalte */
/** @var int    $sortDir   1 = aufsteigend, -1 = absteigend */

// Datetime-Wert ("Y-m-d H:i:s") fuer die Anzeige formatieren.
$fmt = static function (?string $val): string {
    if (empty($val)) {
        return '&ndash;';
    }
    $ts = strtotime(str_replace('T', ' ', $val));
    return $ts ? date('d.m.y, H:i', $ts) : htmlspecialchars($val, ENT_QUOTES);
};

// Treffer im Text mit <mark> umhuellen. Gibt fertiges, escaptes HTML zurueck.
// Originalschreibweise bleibt erhalten, Suche ist case-insensitive.
$highlight = static function (?string $text, string $term): string {
    $t = (string)($text ?? '');
    if ($t === '') {
        return '&ndash;';
    }
    $safe = htmlspecialchars($t, ENT_QUOTES);
    if ($term === '') {
        return $safe;
    }
    $escaped = preg_quote($term, '/');
    return preg_replace("/({$escaped})/i", '<mark>$1</mark>', $safe);
};

// Sortier-Link fuer den Tabellenkopf -- behaelt alle aktiven Filter (GET) bei.
$sortLink = static function (string $col) use ($sortCol, $sortDir): string {
    $newDir = ($col === $sortCol && $sortDir === 1) ? 'desc' : 'asc';
    $params = array_merge($_GET, ['sortcol' => $col, 'sortdir' => $newDir]);
    return APP_BASE . '/einsatz?' . http_build_query($params);
};

// Sortier-Pfeil fuer die aktive Spalte.
$sortIcon = static function (string $col) use ($sortCol, $sortDir): string {
    if ($col !== $sortCol) {
        return '<i class="bi bi-arrow-down-up ms-1 dim"></i>';
    }
    return $sortDir === 1
        ? '<i class="bi bi-arrow-up ms-1"></i>'
        : '<i class="bi bi-arrow-down ms-1"></i>';
};
?>
<?php if (!empty($warnung)): ?>
<div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <div><?= $warnung ?></div>
</div>
<?php endif; ?>
<table class="app-table" style="min-width:1000px;">
    <thead>
        <tr>
            <th><a href="<?= $sortLink('von') ?>" class="text-decoration-none text-reset">Von<?= $sortIcon('von') ?></a></th>
            <th><a href="<?= $sortLink('bis') ?>" class="text-decoration-none text-reset">Bis<?= $sortIcon('bis') ?></a></th>
            <th><a href="<?= $sortLink('bezeichnung') ?>" class="text-decoration-none text-reset">Bezeichnung<?= $sortIcon('bezeichnung') ?></a></th>
            <th>Fahrer 1</th>
            <th>Fahrer 2</th>
            <th>Fahrzeug</th>
            <th><a href="<?= $sortLink('dienstnr') ?>" class="text-decoration-none text-reset">Dienst-Nr.<?= $sortIcon('dienstnr') ?></a></th>
            <th><a href="<?= $sortLink('typ') ?>" class="text-decoration-none text-reset">Typ<?= $sortIcon('typ') ?></a></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
        <tr>
            <td colspan="8" class="text-center text-muted py-4">Keine Eins&auml;tze gefunden.</td>
        </tr>
        <?php else: ?>
            <?php foreach ($rows as $e): ?>
            <tr>
                <td class="dim"><?= $fmt($e['von'] ?? null) ?></td>
                <td class="dim"><?= $fmt($e['bis'] ?? null) ?></td>
                <td><?= $highlight($e['bezeichnung'] ?? '', $fBegriff) ?></td>
                <td><?= $highlight($e['fahrer1Name'] ?? '', $fFahrer) ?></td>
                <td><?= $highlight($e['fahrer2Name'] ?? '', $fFahrer) ?></td>
                <td><?= $highlight($e['fahrzeug'] ?? '', $fFahrzeug) ?></td>
                <td><?= $highlight($e['dienstnr'] ?? '', $fBegriff) ?></td>
                <td><?= htmlspecialchars((string)($e['typ'] ?? '')) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
