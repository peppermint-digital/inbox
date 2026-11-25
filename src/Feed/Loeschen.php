<?php

namespace Peppermint\Inbox\Feed;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Peppermint\Inbox\Quellen\Quellenverzeichnis;
use RuntimeException;

/**
 * Zeilen wegmachen — egal, in welchem System sie liegen (pm #10227).
 *
 * Das Gegenstueck zu {@see Abhaken}, mit derselben Entscheidung: Eine Zeile
 * ohne Herkunft geht an die eigene Quelle, eine mit Herkunft ueber
 * {@see SchreibendeRemoteSource} dorthin, wo sie liegt.
 *
 * ## Die Faehigkeit
 *
 * `inbox.delete` im Komm-Layer, drueben als `delete-inbox-entries-tool`.
 *
 * ## Was geloescht werden darf
 *
 * Nur, was das Zielsystem selbst als loeschbar meldet — die Oberflaeche
 * zeichnet den Knopf nur dort, und drueben prueft die Quelle noch einmal
 * (eine Art ohne Loeschvertrag wirft). Aufgaben und Belege also nie
 * (Bastian, 29.10.2026: „Aufgaben und Belege nie.").
 */
class Loeschen
{
    public const CAPABILITY = 'inbox.delete';

    public const TOOL = 'delete-inbox-entries-tool';

    public const MAX_IDS = 200;

    public function __construct(
        private readonly Quellenverzeichnis $eigene,
        private readonly RemoteSource $fremde,
    ) {}

    /**
     * Diese Zeilen loeschen.
     *
     * @param  string|null  $quelle  Herkunftssystem; `null` heisst „von hier".
     * @param  array<int, string>  $ids
     *
     * @throws RuntimeException wenn es nicht ankam oder die Art nicht loeschbar ist.
     */
    public function zeilen(Model $person, ?string $quelle, string $art, array $ids): int
    {
        $ids = array_slice(array_values(array_filter(
            array_map(static fn ($id): string => (string) $id, $ids),
            static fn (string $id): bool => $id !== '',
        )), 0, self::MAX_IDS);

        if ($ids === []) {
            return 0;
        }

        if ($quelle === null || $quelle === '') {
            return $this->eigene->loeschen($person, $art, $ids);
        }

        try {
            return $this->schreibend()->loeschen($quelle, $this->adresse($person), $art, $ids);
        } finally {
            GesamtInbox::pufferVergessen($person);
        }
    }

    /**
     * Alle Zeilen einer Art loeschen — hier und in jedem System, das diese
     * Art fuer die Person als loeschbar meldet.
     *
     * Die Art bleibt Pflicht: „Alle meine Meldungen loeschen" ist eine
     * Aussage ueber Meldungen (#9069), auch ueber die Systemgrenze.
     *
     * Wie beim Abhaken haelt ein System, das nicht kann, die anderen nicht
     * auf; es steht danach in `failed`.
     *
     * @return array{deleted: int, failed: array<int, string>}
     *
     * @throws RuntimeException wenn die Art nirgends loeschbar ist.
     */
    public function alleDerArt(Model $person, string $art): array
    {
        $geloescht = 0;
        $gescheitert = [];
        $getroffen = false;

        if ($this->eigene->istLoeschbar($art)) {
            $getroffen = true;
            $geloescht += $this->eigene->allesLoeschen($person, $art);
        }

        $adresse = trim((string) $person->getAttribute('email'));

        if ($adresse !== '' && $this->fremde instanceof SchreibendeRemoteSource) {
            foreach (app(GesamtInbox::class)->fremdeFaehigkeiten($person)['deletable'] as $quelle => $arten) {
                if (! is_string($quelle) || $quelle === '' || ! in_array($art, (array) $arten, true)) {
                    continue;
                }

                $getroffen = true;

                try {
                    $geloescht += $this->fremde->allesLoeschen($quelle, $adresse, $art);
                } catch (\Throwable $fehler) {
                    $gescheitert[] = $quelle;

                    Log::warning('Ein System hat „alle loeschen" nicht angenommen.', [
                        'system' => $quelle,
                        'kind' => $art,
                        'error' => $fehler->getMessage(),
                    ]);
                }
            }

            GesamtInbox::pufferVergessen($person);
        }

        if (! $getroffen) {
            throw new RuntimeException("Die Art '{$art}' kann hier nicht geloescht werden.");
        }

        return ['deleted' => $geloescht, 'failed' => $gescheitert];
    }

    private function schreibend(): SchreibendeRemoteSource
    {
        if (! $this->fremde instanceof SchreibendeRemoteSource) {
            throw new RuntimeException('Dieses System kann Zeilen eines anderen Systems nicht loeschen.');
        }

        return $this->fremde;
    }

    private function adresse(Model $person): string
    {
        $adresse = trim((string) $person->getAttribute('email'));

        if ($adresse === '') {
            throw new RuntimeException('Ohne E-Mail-Adresse findet das andere System die Person nicht.');
        }

        return $adresse;
    }
}
