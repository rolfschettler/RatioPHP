<?php
// standard/Views/home/fahrer.php
// Fahrerportal -- Login per Modal, Registrierung ueber /fahrer/registrieren.
// Nur direkt per URL /fahrer erreichbar.
// Reiner Content-HTML -- kein DOCTYPE, kein Layout-Include.
//
// Noch ohne Fachmodule: sobald feststeht, welche Daten Fahrer sehen sollen,
// kommen die Kacheln in den eingeloggten Zweig -- analog home/mitarbeiter.php.

use Core\Portal;

$eingeloggt = !empty($_COOKIE['jwt_token']);
$benutzer   = $_COOKIE['jwt_user'] ?? '';

// Registrierungspfad aus core/Portal.php -- kein Portalpfad im View
$registrierLink = Portal::registrierung($portal ?? 'fahrer');
?>
<div class="px-3 py-5 py-lg-6">
    <div class="container">
        <div class="row justify-content-center text-center">
            <div class="col-12 col-lg-8">

                <div class="mb-4">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle"
                          style="width:88px;height:88px;background:var(--primary-color-light);">
                        <i class="bi bi-truck-front-fill" style="font-size:2.4rem;color:var(--text-color);"></i>
                    </span>
                </div>

                <h1 class="display-5 fw-bold mb-3" style="color:var(--text-color);">
                    Fahrerportal
                </h1>

                <?php if ($eingeloggt): ?>

                    <p class="lead text-muted mb-4">
                        Sie sind als <strong style="color:var(--primary-color-dark);"><?= htmlspecialchars($benutzer) ?></strong>
                        angemeldet. Hier stehen in K&uuml;rze Ihre Fahrerfunktionen bereit.
                    </p>
                    <?php // portal=fahrer ist Pflicht -- ohne den Parameter landet
                          // der Logout im Kundenportal (Default aus Core\Portal). ?>
                    <a href="<?= APP_BASE ?>/logout?portal=fahrer" class="btn btn-lg fw-semibold btn-app-primary">
                        <i class="bi bi-box-arrow-right me-1"></i>Abmelden
                    </a>

                <?php else: ?>

                    <p class="lead text-muted mb-4">
                        Bereich f&uuml;r Fahrerinnen und Fahrer. Melden Sie sich mit Ihrem
                        Fahrerk&uuml;rzel an.
                    </p>
                    <button type="button" class="btn btn-lg fw-semibold btn-app-primary"
                            data-bs-toggle="modal" data-bs-target="#loginModal">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Jetzt anmelden
                    </button>
                    <p class="text-muted mt-3 mb-0">
                        Noch kein Zugang?
                        <a href="<?= APP_BASE . $registrierLink ?>" style="color:var(--primary-color-dark);">
                            Jetzt registrieren
                        </a>
                    </p>

                <?php endif; ?>

            </div>
        </div>
    </div>
</div>
