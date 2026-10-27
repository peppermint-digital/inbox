<?php

namespace Peppermint\Inbox\Quellen;

use Illuminate\Database\Eloquent\Model;
use Peppermint\Inbox\Feed\GesamtInbox;

/**
 * Eine Quelle, die ihren BESTAND nennen kann.
 *
 * ## Der Unterschied, der die Seite kaputt aussehen liess
 *
 * Die Liste zeigt die neuesten Zeilen je Art und System (Vorgabe 50), die
 * Zahl in der Kopfzeile nannte den Bestand. Am 26.10.2026 stand dort 3953
 * gegen 50 Zeilen, und nichts erklaerte die Luecke — wer einzeln
 * abarbeitet, sieht die Zahl kaum sinken und haelt die Seite fuer kaputt.
 *
 * Beides ist richtig, es sind nur zwei Fragen: „wie viele stehen hier" und
 * „wie viele gibt es". Deshalb liefert {@see GesamtInbox}
 * seither `counts` (in dieser Liste) UND `totals` (Bestand), und die
 * Oberflaeche kann „N von M" sagen, statt zwei Zahlen unverbunden
 * nebeneinanderzustellen.
 *
 * ## Warum optional
 *
 * Fuer die meisten Quellen ist der Bestand gleich dem Gezeigten — eine
 * Handvoll offener Aufgaben passt in die Liste. Nur wer mehr hat, als er
 * zeigt, muss es sagen. Ohne diese Schnittstelle zaehlt der Sammler die
 * gelieferten ungelesenen Zeilen; das ist nie zu VIEL und damit nie ein
 * Versprechen, das die Seite nicht haelt.
 *
 * Und wie bei {@see AbhakbareQuelle}: Als eigenes Interface bricht der
 * Zuwachs keine der vorhandenen Umsetzungen.
 */
interface ZaehlbareQuelle extends InboxQuelle
{
    /**
     * Wie viele ungelesene EINTRAEGE diese Person hier insgesamt hat.
     *
     * Eintraege, nicht Zeilen — der Unterschied ist groesser als die
     * Kappung. Eine Zeile kann mehrere Eintraege buendeln: Im
     * Meldungs-Paket werden sieben gleichartige Meldungen zu EINER Zeile,
     * und `ungelesenGesamt()` sagt dort 7.
     *
     * Das ist die richtige Richtung, weil `allesAlsGelesen()` ebenfalls
     * Eintraege abhakt und nicht Zeilen. Die Zahl am Knopf nennt damit,
     * was er wirklich anfasst — und der Satz unter der Liste benennt beide
     * Einheiten, statt sie gleichzusetzen.
     */
    public function ungelesenGesamt(Model $person): int;
}
