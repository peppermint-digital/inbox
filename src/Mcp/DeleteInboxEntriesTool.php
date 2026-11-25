<?php

namespace Peppermint\Inbox\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Peppermint\Inbox\Feed\Loeschen;
use Peppermint\Inbox\Mcp\Concerns\FindetHandelndePerson;
use Peppermint\Inbox\Mcp\Concerns\LiestTypen;
use Peppermint\Inbox\Quellen\LoeschbareQuelle;
use Peppermint\Inbox\Quellen\Quellenverzeichnis;

/**
 * Die EIGENEN Inbox-Eintraege loeschen (pm #10227).
 *
 * Die Anbieter-Seite der Faehigkeit `inbox.delete` — das Gegenstueck zu
 * {@see MarkInboxEntriesReadTool}, mit denselben Riegeln:
 *
 * - Ohne handelnde Person keine Zeile. Es gibt keinen Parameter fuer
 *   jemand anderen.
 * - Die Art ist Pflicht, auch bei `all`: „Alle meine Meldungen loeschen"
 *   ist eine Aussage ueber Meldungen.
 * - Nur Arten, deren Quelle den Loeschvertrag umsetzt
 *   ({@see LoeschbareQuelle}). Eine Aufgabe oder
 *   ein Beleg wirft — die erledigen sich an der Sache, nicht im Posteingang.
 *
 * ## Warum es das gibt
 *
 * Im Manager stand der groesste Teil von Bastians Posteingang aus AI Brain
 * da, und nichts davon liess sich wegmachen. In AI Brain selbst ging es.
 * Das Werkzeug macht denselben Knopf dort moeglich, wo die Zeile nur
 * angezeigt wird.
 */
class DeleteInboxEntriesTool extends Tool
{
    use FindetHandelndePerson;
    use LiestTypen;

    protected string $description = 'Delete inbox entries of the acting person in this system. Takes the kind and either the ids (as reported by list-inbox-entries-tool) or all=true for every entry of that kind, read or unread. Only kinds this system reports as deletable; only the acting person\'s own entries.';

    public function handle(Request $request, Quellenverzeichnis $quellen): Response
    {
        $person = $this->handelnde();

        if ($person === null) {
            return Response::error('No acting person for this call. An inbox entry always belongs to exactly one human, so there is nothing to delete.');
        }

        $art = trim((string) ($request->get('kind') ?? ''));

        if ($art === '') {
            return Response::error('Missing "kind": which kind should be deleted? It is required even with all=true.');
        }

        try {
            if (filter_var($request->get('all'), FILTER_VALIDATE_BOOLEAN)) {
                return Response::json([
                    'deleted' => $quellen->allesLoeschen($person, $art, $this->typen($request)),
                    'kind' => $art,
                    'all' => true,
                ]);
            }

            $ids = array_values(array_filter(
                array_map(static fn ($id): string => trim((string) $id), (array) ($request->get('ids') ?? [])),
                static fn (string $id): bool => $id !== '',
            ));

            if ($ids === []) {
                return Response::error('Missing "ids": which entries should be deleted? Or pass all=true.');
            }

            return Response::json([
                'deleted' => $quellen->loeschen($person, $art, array_slice($ids, 0, Loeschen::MAX_IDS)),
                'kind' => $art,
            ]);
        } catch (\Throwable $fehler) {
            // Ein Fehler, keine Null — wie beim Abhaken.
            return Response::error($fehler->getMessage());
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'kind' => $schema->string()
                ->description('The kind to delete, as reported by list-inbox-entries-tool (see "deletable"). Required, also with all=true.')
                ->required(),
            'ids' => $schema->array()
                ->description(sprintf('The entry ids to delete. At most %d per call.', Loeschen::MAX_IDS)),
            'all' => $schema->boolean()
                ->description('Delete every entry of this kind of the acting person, read or unread.'),
            'types' => $schema->array()
                ->description('With all=true: only notifications of these types ("" = without type), the same list the caller shows.'),
        ];
    }
}
