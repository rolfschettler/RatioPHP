<?php
// views/components/flash.php
// Zeigt Erfolgsmeldungen aus der Session und loescht sie danach.
//
// Fehler laufen NICHT mehr hierueber:
//   Benutzerfehler -> Dialog                views/components/fehler-dialog.php
//   Systemfehler   -> reservierter Bereich  views/components/systemfehler.php

if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
        <i class="bi bi-check-circle me-1"></i>
        <?= htmlspecialchars($_SESSION['flash_success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schliessen"></button>
    </div>
<?php unset($_SESSION['flash_success']); endif; ?>
