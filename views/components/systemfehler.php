<?php
// views/components/systemfehler.php
// Reservierter Bereich fuer SYSTEMFEHLER -- direkt unter dem Header, ausserhalb
// von .app-main. Scrollt also nie weg und wird von keinem Inhalt verdeckt.
//
// Der Tag #app-systemfehler wird IMMER ausgegeben -- ohne Meldung leer und
// hidden. Er ist ausschliesslich fuer Systemfehler reserviert.
// Gemeldet ueber Core\Fehler::system() -- \api_post(), Router und
// Exception-Handler erledigen das selbst. Dargestellt von meldungen.php.

use Core\Meldungen;
use Core\View;
?>
<div id="app-systemfehler" class="flex-shrink-0" style="max-height:30vh;overflow-y:auto;"
     aria-live="assertive" <?= Meldungen::hat(Meldungen::SYSTEM) ? '' : 'hidden' ?>>
    <?= View::komponente('meldungen', [
        'art'         => Meldungen::SYSTEM,
        'klasse'      => 'rounded-0 border-0 border-bottom mb-0 py-2 px-3 small',
        'schliessbar' => true,
    ]) ?>
</div>
