<?php

namespace Peppermint\Inbox\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Peppermint\Inbox\Feed\Abhaken;
use Peppermint\Inbox\Mcp\Concerns\FindetHandelndePerson;
use Peppermint\Inbox\Quellen\Quellenverzeichnis;

/**
 * Die EIGENEN Inbox-Eintraege abhaken.
 *
 * Die Anbieter-Seite der Faehigkeit `inbox.read` — das Gegenstueck zu
 * {@see ListInboxEntriesTool} und `inbox.entries`.
 *
 * ## Warum das Paket diesen Handler mitbringt
 *
 * Damit die vier Produkte nur die neue Fassung brauchen und keinen eigenen
 * Code. Dasselbe Muster wie bei den Benachrichtigungs-Einstellungen (AI
 * Brain #520), wo die Pflegemaske im Brain steht und ueber Paket-Werkzeuge
 * in die Datenbank des Produkts schreibt.
 *
 * ## Fail-closed: ohne Person keine Zeile
 *
 * Beim Lesen antwortet {@see ListInboxEntriesTool} ohne Person mit einer
 * leeren Liste — aergerlich, aber harmlos. Hier ist es eine Absage: Ein
 * Eintrag gehoert immer genau einem Menschen, und „alle" waere beim
 * Schreiben kein Ergebnis, sondern ein Schaden ueber Systemgrenzen hinweg.
 *
 * Die Einschraenkung auf die handelnde Person IST die Rechtepruefung. Eine
 * Policy haette hier kein Subjekt: Es gibt keinen Parameter, mit dem man
 * den Posteingang von jemand anderem anfassen koennte — nicht einmal
 * absichtlich.
 *
 * ## Und warum die Art ein Pflichtfeld ist
 *
 * Kennungen sind nur innerhalb einer Art eindeutig. Ohne `kind` muesste
 * dieses Werkzeug alle Quellen mit derselben Liste fragen und traefe
 * womoeglich die Meldung 42 UND die Aufgabe 42 — eine Zeile, die niemand
 * angesehen hat, waere abgehakt.
 */
class MarkInboxEntriesReadTool extends Tool
{
    use FindetHandelndePerson;

    protected string $description = 'Mark inbox entries of the acting person in this system as read. Takes the kind and the ids of the rows, as reported by list-inbox-entries-tool. Only the acting person\'s own entries; nothing else can be addressed.';

    public function handle(Request $request, Quellenverzeichnis $quellen): Response
    {
        $person = $this->handelnde();

        if ($person === null) {
            return Response::error('No acting person for this call. An inbox entry always belongs to exactly one human, so there is nothing to mark.');
        }

        $art = trim((string) ($request->get('kind') ?? ''));

        if ($art === '') {
            return Response::error('Missing "kind": which kind do the ids belong to? Ids are only unique within a kind.');
        }

        $ids = array_values(array_filter(
            array_map(static fn ($id): string => trim((string) $id), (array) ($request->get('ids') ?? [])),
            static fn (string $id): bool => $id !== '',
        ));

        if ($ids === []) {
            return Response::error('Missing "ids": which entries should be marked read?');
        }

        try {
            $geaendert = $quellen->alsGelesen($person, $art, array_slice($ids, 0, Abhaken::MAX_IDS));
        } catch (\Throwable $fehler) {
            // Und zwar als Fehler, nicht als `marked: 0`. Null heisst „es
            // war nichts zu tun"; wer einen Fehlschlag so verpackt, laesst
            // die Zeile drueben rot stehen und sagt „erledigt".
            return Response::error($fehler->getMessage());
        }

        return Response::json([
            // Die Zahl der TATSAECHLICH geaenderten Zeilen, nicht die der
            // uebergebenen Kennungen. Hinter einer Zeile stecken oft
            // mehrere Datensaetze, und eine veraltete Kennung darf
            // mitfahren, ohne den Aufruf zu sprengen — wer genau hinsehen
            // will, vergleicht die beiden Zahlen.
            'marked' => $geaendert,
            'kind' => $art,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'kind' => $schema->string()
                ->description('The kind the ids belong to, as reported by list-inbox-entries-tool. Ids are only unique within a kind.')
                ->required(),
            'ids' => $schema->array()
                ->description(sprintf('The entry ids to mark read. At most %d per call.', Abhaken::MAX_IDS))
                ->required(),
        ];
    }
}
