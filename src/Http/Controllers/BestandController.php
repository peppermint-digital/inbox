<?php

namespace Peppermint\Inbox\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Peppermint\Inbox\Feed\GesamtInbox;
use Throwable;

/**
 * Die Zahl am Posteingangs-Symbol und an der Minibar-Kachel (AI Brain #1152).
 *
 * ## Warum es diesen Endpunkt gibt
 *
 * Bis zum 29.10.2026 holten BEIDE Kacheln ihre Zahl aus
 * `/api/inbox/unread-count` — den ungelesenen MELDUNGEN dieses einen
 * Systems. Geoeffnet haben sie aber den gemeinsamen Posteingang, in dem
 * auch Aufgaben, Belege und imkr-Zeilen aus vier Systemen stehen. Die Zahl
 * sprach also ueber etwas anderes als die Seite dahinter.
 *
 * Die Antwortform ist bewusst dieselbe (`{"unread_count": n}`): Beide
 * Kacheln lesen sie heute schon so, und eine neue Form haette denselben
 * Umbau in zwei Paketen und vier Produkten bedeutet, fuer keinen Gewinn.
 *
 * ## Ein Netzaussetzer darf keine Null werden
 *
 * Eine Null heisst „alles gelesen", und das ist die eine Aussage, die
 * dieser Endpunkt nicht aus Versehen machen darf. Faellt die Abfrage,
 * kommt ein 503 ohne Zahl — die Kachel laesst dann ihren letzten
 * bekannten Wert stehen, und genau so ist sie gebaut.
 */
class BestandController
{
    public function __invoke(Request $request, GesamtInbox $inbox): JsonResponse
    {
        $person = $request->user();

        if ($person === null) {
            // Sollte die Middleware abgefangen haben. Doppelt, weil sie
            // aus der Konfiguration kommt und ein Produkt sie falsch
            // setzen kann.
            return response()->json(['message' => 'Nicht angemeldet.'], 401);
        }

        try {
            return response()->json([
                'unread_count' => $inbox->bestand($person, frisch: $request->boolean('frisch')),
            ]);
        } catch (Throwable $fehler) {
            report($fehler);

            return response()->json(['message' => 'Bestand nicht ermittelbar.'], 503);
        }
    }
}
