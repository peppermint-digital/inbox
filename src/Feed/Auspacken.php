<?php

namespace Peppermint\Inbox\Feed;

/**
 * Die Antwort eines MCP-Werkzeugs auspacken.
 *
 * Dieselbe Nutzlast kommt je nach Weg in verschiedenen Huellen an —
 * `data`, `structuredContent`, `content`, `result` oder gleich nackt. Das
 * ist nicht schoen, aber es ist die Wirklichkeit der Leitung, und sie
 * hier einmal aufzuloesen ist besser als an vier Aufrufstellen.
 */
final class Auspacken
{
    /**
     * @param  array<string, mixed>  $antwort
     * @return array{items: array<int, array<string, mixed>>, kinds: array<int, string>}
     */
    public static function antwort(array $antwort): array
    {
        $huelle = self::huelle($antwort);

        return [
            'items' => array_values(array_filter(
                (array) ($huelle['items'] ?? []),
                static fn ($zeile): bool => is_array($zeile),
            )),
            'kinds' => array_values(array_filter(
                (array) ($huelle['kinds'] ?? []),
                static fn ($art): bool => is_string($art) && $art !== '',
            )),
        ];
    }

    /**
     * @param  array<string, mixed>  $antwort
     * @return array<string, mixed>
     */
    private static function huelle(array $antwort): array
    {
        $huellen = [
            $antwort,
            $antwort['data'] ?? null,
            $antwort['structuredContent'] ?? null,
            $antwort['content'] ?? null,
            $antwort['result'] ?? null,
            $antwort['antwort'] ?? null,
        ];

        foreach ($huellen as $huelle) {
            if (is_array($huelle) && isset($huelle['items']) && is_array($huelle['items'])) {
                return $huelle;
            }
        }

        return [];
    }
}
