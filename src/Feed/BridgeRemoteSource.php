<?php

namespace Peppermint\Inbox\Feed;

use Peppermint\AiBrainBridge\Facades\AiBrain;
use Peppermint\AiBrainBridge\Verbund\Bruecke;

/**
 * Die Produkt-Seite: ueber die Bruecke beim AI Brain fragen.
 *
 * Das Brain steht BEWUSST in der Liste, obwohl es kein ConnectedProduct
 * ist und in keinem Verzeichnis der Faehigkeit auftaucht. Es ist die Nabe,
 * und es hat selbst eine Inbox — ohne diesen Eintrag saehe ein Produkt
 * alles ausser dem groessten Beitraeger.
 */
class BridgeRemoteSource implements RemoteSource
{
    private const HUB_SLUG = 'ai-brain';

    private const HUB = ['slug' => self::HUB_SLUG, 'key' => self::HUB_SLUG, 'label' => 'AI Brain'];

    public function anbieter(): array
    {
        if (! Bruecke::eingerichtet()) {
            // Die Bruecke ist Pflicht, ihre EINRICHTUNG nicht garantiert:
            // AI Brain hat das Paket, ohne es einzurichten — es ist der
            // Hub. Dann bleibt die eigene Inbox, und zwar vollstaendig.
            return [];
        }

        return array_merge([self::HUB], AiBrain::providers(FremdeEintraege::CAPABILITY));
    }

    public function holen(string $slug, string $adresse, int $grenze): array
    {
        if ($slug === self::HUB_SLUG) {
            // Die Nabe wird direkt gefragt, nicht ueber den Gateway: Sie
            // wuerde sich sonst selbst weiterreichen.
            return Auspacken::antwort(
                (array) AiBrain::call('list-inbox-tool', ['limit' => $grenze])
            );
        }

        $antwort = AiBrain::gateway(
            FremdeEintraege::CAPABILITY,
            ['limit' => $grenze],
            $slug,
            $adresse,
        );

        return Auspacken::antwort(is_array($antwort) ? $antwort : []);
    }
}
