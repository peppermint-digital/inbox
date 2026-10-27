<?php

namespace Peppermint\Inbox\Mcp\Concerns;

use Peppermint\AiBrainBridge\Facades\AiBrain;
use Peppermint\AiBrainBridge\Verbund\Bruecke;

/**
 * Wer ruft hier eigentlich an?
 *
 * ## Der Weg ueber die Bruecke
 *
 * Das Brain ruft ein Produkt als CLIENT auf, und ein Client hat keine
 * Identitaet. „Bastians Posteingang im Manager" braucht deshalb eine
 * ausdrueckliche Person, und die kommt als E-MAIL-ADRESSE in der Kopfzeile
 * des Aufrufs.
 *
 * Die Adresse ist der richtige Schluessel und nicht die Nummer: IDs sind je
 * Datenbank verschieden, ein Mensch ist in vier Systemen vier Mal angelegt.
 *
 * Ohne Bruecke ist es die angemeldete Person. Beides abzufragen kostet
 * nichts und macht die Werkzeuge auch ohne Hub benutzbar.
 *
 * ## Warum das Paket seinen EIGENEN Zug dafuer hat
 *
 * Weil ein Werkzeug aus einem geteilten Paket `App\Mcp\Concerns\…` nicht
 * benutzen kann — der Importpfad waere in jedem der vier Produkte ein
 * anderer. `peppermint/notifications` bringt aus demselben Grund ein
 * gleichnamiges mit; die Waechter-Tests der Produkte suchen genau diesen
 * Namen im Quelltext, wenn ein schreibendes Werkzeug „nur eigene Daten"
 * anfasst.
 *
 * Und bewusst NICHT aus `peppermint/notifications` geholt: Die
 * Abhaengigkeit zeigt dort in die andere Richtung (Meldungen kennen die
 * Inbox, nicht umgekehrt). Ein Paket, das sein abhaengiges Paket braucht,
 * ist ein Ring.
 */
trait FindetHandelndePerson
{
    /**
     * Die handelnde Person — oder `null`, wenn es keine gibt.
     *
     * `null` ist ein gueltiges Ergebnis und kein Fehler. Was daraus folgt,
     * entscheidet das aufrufende Werkzeug: beim Lesen eine leere Antwort,
     * beim Schreiben eine Absage.
     */
    protected function handelnde(): mixed
    {
        if (Bruecke::eingerichtet()) {
            $adresse = AiBrain::actingUserEmail();

            if (is_string($adresse) && trim($adresse) !== '') {
                $modell = (string) config('inbox.user_model');

                return $modell::query()->where('email', $adresse)->first();
            }
        }

        return auth()->user();
    }
}
