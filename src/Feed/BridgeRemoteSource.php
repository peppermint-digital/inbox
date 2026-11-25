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
class BridgeRemoteSource implements SchreibendeRemoteSource
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
                (array) AiBrain::call('list-inbox-entries-tool', $this->mitTypen($slug, ['limit' => $grenze]))
            );
        }

        $antwort = AiBrain::gateway(
            FremdeEintraege::CAPABILITY,
            $this->mitTypen($slug, ['limit' => $grenze]),
            $slug,
            $adresse,
        );

        return Auspacken::antwort(is_array($antwort) ? $antwort : []);
    }

    public function alsGelesen(string $slug, string $adresse, string $art, array $ids): int
    {
        return Auspacken::abgehakt(
            $this->schreiben($slug, $adresse, Abhaken::TOOL, Abhaken::CAPABILITY, ['kind' => $art, 'ids' => $ids])
        );
    }

    /**
     * Alles abhaken — ein Aufruf mit `all`, keine Kennungsliste (pm #10227).
     *
     * Ein System auf aelterem Stand kennt `all` nicht und antwortet mit
     * einem Fehler („Missing kind" / „Missing ids"). Der kommt hier als
     * Ausnahme an und NICHT als Null: Wer „alle" drueckt und drueben bleibt
     * alles stehen, muss das sehen.
     */
    public function alleAlsGelesen(string $slug, string $adresse, ?string $art = null): int
    {
        return Auspacken::abgehakt(
            $this->schreiben($slug, $adresse, Abhaken::TOOL, Abhaken::CAPABILITY, $this->mitTypen($slug, array_filter(
                ['all' => true, 'kind' => $art],
                static fn ($wert): bool => $wert !== null,
            )))
        );
    }

    public function loeschen(string $slug, string $adresse, string $art, array $ids): int
    {
        return Auspacken::geloescht(
            $this->schreiben($slug, $adresse, Loeschen::TOOL, Loeschen::CAPABILITY, ['kind' => $art, 'ids' => $ids])
        );
    }

    public function allesLoeschen(string $slug, string $adresse, string $art): int
    {
        return Auspacken::geloescht(
            $this->schreiben($slug, $adresse, Loeschen::TOOL, Loeschen::CAPABILITY, $this->mitTypen($slug, ['kind' => $art, 'all' => true]))
        );
    }

    /**
     * Die gezeigten Arten dieses Systems mitgeben (`inbox.fremde_typen`, pm #10227).
     *
     * Beim Holen UND bei „alle": Sonst haekte „alle als gelesen" drueben
     * Zeilen ab, die hier gar nicht zu sehen sind. Einzelne Kennungen
     * brauchen es nicht — die stammen aus der gezeigten Liste.
     *
     * @param  array<string, mixed>  $argumente
     * @return array<string, mixed>
     */
    private function mitTypen(string $slug, array $argumente): array
    {
        $typen = ((array) config('inbox.fremde_typen', []))[$slug] ?? null;

        if (is_array($typen)) {
            $argumente['types'] = array_values(array_map('strval', $typen));
        }

        return $argumente;
    }

    /**
     * Ein Schreibaufruf in ein anderes System — die Nabe direkt, alle
     * anderen ueber ihren Gateway.
     *
     * @param  array<string, mixed>  $argumente
     * @return array<mixed>
     */
    private function schreiben(string $slug, string $adresse, string $werkzeug, string $faehigkeit, array $argumente): array
    {
        if (! Bruecke::eingerichtet()) {
            // Ohne Bruecke gibt es keine fremden Zeilen, also auch keine
            // abzuhaken oder zu loeschen. Kaeme hier trotzdem eine an, waere
            // die Herkunft falsch gesetzt — und das gehoert gesagt, nicht
            // gezaehlt.
            throw new RuntimeException('Keine Bruecke eingerichtet — eine fremde Zeile kann hier nicht bearbeitet werden.');
        }

        if ($slug === self::HUB_SLUG) {
            // Wie beim Holen: die Nabe direkt, nicht ueber ihren eigenen
            // Gateway. Sie wuerde sich sonst selbst weiterreichen.
            return (array) AiBrain::call($werkzeug, $argumente);
        }

        $antwort = AiBrain::gateway($faehigkeit, $argumente, $slug, $adresse);

        return is_array($antwort) ? $antwort : [];
    }
}
