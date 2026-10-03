<?php
// standard/Views/registrierungsverwaltung/index.php
// Verwaltung der Portalzugaenge (REGISTRIERUNG) -- Tabelle plus zwei Dialoge.
// Reiner Content-HTML, kein Layout. .app-main scrollt bereits horizontal.
//
// Bearbeiten und Loeschen nutzen je EIN Modal fuer alle Zeilen: der Button
// traegt die Werte als data-Attribute, das Skript unten fuellt das Modal beim
// Oeffnen (show.bs.modal, relatedTarget = ausloesender Button).

use Core\Pruefung;
use Core\View;

/** @var array  $rows           Gefilterte Registrierungen */
/** @var string $fSuche         Suchbegriff (fuers Highlight) */
/** @var array  $typen          Portalname => Anzeige */
/** @var array  $gesperrtWerte  'NEIN'|'JA' => Anzeige */
/** @var array  $rollenVorschlaege Autocomplete-Liste der Rollen [wert, gruppe] */
/** @var int    $rolleMax       Hoechstlaenge eines Rollennamens */
/** @var string $rolleMuster    JS-RegExp fuer freie Rolleneingaben */
/** @var array  $felder         Feld-Definitionen des Bearbeiten-Dialogs */
/** @var string $modul_url      Modulpfad ohne APP_BASE */
/** @var string $eigener        Benutzername des Angemeldeten, gross */
/** @var int    $breiteMax      Maximale Seitenbreite in Pixel (zentriert) */
/** @var string $fSort          Sortierte Spalte ('username'|'typ'|'letzter_login') */
/** @var string $fRichtung      Sortierrichtung ('auf'|'ab') */
/** @var array  $sortLinks      Spalte => [href, icon, aktiv] fuer die Spaltenkoepfe */

$h = static fn($v): string => htmlspecialchars((string)($v ?? ''), ENT_QUOTES);

// Datetime-Wert ("Y-m-d H:i:s") fuer die Anzeige formatieren.
$fmt = static function (?string $val) use ($h): string {
    if (empty($val)) {
        return '&ndash;';
    }
    $ts = strtotime($val);
    return $ts ? date('d.m.y, H:i', $ts) : $h($val);
};

// Suchtreffer mit <mark> umhuellen -- fertiges, escaptes HTML.
$highlight = static function (?string $text) use ($fSuche, $h): string {
    if ((string)$text === '') {
        return '&ndash;';
    }
    $safe = $h($text);
    if ($fSuche === '') {
        return $safe;
    }
    return preg_replace('/(' . preg_quote($h($fSuche), '/') . ')/iu', '<mark>$1</mark>', $safe);
};

// Typ-Anzeige -- ungueltige Typen (NULL, Legacy) rot markieren.
$typAnzeige = static function (string $typ) use ($typen, $h): string {
    if (isset($typen[$typ])) {
        return $h($typen[$typ]);
    }
    return '<span class="text-danger" title="Kein gültiger Typ – kein Portalzugriff">'
         . ($typ !== '' ? $h($typ) : 'ohne') . ' <i class="bi bi-exclamation-triangle"></i></span>';
};

// Aktuelle Filter und Sortierung -- werden von beiden Dialogen mitgeschickt,
// damit die Liste nach dem Speichern/Loeschen unveraendert erscheint.
$filterFelder = '<input type="hidden" name="f_typ" value="' . $h($_GET['typ'] ?? '') . '">'
              . '<input type="hidden" name="f_suche" value="' . $h($fSuche) . '">'
              . '<input type="hidden" name="f_sort" value="' . $h($fSort) . '">'
              . '<input type="hidden" name="f_richtung" value="' . $h($fRichtung) . '">';

