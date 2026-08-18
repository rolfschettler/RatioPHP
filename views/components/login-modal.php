<?php
// views/components/login-modal.php
// Bootstrap-Modal mit Login-Formular -- auf jeder Seite verfuegbar.
// Ersetzt eine eigene Login-Seite vollstaendig.
//
// - action: POST /login (kein GET /login)
// - Felder: user, password (kein required auf password -- Validierung serverseitig)
// - Fehlermeldung wird direkt im Modal angezeigt
// - Oeffnet sich automatisch wenn URL-Parameter ?login=1 gesetzt ist

$login_error = '';
if (!empty($_SESSION['flash_error']) && !empty($_GET['login'])) {
    $login_error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}
?>
<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= APP_BASE ?>/login">
                <div class="modal-header" style="background:var(--primary-color);color:var(--on-primary);">
                    <h5 class="modal-title" id="loginModalLabel">
                        <i class="bi bi-person-circle me-1"></i>Anmelden
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
                </div>
                <div class="modal-body">
                    <?php if ($login_error !== ''): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <?= htmlspecialchars($login_error) ?>
                    </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="loginUser" class="form-label">Benutzername</label>
                        <input type="text" class="form-control" id="loginUser" name="user" autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="loginPassword" class="form-label">Passwort</label>
                        <input type="password" class="form-control" id="loginPassword" name="password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        Abbrechen
                    </button>
                    <button type="submit" class="btn fw-semibold btn-app-primary">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Anmelden
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (!empty($_GET['login'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('loginModal');
    if (modalEl) {
        new bootstrap.Modal(modalEl).show();
    }
});
</script>
<?php endif; ?>
