<?php

namespace Peppermint\Inbox\Quellen;

use Illuminate\Database\Eloquent\Model;
use Peppermint\Inbox\Feed\Eintrag;

/**
 * Was dieses System zur Inbox beitraegt.
 *
 * Ein Produkt meldet je Art eine Quelle an. Die Inbox fragt sie und weiss
 * nicht, was dahinter liegt — ob eine Aufgabentabelle, ein Meldungs-Feed
 * oder ein Fremdsystem, das dieses Produkt seinerseits anzapft.
 *
 * ## Nur die EIGENEN Eintraege
 *
 * Eine Quelle liefert ausschliesslich, was in diesem System steht. Was die
 * anderen haben, holt der Sammler selbst ueber die Faehigkeit `inbox.entries`.
 * Wuerde eine Quelle ihrerseits fremde Eintraege mitliefern, kaeme jede
 * Zeile ueber zwei Wege herein — und der Verbund fragte sich im Kreis.
 */
interface InboxQuelle
{
    /**
     * Welche Art diese Quelle fuellt.
     *
     * Eine Quelle, eine Art. Wer zwei fuellt, meldet zwei an — sonst muesste
     * der Sammler die Zeilen nachtraeglich sortieren und koennte nicht
     * sagen, welche Quelle gerade nicht antwortet.
     */
    public function art(): string;

    /**
     * Die Eintraege dieser Person, neueste zuerst.
     *
     * @return array<int, Eintrag>
     */
    public function eintraege(Model $person, int $grenze): array;
}
