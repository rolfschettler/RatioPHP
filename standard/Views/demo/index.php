<?php
// standard/Views/demo/index.php
// Testseite des Kundenportals -- "Hallo Welt".
// Reiner Content-HTML -- kein DOCTYPE, kein Layout-Include.
//
// Zeigt den Portal-Kontext, damit beim Durchklicken sichtbar ist, ob Route und
// Token zusammenpassen. Portalpfade kommen aus core/Portal.php.

use Core\Portal;

$routePortal = $route_portal ?? 'kunde';
$tokenPortal = $token_portal ?? null;
$benutzer    = $benutzer     ?? '';
$passt       = ($tokenPortal === $routePortal);

// Testlinks auf fremde Portale -- muessen mit einem Kundenkonto abgewiesen
// werden. Pfade aus dem zentralen Praefix, nicht ausgeschrieben.
$fremdeRouten = [
    Portal::praefix('mitarbeiter') . '/einsatz' => 'Mitarbeiterportal (Eins&auml;tze)',
    Portal::start('fahrer')                     => 'Fahrerportal (Startseite)',
];
?>
<div class="px-3 py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-10 col-lg-7">

                <div class="text-center mb-4">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
                          style="width:72px;height:72px;background:var(--primary-color-light);">
                        <i class="bi bi-emoji-smile" style="font-size:2rem;color:var(--text-color);"></i>
                    </span>
                    <h1 class="h3 fw-bold mb-1" style="color:var(--text-color);">Hallo Welt</h1>
                    <p class="text-muted mb-0">
                        Testseite des Kundenportals &ndash; gesch&uuml;tzte Route ohne Fachfunktion.
                    </p>
                </div>

                <div class="p-4 rounded mb-4"
                     style="background:var(--surface-muted);border:1px solid var(--border-color);">

                    <h2 class="h6 fw-bold mb-3" style="color:var(--primary-color-dark);">
                        <i class="bi bi-shield-check me-1"></i>Portal-Kontext
                    </h2>

                    <table class="app-table mb-0">
                        <tbody>
                            <tr>
                                <th style="width:45%;">Portal der Route</th>
                                <td><code><?= htmlspecialchars($routePortal) ?></code></td>
                            </tr>
                            <tr>
                                <th>Portal des Tokens (<code>role.typ</code>)</th>
                                <td>
                                    <code><?= htmlspecialchars($tokenPortal ?? 'null') ?></code>
                                    <?php if ($passt): ?>
                                        <span class="badge rounded-pill ms-2"
                                              style="background:var(--primary-color-light);color:var(--text-color);">
                                            passt
                                        </span>
                                    <?php else: ?>
                                        <span class="badge rounded-pill bg-warning text-dark ms-2">
                                            fremdes Portal &ndash; darf alles
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Angemeldet als</th>
                                <td><?= htmlspecialchars($benutzer) ?></td>
                            </tr>
                        </tbody>
                    </table>

                    <p class="small text-muted mt-3 mb-0">
                        Steht rechts <em>fremdes Portal</em>, ist ein Mitarbeiterkonto angemeldet &ndash;
                        das darf jede Route besuchen. Mit einem Kundenkonto muss beides
                        <code>kunde</code> sein.
                    </p>
                </div>

                <div class="p-4 rounded mb-4"
                     style="background:var(--surface-muted);border:1px solid var(--border-color);">
                    <h2 class="h6 fw-bold mb-3" style="color:var(--primary-color-dark);">
                        <i class="bi bi-sign-turn-right me-1"></i>Portalgrenze pr&uuml;fen
                    </h2>
                    <p class="small text-muted">
                        Mit einem Kundenkonto f&uuml;hren beide Links zur&uuml;ck ins Kundenportal &ndash;
                        mit der Meldung, dass die Seite nicht zum eigenen Portal geh&ouml;rt.
                        Mit einem Mitarbeiterkonto &ouml;ffnen sie sich normal.
                    </p>
                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <?php foreach ($fremdeRouten as $pfad => $beschriftung): ?>
                        <a href="<?= APP_BASE . $pfad ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-box-arrow-up-right me-1"></i><?= $beschriftung ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="text-center">
                    <a href="<?= APP_BASE . Portal::start($routePortal) ?>"
                       style="color:var(--primary-color-dark);">
                        <i class="bi bi-arrow-left me-1"></i>Zur&uuml;ck zur Startseite
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>
