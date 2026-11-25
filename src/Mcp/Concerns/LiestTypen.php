<?php

namespace Peppermint\Inbox\Mcp\Concerns;

use Laravel\Mcp\Request;

/**
 * `types` aus einem Werkzeug-Aufruf lesen (pm #10227).
 *
 * Fehlt die Angabe oder ist sie keine Liste, heisst das „alle Arten" — so
 * bleibt ein Aufrufer auf aelterem Stand, der nichts schickt, beim alten
 * Verhalten. Eine LEERE Liste heisst dagegen „keine": Wer ausdruecklich
 * nichts sehen will, bekommt nichts.
 */
trait LiestTypen
{
    /**
     * @return array<int, string>|null
     */
    protected function typen(Request $request): ?array
    {
        $roh = $request->get('types');

        if (! is_array($roh)) {
            return null;
        }

        return array_values(array_map(
            static fn ($typ): string => trim((string) $typ),
            array_filter($roh, static fn ($typ): bool => is_scalar($typ) || $typ === null),
        ));
    }
}
