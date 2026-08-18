<?php
// views/components/flash.php
// Zeigt Erfolgs- und Fehlermeldungen aus der Session und loescht sie danach.
// Login-Fehler werden NICHT hier, sondern direkt im Login-Modal angezeigt.

if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
        <i class="bi bi-check-circle me-1"></i>
        <?= htmlspecialchars($_SESSION['flash_success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schliessen"></button>
    </div>
<?php unset($_SESSION['flash_success']); endif; ?>

<?php if (!empty($_SESSION['flash_error']) && empty($_GET['login'])): ?>
    <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <?= htmlspecialchars($_SESSION['flash_error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schliessen"></button>
    </div>
<?php unset($_SESSION['flash_error']); endif; ?>
