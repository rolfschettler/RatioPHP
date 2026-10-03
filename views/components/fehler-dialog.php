<?php
// views/components/fehler-dialog.php
// Dialog fuer BENUTZERFEHLER -- alles, was der Benutzer selbst beheben kann.
// Gemeldet per $this->flashError() bzw. Core\Pruefung::melde().
//
// Regel: ein Modal, das beim Laden der Seite ohnehin aufgeht (Login-Modal bei
// ?login=1), zeigt die Benutzerfehler selbst an und holt sie damit ab. Dieser
// Dialog erscheint nur fuer das, was dann noch uebrig ist -- deshalb wird er
// im Layout NACH den anderen Modals eingebunden.

use Core\Meldungen;
use Core\View;

if (!Meldungen::hat(Meldungen::BENUTZER)) {
    return;
}

echo View::komponente('modal', [
    'id'          => 'fehlerDialog',
    'titel'       => 'Bitte prüfen Sie Ihre Eingabe',
    'icon'        => Meldungen::ARTEN[Meldungen::BENUTZER]['icon'],
    'iconKlasse'  => 'text-warning',
    'inhalt'      => View::komponente('meldungen', ['art' => Meldungen::BENUTZER, 'klasse' => 'mb-0']),
    'fuss'        => '<button type="button" class="btn fw-semibold btn-app-primary" data-bs-dismiss="modal">OK</button>',
    'autoOeffnen' => true,
]);
