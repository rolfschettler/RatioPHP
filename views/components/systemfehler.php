<?php
// views/components/systemfehler.php
// Reservierter Bereich fuer SYSTEMFEHLER -- direkt unter dem Header, ausserhalb
// von .app-main. Scrollt also nie weg und wird von keinem Inhalt verdeckt.
//
// Systemfehler: RATIOserver nicht erreichbar, Datenbankfehler, fehlende
// Berechtigung auf einen Endpunkt (HTTP 403), Seite/Endpunkt existiert nicht,
// abgelaufene Anmeldung, PHP-Ausnahme. Gemeldet ueber core/Fehler.php --
// \api_post() erledigt das fuer alle Endpunkte selbst.
//
// Benutzerfehler (Pflichtfelder, Laengen, ...) erscheinen NICHT hier, sondern
// im Dialog views/components/fehler-dialog.php.
//
// Der Tag #app-systemfehler wird IMMER ausgegeben -- leer mit hidden. Er ist
// fuer Systemfehler reserviert; nichts anderes wird hier hineingeschrieben.
// Technische Details nur bei DEBUG.

use Core\Fehler;

$systemfehler = Fehler::holeSystem();
$zeigeDetails = defined('DEBUG') && DEBUG;
?>
<div id="app-systemfehler" class="flex-shrink-0" role="alert" aria-live="assertive"
     <?= $systemfehler ? '' : 'hidden' ?>>
    <?php if ($systemfehler): ?>
    <div class="alert alert-danger alert-dismissible rounded-0 border-0 border-bottom border-danger-subtle mb-0 py-2 px-3"
         style="max-height:30vh;overflow-y:auto;">
        <?php foreach ($systemfehler as $meldung => $details): ?>
        <div class="d-flex align-items-start gap-2 small">
            <i class="bi bi-exclamation-octagon-fill mt-1"></i>
            <div>
                <strong>Systemfehler:</strong> <?= htmlspecialchars($meldung) ?>
                <?php if ($zeigeDetails && $details): ?>
                <ul class="mb-1 ps-3 font-monospace" style="font-size:.75rem;">
                    <?php foreach ($details as $detail): ?>
                    <li><?= htmlspecialchars($detail) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Schließen"
                onclick="document.getElementById('app-systemfehler').hidden = true;"></button>
    </div>
    <?php endif; ?>
</div>
