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
        ];
    }
}
