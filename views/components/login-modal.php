<?php
// views/components/login-modal.php
// Login-Formular im gemeinsamen Modal-Geruest (modal.php) -- auf jeder Seite
// verfuegbar. Ersetzt eine eigene Login-Seite vollstaendig.
//
// - action: POST /login (kein GET /login)
// - Felder: user, password (kein required auf password -- Validierung serverseitig)
// - verstecktes Feld portal: sagt dem AuthController, wohin nach dem Login
//   weitergeleitet wird. Wird dort gegen eine Whitelist geprueft.
// - Oeffnet sich automatisch bei ?login=1 (Portal::login()) und zeigt dann die
//   Benutzerfehler (falsches Passwort) selbst an -- ueber meldungen.php, wie
//   jede andere Meldung auch.
//
// Wird in JEDEM Portal eingebunden. Portalname, Startseite und der
// Registrierungs-Link kommen aus core/Portal.php.

use Core\Meldungen;
use Core\Portal;
use Core\View;

$modalPortal    = Portal::name($portal ?? Portal::DEFAULT);
$registrierLink = Portal::registrierung($modalPortal);
$oeffnen        = !empty($_GET['login']);

ob_start();
?>
<?= $oeffnen ? View::komponente('meldungen', ['art' => Meldungen::BENUTZER]) : '' ?>
<?php // maxlength entspricht den Zielspalten: REGISTRIERUNG.username
      // (ftstring 120) und die Bcrypt-Grenze von 72 Bytes, gegen die
      // password_verify() prueft. Laengere Eingaben koennten ohnehin
      // nie passen. Kein "required" -- validiert wird serverseitig. ?>
<div class="mb-3">
    <label for="loginUser" class="form-label">Benutzername</label>
    <input type="text" class="form-control" id="loginUser" name="user"
           maxlength="120" autofocus>
</div>
<div class="mb-3">
    <label for="loginPassword" class="form-label">Passwort</label>
    <input type="password" class="form-control" id="loginPassword" name="password"
           maxlength="72">
</div>
<!-- Ziel nach dem Login -- im AuthController gegen Whitelist geprueft -->
<input type="hidden" name="portal" value="<?= htmlspecialchars($modalPortal, ENT_QUOTES) ?>">
<?php if ($registrierLink !== null): ?>
<p class="small text-muted mb-0">
    Noch kein Zugang?
    <a href="<?= APP_BASE . $registrierLink ?>" style="color:var(--primary-color-dark);">
        Jetzt registrieren
    </a>
</p>
<?php endif; ?>
<?php
$inhalt = ob_get_clean();

echo View::komponente('modal', [
    'id'          => 'loginModal',
    'titel'       => 'Anmelden',
    'icon'        => 'bi-person-circle',
    'kopfPrimaer' => true,
    'formAction'  => '/login',
    'inhalt'      => $inhalt,
    'fuss'        => '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>'
                   . '<button type="submit" class="btn fw-semibold btn-app-primary">'
                   . '<i class="bi bi-box-arrow-in-right me-1"></i>Anmelden</button>',
    'autoOeffnen' => $oeffnen,
]);
