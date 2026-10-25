<?php
// views/components/kachel.php
// Modul-Kachel fuer die Portal-Startseiten.
//
// Aufruf:  View::komponente('kachel', [...])
//
// Variablen:
//   $icon   string       Bootstrap-Icon-Klasse, z.B. 'bi-truck' (Pflicht)
//   $titel  string       Ueberschrift, Klartext (Pflicht)
//   $text   string       Kurzbeschreibung, Klartext (Pflicht)
//   $href   string|null  Ziel ohne APP_BASE, z.B. Portal::praefix($portal) . '/einsatz'.
//                        null = Modul noch nicht vorhanden: Kachel ohne Link,
//                        mit Hinweis "In Vorbereitung".

$href = $href ?? null;
$stil = 'background:var(--surface-muted);border:1px solid var(--border-color);color:inherit;';
?>
<?php if ($href !== null): ?>
<a href="<?= APP_BASE . htmlspecialchars($href) ?>" class="text-decoration-none d-block p-3 h-100 rounded"
   style="<?= $stil ?>">
<?php else: ?>
<div class="d-block p-3 h-100 rounded" style="<?= $stil ?>" aria-disabled="true">
<?php endif; ?>
    <div class="d-flex justify-content-between align-items-start mb-2">
        <i class="bi <?= htmlspecialchars($icon) ?>" style="font-size:1.6rem;color:var(--primary-color-dark);"></i>
        <?php if ($href === null): ?>
        <span class="badge rounded-pill text-bg-light border fw-normal">In Vorbereitung</span>
        <?php endif; ?>
    </div>
    <h3 class="h6 fw-bold mb-1" style="color:var(--text-color);"><?= htmlspecialchars($titel) ?></h3>
    <p class="small text-muted mb-0"><?= htmlspecialchars($text) ?></p>
<?php if ($href !== null): ?>
</a>
<?php else: ?>
</div>
<?php endif; ?>
