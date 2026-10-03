<?php
// standard/Views/home/mitarbeiter.php
// Mitarbeiterportal -- Login per Modal, danach Modulauswahl.
// Nur direkt per URL /mitarbeiter erreichbar.
// Reiner Content-HTML -- kein DOCTYPE, kein Layout-Include.
//
// Modul-Links werden aus dem Portal-Praefix gebaut (core/Portal.php) -- kein
// Portalpfad wird hier ausgeschrieben.

use Core\Portal;

$eingeloggt = !empty($_COOKIE['jwt_token']);
$benutzer   = $_COOKIE['jwt_user'] ?? '';

$modulBasis     = Portal::praefix($portal ?? 'mitarbeiter');
$registrierLink = Portal::registrierung($portal ?? 'mitarbeiter');
?>
<div class="px-3 py-5 py-lg-6">
    <div class="container">
        <div class="row justify-content-center text-center">
            <div class="col-12 col-lg-8">

                <div class="mb-4">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle"
                          style="width:88px;height:88px;background:var(--primary-color-light);">
                        <i class="bi bi-grid-3x3-gap-fill" style="font-size:2.4rem;color:var(--text-color);"></i>
                    </span>
                </div>

                <h1 class="display-5 fw-bold mb-3" style="color:var(--text-color);">
                    Mitarbeiterportal
                </h1>

                <?php if ($eingeloggt): ?>

                    <p class="lead text-muted mb-4">
                        Sie sind als <strong style="color:var(--primary-color-dark);"><?= htmlspecialchars($benutzer) ?></strong>
                        angemeldet. W&auml;hlen Sie ein Modul.
                    </p>
                    <?php // portal=mitarbeiter ist Pflicht -- ohne den Parameter landet
                          // der Logout im Kundenportal (Default des AuthControllers). ?>
                    <a href="<?= APP_BASE ?>/logout?portal=mitarbeiter" class="btn btn-lg fw-semibold btn-app-primary">
                        <i class="bi bi-box-arrow-right me-1"></i>Abmelden
                    </a>

                    <h2 class="h6 fw-bold text-uppercase text-muted mt-5 mb-3" style="letter-spacing:.06em;">Module</h2>
                    <div class="row g-3 text-start">
                        <div class="col-12 col-md-6 col-lg-4">
                            <a href="<?= APP_BASE . $modulBasis ?>/einsatz" class="text-decoration-none d-block p-3 h-100 rounded"
                               style="background:var(--surface-muted);border:1px solid var(--border-color);color:inherit;">
                                <i class="bi bi-truck mb-2" style="font-size:1.6rem;color:var(--primary-color-dark);"></i>
                                <h3 class="h6 fw-bold mb-1" style="color:var(--text-color);">Eins&auml;tze</h3>
                                <p class="small text-muted mb-0">Alle Eins&auml;tze als Tabelle anzeigen.</p>
                            </a>
                        </div>
                        <div class="col-12 col-md-6 col-lg-4">
                            <a href="<?= APP_BASE . $modulBasis ?>/anmietimport" class="text-decoration-none d-block p-3 h-100 rounded"
                               style="background:var(--surface-muted);border:1px solid var(--border-color);color:inherit;">
                                <i class="bi bi-upload mb-2" style="font-size:1.6rem;color:var(--primary-color-dark);"></i>
                                <h3 class="h6 fw-bold mb-1" style="color:var(--text-color);">Anmietimport</h3>
                                <p class="small text-muted mb-0">JSON-Datei importieren (ANMIET/ANMIETPOS).</p>
                            </a>
                        </div>
                        <div class="col-12 col-md-6 col-lg-4">
                            <a href="<?= APP_BASE . $modulBasis ?>/registrierungen" class="text-decoration-none d-block p-3 h-100 rounded"
                               style="background:var(--surface-muted);border:1px solid var(--border-color);color:inherit;">
                                <i class="bi bi-people mb-2" style="font-size:1.6rem;color:var(--primary-color-dark);"></i>
                                <h3 class="h6 fw-bold mb-1" style="color:var(--text-color);">Registrierungen</h3>
                                <p class="small text-muted mb-0">Portalzug&auml;nge und Rollenvorlagen verwalten.</p>
                            </a>
                        </div>
                    </div>

                <?php else: ?>

                    <p class="lead text-muted mb-4">
                        Interner Bereich. Melden Sie sich mit Ihrem Mitarbeiterkonto an,
                        um auf die Module zuzugreifen.
                    </p>
                    <button type="button" class="btn btn-lg fw-semibold btn-app-primary"
                            data-bs-toggle="modal" data-bs-target="#loginModal">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Jetzt anmelden
                    </button>

                    <p class="text-muted mt-4 mb-0">
                        Noch kein Portalzugang?
                        <a href="<?= APP_BASE . $registrierLink ?>"
                           style="color:var(--primary-color-dark);">
                            <i class="bi bi-person-badge me-1"></i>Als Mitarbeiter registrieren
                        </a>
                    </p>

                <?php endif; ?>

            </div>
        </div>
    </div>
</div>
