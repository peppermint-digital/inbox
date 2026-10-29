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
     * @return array{items: array<int, array<string, mixed>>, kinds: array<int, array<string, mixed>>, counts: array<string, int>, totals: array{local: array<string, int>, sources: array<string, array<string, int>>}, markable: array{local: array<int, string>, sources: array<string, array<int, string>>}, unreachable: array<int, string>, failed: array<int, string>, labels: array{local: string, sources: array<string, string>}}
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
            'kinds' => $this->arten->alle($eigen['kinds'], $fremd['kinds']),
            // Was in DIESER Liste ungelesen ist — daran haengen die Zahlen
            // an den Reitern.
            'counts' => $this->zaehlen($zeilen),
            /*
             * Der BESTAND, getrennt nach Herkunft (#1096).
             *
             * Bis zum 26.10.2026 hiess beides `counts`, und die Kachel in
             * der Kopfzeile holte ihre Zahl aus einem zweiten Endpunkt.
             * Dann stand dort 3953 gegen 50 Zeilen, nichts erklaerte die
             * Luecke, und beim Abarbeiten sank die Zahl scheinbar nicht —
             * die Seite sah kaputt aus. Zwei Fragen, zwei Felder, eine
             * Quelle: Daraus kann die Ansicht „N von M" sagen.
             */
            'totals' => [
                'local' => $eigen['totals'],
                'sources' => $fremd['totals'],
            ],
            /*
             * Wo ein Haken ein Ziel findet.
             *
             * Die Ansicht raet das nicht. Bis zum 27.10.2026 stand die
             * Entscheidung im Produkt („von hier, ungelesen, Art
             * notification") — eine Regel, die jedes der vier Produkte
             * haette abschreiben muessen, und die bei einer neuen
             * abhakbaren Quelle still falsch geworden waere.
             */
            'markable' => [
                'local' => $eigen['markable'],
                'sources' => $fremd['markable'],
            ],
            'unreachable' => $fremd['unreachable'],
            // Eigene Quellen, die gerade nicht konnten. Eine andere Lage als
            // „System nicht erreichbar", deshalb ein eigenes Feld.
            'failed' => array_merge($eigen['failed'], $fremd['failed']),
            'labels' => SourceLabels::all(),
        ];
    }

    /**
     * Wie viel insgesamt ungelesen wartet — ueber alle Arten und Systeme.
     *
     * ## Wofuer, und warum es nicht die Meldungs-Zahl ist
     *
     * Fuer die Zahl am Posteingangs-Symbol und an der Minibar-Kachel
     * (AI Brain #1152). Beide holten sie bis zum 29.10.2026 aus
     * `/api/inbox/unread-count`, und das ist `NotificationFeed::unreadCount()`
     * — die Zahl der ungelesenen MELDUNGEN dieses einen Systems.
     *
     * Das Symbol oeffnet aber den gemeinsamen Posteingang, und dort stehen
     * auch Aufgaben, Belege, Liegengebliebenes und imkr-Zeilen aus vier
     * Systemen. Die Zahl sprach also ueber etwas anderes als die Seite, die
     * sie aufmacht. Genau diese Art Widerspruch hat schon #1096 gekostet:
     * Wer abarbeitet und die Zahl nicht sinken sieht, haelt die Seite fuer
     * kaputt.
     *
     * ## Der Bestand, nicht die Liste
     *
     * Gezaehlt wird, was WARTET — nicht, was in der Liste steht. Die Liste
     * ist je Art und System gekappt; eine Zahl, die der Kappung folgte,
     * stuende bei 50 und bliebe dort.
     *
     * ## Kosten
     *
     * Die eigenen Quellen nennen ihren Bestand, ohne die Zeilen aufzubauen
     * ({@see Quellenverzeichnis::bestand()}). Die fremden kommen aus
     * DEMSELBEN Zwischenspeicher wie die Seite — derselbe Schluessel,
     * dieselbe Minute. Die Zahl im Takt abzufragen fragt also nicht vier
     * Systeme, sondern liest mit.
     */
    public function bestand(Model $person, bool $frisch = false): int
    {
        $grenze = $this->grenze();

        $summe = array_sum($this->eigene->bestand($person, $grenze));

        foreach ($this->fremdeZeilen($person, $grenze, $frisch)['totals'] as $jeSystem) {
            // Ein System auf aelterem Paketstand schickt `totals` nicht
            // mit. Dann fehlt sein Beitrag — und das ist die richtige
            // Richtung: zu niedrig zaehlen heisst, dass jemand nachsieht
            // und MEHR findet. Zu hoch zaehlen schickt ihn auf eine Suche
            // nach etwas, das es nicht gibt.
            $summe += array_sum(is_array($jeSystem) ? $jeSystem : []);
        }

        return (int) $summe;
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
     * @return array{items: array<int, array<string, mixed>>, kinds: array<int, array<string, mixed>>, unreachable: array<int, string>, failed: array<int, string>, totals: array<string, array<string, int>>, markable: array<string, array<int, string>>}
     */
    private function fremdeZeilen(Model $person, int $grenze, bool $frisch): array
    {
        $adresse = $person->getAttribute('email');

        if (! is_string($adresse) || trim($adresse) === '') {
            // Ohne Adresse kann kein anderes System den Menschen finden.
            return ['items' => [], 'kinds' => [], 'unreachable' => [], 'failed' => [], 'totals' => [], 'markable' => []];
        }

        $schluessel = 'inbox.fremde.'.sha1($adresse);

        if ($frisch) {
            Cache::forget($schluessel);
        }

        // Der Puffer kann eine Antwort in der ALTEN Form enthalten — er
        // haelt eine Minute, und die ueberlebt jedes Ausrollen. Die
        // Vorgaben hier kosten nichts und ersparen ein „undefined" in der
        // Oberflaeche in genau der Minute, in der jemand nachsieht.
        return array_merge(
            ['items' => [], 'kinds' => [], 'unreachable' => [], 'failed' => [], 'totals' => [], 'markable' => []],
            Cache::remember(
                $schluessel,
                self::HALTBAR,
                fn (): array => $this->fremde->holen($adresse, $grenze),
            ),
        );
    }

    private function grenze(): int
    {
        return max(1, (int) config('inbox.max_items', 50));
    }
}
