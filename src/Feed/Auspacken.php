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
     * @param  array<string, mixed>  $antwort
     * @return array{items: array<int, array<string, mixed>>, kinds: array<int, string>, failed: array<int, string>}
     */
    public static function antwort(array $antwort): array
    {
        $huelle = Huelle::schaelen($antwort);

        return [
            'items' => Huelle::zeilen($huelle),
            'kinds' => Huelle::woerter($huelle, 'kinds'),
            // Arten, die DRUEBEN nicht geliefert werden konnten. Sie
            // fehlen in der Liste, und ohne diese Angabe faellt das
            // niemandem auf.
            'failed' => Huelle::woerter($huelle, 'failed'),
        ];
    }
}
