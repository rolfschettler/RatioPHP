<?php
// views/components/tag-auswahl.php
// Mehrfachauswahl mit Autocomplete: gewaehlte Werte erscheinen als Badges
// (je ein verstecktes Feld $name), Eingabe filtert eine gruppierte
// Vorschlagsliste. Freie Eingaben sind moeglich -- die Liste ist eine Hilfe,
// keine Whitelist; verbindlich prueft der Controller.
//
// Aufruf:  View::komponente('tag-auswahl', [...])
//
// Variablen:
//   $id           string  DOM-id des Bausteins (Pflicht)
//   $name         string  Feldname der versteckten Felder, z.B. 'rollen[]' (Pflicht)
//   $label        string  Beschriftung, Klartext (Pflicht)
//   $vorschlaege  array   [['wert' => .., 'gruppe' => ..], ...] -- in Anzeigereihenfolge
//   $maxlength    int     Hoechstlaenge eines Wertes
//   $muster       string  JS-RegExp-Quelltext fuer freie Eingaben (optional)
//   $platzhalter  string  Platzhalter im Eingabefeld (optional)
//   $hinweis      string  Hilfetext unter dem Feld, Klartext (optional)
//
// Werte von aussen setzen (z.B. beim Oeffnen eines Dialogs):
//   document.getElementById(id).setzeWerte(['wert1', 'wert2']);
//
// Bedienung: Pfeiltasten waehlen, Enter/Komma uebernimmt, Esc schliesst,
// Ruecktaste im leeren Feld entfernt den letzten Wert.
//
// Liegt der Baustein in einem deaktivierten <fieldset>, werden Eingabe,
// Entfernen-Knoepfe und die versteckten Felder automatisch mit deaktiviert.

$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES);

