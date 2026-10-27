<?php

namespace Peppermint\Inbox\Feed;

use Illuminate\Database\Eloquent\Model;
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

        return $this->fremde->alsGelesen($quelle, $adresse, $art, $ids);
    }

    /**
     * Alles abhaken, was dieser Person HIER ungelesen liegt.
     *
     * Bewusst nur die eigenen. Was in einem anderen System liegt, kann
     * dieses hier weder zeigen noch abraeumen, und es in einen Knopf zu
     * mischen, der „alle" sagt, waere ein Versprechen, das er nicht haelt.
     *
     * @return int Wie viele Zeilen sich tatsaechlich geaendert haben.
     */
    public function alleEigenen(Model $person): int
    {
        return $this->eigene->allesAlsGelesen($person);
    }
}
