<?php
// standard/Views/fehler/index.php
// Inhalt der Fehlerseite (core/Fehler.php::seite()) -- 404, Konfigurations-
// fehler, PHP-Ausnahmen. Die Meldung selbst steht im reservierten
// Systemfehler-Bereich unter dem Header; hier nur der Weg zurueck.

use Core\Portal;
?>
<div class="container py-5 text-center">
    <i class="bi bi-exclamation-octagon display-4" style="color:var(--primary-color-dark);"></i>
    <h1 class="h4 mt-3" style="color:var(--text-color);">
        <?= (int)$http === 404 ? 'Seite nicht gefunden' : 'Die Seite konnte nicht angezeigt werden' ?>
    </h1>
    <p class="text-muted">Einzelheiten stehen in der Meldung oben.</p>
    <a href="<?= APP_BASE . Portal::start($portal) ?>" class="btn fw-semibold btn-app-primary">
        <i class="bi bi-house me-1"></i>Zur Startseite
    </a>
</div>
