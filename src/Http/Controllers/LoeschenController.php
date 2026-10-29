<?php

namespace Peppermint\Inbox\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Peppermint\Inbox\Quellen\LoeschbareQuelle;
use Peppermint\Inbox\Quellen\Quellenverzeichnis;
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
 * ## Nur die EIGENEN Zeilen
 *
 * Anders als beim Abhaken gibt es hier keinen Weg ueber die Systemgrenze
 * und deshalb auch kein `source`-Feld. Die Begruendung steht in
 * {@see LoeschbareQuelle}. Kommt eine fremde
 * Zeile trotzdem an, faellt sie in {@see Quellenverzeichnis::loeschen()}
 * durch den Rechte-Filter der Quelle — die fragt nach der handelnden
 * Person, und eine fremde Kennung trifft dort nichts.
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
    public function __invoke(Request $request, Quellenverzeichnis $quellen): JsonResponse
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
            'kind' => ['required', 'string', 'max:120'],
            'ids' => ['required_without:alle', 'array', 'max:200'],
            'ids.*' => ['required', 'string', 'max:190'],
        ]);

        try {
            $geloescht = ($daten['alle'] ?? false)
                ? $quellen->allesLoeschen($person, (string) $daten['kind'])
                : $quellen->loeschen($person, (string) $daten['kind'], $daten['ids']);
        } catch (Throwable $fehler) {
            return response()->json(['message' => $fehler->getMessage()], 502);
        }

        return response()->json(['deleted' => $geloescht]);
    }
}
