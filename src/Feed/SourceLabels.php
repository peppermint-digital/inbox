<?php

namespace Peppermint\Inbox\Feed;

use Peppermint\Inbox\Verbund\Systemnamen;

/**
 * Wie die Systeme in DIESEM Paket heissen.
 *
 * Nur die Zuordnung auf `config('inbox.labels')`; die Mechanik steht in
 * {@see Systemnamen} und wird mit dem Benachrichtigungspaket geteilt.
 */
final class SourceLabels
{
    private const KONFIG = 'inbox';

    /** @return array{local: string, sources: array<string, string>} */
    public static function all(): array
    {
        return Systemnamen::alle(self::KONFIG);
    }

    public static function local(): string
    {
        return Systemnamen::eigenes(self::KONFIG);
    }

    /** @return array<string, string> */
    public static function sources(): array
    {
        return Systemnamen::fremde(self::KONFIG);
    }
}
