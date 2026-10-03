<?php
// views/components/flash.php
// Erfolgsmeldungen oben im Inhaltsbereich. Dargestellt von meldungen.php.
//
// Fehler laufen NICHT hierueber:
//   Benutzerfehler -> Dialog                views/components/fehler-dialog.php
//   Systemfehler   -> reservierter Bereich  views/components/systemfehler.php

use Core\Meldungen;
use Core\View;

echo View::komponente('meldungen', [
    'art'         => Meldungen::ERFOLG,
    'klasse'      => 'm-3',
    'schliessbar' => true,
]);
