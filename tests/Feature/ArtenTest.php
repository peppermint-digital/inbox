<?php

use Peppermint\Inbox\Arten\InboxArt;
use Peppermint\Inbox\Arten\InboxArten;

/**
 * Das Verzeichnis der Arten — die Stelle, an der die Inbox waechst.
 */
beforeEach(function (): void {
    app(InboxArten::class)->vergessen();
});

it('sortiert die Reiter nach Reihenfolge, nicht nach Anmeldezeitpunkt', function (): void {
    // Sonst haengt die Reiterleiste davon ab, in welcher Zeile eines
    // Dienstanbieters etwas steht — und sieht in zwei Systemen anders aus,
    // obwohl beide dasselbe anmelden.
    app(InboxArten::class)->registriereViele([
        new InboxArt('aktivitaet', 'Aktivitäten', reihenfolge: 50),
        new InboxArt('aufgabe', 'Neue Aufgaben', reihenfolge: 10),
        new InboxArt('meldung', 'Benachrichtigungen', reihenfolge: 40),
    ]);

    expect(array_column(app(InboxArten::class)->alle(), 'key'))
        ->toBe(['aufgabe', 'meldung', 'aktivitaet']);
});

it('wirft eine unbekannte Art NICHT weg, sondern haengt sie hinten an', function (): void {
    // Beim Ausrollen ist immer ein System einen Schritt weiter. Faellt eine
    // fremde Art hier heraus, verschwinden ihre Zeilen — und zwar genau in
    // dem Moment, in dem jemand eine neue Kategorie einfuehrt und nachsehen
    // will, ob sie ankommt.
    app(InboxArten::class)->registrieren(new InboxArt('aufgabe', 'Neue Aufgaben', reihenfolge: 10));

    $alle = app(InboxArten::class)->alle(['rechnung']);

    expect(array_column($alle, 'key'))->toBe(['aufgabe', 'rechnung'])
        // Kein huebsches Wort, aber auffindbar — besser als nicht da.
        ->and($alle[1]['label'])->toBe('rechnung');
});

it('kennt eine Art erst, wenn sie angemeldet wurde', function (): void {
    expect(app(InboxArten::class)->kennt('aufgabe'))->toBeFalse();

    app(InboxArten::class)->registrieren(new InboxArt('aufgabe', 'Neue Aufgaben'));

    expect(app(InboxArten::class)->kennt('aufgabe'))->toBeTrue();
});
