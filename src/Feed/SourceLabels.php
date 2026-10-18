<?php

namespace Peppermint\Inbox\Feed;

/**
 * Wie die Systeme heissen.
 *
 * Ueber die Leitung kommt ein Kuerzel (`peppermint-crm`), in der Liste soll
 * ein Wort stehen. Dieselbe Zuordnung in jedem System — wer eines ergaenzt,
 * ergaenzt sie ueberall.
 *
 * Ein fehlender Eintrag ist gutmuetig: Dann steht das Kuerzel da, und die
 * Zeilen bleiben erreichbar. Beim Ausrollen ist immer eines der vier
 * Systeme einen Schritt weiter.
 */
final class SourceLabels
{
    /** @return array{local: string, sources: array<string, string>} */
    public static function all(): array
    {
        return [
            'local' => self::local(),
            'sources' => self::sources(),
        ];
    }

    /** Wie dieses System sich selbst nennt. */
    public static function local(): string
    {
        $eigen = config('inbox.labels.local');

        return is_string($eigen) && $eigen !== '' ? $eigen : (string) config('app.name', 'Hier');
    }

    /** @return array<string, string> */
    public static function sources(): array
    {
        $liste = config('inbox.labels.sources', []);

        if (! is_array($liste)) {
            return [];
        }

        return array_filter(
            $liste,
            static fn ($wert, $key): bool => is_string($key) && is_string($wert),
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
