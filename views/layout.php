<?php
/**
 * layout.php -- Haupt-Layout-Template
 *
 * Wird von BaseController::render() eingebunden.
 * Views enthalten NUR reinen Content-HTML -- kein DOCTYPE, kein html/head/body.
 *
 * Variablen:
 *   $page_title  string  -- Seitentitel (required)
 *   $content     string  -- Haupt-Inhalt (required)
 *   $portal      string  -- 'kunde' (Default) oder 'mitarbeiter' (optional)
 *   $toolbar     string  -- HTML der Toolbar, leer = keine Toolbar (optional)
 *   $pager       string  -- HTML des Pagers, leer = kein Pager (optional)
 *   $page_header string  -- HTML des Seitentitels, leer = kein Page-Header (optional)
 *
 * Header und Login-Modal sind portalabhaengig -- das Kundenportal hat
 * keinen Login-Bereich.
 */

$portal = $portal ?? 'kunde';
$istMitarbeiter = ($portal === 'mitarbeiter');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= APP_BASE ?>/public/css/app.css">
</head>
<body>
<div class="app-wrapper">

    <!-- HEADER -- portalabhaengige Navigation -->
    <?php include VIEW_PATH . '/components/header.php'; ?>

    <!-- SYSTEMFEHLER -- reservierter Bereich, immer sichtbar, scrollt nie weg -->
    <?php include VIEW_PATH . '/components/systemfehler.php'; ?>

    <!-- ALLES OBERHALB DER TABELLE IN EINEM BLOCK -->
    <!-- Kein Border zwischen diesen Elementen -- verhindert Scroll-Luecke -->
    <div class="app-above-table">

        <?php if (!empty($toolbar)): ?>
        <!-- Toolbar Toggle (nur schmale Devices) -->
        <button class="toolbar-toggle" id="toolbarToggle" onclick="toggleToolbar()">
            <i class="bi bi-funnel me-1"></i>Filter &amp; Aktionen
            <i class="bi bi-chevron-down ms-1" id="toolbarChevron"></i>
        </button>
        <!-- Toolbar -->
        <div class="app-toolbar" id="appToolbar">
            <?= $toolbar ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($page_header)): ?>
        <!-- Seitentitel + Badge -->
        <div class="app-page-header">
            <?= $page_header ?>
        </div>
        <?php endif; ?>

    </div>

    <!-- MAIN -- NUR Tabelle oder Content, padding: 0 -->
    <main class="app-main" id="mainContent">
        <?php include VIEW_PATH . '/components/flash.php'; ?>
        <?= $content ?? '' ?>
        <?php include VIEW_PATH . '/components/debug.php'; ?>
    </main>

    <?php if (!empty($pager)): ?>
    <!-- PAGER -->
    <div class="app-pager">
        <?= $pager ?>
    </div>
    <?php endif; ?>

    <!-- FOOTER -->
    <footer class="app-footer">
        <span>&copy; <?= date('Y') ?> <?= htmlspecialchars(APP_NAME) ?></span>
        <div class="d-flex gap-3 align-items-center">
            <a href="#"
               onclick="document.getElementById('mainContent').scrollTop=0;return false;">
                <i class="bi bi-arrow-up-circle me-1"></i>Nach oben
            </a>
            <?php // Benutzername im Footer -- in beiden Portalen, sobald angemeldet ?>
            <?php if (!empty($_COOKIE['jwt_user'])): ?>
            <span style="color:#aaa;">|</span>
            <span style="color:#888;font-size:.8rem;">
                <i class="bi bi-person me-1"></i>
                <?= htmlspecialchars($_COOKIE['jwt_user']) ?>
            </span>
            <?php endif; ?>
        </div>
    </footer>

</div>

<?php // Login-Modal in BEIDEN Portalen -- das Modal wertet $portal selbst aus
      // und setzt daraus das Weiterleitungsziel sowie den Registrierungs-Link. ?>
<?php include VIEW_PATH . '/components/login-modal.php'; ?>

<?php // Benutzerfehler als Dialog -- Systemfehler stehen oben unter dem Header ?>
<?php include VIEW_PATH . '/components/fehler-dialog.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleToolbar() {
    var tb = document.getElementById('appToolbar');
    var ch = document.getElementById('toolbarChevron');
    var open = tb.classList.toggle('open');
    ch.className = open ? 'bi bi-chevron-up ms-1' : 'bi bi-chevron-down ms-1';
}
</script>
</body>
</html>
