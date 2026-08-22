<?php
// standard/Views/registrierung/index.php
// Registrierungsformular fuer BEIDE Portale -- legt per
// /registrierung/insertregistrierunglocal eine Adresse (ADRESSEN) und die
// Registrierung (REGISTRIERUNG) an.
// Reiner Content-HTML -- kein DOCTYPE, kein Layout-Include.
//
// Gesteuert wird die Variante ueber die Variablen aus dem Controller:
//   $formular_action      Ziel des Formulars (portalabhaengig)
//   $mitarbeiter_pruefung true -> Block mit USERS-Zugangsdaten (Nachweis).
//                         Das dort eingegebene Mitarbeiterpasswort ist
//                         gleichzeitig das Portalpasswort -- kein eigenes
//                         Passwortfeld, keine Wiederholung.
//   $adressdaten          true -> Adressfelder und Kundennummer abfragen.
//                         Nur bei typ=kunde; bei jedem anderen typ legt der
//                         Endpunkt keine Adresse an und ignoriert die Felder.
//
// name1 = Vorname, name2 = Nachname bzw. Firma -- bei typ=kunde Pflicht, der
// Endpunkt lehnt leere Werte ab.

$eingaben        = $eingaben        ?? [];
$anreden         = $anreden         ?? ['Frau', 'Herr', 'Firma', 'Familie'];
$maxLaenge       = $max_laenge      ?? [];
$maxLoginname    = $max_loginname   ?? 20;
$minPwd          = $min_pwd         ?? 6;
$titelText       = $titel           ?? 'Registrieren';
$untertitelText  = $untertitel      ?? 'Legen Sie ein neues Konto an.';
$aktion          = $formular_action ?? '/registrieren/absenden';
$zurueckLink     = $zurueck_link    ?? '/';
$zurueckText     = $zurueck_text    ?? 'Zurück zur Startseite';
$userspruefung   = !empty($mitarbeiter_pruefung);
$mitAdresse      = $adressdaten     ?? true;
$mitEmail        = $email_feld      ?? true;

/** Gibt einen Eingabewert HTML-sicher zurueck. */
$wert = static function (string $feld) use ($eingaben): string {
    return htmlspecialchars((string)($eingaben[$feld] ?? ''), ENT_QUOTES);
};

