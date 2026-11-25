<?php

namespace Peppermint\Inbox\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Peppermint\Inbox\Feed\Loeschen;
use Throwable;

/**
 * Der Endpunkt hinter dem Loeschknopf (AI Brain #9069).
 *
 * ## Warum er hier liegt und nicht im Produkt
 *
 * Aus demselben Grund wie {@see AbhakenController}: Es waeren in jedem der
 * vier Produkte dieselben dreissig Zeilen. Was im Produkt bleibt, ist die
 * Autorisierung — `middleware` kommt aus der Konfiguration, und wer die
 * Seite nicht sehen darf, kommt auch hier nicht durch.
 *
 * ## Auch fremde Zeilen (seit v0.8.0, pm #10227)
 *
 * Bis v0.8.0 stand hier „nur die EIGENEN Zeilen, kein `source`-Feld". Seit
 * Bastian im Manager 1320 AI-Brain-Zeilen vor sich hatte und keine davon
 * wegmachen konnte, geht eine Zeile mit Herkunft ueber {@see Loeschen}
 * dorthin, wo sie liegt — und nur, wenn das System die Art selbst als
 * loeschbar meldet. Drueben prueft die Quelle die handelnde Person; eine
 * fremde Kennung trifft dort nichts.
 *
 * ## `kind` ist Pflicht, auch bei „alle"
 *
 * „Alle meine Meldungen loeschen" ist eine Aussage ueber MELDUNGEN. Ohne
 * die Art muesste der Server raten, und beim naechsten loeschbaren
 * Vokabular naehme derselbe Knopf stillschweigend mehr mit, als
 * draufsteht.
 *
 * ## Der Fehlschlag ist der wichtige Fall
 *
 * Wie beim Abhaken: 502 mit dem Wortlaut der Quelle statt `deleted: 0`.
 * Wer loescht und es kommt nicht an, muss das sehen — sonst steht die
 * Zeile beim Neuladen wieder da, und der Fehler sieht aus wie der Fix.
 */
class LoeschenController
{
    public function __invoke(Request $request, Loeschen $loeschen): JsonResponse
    {
        $person = $request->user();

        if ($person === null) {
            // Sollte die Middleware abgefangen haben. Doppelt, weil sie aus
            // der Konfiguration kommt und ein Produkt sie falsch setzen
            // kann — und ein Loeschen ohne Person haette kein Subjekt.
            return response()->json(['message' => 'Nicht angemeldet.'], 401);
        }

        $daten = $request->validate([
            // „Alle": Dafuer gibt es keine Kennungen. Bei tausenden Zeilen
            // waere die Liste eine Fahrt durch den Browser, und der Deckel
            // je Aufruf wuerde sie kappen — der Knopf haette dann einen
            // Teil erledigt und „alle" gesagt.
            'alle' => ['sometimes', 'boolean'],
            // Herkunft seit v0.8.0 (pm #10227): `null` heisst „von hier".
            'source' => ['nullable', 'string', 'max:120'],
            'kind' => ['required', 'string', 'max:120'],
            'ids' => ['required_without:alle', 'array', 'max:200'],
            'ids.*' => ['required', 'string', 'max:190'],
        ]);

        try {
            if ($daten['alle'] ?? false) {
                return response()->json($loeschen->alleDerArt($person, (string) $daten['kind']));
            }

            $geloescht = $loeschen->zeilen($person, $daten['source'] ?? null, (string) $daten['kind'], $daten['ids']);
        } catch (Throwable $fehler) {
            return response()->json(['message' => $fehler->getMessage()], 502);
        }

        return response()->json(['deleted' => $geloescht]);
    }
}
