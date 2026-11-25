<?php

namespace Peppermint\Inbox\Quellen;

/**
 * Eine Quelle, die nur bestimmte Arten ihrer Zeilen herausgibt (pm #10227).
 *
 * Gemeint ist die FACHLICHE Art einer Zeile innerhalb einer Posteingangs-Art
 * — bei Meldungen etwa `task.done` oder `app.health`, nicht `notification`.
 *
 * Ein anderes System fragt mit `types` an (siehe `inbox.fremde_typen`), und
 * jede Quelle, die das kann, gibt eine eingeschraenkte Fassung von sich
 * zurueck. Liste, Bestand, „alle als gelesen" und „alle loeschen" sehen dann
 * dieselbe Menge. Eine Quelle ohne diesen Vertrag bleibt unberuehrt.
 */
interface NachTypenFilterbar
{
    /**
     * Dieselbe Quelle, nur mit diesen Arten. `''` steht fuer Zeilen ohne Art;
     * `null` heisst: alle.
     *
     * @param  array<int, string>|null  $typen
     */
    public function nurTypen(?array $typen): static;
}
