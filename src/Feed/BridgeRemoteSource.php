<?php

namespace Peppermint\Inbox\Feed;

use Peppermint\AiBrainBridge\Facades\AiBrain;
use Peppermint\AiBrainBridge\Verbund\Bruecke;
use RuntimeException;

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

    public function anbieter(): array
    {
        if (! Bruecke::eingerichtet()) {
            // Die Bruecke ist Pflicht, ihre EINRICHTUNG nicht garantiert:
            // AI Brain hat das Paket, ohne es einzurichten — es ist der
            // Hub. Dann bleibt die eigene Inbox, und zwar vollstaendig.
            return [];
        }

        return array_merge([$this->hub()], AiBrain::providers(FremdeEintraege::CAPABILITY));
    }

    /**
     * Die Nabe als Anbieterzeile — von Hand, weil sie in keinem
     * Verzeichnis steht.
     *
     * ## Warum `url` dazugehoert (#1151)
     *
     * Die uebrigen Anbieter bringen ihre Adresse aus dem Verzeichnis mit
     * (`web_url`), und {@see FremdeEintraege} macht damit einen noch
     * relativen Verweis absolut. Die Nabe braucht dieselbe Angabe, und sie
     * steht in der eigenen Konfiguration: Es ist die Adresse, mit der
     * dieses Produkt AI Brain ohnehin anspricht.
     *
     * Ohne sie wuerde ausgerechnet der groesste Beitraeger weiter relative
     * Verweise liefern, solange AI Brain nicht nachgezogen ist — und ein
     * `/bugs/1122` meint in diesem Produkt nichts.
     *
     * @return array{slug: string, key: string, label: string, url: string|null}
     */
    private function hub(): array
    {
        $adresse = trim((string) config('ai-brain-bridge.base_url'));

        return [
            'slug' => self::HUB_SLUG,
            'key' => self::HUB_SLUG,
            'label' => 'AI Brain',
            'url' => $adresse === '' ? null : $adresse,
        ];
    }

    public function holen(string $slug, string $adresse, int $grenze): array
    {
        if ($slug === self::HUB_SLUG) {
            // Die Nabe wird direkt gefragt, nicht ueber den Gateway: Sie
            // wuerde sich sonst selbst weiterreichen.
            return Auspacken::antwort(
                (array) AiBrain::call('list-inbox-entries-tool', ['limit' => $grenze])
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

    public function alsGelesen(string $slug, string $adresse, string $art, array $ids): int
    {
        if (! Bruecke::eingerichtet()) {
            // Ohne Bruecke gibt es keine fremden Zeilen, also auch keine
            // abzuhaken. Kaeme hier trotzdem eine an, waere die Herkunft
            // falsch gesetzt — und das gehoert gesagt, nicht gezaehlt.
            throw new RuntimeException('Keine Bruecke eingerichtet — eine fremde Zeile kann hier nicht abgehakt werden.');
        }

        if ($slug === self::HUB_SLUG) {
            // Wie beim Holen: die Nabe direkt, nicht ueber ihren eigenen
            // Gateway. Sie wuerde sich sonst selbst weiterreichen.
            return Auspacken::abgehakt(
                (array) AiBrain::call(Abhaken::TOOL, ['kind' => $art, 'ids' => $ids])
            );
        }

        $antwort = AiBrain::gateway(
            Abhaken::CAPABILITY,
            ['kind' => $art, 'ids' => $ids],
            $slug,
            $adresse,
        );

        return Auspacken::abgehakt(is_array($antwort) ? $antwort : []);
    }
}
