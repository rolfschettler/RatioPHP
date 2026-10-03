<?php
// views/components/fehler-dialog.php
// Dialog fuer BENUTZERFEHLER -- alles, was der Benutzer selbst beheben kann:
// Pflichtfeld leer, Eingabe zu lang, Registrierung vom Server abgelehnt, ...
// Gemeldet per $this->flashError() bzw. Core\Fehler::benutzer(). Oeffnet sich
// beim Laden der Seite automatisch.
//
// Systemfehler gehoeren NICHT hierher -- die stehen im reservierten Bereich
// views/components/systemfehler.php.
//
// Bei ?login=1 zeigt das Login-Modal die Meldung selbst an (falsches Passwort)
// -- der Dialog bleibt dann zu, damit nicht zwei Modals uebereinander liegen.

use Core\Fehler;

$benutzerfehler = empty($_GET['login']) ? Fehler::holeBenutzer() : '';
?>
<?php if ($benutzerfehler !== ''): ?>
<div class="modal fade" id="fehlerDialog" tabindex="-1" aria-labelledby="fehlerDialogLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fehlerDialogLabel">
                    <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>Bitte prüfen Sie Ihre Eingabe
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <?= nl2br(htmlspecialchars($benutzerfehler)) ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn fw-semibold btn-app-primary" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var dialog = document.getElementById('fehlerDialog');
    if (dialog) {
        new bootstrap.Modal(dialog).show();
    }
});
</script>
<?php endif; ?>
