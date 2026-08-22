<?php
// views/components/header.php
// Kontextabhaengige Navigation. Zeigt ausschliesslich die Menuepunkte des
// aktiven Portals -- kein Querverweis zwischen Kunden- und Mitarbeiterportal.
//
// Erwartet die Layout-Variable $portal:
//   'kunde'       -- Kundenportal (Default), Registrierung + Login
//   'mitarbeiter' -- Mitarbeiterportal, Login per Modal + geschuetzte Module
//
// Das Mitarbeiterportal ist bewusst nur direkt per URL erreichbar.
// BEIDE Portale haben einen Login (Modal). Der Benutzerbereich ist deshalb
// nicht mehr an $istMitarbeiter gebunden -- Abmelden fuehrt aber immer zurueck
// in das Portal, in dem man sich befindet (?portal=..).

$portal = $portal ?? 'kunde';
$istMitarbeiter = ($portal === 'mitarbeiter');
$portalStart    = $istMitarbeiter ? '/mitarbeiter' : '/';
$portalLabel    = $istMitarbeiter ? 'Mitarbeiterportal' : 'Kundenportal';
$logoutLink     = '/logout?portal=' . ($istMitarbeiter ? 'mitarbeiter' : 'kunde');
?>
<nav class="navbar navbar-expand-lg app-header px-3">
    <a class="navbar-brand" href="<?= APP_BASE . $portalStart ?>">
        <i class="bi bi-grid-3x3-gap-fill"></i>
        <?= htmlspecialchars(APP_NAME) ?>
    </a>
    <span class="navbar-text d-none d-lg-inline me-3"
          style="color:var(--on-primary);opacity:.75;font-size:.85rem;">
        <?= htmlspecialchars($portalLabel) ?>
    </span>
    <button class="navbar-toggler ms-auto" type="button"
            data-bs-toggle="collapse" data-bs-target="#navMain">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
        <!-- Keine Feature-Links im Header -- Module werden ausschliesslich ueber
             die Kacheln der Portal-Startseite erreicht. Nur Start bleibt. -->
        <ul class="navbar-nav me-auto">
            <li class="nav-item">
                <a class="nav-link" href="<?= APP_BASE . $portalStart ?>">
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
                        <a class="dropdown-item" href="<?= APP_BASE . $logoutLink ?>">
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
