<?php
// standard/Views/tour/index.php
// Meine Tour -- Fahrerportal, fuer das Handy gebaut: KW-Zeile mit Schalter
// "7 Tage", Tagesleiste (heute + 6 Tage), darunter je Einsatz eine Karte.
// Reiner Content-HTML -- kein DOCTYPE, kein Layout-Include.
//
// Variablen aus TourController::zeige():
//   $woche      true = alle 7 Tage, false = nur der gewaehlte Tag
//   $kw         "KW 44" bzw. "KW 43 – 44"
//   $kw_zusatz  Zeitraum bzw. gewaehlter Tag als Text
//   $leiste     Tage der Leiste (kurz, nummer, anzahl, url, aktiv)
//   $woche_url  Pfad der 7-Tage-Ansicht ohne APP_BASE
//   $gruppen    Liste [titel, uebersicht, karten] -- uebersicht: erster,
//               letzter, letzter_bis; Karten: zeit, bezeichnung, typ,
//               fahrzeug, abfahrtsort, zielort, hinweis, markierung
//   $hinweis    Text statt der Karten (leer, kein Fahrer, Fehler) oder null
//   $neu_laden  Sekunden bis zum automatischen Neuladen
//   $stand      Uhrzeit, zu der die Daten geladen wurden ("15:52")
?>
<div class="px-3 py-3 mx-auto" style="max-width:640px;">

    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 fw-bold mb-0" style="color:var(--text-color);">
                <i class="bi bi-signpost-split me-1" style="color:var(--primary-color-dark);"></i>Meine Tour
            </h1>
            <span class="small text-muted">Stand <?= htmlspecialchars($stand) ?> Uhr</span>
        </div>
        <button type="button" class="btn btn-outline-secondary" id="tourAktualisieren">
            <i class="bi bi-arrow-clockwise me-1"></i>Aktualisieren
        </button>
    </div>

    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
        <div>
            <span class="fw-bold fs-5" style="color:var(--text-color);"><?= htmlspecialchars($kw) ?></span>
            <span class="text-muted ms-1"><?= htmlspecialchars($kw_zusatz) ?></span>
        </div>
        <a href="<?= APP_BASE . htmlspecialchars($woche_url) ?>"
           class="btn fw-semibold <?= $woche ? 'btn-app-primary' : 'btn-outline-secondary' ?>"
           <?= $woche ? 'aria-current="page"' : '' ?>>
            <i class="bi bi-calendar-week me-1"></i>7 Tage
        </a>
    </div>

    <nav class="d-flex gap-1 mb-4" aria-label="Tag wählen">
        <?php foreach ($leiste as $t): ?>
        <a href="<?= APP_BASE . htmlspecialchars($t['url']) ?>"
           class="btn flex-fill px-0 py-2 lh-sm <?= $t['aktiv'] ? 'btn-app-primary' : 'btn-outline-secondary' ?>"
           style="min-width:0;" <?= $t['aktiv'] ? 'aria-current="page"' : '' ?>
           aria-label="<?= htmlspecialchars($t['kurz'] . ' ' . $t['nummer'] . '., ' . $t['anzahl'] . ' Einsätze') ?>">
            <span class="d-block small"><?= htmlspecialchars($t['kurz']) ?></span>
            <span class="d-block fs-5 fw-bold"><?= htmlspecialchars($t['nummer']) ?></span>
            <?php // Punkt = an diesem Tag stehen Einsaetze an ?>
            <span class="d-block" style="height:.5rem;">
                <?php if ($t['anzahl'] > 0): ?>
                <i class="bi bi-circle-fill" style="font-size:.4rem;vertical-align:top;"></i>
                <?php endif; ?>
            </span>
        </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($hinweis !== null): ?>

        <div class="text-center text-muted py-5">
            <i class="bi bi-calendar-check d-block mb-3" style="font-size:3rem;color:var(--primary-color-light);"></i>
            <p class="fs-5 mb-0"><?= htmlspecialchars($hinweis) ?></p>
        </div>

    <?php else: ?>

        <?php foreach ($gruppen as $g): ?>
            <?php if ($woche): ?>
            <h2 class="h6 fw-bold text-uppercase text-muted mt-4 mb-2" style="letter-spacing:.06em;">
                <?= htmlspecialchars($g['titel']) ?>
            </h2>
            <?php endif; ?>

            <?php // Tagesuebersicht: erster und letzter Termin des Tages ?>
            <div class="d-flex gap-2 mb-3">
                <div class="flex-fill rounded p-2 text-center" style="background:var(--primary-color-light);color:var(--text-color);">
                    <div class="small"><i class="bi bi-sunrise me-1"></i>Erster Termin</div>
                    <div class="fs-5 fw-bold"><?= htmlspecialchars($g['uebersicht']['erster']) ?> Uhr</div>
                </div>
                <div class="flex-fill rounded p-2 text-center" style="background:var(--primary-color-light);color:var(--text-color);">
                    <div class="small"><i class="bi bi-sunset me-1"></i>Letzter Termin</div>
                    <div class="fs-5 fw-bold"><?= htmlspecialchars($g['uebersicht']['letzter']) ?> Uhr</div>
                    <div class="small">Ende <?= htmlspecialchars($g['uebersicht']['letzter_bis']) ?> Uhr</div>
                </div>
            </div>

            <?php foreach ($g['karten'] as $k): ?>
            <div class="rounded p-3 mb-3"
                 style="background:var(--surface-muted);border:1px solid <?= $k['markierung'] !== null ? 'var(--primary-color)' : 'var(--border-color)' ?>;">

                <div class="d-flex justify-content-between align-items-start gap-2">
                    <span class="fs-4 fw-bold" style="color:var(--text-color);"><?= htmlspecialchars($k['zeit']) ?></span>
                    <?php if ($k['markierung'] !== null): ?>
                    <span class="badge rounded-pill btn-app-primary mt-2"><?= htmlspecialchars($k['markierung']) ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($k['bezeichnung'] !== ''): ?>
                <div class="fw-bold fs-5 mt-1" style="color:var(--text-color);"><?= htmlspecialchars($k['bezeichnung']) ?></div>
                <?php endif; ?>

                <?php if ($k['typ'] !== ''): ?>
                <span class="badge rounded-pill fw-normal mt-1"
                      style="background:var(--primary-color-light);color:var(--text-color);"><?= htmlspecialchars($k['typ']) ?></span>
                <?php endif; ?>

                <ul class="list-unstyled mb-0 mt-3">
                    <?php if ($k['fahrzeug'] !== ''): ?>
                    <li class="d-flex gap-2 mb-2">
                        <i class="bi bi-truck" style="color:var(--primary-color-dark);"></i>
                        <span><?= htmlspecialchars($k['fahrzeug']) ?></span>
                    </li>
                    <?php endif; ?>
                    <?php if ($k['abfahrtsort'] !== ''): ?>
                    <li class="d-flex gap-2 mb-2">
                        <i class="bi bi-geo-alt" style="color:var(--primary-color-dark);"></i>
                        <span><?= htmlspecialchars($k['abfahrtsort']) ?></span>
                    </li>
                    <?php endif; ?>
                    <?php if ($k['zielort'] !== ''): ?>
                    <li class="d-flex gap-2 mb-2">
                        <i class="bi bi-flag" style="color:var(--primary-color-dark);"></i>
                        <span><?= htmlspecialchars($k['zielort']) ?></span>
                    </li>
                    <?php endif; ?>
                    <?php if ($k['hinweis'] !== ''): ?>
                    <li class="d-flex gap-2 mb-2">
                        <i class="bi bi-info-circle" style="color:var(--primary-color-dark);"></i>
                        <span><?= htmlspecialchars($k['hinweis']) ?></span>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endforeach; ?>

        <?php endforeach; ?>

    <?php endif; ?>
</div>
<script>
// Neu laden nach $neu_laden Sekunden -- Aenderungen der Dispo erscheinen ohne
// Zutun. Im Hintergrund (Handy gesperrt, andere App) wird nicht geladen; wird
// die Seite wieder sichtbar und die Zeit ist um, laedt sie sofort neu.
(function () {
    var ms = <?= (int)$neu_laden ?> * 1000;
    var geladen = Date.now();
    function neuLaden() {
        if (document.visibilityState === 'visible') {
            location.reload();
        }
    }
    setInterval(neuLaden, ms);
    document.getElementById('tourAktualisieren').addEventListener('click', function () {
        location.reload();
    });
    document.addEventListener('visibilitychange', function () {
        if (Date.now() - geladen >= ms) {
            neuLaden();
        }
    });
})();
</script>
