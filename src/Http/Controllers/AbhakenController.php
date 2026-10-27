<?php

namespace Peppermint\Inbox\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Peppermint\Inbox\Feed\Abhaken;
use Throwable;

/**
 * Der Endpunkt hinter dem Haken.
 *
 * ## Warum der im Paket liegt und nicht im Produkt
 *
 * Weil es in jedem der vier Produkte dieselben dreissig Zeilen waeren —
 * und damit die dritte Pruefrage des Paket-Vertrags („Schreibt jedes
 * Produkt denselben Verdrahtungscode?") mit ja beantwortet. Was im Produkt
 * bleibt, ist die Autorisierung: `middleware` kommt aus der Konfiguration,
 * und wer die Seite nicht sehen darf, kommt auch hier nicht durch.
 *
 * ## Der Fehlschlag ist der wichtige Fall
 *
 * Das Holen wirft absichtlich eine Ausnahme statt einer leeren Liste,
 * damit die Ansicht „nicht erreichbar" von „nichts da" trennen kann.
 * Dieselbe Regel beim Schreiben: Ein Fehlschlag kommt mit Status 502 und
 * einem Satz zurueck, nicht als `marked: 0`. Wer abhakt und es kommt nicht
 * an, muss das sehen — sonst steht die Zeile beim Neuladen wieder da, und
 * der Fehler sieht aus wie der Fix.
 */
class AbhakenController
{
    public function __invoke(Request $request, Abhaken $abhaken): JsonResponse
    {
        $person = $request->user();

        if ($person === null) {
            // Sollte die Middleware schon abgefangen haben. Doppelt, weil
            // die Middleware aus der Konfiguration kommt und ein Produkt
            // sie falsch setzen kann — und ein Posteingang ohne Person
            // haette kein Subjekt, nur eine Richtung.
            return response()->json(['message' => 'Nicht angemeldet.'], 401);
        }

        $daten = $request->validate([
            // „Alle eigenen": Dafuer gibt es keine Kennungen. Bei 3953
            // ungelesenen Meldungen waere die Liste der Kennungen eine
            // Fahrt durch den Browser, und der Deckel je Aufruf wuerde sie
            // kappen — der Knopf haette dann einen Teil erledigt und
            // „alle" gesagt.
            'alle' => ['sometimes', 'boolean'],
            'source' => ['nullable', 'string', 'max:120'],
            'kind' => ['required_without:alle', 'string', 'max:120'],
            'ids' => ['required_without:alle', 'array', 'max:'.Abhaken::MAX_IDS],
            'ids.*' => ['required', 'string', 'max:190'],
        ]);

        try {
            $geaendert = ($daten['alle'] ?? false)
                ? $abhaken->alleEigenen($person)
                : $abhaken->zeilen(
                    $person,
                    $daten['source'] ?? null,
                    (string) $daten['kind'],
                    $daten['ids'],
                );
        } catch (Throwable $fehler) {
            return response()->json([
                // Der Wortlaut der Quelle, nicht ein eigener: Er nennt das
                // System und den Grund, und genau das braucht der, der
                // nachsieht.
                'message' => $fehler->getMessage(),
            ], 502);
        }

        return response()->json(['marked' => $geaendert]);
    }
}