// Sortierbarer Spaltenkopf -- Link mit Richtungssymbol, aria-sort fuer Screenreader.
$sortKopf = static function (string $feld, string $text) use ($sortLinks, $fRichtung, $h): string {
    $l    = $sortLinks[$feld];
    $aria = $l['aktiv'] ? ' aria-sort="' . ($fRichtung === 'ab' ? 'descending' : 'ascending') . '"' : '';
    return '<th' . $aria . '><a href="' . $h($l['href']) . '" class="text-reset text-decoration-none text-nowrap"'
         . ' title="Nach ' . $h($text) . ' sortieren">' . $h($text)
         . ' <i class="bi ' . $h($l['icon']) . ($l['aktiv'] ? '' : ' opacity-50') . '"></i></a></th>';
};
?>
<div class="mx-auto w-100" style="max-width:<?= (int)$breiteMax ?>px;max-height:100%;overflow:auto;border:1px solid var(--frame-color);">
<table class="app-table" style="min-width:1100px;">
    <thead>
        <tr>
            <th>Aktionen</th>
            <th>Nr.</th>
            <?= $sortKopf('username', 'Benutzername') ?>
            <th>E-Mail</th>
            <?= $sortKopf('typ', 'Typ') ?>
            <th>Rollen</th>
            <th>Status</th>
            <th>Kennziffer</th>
            <th>Erstellt</th>
            <?= $sortKopf('letzter_login', 'Letzter Login') ?>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
        <tr>
            <td colspan="10" class="text-center text-muted py-4">Keine Registrierungen gefunden.</td>
        </tr>
        <?php else: ?>
            <?php foreach ($rows as $r):
                $typ      = (string)($r['typ'] ?? '');
                $gesperrt = strcasecmp(trim((string)($r['gesperrt'] ?? '')), 'JA') === 0;
                $istEigen = $eigener !== '' && mb_strtoupper((string)($r['username'] ?? '')) === $eigener;
            ?>
            <tr>
                <td class="act">
                    <button type="button" class="btn btn-sm btn-outline-secondary" style="padding:.2rem .4rem;margin-right:.2rem;"
                            title="Bearbeiten"
                            data-bs-toggle="modal" data-bs-target="#regBearbeiten"
                            data-nr="<?= $h($r['nr']) ?>"
                            data-username="<?= $h($r['username']) ?>"
                            data-typ="<?= $h(strip_tags($typAnzeige($typ))) ?>"
                            data-email="<?= $h($r['email']) ?>"
                            data-gesperrt="<?= $gesperrt ? 'JA' : 'NEIN' ?>"
                            data-rollen="<?= $h(json_encode($r['rollen_liste'])) ?>"
                            data-eigen="<?= $istEigen ? '1' : '0' ?>">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" style="padding:.2rem .4rem;"
                            title="<?= $istEigen ? 'Eigener Zugang' : 'Löschen' ?>"
                            data-bs-toggle="modal" data-bs-target="#regLoeschen"
                            data-nr="<?= $h($r['nr']) ?>"
                            data-username="<?= $h($r['username']) ?>"
                            <?= $istEigen ? 'disabled' : '' ?>>
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
                <td class="dim"><?= $h($r['nr']) ?></td>
                <td>
                    <?= $highlight($r['username'] ?? '') ?>
                    <?php if ($istEigen): ?><span class="badge text-bg-secondary ms-1">Sie</span><?php endif; ?>
                </td>
                <td><?= $highlight($r['email'] ?? '') ?></td>
                <td><?= $typAnzeige($typ) ?></td>
                <td>
                    <?php // max-width wirkt an einer Tabellenzelle nicht -- deshalb am inneren div.
                          // Was nicht in 400px passt, endet in "...", der Tooltip zeigt alle Rollen. ?>
                    <div class="text-truncate" style="max-width:400px;"
                         title="<?= $h(implode(', ', $r['rollen_liste'])) ?>">
                    <?php foreach ($r['rollen_liste'] as $rolle): ?>
                        <span class="badge rounded-pill" style="background:var(--primary-color-light);color:var(--text-color);"><?= $h($rolle) ?></span>
                    <?php endforeach; ?>
                    <?php if (empty($r['rollen_liste'])): ?>
                        <span class="text-danger" title="Ohne Rolle hat der Zugang uneingeschränkten Zugriff">
                            keine <i class="bi bi-exclamation-triangle"></i>
                        </span>
                    <?php endif; ?>
                    </div>
                </td>
                <td>
                    <?php if ($gesperrt): ?>
                        <span class="badge text-bg-danger">Gesperrt</span>
                    <?php else: ?>
                        <span class="badge text-bg-success">Aktiv</span>
                    <?php endif; ?>
                </td>
                <td class="dim"><?= $r['kennziffer'] !== null ? $h($r['kennziffer']) : '&ndash;' ?></td>
                <td class="dim"><?= $fmt($r['erstellt'] ?? null) ?></td>
                <td class="dim"><?= $fmt($r['letzter_login'] ?? null) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
