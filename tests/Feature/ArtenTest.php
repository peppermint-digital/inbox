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

it('nennt den Verlauf einer Art, wenn sie einen hat (pm #10233)', function (): void {
    // Die Aktivitaeten-Seite stand in der Seitenleiste des Managers. Seit
    // sie dort weg ist, fuehrt der Reiter „Aktivitaeten" auf den ganzen
    // Verlauf — der Posteingang zeigt nur die neuesten Zeilen.
    app(InboxArten::class)->registriereViele([
        new InboxArt('activity', 'Aktivitäten', reihenfolge: 50, verlauf: '/activity'),
        new InboxArt('task', 'Neue Aufgaben', reihenfolge: 10),
    ]);

    $alle = app(InboxArten::class)->alle();

    expect($alle[1]['history'])->toBe('/activity')
        // Ohne Verlauf kein Schluessel: Die Form der uebrigen Arten bleibt,
        // wie sie war — auch fuer ein System, das ihn noch nicht kennt.
        ->and($alle[0])->not->toHaveKey('history');
});

it('uebernimmt den Verlauf einer FREMDEN Art nicht', function (): void {
    // Der Pfad gehoert dem anderen System. Hier angehaengt, fuehrte er auf
    // eine Seite, die es hier nicht gibt.
    $alle = app(InboxArten::class)->alle([], [
        ['key' => 'rechnung', 'label' => 'Belege', 'icon' => 'file-text', 'color' => 'orange', 'order' => 25, 'history' => '/belege'],
    ]);

    expect($alle[0])->not->toHaveKey('history');
});
