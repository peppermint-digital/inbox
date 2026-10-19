<?php

namespace Peppermint\Inbox\Feed;

use Peppermint\AiBrainBridge\Verbund\Huelle;

/**
 * Die Antwort von `list-inbox-entries-tool` auspacken.
 *
 * Die Huellen-Logik selbst steht in {@see Huelle} — sie ist dieselbe fuer
 * jedes Werkzeug am Komm-Layer, und eine zweite Kopie davon veraltet
 * still.
 */
final class Auspacken
{
    /**
     * Art-Definitionen aus beiden Formen lesen.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function arten(mixed $roh): array
    {
        $arten = [];

        foreach ((array) $roh as $eintrag) {
            if (is_string($eintrag) && $eintrag !== '') {
                // Alte Form: nur der Schluessel. Das Wort fehlt dann, und
                // das Verzeichnis setzt den Schluessel ein — haesslich,
                // aber auffindbar.
                $arten[] = ['key' => $eintrag];

                continue;
            }

            if (is_array($eintrag) && is_string($eintrag['key'] ?? null) && $eintrag['key'] !== '') {
                $arten[] = $eintrag;
            }
        }

        return $arten;
    }

    /**
     * @param  array<string, mixed>  $antwort
     * @return array{items: array<int, array<string, mixed>>, kinds: array<int, array<string, mixed>>, failed: array<int, string>}
     */
    public static function antwort(array $antwort): array
    {
        $huelle = Huelle::schaelen($antwort);

        return [
            'items' => Huelle::zeilen($huelle),
            // Beide Formen: Bis v0.3.1 kam eine Liste von Schluesseln,
            // seither ganze Definitionen. Ein System, das noch nicht
            // ausgerollt ist, soll seine Zeilen trotzdem loswerden.
            'kinds' => self::arten($huelle['kinds'] ?? []),
            // Arten, die DRUEBEN nicht geliefert werden konnten. Sie
            // fehlen in der Liste, und ohne diese Angabe faellt das
            // niemandem auf.
            'failed' => Huelle::woerter($huelle, 'failed'),
        ];
    }
}