</div>

<?php
// ---------------------------------------------------------------------------
// Dialog: Bearbeiten
// ---------------------------------------------------------------------------
$gesperrtOptionen = '';
foreach ($gesperrtWerte as $wert => $label) {
    $gesperrtOptionen .= '<option value="' . $h($wert) . '">' . $h($label) . '</option>';
}

$rollenAuswahl = View::komponente('tag-auswahl', [
    'id'          => 'regRollen',
    'name'        => 'rollen[]',
    'label'       => 'Rollen',
    'vorschlaege' => $rollenVorschlaege,
    'maxlength'   => $rolleMax,
    'muster'      => $rolleMuster,
    'platzhalter' => 'Rolle oder Endpunkt suchen …',
    'hinweis'     => 'Mindestens eine Rolle ist Pflicht – ohne Rolle hätte der Zugang uneingeschränkten Zugriff.',
]);

echo View::komponente('modal', [
    'id'          => 'regBearbeiten',
    'titel'       => 'Registrierung bearbeiten',
    'icon'        => 'bi-pencil-square',
    'kopfPrimaer' => true,
    'formAction'  => $modul_url . '/speichern',
    'inhalt'      => $filterFelder . '
        <input type="hidden" name="nr" data-feld="nr">
        <dl class="row mb-3">
            <dt class="col-4 fw-normal text-muted">Benutzername</dt>
            <dd class="col-8 mb-1 fw-semibold" data-feld="username"></dd>
            <dt class="col-4 fw-normal text-muted">Typ</dt>
            <dd class="col-8 mb-0" data-feld="typ"></dd>
        </dl>
        <div class="mb-3">
            <label class="form-label" for="regEmail">' . $h($felder['email']['bezeichnung']) . '</label>
            <input type="email" class="form-control" id="regEmail" name="email" data-feld="email"'
                . Pruefung::htmlAttribute($felder, 'email') . '>
        </div>
        <fieldset data-feld="geschuetzt">
            <div class="mb-3">
                <label class="form-label" for="regGesperrt">' . $h($felder['gesperrt']['bezeichnung']) . '</label>
                <select class="form-select" id="regGesperrt" name="gesperrt" data-feld="gesperrt"'
                    . Pruefung::htmlAttribute($felder, 'gesperrt') . '>
                    ' . $gesperrtOptionen . '
                </select>
            </div>
            <div class="mb-0">
                ' . $rollenAuswahl . '
            </div>
        </fieldset>
        <p class="small text-muted mt-3 mb-0 d-none" data-feld="eigen-hinweis">
            <i class="bi bi-info-circle me-1"></i>Das ist Ihr eigener Zugang &ndash; Status und Rollen lassen sich hier nicht ändern.
        </p>
        <div class="border-top mt-3 pt-3">
            <button type="button" class="btn btn-link p-0 fw-semibold text-decoration-none" style="color:var(--primary-color-dark);"
                    data-bs-toggle="collapse" data-bs-target="#regPasswortBereich"
                    aria-expanded="false" aria-controls="regPasswortBereich">
                <i class="bi bi-key me-1"></i>Neues Passwort setzen <i class="bi bi-chevron-down small"></i>
            </button>
            <div class="collapse" id="regPasswortBereich">
                <div class="pt-3">
                    <div class="mb-3">
                        <label class="form-label" for="regPasswortNeu">' . $h($felder['passwort_neu']['bezeichnung']) . '</label>
                        <input type="password" class="form-control" id="regPasswortNeu" name="passwort_neu"
                               autocomplete="new-password"' . Pruefung::htmlAttribute($felder, 'passwort_neu') . '>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="regPasswortNeuWdh">' . $h($felder['passwort_neu_wdh']['bezeichnung']) . '</label>
                        <input type="password" class="form-control" id="regPasswortNeuWdh" name="passwort_neu_wdh"
                               autocomplete="new-password"' . Pruefung::htmlAttribute($felder, 'passwort_neu_wdh') . '>
                    </div>
                    <div class="form-text">
                        Leer lassen, um das bisherige Passwort zu behalten. Der Benutzer meldet sich danach
                        mit dem neuen Passwort an.
                    </div>
                </div>
            </div>
        </div>',
    'fuss'        => '
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
        <button type="submit" class="btn fw-semibold btn-app-primary"><i class="bi bi-check-lg me-1"></i>Speichern</button>',
]);

