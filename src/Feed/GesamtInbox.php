<?php

namespace Peppermint\Inbox\Feed;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Peppermint\Inbox\Arten\InboxArten;
use Peppermint\Inbox\Quellen\Quellenverzeichnis;

/**
 * Die Inbox ueber alle Systeme.
 *
 * Eigene Quellen plus die der anderen — geholt, nicht kopiert. Solange es
 * das AI Brain gibt, ist es die einzige gueltige Quelle fuer das, was dort
 * steht; dasselbe gilt fuer jedes andere System und seine Daten.
 *
 * ## Gekappt wird JE ART UND SYSTEM, nicht global
 *
 * Ein globaler Deckel ueber die zusammengefuegte Liste klingt harmlos und
 * ist der Fehler, der diese Ansicht schon zweimal ausgehoehlt hat: Das
 * lauteste System fuellt die Obergrenze allein, und die anderen fallen
 * unsichtbar hinten herunter. Die Liste sieht dann vollstaendig aus.
 *
 * ## Der Zwischenspeicher gilt nur fuer die FREMDEN
 *
 * Die eigenen Zeilen kommen immer frisch: Wer gerade etwas erledigt hat,
 * will es sofort verschwinden sehen. Die fremden darf man eine Minute alt
 * zeigen — vier Systeme bei jedem Seitenaufruf zu fragen kostet mehr, als
 * die Aktualitaet wert ist.
 */
class GesamtInbox
{
    /** Wie lange fremde Zeilen wiederverwendet werden. */
    private const HALTBAR = 60;

    public function __construct(
        private readonly Quellenverzeichnis $eigene,
        private readonly FremdeEintraege $fremde,
        private readonly InboxArten $arten,
    ) {}

    /**
     * @return array{items: array<int, array<string, mixed>>, kinds: array<int, array<string, mixed>>, counts: array<string, int>, unreachable: array<int, string>, failed: array<int, string>, labels: array{local: string, sources: array<string, string>}}
     */
    public function for(Model $person, bool $frisch = false): array
    {
        $grenze = $this->grenze();

        $eigen = $this->eigene->sammeln($person, $grenze);
        $fremd = $this->fremdeZeilen($person, $grenze, $frisch);

        $zeilen = array_merge(
            array_map($this->alsEigene(...), $eigen['items']),
            $fremd['items'],
        );

        usort($zeilen, $this->sortierung(...));

        $zeilen = $this->gekapptJeArtUndSystem($zeilen, $grenze);

        return [
            'items' => $zeilen,
            'kinds' => $this->arten->alle(array_merge($eigen['kinds'], $fremd['kinds'])),
            'counts' => $this->zaehlen($zeilen),
            'unreachable' => $fremd['unreachable'],
            // Eigene Quellen, die gerade nicht konnten. Eine andere Lage als
            // „System nicht erreichbar", deshalb ein eigenes Feld.
            'failed' => $eigen['failed'],
            'labels' => SourceLabels::all(),
        ];
    }

    /**
     * Die eigenen Zeilen tragen keine Herkunft.
     *
     * `null` heisst „von hier". Die Oberflaeche setzt dafuer das eigene
     * Wort ein — der Leser soll nicht raten muessen, ob eine Zeile ohne
     * Marke aus diesem System kommt oder die Marke verlorenging.
     *
     * @param  array<string, mixed>  $zeile
     * @return array<string, mixed>
     */
    private function alsEigene(array $zeile): array
    {
        // `array_merge`, NICHT `+`: Der Plus-Operator behaelt bei gleichen
        // Schluesseln den LINKEN Wert, und eine Quelle, die versehentlich
        // selbst ein `source` setzt, behielte es.
        return array_merge($zeile, ['source' => null, 'source_label' => null]);
    }

    /**
     * Ungelesenes zuerst, dann das Neueste.
     *
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function sortierung(array $a, array $b): int
    {
        $ungelesen = (int) ($b['is_unread'] ?? false) <=> (int) ($a['is_unread'] ?? false);

        if ($ungelesen !== 0) {
            return $ungelesen;
        }

        return strtotime((string) ($b['created_at'] ?? '')) <=> strtotime((string) ($a['created_at'] ?? ''));
    }

    /**
     * @param  array<int, array<string, mixed>>  $zeilen
     * @return array<int, array<string, mixed>>
     */
    private function gekapptJeArtUndSystem(array $zeilen, int $grenze): array
    {
        $gezaehlt = [];
        $behalten = [];

        // Die Liste ist bereits sortiert — wer zuerst kommt, gehoert
        // gezeigt. Deshalb reicht ein Durchlauf.
        foreach ($zeilen as $zeile) {
            $fach = ($zeile['kind'] ?? '?').'|'.($zeile['source'] ?? '');

            if (($gezaehlt[$fach] = ($gezaehlt[$fach] ?? 0) + 1) > $grenze) {
                continue;
            }

            $behalten[] = $zeile;
        }

        return $behalten;
    }

    /**
     * Wie viele UNGELESENE je Art — die Zahlen an den Reitern.
     *
     * @param  array<int, array<string, mixed>>  $zeilen
     * @return array<string, int>
     */
    private function zaehlen(array $zeilen): array
    {
        $zahlen = [];

        foreach ($zeilen as $zeile) {
            if (! ($zeile['is_unread'] ?? false)) {
                continue;
            }

            $art = (string) ($zeile['kind'] ?? '?');
            $zahlen[$art] = ($zahlen[$art] ?? 0) + 1;
        }

        return $zahlen;
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, kinds: array<int, string>, unreachable: array<int, string>}
     */
    private function fremdeZeilen(Model $person, int $grenze, bool $frisch): array
    {
        $adresse = $person->getAttribute('email');

        if (! is_string($adresse) || trim($adresse) === '') {
            // Ohne Adresse kann kein anderes System den Menschen finden.
            return ['items' => [], 'kinds' => [], 'unreachable' => []];
        }

        $schluessel = 'inbox.fremde.'.sha1($adresse);

        if ($frisch) {
            Cache::forget($schluessel);
        }

        return Cache::remember(
            $schluessel,
            self::HALTBAR,
            fn (): array => $this->fremde->holen($adresse, $grenze),
        );
    }

    private function grenze(): int
    {
        return max(1, (int) config('inbox.max_items', 50));
    }
}
