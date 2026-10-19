<?php

namespace Peppermint\Inbox\Feed;

use Peppermint\Inbox\Verbund\Huelle;

/**
 * Die Antwort von `list-inbox-tool` auspacken.
 *
 * Die Huellen-Logik selbst steht in {@see Huelle} — sie ist dieselbe fuer
 * jedes Werkzeug am Komm-Layer, und eine zweite Kopie davon veraltet
 * still.
 */
final class Auspacken
{
    /**
     * @param  array<string, mixed>  $antwort
     * @return array{items: array<int, array<string, mixed>>, kinds: array<int, string>}
     */
    public static function antwort(array $antwort): array
    {
        $huelle = Huelle::schaelen($antwort);

        return [
            'items' => Huelle::zeilen($huelle),
            'kinds' => Huelle::woerter($huelle, 'kinds'),
        ];
    }
}