// ---------------------------------------------------------------------------
// Dialog: Loeschen bestaetigen
// ---------------------------------------------------------------------------
echo View::komponente('modal', [
    'id'          => 'regLoeschen',
    'titel'       => 'Registrierung löschen',
    'icon'        => 'bi-trash',
    'iconKlasse'  => 'text-danger',
    'formAction'  => $modul_url . '/loeschen',
    'inhalt'      => $filterFelder . '
        <input type="hidden" name="nr" data-feld="nr">
        <p class="mb-0">Soll der Portalzugang <strong data-feld="username"></strong> wirklich gelöscht werden?
        Die Anmeldung mit diesem Zugang ist danach nicht mehr möglich.</p>',
    'fuss'        => '
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
        <button type="submit" class="btn btn-danger fw-semibold"><i class="bi bi-trash me-1"></i>Löschen</button>',
]);
?>
<script>
// Fuellt Bearbeiten- und Loeschen-Dialog aus den data-Attributen des Buttons.
document.querySelectorAll('#regBearbeiten, #regLoeschen').forEach(function (modal) {
    modal.addEventListener('show.bs.modal', function (event) {
        var d      = event.relatedTarget.dataset;
        var rollen = d.rollen ? JSON.parse(d.rollen) : [];
        var eigen  = d.eigen === '1';

        modal.querySelectorAll('[data-feld]').forEach(function (el) {
            var feld = el.dataset.feld;
            if (feld === 'eigen-hinweis') {
                el.classList.toggle('d-none', !eigen);
            } else if (feld === 'geschuetzt') {
                el.disabled = eigen;
            } else if (el.tagName === 'INPUT' || el.tagName === 'SELECT') {
                el.value = d[feld] || '';
            } else {
                el.textContent = d[feld] || '';
            }
        });

        var rollenAuswahl = modal.querySelector('#regRollen');
        if (rollenAuswahl) {
            rollenAuswahl.setzeWerte(rollen);
        }

        // Passwortbereich bei jedem Oeffnen leer und zugeklappt
        var pwBereich = modal.querySelector('#regPasswortBereich');
        if (pwBereich) {
            pwBereich.querySelectorAll('input').forEach(function (el) {
                el.value = '';
                el.setCustomValidity('');
            });
            bootstrap.Collapse.getOrCreateInstance(pwBereich, { toggle: false }).hide();
        }

        // Ein deaktiviertes fieldset wird nicht mitgesendet -- beim eigenen
        // Zugang die unveraenderten Werte als versteckte Felder nachreichen.
        modal.querySelectorAll('.eigen-kopie').forEach(function (el) { el.remove(); });
        if (eigen) {
            var form = modal.querySelector('form');
            modal.querySelectorAll('fieldset [name]').forEach(function (el) {
                if (el.type === 'checkbox' && !el.checked) {
                    return;
                }
                var kopie = document.createElement('input');
                kopie.type = 'hidden';
                kopie.name = el.name;
                kopie.value = el.value;
                kopie.className = 'eigen-kopie';
                form.appendChild(kopie);
            });
        }
    });
});

// Passwort-Wiederholung schon im Browser abgleichen (verbindlich prueft der
// Controller). Ein ungueltiges Feld im zugeklappten Bereich koennte der
// Browser nicht anzeigen -- deshalb klappt "invalid" den Bereich auf.
(function () {
    var bereich = document.getElementById('regPasswortBereich');
    var pw      = document.getElementById('regPasswortNeu');
    var wdh     = document.getElementById('regPasswortNeuWdh');
    if (!bereich) {
        return;
    }
    function abgleichen() {
        wdh.setCustomValidity(pw.value !== wdh.value ? 'Die Passwörter stimmen nicht überein.' : '');
    }
    pw.addEventListener('input', abgleichen);
    wdh.addEventListener('input', abgleichen);
    [pw, wdh].forEach(function (el) {
        el.addEventListener('invalid', function () {
            bootstrap.Collapse.getOrCreateInstance(bereich, { toggle: false }).show();
        });
    });
})();
</script>
