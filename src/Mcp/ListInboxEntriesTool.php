<?php

namespace Peppermint\Inbox\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Peppermint\AiBrainBridge\Facades\AiBrain;
use Peppermint\AiBrainBridge\Verbund\Bruecke;
use Peppermint\Inbox\Arten\InboxArten;
use Peppermint\Inbox\Feed\GesamtInbox;
use Peppermint\Inbox\Feed\SourceLabels;
use Peppermint\Inbox\Quellen\Quellenverzeichnis;

/**
 * Die EIGENEN Inbox-Eintraege herausgeben.
 *
 * Die Anbieter-Seite der Faehigkeit `inbox.entries`.
 *
 * ## Warum nicht `inbox.list`
 *
 * Die war schon vergeben — fuer den Aufgaben-Posteingang des Managers
 * und der Verwaltung, mit einer ganz anderen Antwortform
 * (`inbox`/`unread` statt `items`/`kinds`). Beides „Inbox" zu nennen
 * waere richtig und trotzdem falsch: Ein Aufrufer, der die eine erwartet
 * und die andere bekommt, sieht eine leere Liste und keinen Fehler. Ein anderes System fragt,
 * dieses antwortet mit dem, was hier fuer die handelnde Person steht —
 * Aufgaben, Meldungen, Liegengebliebenes, was auch immer angemeldet ist.
 *
 * ## Nur die eigenen, nie die geholten
 *
 * Bewusst das {@see Quellenverzeichnis} und nicht die
 * {@see GesamtInbox}: Letztere wuerde ihrerseits die
 * anderen Systeme befragen. Vier Systeme, die sich gegenseitig nach ihrer
 * Gesamtansicht fragen, ergeben keine Liste, sondern eine Schleife.
 *
 * ## Fail-closed
 *
 * Ohne handelnde Person eine leere Liste und kein Fehler. Ein Eintrag
 * gehoert immer genau einem Menschen; ein Aufruf ohne Person koennte nur
 * „alle" meinen, und das waere ein Datenleck ueber Systemgrenzen hinweg.
 */
class ListInboxEntriesTool extends Tool
{
    protected string $description = 'List the inbox entries of the acting person in this system — tasks, notifications and whatever else this product contributes. Read-only; the caller merges them with its own.';

    public function handle(Request $request, Quellenverzeichnis $quellen): Response
    {
        $grenze = min(max((int) ($request->get('limit') ?? 100), 1), 500);

        $person = $this->handelnde();

        if ($person === null) {
            return Response::json([
                'source' => SourceLabels::local(),
                'items' => [],
                'kinds' => [],
                'failed' => [],
            ]);
        }

        $gesammelt = $quellen->sammeln($person, $grenze);

        return Response::json([
            // Das eigene Wort liegt bei, damit der Aufrufer die Zeilen
            // zuordnen kann, ohne es aus der Antwort zu raten.
            'source' => SourceLabels::local(),
            // Die Arten mitzuschicken ist der Unterschied zu einer festen
            // Reiterleiste: Ein System, das eine neue Kategorie einfuehrt,
            // bringt sie den anderen mit, ohne dass dort etwas zu tun waere.
            //
            // Und zwar mit WORT, Symbol und Reihenfolge — nicht nur dem
            // Schluessel. Sonst steht drueben „document" statt „Belege
            // sichten", und die Erweiterbarkeit endet an der Beschriftung.
            'kinds' => array_map(
                fn (string $art): array => app(InboxArten::class)->holen($art)->toArray(),
                $gesammelt['kinds'],
            ),
            // Welche eigene Quelle gerade NICHT konnte. Ohne diese Angabe
            // sieht der Aufrufer nur eine kuerzere Liste — und das sieht
            // aus wie „da ist nichts", nicht wie ein Ausfall.
            'failed' => $gesammelt['failed'],
            'items' => array_slice($gesammelt['items'], 0, $grenze),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()
                ->description('How many at most, per kind. Default 100, maximum 500.'),
        ];
    }

    /**
     * Die handelnde Person — oder `null`.
     *
     * Die Bruecke setzt sie aus der Kopfzeile des Aufrufs; ohne Bruecke ist
     * es die angemeldete Person. Beides abzufragen kostet nichts und macht
     * das Werkzeug auch ohne Hub benutzbar.
     */
    private function handelnde(): mixed
    {
        if (Bruecke::eingerichtet()) {
            $adresse = AiBrain::actingUserEmail();

            if (is_string($adresse) && trim($adresse) !== '') {
                $modell = (string) config('inbox.user_model');

                return $modell::query()->where('email', $adresse)->first();
            }
        }

        return auth()->user();
    }
}
