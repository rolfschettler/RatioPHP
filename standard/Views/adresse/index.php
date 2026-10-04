<?php
// standard/Views/adresse/index.php
// Eigene Adresse bearbeiten (Kundenportal) -- schreibt ueber
// /adressen/updateadressen. Die Kennziffer steht NICHT im Formular, der
// Controller nimmt sie aus dem Token.
// Reiner Content-HTML -- kein DOCTYPE, kein Layout-Include.
//
// Variablen aus dem AdresseController:
//   $felder           Feld-Definitionen (aus RegistrierungController::FELDER)
//                     -- Beschriftung, Pflicht und Grenzen jedes Feldes
//   $eingaben         aktuelle Werte (aus der Datenbank bzw. dem POST)
//   $hinweis          Text statt des Formulars, wenn es nichts zu bearbeiten gibt
//   $formular_action  Ziel des Formulars

use Core\Portal;
use Core\Pruefung;

$eingaben = $eingaben ?? [];
$felder   = $felder   ?? [];
$hinweis  = $hinweis  ?? null;
$aktion   = $formular_action ?? '';
$start    = Portal::start($portal ?? Portal::DEFAULT);

/** Gibt einen Eingabewert HTML-sicher zurueck. */
$wert = static function (string $feld) use ($eingaben): string {
    return htmlspecialchars((string)($eingaben[$feld] ?? ''), ENT_QUOTES);
};

/** Label aus der Feld-Definition -- Pflichtfelder mit Stern. */
$label = static function (string $feld, string $id) use ($felder): string {
    $def = $felder[$feld] ?? [];
    return '<label for="' . $id . '" class="form-label">'
         . htmlspecialchars($def['bezeichnung'] ?? $feld)
         . (!empty($def['pflicht']) ? ' <span class="text-danger">*</span>' : '')
         . '</label>';
};

/** maxlength und required aus der Feld-Definition. */
$attr = static fn(string $feld): string => Pruefung::htmlAttribute($felder, $feld);
?>
<div class="px-3 py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-10 col-lg-7">

                <div class="text-center mb-4">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
                          style="width:72px;height:72px;background:var(--primary-color-light);">
                        <i class="bi bi-house-gear-fill" style="font-size:2rem;color:var(--text-color);"></i>
                    </span>
                    <h1 class="h3 fw-bold mb-1" style="color:var(--text-color);">Meine Adresse</h1>
                    <p class="text-muted mb-0">Pr&uuml;fen und &auml;ndern Sie Ihre hinterlegten Adressdaten.</p>
                </div>

                <?php if ($hinweis !== null): ?>

                <div class="p-4 rounded text-center"
                     style="background:var(--surface-muted);border:1px solid var(--border-color);">
                    <i class="bi bi-info-circle mb-2 d-block" style="font-size:1.6rem;color:var(--primary-color-dark);"></i>
                    <p class="mb-0"><?= htmlspecialchars($hinweis) ?></p>
                </div>

                <?php else: ?>

                <form method="POST" action="<?= APP_BASE . $aktion ?>"
                      class="p-4 rounded" style="background:var(--surface-muted);border:1px solid var(--border-color);">

                    <div class="row g-3">
                        <div class="col-12 col-sm-4">
                            <?= $label('anrede', 'adrAnrede') ?>
                            <select class="form-select" id="adrAnrede" name="anrede"<?= $attr('anrede') ?>>
                                <option value="">Bitte w&auml;hlen</option>
                                <?php foreach ($felder['anrede']['auswahl'] as $anrede): ?>
                                <option value="<?= htmlspecialchars($anrede, ENT_QUOTES) ?>"
                                    <?= ($eingaben['anrede'] ?? '') === $anrede ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($anrede) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-sm-4">
                            <?= $label('name1', 'adrName1') ?>
                            <input type="text" class="form-control" id="adrName1" name="name1"
                                   value="<?= $wert('name1') ?>"<?= $attr('name1') ?> autocomplete="given-name">
                        </div>
                        <div class="col-12 col-sm-4">
                            <?= $label('name2', 'adrName2') ?>
                            <input type="text" class="form-control" id="adrName2" name="name2"
                                   value="<?= $wert('name2') ?>"<?= $attr('name2') ?> autocomplete="family-name">
                        </div>
                        <div class="col-12">
                            <?= $label('strasse', 'adrStrasse') ?>
                            <input type="text" class="form-control" id="adrStrasse" name="strasse"
                                   value="<?= $wert('strasse') ?>"<?= $attr('strasse') ?> autocomplete="street-address">
                        </div>
                        <div class="col-12 col-sm-4">
                            <?= $label('plz', 'adrPlz') ?>
                            <input type="text" class="form-control" id="adrPlz" name="plz"
                                   value="<?= $wert('plz') ?>"<?= $attr('plz') ?> autocomplete="postal-code">
                        </div>
                        <div class="col-12 col-sm-8">
                            <?= $label('ort', 'adrOrt') ?>
                            <input type="text" class="form-control" id="adrOrt" name="ort"
                                   value="<?= $wert('ort') ?>"<?= $attr('ort') ?> autocomplete="address-level2">
                        </div>
                    </div>

                    <button type="submit" class="btn fw-semibold w-100 btn-app-primary mt-4">
                        <i class="bi bi-check-lg me-1"></i>Speichern
                    </button>
                </form>

                <?php endif; ?>

                <p class="text-center text-muted mt-4 mb-0">
                    <a href="<?= APP_BASE . $start ?>" style="color:var(--primary-color-dark);">
                        <i class="bi bi-arrow-left me-1"></i>Zur&uuml;ck zur Startseite
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>
