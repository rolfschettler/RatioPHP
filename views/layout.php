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
 *   $toolbar     string  -- HTML der Toolbar, leer = keine Toolbar (optional)
 *   $pager       string  -- HTML des Pagers, leer = kein Pager (optional)
 *   $page_header string  -- HTML des Seitentitels, leer = kein Page-Header (optional)
 */
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

    <!-- HEADER -->
    <nav class="navbar navbar-expand-lg app-header px-3">
        <a class="navbar-brand" href="<?= APP_BASE ?>/">
            <i class="bi bi-grid-3x3-gap-fill"></i>
            <?= htmlspecialchars(APP_NAME) ?>
        </a>
        <button class="navbar-toggler ms-auto" type="button"
                data-bs-toggle="collapse" data-bs-target="#navMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?= APP_BASE ?>/">
                        <i class="bi bi-house me-1"></i>Start
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <?php if (!empty($_COOKIE['jwt_token'])): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#"
                       role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= htmlspecialchars($_COOKIE['jwt_user'] ?? '') ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="<?= APP_BASE ?>/logout">
                                <i class="bi bi-box-arrow-right me-2"></i>Abmelden
                            </a>
                        </li>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" href="#"
                       data-bs-toggle="modal" data-bs-target="#loginModal">
                        <i class="bi bi-person me-1"></i>Anmelden
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

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
            <span style="color:#aaa;">|</span>
            <span style="color:#888;font-size:.8rem;">
                <i class="bi bi-person me-1"></i>
                <?= htmlspecialchars($_COOKIE['jwt_user'] ?? '') ?>
            </span>
        </div>
    </footer>

</div>

<?php include VIEW_PATH . '/components/login-modal.php'; ?>

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
