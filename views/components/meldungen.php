<?php
// views/components/meldungen.php
// Einzige Darstellung von Meldungen -- fuer ALLE Arten (System, Benutzer,
// Erfolg) und an ALLEN Stellen (reservierter Bereich, Dialog, Login-Modal,
// Inhaltsbereich). Farbe, Icon und Praefix kommen aus Core\Meldungen::ARTEN.
//
// Aufruf:  View::komponente('meldungen', ['art' => Meldungen::SYSTEM, 'klasse' => '...'])
//
// Variablen:
//   $art          string  Meldungsart -- die Meldungen werden abgeholt und geloescht
//   $klasse       string  zusaetzliche CSS-Klassen fuer das alert (optional)
//   $schliessbar  bool    Schliessen-Knopf anzeigen (optional, Default false)
//
// Technische Details erscheinen nur bei DEBUG.

use Core\Meldungen;

$meldungen = Meldungen::hole($art);
if (!$meldungen) {
    return;
}

$stil         = Meldungen::ARTEN[$art];
$schliessbar  = $schliessbar ?? false;
$zeigeDetails = defined('DEBUG') && DEBUG;
?>
<div class="alert alert-<?= $stil['farbe'] ?> <?= $schliessbar ? 'alert-dismissible' : '' ?> <?= htmlspecialchars($klasse ?? '') ?>"
     role="alert">
    <?php foreach ($meldungen as $text => $details): ?>
    <div class="d-flex align-items-start gap-2">
        <i class="bi <?= $stil['icon'] ?> mt-1"></i>
        <div>
            <?php if ($stil['praefix'] !== ''): ?><strong><?= htmlspecialchars($stil['praefix']) ?></strong><?php endif; ?>
            <?= nl2br(htmlspecialchars($text)) ?>
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
    <?php if ($schliessbar): ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>
    <?php endif; ?>
</div>
