<?php

namespace Peppermint\Inbox\Arten;

/**
 * Eine Art von Eintrag in der Inbox.
 *
 * Mehr ist das nicht: ein Schluessel, ein Wort und wie es aussieht. Was
 * HINTER einer Art steckt, weiss dieses Paket nicht — eine Aufgabe, eine
 * Meldung, ein liegengebliebener Vorgang, alles dasselbe fuer die Liste.
 *
 * Genau darin liegt die Erweiterbarkeit: Eine neue Kategorie braucht kein
 * neues Rohr durch den Verbund, nur eine weitere Art.
 */
final readonly class InboxArt
{
    public function __construct(
        /** Der Schluessel, unter dem Systeme ihre Eintraege einsortieren. */
        public string $schluessel,
        /** Was in der Reiterleiste steht. */
        public string $wort,
        /** Lucide-Name. Die Oberflaeche loest ihn auf. */
        public string $symbol = 'inbox',
        public string $farbe = 'gray',
        /**
         * Reihenfolge der Reiter. Kleiner steht weiter links.
         *
         * Bewusst eine Zahl und keine Liste an zentraler Stelle: Ein System,
         * das eine eigene Art mitbringt, soll sie einordnen koennen, ohne
         * eine Datei zu aendern, die allen gehoert.
         */
        public int $reihenfolge = 100,
        /**
         * Wo der GANZE Verlauf dieser Art steht — ein Pfad in diesem System
         * (pm #10233). Der Posteingang zeigt nur die neuesten Zeilen einer
         * Art; wer mehr will, kommt ueber den Link im Reiter dorthin.
         *
         * Nur fuer eigene Arten: Eine Art aus einem anderen System wird ohne
         * ihn nachgebaut ({@see InboxArten::alle()}), sein Pfad fuehrte hier
         * ins Leere.
         */
        public ?string $verlauf = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->schluessel,
            'label' => $this->wort,
            'icon' => $this->symbol,
            'color' => $this->farbe,
            'order' => $this->reihenfolge,
            // Nur wenn es einen gibt — die Form der uebrigen Arten bleibt.
            ...($this->verlauf !== null ? ['history' => $this->verlauf] : []),
        ];
    }
}
