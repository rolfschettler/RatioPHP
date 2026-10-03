<?php
// standard/Views/registrierungsverwaltung/vorlagen.php
// Rollenvorlagen (Blaupausen '@NAME' in REGISTRIERUNG) -- Tabelle plus drei
// Dialoge (Neu, Bearbeiten, Loeschen). Reiner Content-HTML, kein Layout.
//
// Wie in index.php je EIN Modal fuer alle Zeilen: der ausloesende Button
// traegt die Werte als data-Attribute, das Skript unten fuellt das Modal.

use Core\Pruefung;
use Core\View;

/** @var array  $rows              Vorlagen: nr|null, name, rollen_liste, system, verwendet */
/** @var array  $rollenVorschlaege Autocomplete-Liste der Rollen [wert, gruppe], ohne Vorlagen */
/** @var int    $rolleMax          Hoechstlaenge eines Rollennamens */
/** @var string $rolleMuster       JS-RegExp fuer freie Rolleneingaben (ohne @) */
/** @var array  $felder            Feld-Definitionen des Neu-Dialogs */
/** @var string $modul_url         Modulpfad ohne APP_BASE */
/** @var int    $breiteMax         Maximale Seitenbreite in Pixel (zentriert) */

$h = static fn($v): string => htmlspecialchars((string)($v ?? ''), ENT_QUOTES);

$badgeStil = 'background:var(--primary-color-light);color:var(--text-color);';
?>
<div class="mx-auto w-100" style="max-width:<?= (int)$breiteMax ?>px;max-height:100%;overflow:auto;border:1px solid var(--frame-color);">
<table class="app-table" style="min-width:700px;">
    <thead>
        <tr>
            <th>Aktionen</th>
            <th>Vorlage</th>
            <th>Rollen</th>
            <th>Verwendet von</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
        <tr>
            <td colspan="4" class="text-center text-muted py-4">Keine Rollenvorlagen vorhanden.</td>
        </tr>
        <?php else: ?>
            <?php foreach ($rows as $r):
                $angelegt   = $r['nr'] !== null;
                $loeschbar  = $angelegt && !$r['system'] && $r['verwendet'] === 0;
                $loeschInfo = $r['system'] ? 'Systemvorlage – nicht löschbar'
                            : ($r['verwendet'] > 0 ? 'Wird noch verwendet – nicht löschbar' : 'Löschen');
            ?>
            <tr>
                <td class="act">
                    <?php if ($angelegt): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary" style="padding:.2rem .4rem;margin-right:.2rem;"
                            title="Bearbeiten"
                            data-bs-toggle="modal" data-bs-target="#vorlageBearbeiten"
                            data-nr="<?= $h($r['nr']) ?>"
                            data-name="<?= $h($r['name']) ?>"
                            data-rollen="<?= $h(json_encode($r['rollen_liste'])) ?>">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <?php // Ein deaktivierter Button zeigt keinen Tooltip -- deshalb der Titel am span ?>
                    <span title="<?= $h($loeschInfo) ?>">
                        <button type="button" class="btn btn-sm btn-outline-danger" style="padding:.2rem .4rem;"
                                data-bs-toggle="modal" data-bs-target="#vorlageLoeschen"
                                data-nr="<?= $h($r['nr']) ?>"
                                data-name="<?= $h($r['name']) ?>"
                                <?= $loeschbar ? '' : 'disabled' ?>>
                            <i class="bi bi-trash"></i>
                        </button>
                    </span>
                    <?php else: ?>
                    <button type="button" class="btn btn-sm fw-semibold btn-app-primary" style="padding:.2rem .5rem;"
                            data-bs-toggle="modal" data-bs-target="#vorlageNeu"
                            data-name="<?= $h(ltrim($r['name'], '@')) ?>">
                        <i class="bi bi-plus-lg me-1"></i>Anlegen
                    </button>
                    <?php endif; ?>
                </td>
                <td class="text-nowrap">
                    <span class="fw-semibold"><?= $h($r['name']) ?></span>
                    <?php if ($r['system']): ?>
                        <span class="badge text-bg-secondary ms-1" title="Wird neuen Zugängen dieses Portals automatisch zugewiesen">System</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!$angelegt): ?>
                        <span class="text-danger">nicht angelegt <i class="bi bi-exclamation-triangle"></i></span>
                    <?php else: ?>
                        <?php // max-width wirkt an einer Tabellenzelle nicht -- deshalb am inneren div.
                              // Was nicht in 400px passt, endet in "...", der Tooltip zeigt alle Rollen. ?>
                        <div class="text-truncate" style="max-width:400px;"
                             title="<?= $h(implode(', ', $r['rollen_liste'])) ?>">
                        <?php foreach ($r['rollen_liste'] as $rolle): ?>
                            <?php if (strcasecmp($rolle, 'supervisor') === 0): ?>
                                <span class="badge rounded-pill text-bg-warning" title="Voller Zugriff für alle Zugänge dieser Vorlage"><?= $h($rolle) ?> <i class="bi bi-exclamation-triangle"></i></span>
                            <?php else: ?>
                                <span class="badge rounded-pill" style="<?= $badgeStil ?>"><?= $h($rolle) ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if (empty($r['rollen_liste'])): ?>
                            <span class="text-muted" title="Eine leere Vorlage ergibt keine Rechte">keine</span>
                        <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td class="dim">
                    <?= $r['verwendet'] === 1 ? '1 Zugang' : $h($r['verwendet']) . ' Zugänge' ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