/** Gibt das maxlength-Attribut zurueck, falls eine Grenze bekannt ist. */
$maxAttr = static function (string $feld) use ($maxLaenge): string {
    return isset($maxLaenge[$feld]) ? ' maxlength="' . (int)$maxLaenge[$feld] . '"' : '';
};
?>
<div class="px-3 py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-10 col-lg-7">

                <div class="text-center mb-4">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
                          style="width:72px;height:72px;background:var(--primary-color-light);">
                        <i class="bi <?= $userspruefung ? 'bi-person-badge-fill' : 'bi-person-plus-fill' ?>"
                           style="font-size:2rem;color:var(--text-color);"></i>
                    </span>
                    <h1 class="h3 fw-bold mb-1" style="color:var(--text-color);"><?= htmlspecialchars($titelText) ?></h1>
                    <p class="text-muted mb-0"><?= htmlspecialchars($untertitelText) ?></p>
                </div>

                <form method="POST" action="<?= APP_BASE . $aktion ?>" id="regForm"
                      class="p-4 rounded" style="background:var(--surface-muted);border:1px solid var(--border-color);">

                    <!-- Honeypot -- fuer echte Nutzer unsichtbar, nur einfache Formular-Bots fuellen es aus -->
                    <div style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true">
                        <label for="regHomepage">Homepage</label>
                        <input type="text" id="regHomepage" name="homepage" tabindex="-1" autocomplete="off">
                    </div>

                    <?php if ($userspruefung): ?>
                    <!-- ---------------------------------------------------- -->
                    <!-- Identitaetsnachweis -- Zugangsdaten aus USERS         -->
                    <!-- ---------------------------------------------------- -->
                    <h2 class="h6 fw-bold mb-3" style="color:var(--primary-color-dark);">
                        <i class="bi bi-shield-check me-1"></i>Ihre Mitarbeiter-Zugangsdaten
                    </h2>
                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <label for="regLoginname" class="form-label">
                                Loginname <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="regLoginname" name="loginname"
                                   value="<?= $wert('loginname') ?>" maxlength="<?= (int)$maxLoginname ?>"
                                   autocomplete="off" required autofocus>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label for="regLoginPassword" class="form-label">
                                Passwort <span class="text-danger">*</span>
                            </label>
                            <input type="password" class="form-control" id="regLoginPassword" name="login_password"
                                   autocomplete="off">
                        </div>
                        <div class="col-12">
                            <div class="form-text">
                                Nur wer im System bereits als Mitarbeiter angelegt ist, kann sich hier
                                registrieren. Mit genau diesen Zugangsdaten melden Sie sich anschlie&szlig;end
                                am Portal an &ndash; diese Eingabe meldet Sie aber noch nicht an.
                            </div>
                        </div>
                    </div>
                    <?php if ($mitAdresse || $mitEmail): ?>
                    <hr class="my-4" style="border-color:var(--border-color);">
                    <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($mitAdresse): ?>
                    <!-- ---------------------------------------------------- -->
                    <!-- Bestandskunde -- optionale Kundennummer (kennziffer)  -->
                    <!-- ---------------------------------------------------- -->
                    <div class="mb-4">
                        <label for="regKennziffer" class="form-label">
                            Wenn Sie schon Kunde sind: Ihre Kundennummer
                        </label>
                        <input type="text" class="form-control" id="regKennziffer" name="kennziffer"
                               value="<?= $wert('kennziffer') ?>" inputmode="numeric" pattern="[0-9]*"
                               maxlength="10" placeholder="optional">
                        <div class="form-text">
                            Damit ordnen wir Ihre Anmeldung Ihren bestehenden Kundendaten zu.
                            Vor- und Nachname m&uuml;ssen dazu mit Ihren hinterlegten Daten
                            &uuml;bereinstimmen -- sonst brechen wir die Registrierung mit einer
                            Fehlermeldung ab. Feld einfach leer lassen, wenn Sie neu bei uns sind.
                        </div>
                    </div>

                    <!-- ---------------------------------------------------- -->
                    <!-- Persoenliche Daten                                   -->
                    <!-- ---------------------------------------------------- -->
                    <h2 class="h6 fw-bold mb-3" style="color:var(--primary-color-dark);">
                        <i class="bi bi-person-vcard me-1"></i>Ihre Daten
                    </h2>

                    <div class="row g-3">
                        <div class="col-12 col-sm-4">
                            <label for="regAnrede" class="form-label">Anrede <span class="text-danger">*</span></label>
                            <select class="form-select" id="regAnrede" name="anrede" required>
                                <option value="">Bitte w&auml;hlen</option>
                                <?php foreach ($anreden as $anrede): ?>
                                <option value="<?= htmlspecialchars($anrede, ENT_QUOTES) ?>"
                                    <?= ($eingaben['anrede'] ?? '') === $anrede ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($anrede) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-sm-4">
                            <label for="regName1" class="form-label">Vorname <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="regName1" name="name1"
                                   value="<?= $wert('name1') ?>"<?= $maxAttr('name1') ?> required
                                   <?= $userspruefung ? '' : 'autofocus' ?>>
                        </div>
                        <div class="col-12 col-sm-4">
                            <label for="regName2" class="form-label">
                                Nachname / Firma <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="regName2" name="name2"
                                   value="<?= $wert('name2') ?>"<?= $maxAttr('name2') ?> required>
                        </div>

                        <div class="col-12">
                            <label for="regStrasse" class="form-label">Stra&szlig;e und Hausnummer</label>
                            <input type="text" class="form-control" id="regStrasse" name="strasse"
                                   value="<?= $wert('strasse') ?>"<?= $maxAttr('strasse') ?>>
                        </div>
                        <div class="col-12 col-sm-4">
                            <label for="regPlz" class="form-label">PLZ</label>
                            <input type="text" class="form-control" id="regPlz" name="plz"
                                   value="<?= $wert('plz') ?>"<?= $maxAttr('plz') ?>>
                        </div>
                        <div class="col-12 col-sm-8">
                            <label for="regOrt" class="form-label">Ort</label>
                            <input type="text" class="form-control" id="regOrt" name="ort"
                                   value="<?= $wert('ort') ?>"<?= $maxAttr('ort') ?>>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label for="regTelefon" class="form-label">Telefon</label>
                            <input type="tel" class="form-control" id="regTelefon" name="telefon1"
                                   value="<?= $wert('telefon1') ?>"<?= $maxAttr('telefon1') ?>>
                        </div>
                    </div>

                    <hr class="my-4" style="border-color:var(--border-color);">
                    <?php endif; ?>

                    <?php if ($mitEmail): ?>
                    <!-- ---------------------------------------------------- -->
                    <!-- Zugangsdaten des neuen Portalkontos                  -->
                    <!-- ---------------------------------------------------- -->
                    <h2 class="h6 fw-bold mb-3" style="color:var(--primary-color-dark);">
                        <i class="bi bi-key me-1"></i><?= $mitAdresse ? 'Zugangsdaten' : 'Portalzugang' ?>
                    </h2>

                    <div class="row g-3">
                        <div class="col-12">
                            <label for="regUsername" class="form-label">
                                E-Mail-Adresse <span class="text-danger">*</span>
                            </label>
                            <input type="email" class="form-control" id="regUsername" name="username"
                                   value="<?= $wert('username') ?>"<?= $maxAttr('username') ?>
                                   autocomplete="username" required>
                            <div class="form-text">
                                <?php if ($mitAdresse): ?>
                                    Ihre E-Mail-Adresse ist gleichzeitig Ihr Benutzername.
                                <?php else: ?>
                                    Damit melden Sie sich k&uuml;nftig am Portal an &ndash; zusammen mit
                                    Ihrem oben eingegebenen Mitarbeiter-Passwort.
                                <?php endif; ?>
                            </div>
                            <!-- Ergebnis der Verfuegbarkeitspruefung (per fetch gefuellt) -->
                            <div id="regUsernameHinweis" class="small mt-1" role="status" aria-live="polite"></div>
                        </div>
                        <?php if ($mitAdresse): ?>
                        <div class="col-12 col-sm-6">
                            <label for="regPassword" class="form-label">Passwort <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="regPassword" name="password"
                                   minlength="<?= (int)$minPwd ?>" autocomplete="new-password" required>
                            <div class="form-text">Mindestens <?= (int)$minPwd ?> Zeichen.</div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label for="regPasswordWdh" class="form-label">
                                Passwort wiederholen <span class="text-danger">*</span>
                            </label>
                            <input type="password" class="form-control" id="regPasswordWdh" name="password_wdh"
                                   minlength="<?= (int)$minPwd ?>" autocomplete="new-password" required>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <button type="submit" class="btn fw-semibold w-100 btn-app-primary mt-4">
                        <i class="bi bi-person-plus me-1"></i><?= htmlspecialchars($titelText) ?>
                    </button>
                </form>

                <p class="text-center text-muted mt-4 mb-0">
                    <a href="<?= APP_BASE . $zurueckLink ?>" style="color:var(--primary-color-dark);">
                        <i class="bi bi-arrow-left me-1"></i><?= htmlspecialchars($zurueckText) ?>
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php if ($mitEmail): ?>
<script>
// Verfuegbarkeitspruefung der E-Mail-Adresse ueber
// /registrieren/username-pruefen (bedient /registrierung/checkusernamelocal).
// Dieselbe Route fuer beide Portale. Rein informativ -- die verbindliche
// Pruefung passiert serverseitig beim Absenden. Keine JS-Standard-Dialoge,
// nur Inline-Hinweis am Feld.
(function () {
    var feld    = document.getElementById('regUsername');
    var hinweis = document.getElementById('regUsernameHinweis');
    if (!feld || !hinweis) { return; }

    var timer   = null;
    var geprueft = '';

    function zeige(text, klasse) {
        hinweis.textContent = text;
        hinweis.className   = 'small mt-1 ' + klasse;
    }

    function pruefen() {
        var username = feld.value.trim();

        if (username === '' || !feld.checkValidity()) {
            zeige('', '');
            feld.classList.remove('is-valid', 'is-invalid');
            geprueft = '';
            return;
        }
        if (username === geprueft) { return; }
        geprueft = username;

        zeige('Prüfe Verfügbarkeit …', 'text-muted');

        var daten = new URLSearchParams();
        daten.append('username', username);

        fetch('<?= APP_BASE ?>/registrieren/username-pruefen', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: daten.toString()
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            // Antwort verwerfen, wenn der Nutzer inzwischen weitergetippt hat
            if (feld.value.trim() !== username) { return; }

            if (d.frei === true) {
                feld.classList.remove('is-invalid');
                feld.classList.add('is-valid');
                zeige(d.message || '', 'text-success');
            } else if (d.frei === false) {
                feld.classList.remove('is-valid');
                feld.classList.add('is-invalid');
                zeige(d.message || '', 'text-danger');
            } else {
                feld.classList.remove('is-valid', 'is-invalid');
                zeige(d.message || '', 'text-muted');
            }
        })
        .catch(function () {
            // Netzwerkfehler -- keine Aussage treffen, Formular bleibt nutzbar
            feld.classList.remove('is-valid', 'is-invalid');
            zeige('', '');
            geprueft = '';
        });
    }

    feld.addEventListener('input', function () {
        window.clearTimeout(timer);
        timer = window.setTimeout(pruefen, 600);
    });
    feld.addEventListener('blur', function () {
        window.clearTimeout(timer);
        pruefen();
    });
})();
</script>
<?php endif; ?>
