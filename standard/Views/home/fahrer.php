<?php
// standard/Views/home/fahrer.php
// Fahrerportal -- Login per Modal, Registrierung ueber /fahrer/registrieren.
// Nur direkt per URL /fahrer erreichbar.
// Reiner Content-HTML -- kein DOCTYPE, kein Layout-Include.
//
// Das Fahrerportal wird am Handy benutzt: Kacheln ueber die volle Breite,
// kurze Texte. Kacheln ueber views/components/kachel.php -- ein weiteres
// Modul bekommt hier eine Zeile ('href' => null zeigt "In Vorbereitung").

use Core\Portal;
use Core\View;

$eingeloggt = !empty($_COOKIE['jwt_token']);
$benutzer   = $_COOKIE['jwt_user'] ?? '';

// Pfade aus core/Portal.php -- kein Portalpfad im View
$modulBasis     = Portal::praefix($portal ?? 'fahrer');
$registrierLink = Portal::registrierung($portal ?? 'fahrer');

// Texte sind Klartext, die Komponente escaped.
// Heute / 7 Tage waehlt der Fahrer im Modul selbst.
$kacheln = [
    ['icon' => 'bi-signpost-split', 'titel' => 'Meine Tour', 'text' => 'Ihre Einsätze für heute oder die nächsten 7 Tage.', 'href' => $modulBasis . '/tour'],
];
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
                        Hallo <strong style="color:var(--primary-color-dark);"><?= htmlspecialchars($benutzer) ?></strong>.
                    </p>

                    <div class="row g-3 text-start">
                        <?php foreach ($kacheln as $kachel): ?>
                        <div class="col-12">
                            <?= View::komponente('kachel', $kachel) ?>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <?php // portal=fahrer ist Pflicht -- ohne den Parameter landet
                          // der Logout im Kundenportal (Default aus Core\Portal). ?>
                    <a href="<?= APP_BASE ?>/logout?portal=fahrer" class="btn btn-outline-secondary w-100 mt-4">
                        <i class="bi bi-box-arrow-right me-1"></i>Abmelden
                    </a>

                <?php else: ?>

                    <p class="lead text-muted mb-4">
                        Bereich f&uuml;r Fahrerinnen und Fahrer. Melden Sie sich mit Ihrem
                        Benutzernamen an.
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
