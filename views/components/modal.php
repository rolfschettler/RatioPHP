<?php
// views/components/modal.php
// Einziges Bootstrap-Modal-Geruest der App -- Login-Modal, Fehler-Dialog und
// jeder kuenftige Dialog (statt alert()/confirm()) bauen darauf auf.
//
// Aufruf:  View::komponente('modal', [...])
//
// Variablen:
//   $id            string  DOM-id des Modals (Pflicht)
//   $titel         string  Ueberschrift, Klartext (Pflicht)
//   $icon          string  Bootstrap-Icon-Klasse fuer den Titel, z.B. 'bi-person-circle'
//   $iconKlasse    string  zusaetzliche Klassen am Icon, z.B. 'text-warning'
//   $kopfPrimaer   bool    Kopf in der Primaerfarbe (Default false)
//   $inhalt        string  HTML des Modal-Bodys (Pflicht)
//   $fuss          string  HTML des Modal-Footers (optional, leer = kein Footer)
//   $formAction    string  Wenn gesetzt: Kopf/Body/Fuss stecken in einem
//                          <form method="POST"> mit dieser action (ohne APP_BASE)
//   $autoOeffnen   bool    Modal beim Laden der Seite oeffnen (Default false)

$titelId     = $id . 'Label';
$kopfPrimaer = $kopfPrimaer ?? false;
$formAction  = $formAction ?? null;
?>
<div class="modal fade" id="<?= htmlspecialchars($id) ?>" tabindex="-1"
     aria-labelledby="<?= htmlspecialchars($titelId) ?>" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <?php if ($formAction !== null): ?>
            <form method="POST" action="<?= APP_BASE . htmlspecialchars($formAction) ?>">
            <?php endif; ?>
                <div class="modal-header"
                     <?= $kopfPrimaer ? 'style="background:var(--primary-color);color:var(--on-primary);"' : '' ?>>
                    <h5 class="modal-title" id="<?= htmlspecialchars($titelId) ?>">
                        <?php if (!empty($icon)): ?>
                        <i class="bi <?= htmlspecialchars($icon) ?> <?= htmlspecialchars($iconKlasse ?? '') ?> me-1"></i>
                        <?php endif; ?>
                        <?= htmlspecialchars($titel) ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                </div>
                <div class="modal-body">
                    <?= $inhalt ?>
                </div>
                <?php if (!empty($fuss)): ?>
                <div class="modal-footer">
                    <?= $fuss ?>
                </div>
                <?php endif; ?>
            <?php if ($formAction !== null): ?>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php if (!empty($autoOeffnen)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    new bootstrap.Modal(document.getElementById(<?= json_encode($id) ?>)).show();
});
</script>
<?php endif; ?>
