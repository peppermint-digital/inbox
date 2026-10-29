<?php

namespace Peppermint\Inbox\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Peppermint\Inbox\Arten\InboxArten;
use Peppermint\Inbox\Feed\GesamtInbox;
use Peppermint\Inbox\Feed\SourceLabels;
use Peppermint\Inbox\Feed\Verweise;
use Peppermint\Inbox\Mcp\Concerns\FindetHandelndePerson;
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
 *
 * ## Hier werden die Verweise absolut (#1151)
 *
 * Und genau hier, nicht in {@see Quellenverzeichnis::sammeln()}: Dieselben
 * Zeilen tragen die EIGENE Seite, und dort gehoert der relative Verweis hin.
 * Diese Antwort dagegen verlaesst das System — und ein `/bugs/1122` meint
 * drueben etwas anderes oder nichts. Begruendung und Messung stehen in
 * {@see Verweise}.
 */
class ListInboxEntriesTool extends Tool
{
    use FindetHandelndePerson;

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
                'totals' => [],
                'markable' => [],
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
            /*
             * Der BESTAND je Art, nicht die Zahl der gelieferten Zeilen
             * (#1096).
             *
             * Ohne diese Angabe sieht der Aufrufer 50 Zeilen und weiss
             * nicht, dass 3953 dahinterstehen. Seine Ansicht sagt dann
             * entweder nichts oder etwas Falsches — und beim Abarbeiten
             * sinkt eine Zahl scheinbar nicht.
             */
            'totals' => $gesammelt['totals'],
            /*
             * An welchen Arten ein Haken hier ein Ziel findet.
             *
             * Der Aufrufer zeichnet den Knopf nur dort. Bis zum
             * 27.10.2026 stand die Regel dafuer im Produkt und lautete
             * „nur die eigenen Zeilen" — eine fremde Zeile hatte keinen
             * Haken, weil niemand wusste, ob er ankommt. Jetzt sagt es
             * das System, dem sie gehoert.
             */
            'markable' => $gesammelt['markable'],
            // KEIN Deckel ueber die Gesamtliste. Jede Quelle hat `$grenze`
            // bereits fuer SICH angewandt; ein zweiter Schnitt hier kappt
            // nach Anmelde-Reihenfolge statt nach Inhalt.
            //
            // Genau das ist am 19.10.2026 passiert: Der Manager meldet
            // Aufgaben, Aenderungen und Liegengebliebenes vor den
            // Meldungen an — zusammen 49. Die 50er-Grenze liess danach
            // genau EINE Meldung durch, und in den anderen drei Systemen
            // fehlten seine 50 Meldungen vollstaendig. Ohne Fehler, ohne
            // Hinweis: Die Liste sah nur kuerzer aus.
            //
            // Mit absoluten Verweisen, weil diese Liste das System
            // verlaesst (#1151) — siehe Klassenkopf.
            'items' => Verweise::absolut($gesammelt['items'], (string) config('app.url')),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()
                ->description('How many at most, per kind. Default 100, maximum 500.'),
        ];
    }
}