</div>

<?php
// ---------------------------------------------------------------------------
// Rollen-Auswahl -- in Neu und Bearbeiten identisch, nur die id unterscheidet
// ---------------------------------------------------------------------------
$rollenAuswahl = static fn(string $id): string => View::komponente('tag-auswahl', [
    'id'          => $id,
    'name'        => 'rollen[]',
    'label'       => 'Rollen',
    'vorschlaege' => $rollenVorschlaege,
    'maxlength'   => $rolleMax,
    'muster'      => $rolleMuster,
    'platzhalter' => 'Rolle oder Endpunkt suchen …',
    'fehlertext'  => 'Ungültiger Wert – erlaubt sind Buchstaben, Ziffern und _ . / * -. Vorlagen (@…) wirken innerhalb einer Vorlage nicht.',
    'hinweis'     => 'Eine leere Liste ergibt keine Rechte. „supervisor“ gibt allen Zugängen dieser Vorlage vollen Zugriff. Änderungen wirken beim nächsten Login.',
]);

$fussSpeichern = '
    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
    <button type="submit" class="btn fw-semibold btn-app-primary"><i class="bi bi-check-lg me-1"></i>Speichern</button>';

// ---------------------------------------------------------------------------
// Dialog: Neue Vorlage
// ---------------------------------------------------------------------------
echo View::komponente('modal', [
    'id'          => 'vorlageNeu',
    'titel'       => 'Neue Rollenvorlage',
    'icon'        => 'bi-plus-square',
    'kopfPrimaer' => true,
    'formAction'  => $modul_url . '/vorlage-anlegen',
    'inhalt'      => '
        <div class="mb-3">
            <label class="form-label" for="vorlageName">' . $h($felder['name']['bezeichnung']) . '</label>
            <div class="input-group">
                <span class="input-group-text">@</span>
                <input type="text" class="form-control text-uppercase" id="vorlageName" name="name" data-feld="name"
                       pattern="@?[A-Za-z0-9_\-]+" title="Buchstaben A–Z, Ziffern, _ und -"
                       autocomplete="off"' . Pruefung::htmlAttribute($felder, 'name') . '>
            </div>
            <div class="form-text">Zuweisbar als „@NAME“ in den Rollen eines Zugangs.</div>
        </div>
        <div class="mb-0">' . $rollenAuswahl('vorlageNeuRollen') . '</div>',
    'fuss'        => $fussSpeichern,
]);

// ---------------------------------------------------------------------------
// Dialog: Bearbeiten
// ---------------------------------------------------------------------------
echo View::komponente('modal', [
    'id'          => 'vorlageBearbeiten',
    'titel'       => 'Rollenvorlage bearbeiten',
    'icon'        => 'bi-pencil-square',
    'kopfPrimaer' => true,
    'formAction'  => $modul_url . '/vorlage-speichern',
    'inhalt'      => '
        <input type="hidden" name="nr" data-feld="nr">
        <dl class="row mb-3">
            <dt class="col-4 fw-normal text-muted">Vorlage</dt>
            <dd class="col-8 mb-0 fw-semibold" data-feld="name"></dd>
        </dl>
        <div class="mb-0">' . $rollenAuswahl('vorlageRollen') . '</div>',
    'fuss'        => $fussSpeichern,
]);

// ---------------------------------------------------------------------------
// Dialog: Loeschen bestaetigen
// ---------------------------------------------------------------------------
echo View::komponente('modal', [
    'id'          => 'vorlageLoeschen',
    'titel'       => 'Rollenvorlage löschen',
    'icon'        => 'bi-trash',
    'iconKlasse'  => 'text-danger',
    'formAction'  => $modul_url . '/vorlage-loeschen',
    'inhalt'      => '
        <input type="hidden" name="nr" data-feld="nr">
        <p class="mb-0">Soll die Rollenvorlage <strong data-feld="name"></strong> wirklich gelöscht werden?</p>',
    'fuss'        => '
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
        <button type="submit" class="btn btn-danger fw-semibold"><i class="bi bi-trash me-1"></i>Löschen</button>',
]);
?>
<script>
// Fuellt die Dialoge aus den data-Attributen des ausloesenden Buttons.
document.querySelectorAll('#vorlageNeu, #vorlageBearbeiten, #vorlageLoeschen').forEach(function (modal) {
    modal.addEventListener('show.bs.modal', function (event) {
        var d = event.relatedTarget.dataset;

        modal.querySelectorAll('[data-feld]').forEach(function (el) {
            if (el.tagName === 'INPUT') {
                el.value = d[el.dataset.feld] || '';
            } else {
                el.textContent = d[el.dataset.feld] || '';
            }
        });

        var auswahl = modal.querySelector('[data-name="rollen[]"]');
        if (auswahl) {
            auswahl.setzeWerte(d.rollen ? JSON.parse(d.rollen) : []);
        }
    });
});
</script>
