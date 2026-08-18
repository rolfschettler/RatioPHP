<?php
/**
 * Pagination-Komponente
 *
 * Einbindung im View:
 *
 *   <?php
 *   $base_url = '/adressen';
 *   include VIEW_PATH . '/components/pagination.php';
 *   ?>
 *
 * Erwartet folgende Variablen aus dem Controller:
 *   $page          -- aktuelle Seite (0-basiert)
 *   $seiten_gesamt -- Gesamtanzahl Seiten
 *   $base_url      -- Basis-URL der Liste ohne GET-Parameter (z.B. '/adressen')
 *
 * Wird 1:1 nach views/components/pagination.php kopiert.
 */

// Nicht anzeigen wenn nur eine Seite
if (!isset($seiten_gesamt) || $seiten_gesamt <= 1) return;

// Maximale Seitenzahlen die angezeigt werden (links und rechts der aktuellen)
$window = 2;
$from   = max(0, $page - $window);
$to     = min($seiten_gesamt - 1, $page + $window);
?>

<nav aria-label="Seitennavigation" class="mt-4">
    <ul class="pagination justify-content-center flex-wrap">

        <?php // Erste Seite ?>
        <li class="page-item <?= $page <= 0 ? 'disabled' : '' ?>">
            <a class="page-link"
               href="<?= APP_BASE . $base_url ?>?page=0"
               title="Erste Seite">
                &laquo;&laquo;
            </a>
        </li>

        <?php // Vorherige Seite ?>
        <li class="page-item <?= $page <= 0 ? 'disabled' : '' ?>">
            <a class="page-link"
               href="<?= APP_BASE . $base_url ?>?page=<?= max(0, $page - 1) ?>"
               title="Vorherige Seite">
                &laquo;
            </a>
        </li>

        <?php // Auslassung am Anfang ?>
        <?php if ($from > 0): ?>
            <li class="page-item disabled">
                <span class="page-link">…</span>
            </li>
        <?php endif; ?>

        <?php // Seitenzahlen im Fenster ?>
        <?php for ($i = $from; $i <= $to; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link"
                   href="<?= APP_BASE . $base_url ?>?page=<?= $i ?>">
                    <?= $i + 1 ?>
                </a>
            </li>
        <?php endfor; ?>

        <?php // Auslassung am Ende ?>
        <?php if ($to < $seiten_gesamt - 1): ?>
            <li class="page-item disabled">
                <span class="page-link">…</span>
            </li>
        <?php endif; ?>

        <?php // Naechste Seite ?>
        <li class="page-item <?= $page >= $seiten_gesamt - 1 ? 'disabled' : '' ?>">
            <a class="page-link"
               href="<?= APP_BASE . $base_url ?>?page=<?= min($seiten_gesamt - 1, $page + 1) ?>"
               title="Naechste Seite">
                &raquo;
            </a>
        </li>

        <?php // Letzte Seite ?>
        <li class="page-item <?= $page >= $seiten_gesamt - 1 ? 'disabled' : '' ?>">
            <a class="page-link"
               href="<?= APP_BASE . $base_url ?>?page=<?= $seiten_gesamt - 1 ?>"
               title="Letzte Seite">
                &raquo;&raquo;
            </a>
        </li>

    </ul>

    <?php // Anzeige: "Seite X von Y" ?>
    <p class="text-center text-muted small mt-1">
        Seite <?= $page + 1 ?> von <?= $seiten_gesamt ?>
    </p>

</nav>
