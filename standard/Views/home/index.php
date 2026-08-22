<?php
// standard/Views/home/index.php
// Kundenportal -- oeffentlicher Einstieg: Registrierung und Anmeldung.
// Kein Verweis auf das Mitarbeiterportal.
// Reiner Content-HTML -- kein DOCTYPE, kein Layout-Include.

$eingeloggt = !empty($_COOKIE['jwt_token']);
$benutzer   = $_COOKIE['jwt_user'] ?? '';
?>
<div class="px-3 py-5 py-lg-6">
    <div class="container">
        <div class="row justify-content-center text-center">
            <div class="col-12 col-lg-8">

                <div class="mb-4">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle"
                          style="width:88px;height:88px;background:var(--primary-color-light);">
                        <i class="bi bi-people-fill" style="font-size:2.4rem;color:var(--text-color);"></i>
                    </span>
                </div>

                <h1 class="display-5 fw-bold mb-3" style="color:var(--text-color);">
                    Willkommen im Kundenportal
                </h1>

                <?php if ($eingeloggt): ?>

                    <p class="lead text-muted mb-4">
                        Sie sind als <strong style="color:var(--primary-color-dark);"><?= htmlspecialchars($benutzer) ?></strong>
                        angemeldet.
                    </p>
                    <a href="<?= APP_BASE ?>/logout?portal=kunde" class="btn btn-lg fw-semibold btn-app-primary">
                        <i class="bi bi-box-arrow-right me-1"></i>Abmelden
                    </a>

                <?php else: ?>

                    <p class="lead text-muted mb-4">
                        Registrieren Sie sich einmalig &ndash; wir pr&uuml;fen Ihre Angaben und
                        melden uns bei Ihnen. Bereits registriert? Dann melden Sie sich an.
                    </p>

                    <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
                        <a href="<?= APP_BASE ?>/registrieren" class="btn btn-lg fw-semibold btn-app-primary">
                            <i class="bi bi-person-plus me-1"></i>Jetzt registrieren
                        </a>
                        <button type="button" class="btn btn-lg fw-semibold btn-outline-secondary"
                                data-bs-toggle="modal" data-bs-target="#loginModal">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Anmelden
                        </button>
                    </div>

                <?php endif; ?>

                <h2 class="h6 fw-bold text-uppercase text-muted mt-5 mb-3" style="letter-spacing:.06em;">
                    So geht es weiter
                </h2>
                <div class="row g-3 text-start">
                    <div class="col-12 col-md-4">
                        <div class="p-3 h-100 rounded"
                             style="background:var(--surface-muted);border:1px solid var(--border-color);">
                            <i class="bi bi-1-circle mb-2" style="font-size:1.6rem;color:var(--primary-color-dark);"></i>
                            <h3 class="h6 fw-bold mb-1" style="color:var(--text-color);">Formular ausf&uuml;llen</h3>
                            <p class="small text-muted mb-0">
                                Firmen- und Kontaktdaten eingeben und absenden.
                            </p>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="p-3 h-100 rounded"
                             style="background:var(--surface-muted);border:1px solid var(--border-color);">
                            <i class="bi bi-2-circle mb-2" style="font-size:1.6rem;color:var(--primary-color-dark);"></i>
                            <h3 class="h6 fw-bold mb-1" style="color:var(--text-color);">Pr&uuml;fung</h3>
                            <p class="small text-muted mb-0">
                                Wir pr&uuml;fen Ihre Registrierung und legen Ihr Konto an.
                            </p>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="p-3 h-100 rounded"
                             style="background:var(--surface-muted);border:1px solid var(--border-color);">
                            <i class="bi bi-3-circle mb-2" style="font-size:1.6rem;color:var(--primary-color-dark);"></i>
                            <h3 class="h6 fw-bold mb-1" style="color:var(--text-color);">R&uuml;ckmeldung</h3>
                            <p class="small text-muted mb-0">
                                Sie erhalten eine Nachricht, sobald Ihr Zugang bereitsteht.
                            </p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