$eingabeId = $id . 'Eingabe';
$listeId   = $id . 'Liste';
?>
<div id="<?= $h($id) ?>" data-name="<?= $h($name) ?>" data-muster="<?= $h($muster ?? '') ?>">
    <label class="form-label" for="<?= $h($eingabeId) ?>"><?= $h($label) ?></label>
    <div class="form-control d-flex flex-wrap align-items-center gap-1 position-relative" data-teil="feld">
        <input type="text" id="<?= $h($eingabeId) ?>" class="border-0 flex-grow-1 p-0 bg-transparent"
               style="outline:none;min-width:12rem;" autocomplete="off"
               role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="<?= $h($listeId) ?>"
               maxlength="<?= (int)($maxlength ?? 100) ?>"
               placeholder="<?= $h($platzhalter ?? '') ?>" data-teil="eingabe">
        <ul class="dropdown-menu w-100 shadow-sm" id="<?= $h($listeId) ?>" role="listbox"
            style="top:100%;left:0;max-height:260px;overflow-y:auto;" data-teil="liste"></ul>
    </div>
    <div class="invalid-feedback" data-teil="fehler">Ungültiger Wert &ndash; erlaubt sind Buchstaben, Ziffern und _ . / * -, optional mit @ am Anfang.</div>
    <?php if (!empty($hinweis)): ?>
    <div class="form-text"><?= $h($hinweis) ?></div>
    <?php endif; ?>
    <script type="application/json" data-teil="vorschlaege"><?= json_encode(array_values($vorschlaege ?? []), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
</div>
<script>
(function () {
    var wurzel  = document.getElementById(<?= json_encode($id) ?>);
    var feld    = wurzel.querySelector('[data-teil=feld]');
    var eingabe = wurzel.querySelector('[data-teil=eingabe]');
    var liste   = wurzel.querySelector('[data-teil=liste]');
    var fehler  = wurzel.querySelector('[data-teil=fehler]');
    var name    = wurzel.dataset.name;
    var muster  = wurzel.dataset.muster ? new RegExp(wurzel.dataset.muster) : null;
    var alle    = JSON.parse(wurzel.querySelector('[data-teil=vorschlaege]').textContent);
    var MAX_TREFFER = 50;
    var aktiv = -1;

    function gewaehlt() {
        return Array.prototype.map.call(
            feld.querySelectorAll('input[type=hidden]'),
            function (el) { return el.value.toLowerCase(); }
        );
    }

    function zeigeFehler(an) {
        feld.classList.toggle('is-invalid', an);
        fehler.classList.toggle('d-block', an);
    }

    function hinzufuegen(wert) {
        wert = wert.trim();
        if (wert === '') {
            return true;
        }
        if (muster && !muster.test(wert)) {
            zeigeFehler(true);
            return false;
        }
        zeigeFehler(false);
        if (gewaehlt().indexOf(wert.toLowerCase()) !== -1) {
            return true;
        }

        var badge = document.createElement('span');
        badge.className = 'badge rounded-pill d-inline-flex align-items-center';
        badge.style.background = 'var(--primary-color-light)';
        badge.style.color = 'var(--text-color)';
        badge.appendChild(document.createTextNode(wert));

        var entfernen = document.createElement('button');
        entfernen.type = 'button';
        entfernen.className = 'btn-close ms-1';
        entfernen.style.fontSize = '.5rem';
        entfernen.setAttribute('aria-label', wert + ' entfernen');
        entfernen.addEventListener('click', function () {
            badge.remove();
            eingabe.focus();
        });
        badge.appendChild(entfernen);

        var versteckt = document.createElement('input');
        versteckt.type = 'hidden';
        versteckt.name = name;
        versteckt.value = wert;
        badge.appendChild(versteckt);

        feld.insertBefore(badge, eingabe);
        return true;
    }

    function schliessen() {
        liste.classList.remove('show');
        eingabe.setAttribute('aria-expanded', 'false');
        aktiv = -1;
    }

    function eintraege() {
        return liste.querySelectorAll('[data-wert]');
    }

    function markiere(index) {
        var e = eintraege();
        if (e.length === 0) {
            aktiv = -1;
            return;
        }
        aktiv = (index + e.length) % e.length;
        e.forEach(function (el, i) { el.classList.toggle('active', i === aktiv); });
        e[aktiv].scrollIntoView({ block: 'nearest' });
    }

    function eintrag(wert, text) {
        var li = document.createElement('li');
        var knopf = document.createElement('button');
        knopf.type = 'button';
        knopf.className = 'dropdown-item';
        knopf.setAttribute('role', 'option');
        knopf.dataset.wert = wert;
        knopf.textContent = text;
        // mousedown statt click: sonst verliert das Eingabefeld vorher den Fokus
        knopf.addEventListener('mousedown', function (ev) {
            ev.preventDefault();
            uebernehmen(wert);
        });
        li.appendChild(knopf);
        return li;
    }

    function oeffnen() {
        var text  = eingabe.value.trim();
        var suche = text.toLowerCase();
        var schon = gewaehlt();
        var exakt = false;
        var gruppe = null;
        var anzahl = 0;

        liste.innerHTML = '';

        alle.forEach(function (v) {
            var klein = v.wert.toLowerCase();
            if (klein === suche) {
                exakt = true;
            }
            if (anzahl >= MAX_TREFFER || schon.indexOf(klein) !== -1
                || (suche !== '' && klein.indexOf(suche) === -1)) {
                return;
            }
            if (v.gruppe !== gruppe) {
                gruppe = v.gruppe;
                var kopf = document.createElement('li');
                kopf.innerHTML = '<h6 class="dropdown-header"></h6>';
                kopf.firstChild.textContent = gruppe;
                liste.appendChild(kopf);
            }
            liste.appendChild(eintrag(v.wert, v.wert));
            anzahl++;
        });

        // Freie Eingabe als ersten Eintrag anbieten -- nur, wenn sie gueltig
        // ist; sonst gleich den Hinweis zeigen statt eines toten Eintrags
        var gueltig = !muster || muster.test(text);
        zeigeFehler(text !== '' && !gueltig);
        if (text !== '' && gueltig && !exakt && schon.indexOf(suche) === -1) {
            var frei = eintrag(text, '„' + text + '“ übernehmen');
            liste.insertBefore(frei, liste.firstChild);
        }

        if (liste.children.length === 0) {
            schliessen();
            return;
        }
        liste.classList.add('show');
        eingabe.setAttribute('aria-expanded', 'true');
        // Mit Suchtext ist der erste Treffer vorgewaehlt, sonst nichts
        if (suche !== '') {
            markiere(0);
        } else {
            aktiv = -1;
        }
    }

    function uebernehmen(wert) {
        if (hinzufuegen(wert)) {
            eingabe.value = '';
        }
        oeffnen();
    }

    eingabe.addEventListener('input', oeffnen);
    eingabe.addEventListener('focus', oeffnen);
    eingabe.addEventListener('blur', function () {
        schliessen();
    });
    eingabe.addEventListener('keydown', function (ev) {
        var e = eintraege();
        if (ev.key === 'ArrowDown') {
            ev.preventDefault();
            if (!liste.classList.contains('show')) {
                oeffnen();
            }
            markiere(aktiv + 1);
        } else if (ev.key === 'ArrowUp') {
            ev.preventDefault();
            markiere(aktiv - 1);
        } else if (ev.key === 'Enter' || ev.key === ',' || ev.key === ';') {
            // Enter darf das Formular nicht absenden, solange etwas gewaehlt wird
            if (ev.key !== 'Enter' || eingabe.value.trim() !== '' || aktiv >= 0) {
                ev.preventDefault();
                uebernehmen(aktiv >= 0 && e[aktiv] ? e[aktiv].dataset.wert : eingabe.value);
            }
        } else if (ev.key === 'Escape') {
            if (liste.classList.contains('show')) {
                // Nur die Liste schliessen, nicht den umgebenden Dialog
                ev.stopPropagation();
                schliessen();
            }
        } else if (ev.key === 'Backspace' && eingabe.value === '') {
            var badges = feld.querySelectorAll('.badge');
            if (badges.length > 0) {
                badges[badges.length - 1].remove();
            }
        }
    });

    // Klick in den Rahmen fokussiert das Eingabefeld
    feld.addEventListener('click', function (ev) {
        if (ev.target === feld) {
            eingabe.focus();
        }
    });

    wurzel.setzeWerte = function (werte) {
        feld.querySelectorAll('.badge').forEach(function (el) { el.remove(); });
        eingabe.value = '';
        zeigeFehler(false);
        schliessen();
        (werte || []).forEach(hinzufuegen);
    };
})();
</script>
