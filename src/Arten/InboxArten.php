<?php

namespace Peppermint\Inbox\Arten;

/**
 * Das Verzeichnis der Arten.
 *
 * ## Warum die Arten nicht in der Konfiguration stehen
 *
 * Sie koennen es — `config('inbox.kinds')` wird beim Start eingelesen. Aber
 * eine Art kommt selten allein: Wer eine neue einfuehrt, bringt auch die
 * Quelle mit, die sie fuellt. Beides gehoert nebeneinander, in den
 * Dienstanbieter des Produkts, nicht auseinandergerissen auf Code und
 * Konfiguration.
 *
 * ## Unbekannte Arten fliegen nicht raus
 *
 * Liefert ein anderes System eine Art, die hier niemand kennt, bekommt sie
 * einen Platz hinten und ihren Schluessel als Wort. Das ist Absicht: Beim
 * Ausrollen ist immer ein System einen Schritt weiter als die anderen, und
 * in dieser Zeit soll nichts verschwinden — eine Zeile ohne huebsches Wort
 * ist besser als eine fehlende Zeile.
 */
class InboxArten
{
    /** @var array<string, InboxArt> */
    private array $arten = [];

    public function registrieren(InboxArt $art): self
    {
        $this->arten[$art->schluessel] = $art;

        return $this;
    }

    /** @param array<int, InboxArt|array<string, mixed>> $arten */
    public function registriereViele(array $arten): self
    {
        foreach ($arten as $art) {
            $this->registrieren($art instanceof InboxArt ? $art : new InboxArt(
                schluessel: (string) ($art['key'] ?? ''),
                wort: (string) ($art['label'] ?? ''),
                symbol: (string) ($art['icon'] ?? 'inbox'),
                farbe: (string) ($art['color'] ?? 'gray'),
                reihenfolge: (int) ($art['order'] ?? 100),
            ));
        }

        return $this;
    }

    public function kennt(string $schluessel): bool
    {
        return isset($this->arten[$schluessel]);
    }

    /**
     * Eine Art — notfalls eine behelfsmaessige.
     *
     * Siehe Klassenkommentar: Ein unbekannter Schluessel ist kein Fehler,
     * sondern ein System, das gerade weiter ist als dieses.
     */
    public function holen(string $schluessel): InboxArt
    {
        return $this->arten[$schluessel] ?? new InboxArt(
            schluessel: $schluessel,
            wort: $schluessel,
            reihenfolge: 900,
        );
    }

    /**
     * Alle Arten in Anzeigereihenfolge.
     *
     * ## Die fremden Definitionen zaehlen
     *
     * Ein anderes System schickt seine Arten MIT Wort, Symbol und
     * Reihenfolge mit. Nur so erscheint eine neue Kategorie drueben als
     * „Belege sichten" statt als `document` — ohne sie waere die
     * Erweiterbarkeit an der Beschriftung zu Ende.
     *
     * Die EIGENE Anmeldung gewinnt trotzdem: Wer eine Art hier benennt,
     * meint sein eigenes Wort, auch wenn ein anderes System ein anderes
     * dafuer hat.
     *
     * @param  array<int, string>  $eigene  Schluessel, die in den eigenen Daten vorkamen.
     * @param  array<int, array<string, mixed>>  $fremde  Definitionen anderer Systeme.
     * @return array<int, array<string, mixed>>
     */
    public function alle(array $eigene = [], array $fremde = []): array
    {
        $arten = $this->arten;

        foreach ($fremde as $definition) {
            $schluessel = (string) ($definition['key'] ?? '');

            if ($schluessel === '' || isset($arten[$schluessel])) {
                continue;
            }

            $arten[$schluessel] = new InboxArt(
                schluessel: $schluessel,
                wort: (string) ($definition['label'] ?? $schluessel),
                symbol: (string) ($definition['icon'] ?? 'inbox'),
                farbe: (string) ($definition['color'] ?? 'gray'),
                reihenfolge: (int) ($definition['order'] ?? 900),
            );
        }

        foreach ($eigene as $schluessel) {
            if (! isset($arten[$schluessel])) {
                $arten[$schluessel] = $this->holen($schluessel);
            }
        }

        $liste = array_values($arten);

        usort($liste, fn (InboxArt $a, InboxArt $b): int => [$a->reihenfolge, $a->wort] <=> [$b->reihenfolge, $b->wort]);

        return array_map(fn (InboxArt $a): array => $a->toArray(), $liste);
    }

    /** Fuer Tests: das Verzeichnis leeren. */
    public function vergessen(): void
    {
        $this->arten = [];
    }
}
