<?php

namespace Peppermint\Inbox\Feed;

use Illuminate\Support\Facades\Log;

/**
 * Die Eintraege der anderen Systeme holen und zusammenlegen.
 *
 * ## Die Faehigkeit
 *
 * `inbox.entries` im Komm-Layer, drueben als `list-inbox-entries-tool`.
 * NICHT `inbox.list` — die ist seit laengerem fuer den Aufgaben-
 * Posteingang des Managers vergeben, mit anderer Antwortform. Jedes System
 * gibt nur seine EIGENEN Eintraege heraus — wuerde es die geholten
 * mitliefern, fragte der Verbund sich im Kreis und jede Zeile kaeme
 * mehrfach an.
 *
 * ## Fail-open, aber nicht stumm
 *
 * Ein System, das nicht antwortet, kostet seine Zeilen — nicht die Liste.
 * Es steht aber im Ergebnis, denn eine still verkuerzte Liste sieht aus wie
 * „da ist nichts", und dann sucht man im falschen System.
 */
class FremdeEintraege
{
    public const CAPABILITY = 'inbox.entries';

    public function __construct(private readonly RemoteSource $quelle) {}

    /**
     * @return array{items: array<int, array<string, mixed>>, kinds: array<int, string>, unreachable: array<int, string>}
     */
    public function holen(string $adresse, int $grenze = 50): array
    {
        $zeilen = [];
        $arten = [];
        $stumm = [];

        foreach ($this->quelle->anbieter() as $anbieter) {
            $schluessel = (string) ($anbieter['key'] ?? $anbieter['slug'] ?? '');
            $slug = (string) ($anbieter['slug'] ?? $schluessel);
            $wort = (string) ($anbieter['label'] ?? $schluessel);

            if ($slug === '') {
                continue;
            }

            try {
                $antwort = $this->quelle->holen($slug, $adresse, $grenze);
            } catch (\Throwable $fehler) {
                $stumm[] = $wort;

                Log::warning('System hat seine Inbox nicht herausgegeben.', [
                    'system' => $slug,
                    'error' => $fehler->getMessage(),
                ]);

                continue;
            }

            foreach ($antwort['items'] as $zeile) {
                // Die Herkunft setzt der FRAGENDE, nicht der Befragte. Ein
                // System weiss nicht, unter welchem Kuerzel es hier gefuehrt
                // wird — und wenn es sich selbst benennen duerfte, stuenden
                // in vier Oberflaechen vier verschiedene Woerter.
                $zeile['source'] = $schluessel;
                $zeile['source_label'] = $wort;
                $zeilen[] = $zeile;
            }

            foreach ($antwort['kinds'] as $art) {
                $arten[] = $art;
            }
        }

        return [
            'items' => $zeilen,
            'kinds' => array_values(array_unique($arten)),
            'unreachable' => $stumm,
        ];
    }
}
