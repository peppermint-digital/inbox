<?php

namespace Peppermint\Inbox\Feed;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Peppermint\Inbox\Quellen\AbhakbareQuelle;
use Peppermint\Inbox\Quellen\Quellenverzeichnis;
use RuntimeException;

/**
 * Eine Zeile abhaken — egal, in welchem System sie liegt.
 *
 * ## Die Faehigkeit
 *
 * `inbox.read` im Komm-Layer, drueben als `mark-inbox-entries-read-tool`.
 * Das Gegenstueck zu `inbox.entries`: dieselbe Richtung, dieselbe Adresse
 * als Schluessel fuer den Menschen, nur schreibend.
 *
 * ## Zwei Wege, eine Entscheidung
 *
 * Traegt die Zeile keine Herkunft, kommt sie von hier und geht an die
 * eigene {@see AbhakbareQuelle}. Traegt sie eine,
 * geht sie ueber {@see RemoteSource} dorthin, wo sie wirklich liegt. Die
 * Oberflaeche trifft diese Entscheidung nicht — sie schickt die Zeile, wie
 * sie sie bekommen hat.
 *
 * Das ist der Punkt, an dem dieses Paket die Doppelpflege verhindert: Ohne
 * ihn schriebe jedes der vier Produkte denselben Verdrahtungscode, und die
 * zweite Pruefrage des Paket-Vertrags („Schreibt jedes Produkt denselben
 * Verdrahtungscode?") waere mit ja zu beantworten.
 *
 * ## Fail-LOUD, anders als beim Holen
 *
 * Beim Holen kostet ein stummes System seine Zeilen, nicht die Liste — und
 * die Ansicht sagt es. Beim Abhaken gibt es diese Milde nicht: Wer abhakt
 * und es kommt nicht an, muss das sehen. Sonst steht die Zeile beim
 * Neuladen wieder da, und der Fehler sieht aus wie der Fix.
 */
class Abhaken
{
    public const CAPABILITY = 'inbox.read';

    /** Wie das Werkzeug drueben heisst, das die Faehigkeit beantwortet. */
    public const TOOL = 'mark-inbox-entries-read-tool';

    /**
     * Hoechstzahl Kennungen je Aufruf.
     *
     * Ein Deckel, damit eine Liste aus dem Browser nicht zu einer Abfrage
     * mit zehntausend Werten wird. Dieselbe Zahl wie in
     * `NotificationActions` aus `peppermint/notifications` — die Quelle
     * dahinter kappt ohnehin, aber ein Deckel vor der Leitung ist billiger
     * als einer dahinter.
     */
    public const MAX_IDS = 200;

    public function __construct(
        private readonly Quellenverzeichnis $eigene,
        private readonly RemoteSource $fremde,
    ) {}

    /**
     * Diese Zeilen auf gelesen setzen.
     *
     * @param  string|null  $quelle  Herkunftssystem der Zeilen; `null` heisst „von hier".
     * @param  array<int, string>  $ids
     * @return int Wie viele Zeilen sich tatsaechlich geaendert haben.
     *
     * @throws RuntimeException wenn es nicht ankam.
     */
    public function zeilen(Model $person, ?string $quelle, string $art, array $ids): int
    {
        $ids = array_values(array_filter(
            array_map(static fn ($id): string => (string) $id, $ids),
            static fn (string $id): bool => $id !== '',
        ));

        if ($ids === []) {
            return 0;
        }

        $ids = array_slice($ids, 0, self::MAX_IDS);

        if ($quelle === null || $quelle === '') {
            return $this->eigene->alsGelesen($person, $art, $ids);
        }

        $adresse = (string) $person->getAttribute('email');

        if (trim($adresse) === '') {
            // Ohne Adresse kann das andere System den Menschen nicht
            // finden — es wuerde entweder nichts tun oder, schlimmer,
            // irgendetwas. Beides gehoert gesagt, nicht gezaehlt.
            throw new RuntimeException('Ohne E-Mail-Adresse findet das andere System die Person nicht.');
        }

        try {
            return $this->fremde->alsGelesen($quelle, $adresse, $art, $ids);
        } finally {
            // Auch nach einem Fehlschlag: Was drueben angekommen ist, soll
            // beim naechsten Laden stimmen, nicht eine Minute spaeter.
            GesamtInbox::pufferVergessen($person);
        }
    }

    /**
     * Alles abhaken, was dieser Person HIER ungelesen liegt.
     *
     * Nur die eigenen — fuer ein Produkt, das ausdruecklich nichts anderes
     * will. Der Knopf auf der Seite nimmt {@see alle()}.
     *
     * @return int Wie viele Zeilen sich tatsaechlich geaendert haben.
     */
    public function alleEigenen(Model $person): int
    {
        return $this->eigene->allesAlsGelesen($person);
    }

    /**
     * Alles abhaken — hier UND in jedem anderen System, das Zeilen dieser
     * Person zeigt und sie abhaken kann (pm #10227).
     *
     * ## Warum nicht mehr „nur die eigenen"
     *
     * Hier stand bis v0.8.0: „Was in einem anderen System liegt, kann
     * dieses hier weder zeigen noch abraeumen." Zeigen konnte es sie aber
     * laengst. Bei Bastian stand der Knopf auf „Alle 1 eigenen als
     * gelesen", das Abzeichen auf 1461 — und nach dem Klick auf 1460. Ein
     * Knopf, der neben einer solchen Zahl steht, wird als „alle" gelesen,
     * egal was draufsteht.
     *
     * ## Ein System, das nicht kann, haelt die anderen nicht auf
     *
     * Jedes System wird fuer sich gefragt. Scheitert eines (aelterer Stand
     * ohne `all`, nicht erreichbar), stehen seine Zeilen weiter da und sein
     * Name kommt in `failed` zurueck — die Oberflaeche sagt es. Die anderen
     * sind trotzdem abgehakt.
     *
     * @return array{marked: int, failed: array<int, string>}
     */
    public function alle(Model $person): array
    {
        $geaendert = $this->eigene->allesAlsGelesen($person);
        $gescheitert = [];

        $adresse = trim((string) $person->getAttribute('email'));

        if ($adresse !== '' && $this->fremde instanceof SchreibendeRemoteSource) {
            $faehig = app(GesamtInbox::class)->fremdeFaehigkeiten($person)['markable'];

            foreach ($faehig as $quelle => $arten) {
                if ($arten === [] || ! is_string($quelle) || $quelle === '') {
                    continue;
                }

                try {
                    $geaendert += $this->fremde->alleAlsGelesen($quelle, $adresse);
                } catch (\Throwable $fehler) {
                    $gescheitert[] = $quelle;

                    Log::warning('Ein System hat „alle als gelesen" nicht angenommen.', [
                        'system' => $quelle,
                        'error' => $fehler->getMessage(),
                    ]);
                }
            }

            GesamtInbox::pufferVergessen($person);
        }

        return ['marked' => $geaendert, 'failed' => $gescheitert];
    }
}
