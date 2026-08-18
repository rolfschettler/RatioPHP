<?php
// standard/Views/anmietimport/index.php
// Anmietimport -- Ergebnis des letzten Uploads (kein Layout-Include).
// .app-main scrollt bereits horizontal -- kein table-responsive Wrapper.

/** @var array $rows        Ergebnis je Vorgang aus dem letzten Import -- bezeichnung, status, meldung */
/** @var array $anmietDaten Importierte ANMIET-Datensaetze inkl. 'positionen' (1:N ANMIETPOS) */

// Datum ("Y-m-d") und Uhrzeit ("H:i") getrennt formatieren und zusammenfuehren.
$fmtDatumZeit = static function (?string $datum, ?string $zeit): string {
    if (empty($datum)) {
        return '&ndash;';
    }
    $ts = strtotime($datum);
    $d  = $ts ? date('d.m.Y', $ts) : htmlspecialchars($datum, ENT_QUOTES);
    return $zeit ? $d . ', ' . htmlspecialchars($zeit, ENT_QUOTES) . ' Uhr' : $d;
};

// Preis mit genau 2 Nachkommastellen (deutsches Format: Komma, Tausenderpunkt).
$fmtPreis = static function ($wert): string {
    if ($wert === null || $wert === '') {
        return '&ndash;';
    }
    return number_format((float)$wert, 2, ',', '.');
};

$statusBadge = static function (string $status): string {
    $map = [
        'erfolgreich'   => ['bg-success',   'Erfolgreich'],
        'teilweise'     => ['bg-warning',   'Teilweise'],
        'fehler'        => ['bg-danger',    'Fehler'],
        'uebersprungen' => ['bg-secondary', 'Übersprungen'],
    ];
    [$class, $label] = $map[$status] ?? ['bg-secondary', ucfirst($status)];
    return '<span class="badge ' . $class . '">' . htmlspecialchars($label) . '</span>';
};
?>
<div style="max-width:100vw;margin:0px 20px 0px 20px;padding:12px" class="shadow">

<button class="btn btn-sm btn-outline-secondary mb-2" type="button"
        data-bs-toggle="collapse" data-bs-target="#eingeleseneDatensaetze"
        aria-expanded="false" aria-controls="eingeleseneDatensaetze" id="eingeleseneDatensaetzeToggle">
    <i class="bi bi-chevron-right me-1"></i>Eingelesene Datens&auml;tze (<?= count($rows) ?>)
</button>

<div class="collapse" id="eingeleseneDatensaetze">
<table class="app-table" style="min-width:700px;">
    <thead>
        <tr>
            <th>Bezeichnung</th>
            <th>Status</th>
            <th>Meldung</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
        <tr>
            <td colspan="3" class="text-center text-muted py-4">Noch kein Import durchgef&uuml;hrt.</td>
        </tr>
        <?php else: ?>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= htmlspecialchars((string)($r['bezeichnung'] ?? '')) ?></td>
                <td><?= $statusBadge((string)($r['status'] ?? '')) ?></td>
                <td class="dim"><?= htmlspecialchars((string)($r['meldung'] ?? '')) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
</div>
<script>
// Chevron-Icon passend zum Auf-/Zuklappen drehen
(function () {
    var toggle = document.getElementById('eingeleseneDatensaetzeToggle');
    var panel  = document.getElementById('eingeleseneDatensaetze');
    if (!toggle || !panel) { return; }
    panel.addEventListener('shown.bs.collapse', function () {
        toggle.querySelector('i').className = 'bi bi-chevron-down me-1';
    });
    panel.addEventListener('hidden.bs.collapse', function () {
        toggle.querySelector('i').className = 'bi bi-chevron-right me-1';
    });
})();
</script>

<?php if (!empty($anmietDaten)): ?>
<h2 class="h6 fw-bold mt-4 mb-3" style="color:var(--text-color);">Importierte Datens&auml;tze</h2>

<table class="app-table" style="min-width:900px;">
    <thead>
        <tr>
            <th>Vorgang</th>
            <th>Von</th>
            <th>Bis</th>
            <th>Ziel</th>
            <th>Personen</th>
            <th>Reiseart</th>
            <th>Einsatzart</th>
            <th>Start</th>
            <th>Ende</th>
            <th>Fahrzeug</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($anmietDaten as $a): ?>
        <tr style="background:var(--primary-color-light);font-weight:bold;">
            <td><?= htmlspecialchars((string)($a['vorgang'] ?? '')) ?: '&ndash;' ?></td>
            <td class="lightblue"><?= $fmtDatumZeit($a['von'] ?? null, $a['vonzeit'] ?? null) ?></td>
            <td class="lightblue"><?= $fmtDatumZeit($a['bis'] ?? null, $a['biszeit'] ?? null) ?></td>
            <td><?= htmlspecialchars((string)($a['ziel'] ?? '')) ?: '&ndash;' ?></td>
            <td class="lightblue"><?= htmlspecialchars((string)($a['perszahl'] ?? '')) ?: '&ndash;' ?></td>
            <td class="lightblue"><?= htmlspecialchars((string)($a['reiseart'] ?? '')) ?: '&ndash;' ?></td>
            <td class="lightblue"><?= htmlspecialchars((string)($a['eart'] ?? '')) ?: '&ndash;' ?></td>
            <td class="lightblue"><?= htmlspecialchars((string)($a['vonzeit'] ?? '')) ?: '&ndash;' ?></td>
            <td class="lightblue"><?= htmlspecialchars((string)($a['biszeit'] ?? '')) ?: '&ndash;' ?></td>
            <td class="lightblue"><?= htmlspecialchars((string)($a['kennzeichen'] ?? '')) ?: '&ndash;' ?></td>
        </tr>
        <?php foreach (($a['positionen'] ?? []) as $p): ?>
        <tr style="background:#fff;">
            <td colspan="2" style="padding-left:2.5rem;" class="dim">
                Pos. <?= (int)($p['positionsnr'] ?? 0) ?> &ndash;
                <?= htmlspecialchars((string)($p['bezeichnung'] ?? '')) ?: '&ndash;' ?>
            </td>
            <td class="dim">Menge: <?= htmlspecialchars((string)($p['menge'] ?? '')) ?: '&ndash;' ?></td>
            <td colspan="7" class="dim">Einzelpreis: <?= $fmtPreis($p['epreis'] ?? null) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

</div>
