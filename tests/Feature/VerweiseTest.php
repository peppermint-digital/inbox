<?php

use Peppermint\Inbox\Feed\Verweise;

/**
 * Verweise auf dem Weg aus dem System heraus (AI Brain #1151).
 *
 * Die Pfade in diesen Tests sind die, die am 29.10.2026 in Produktion
 * gemessen wurden — nicht erfundene. `/tasks/9057` steht bewusst dabei: Er
 * ist der Fall, der keinen 404 ergab, sondern im Manager und im CRM eine
 * fremde Aufgabe derselben Nummer oeffnete.
 */
it('setzt die eigene Adresse vor einen relativen Verweis', function (): void {
    $zeilen = Verweise::absolut(
        [['id' => '1', 'link' => '/bugs/1122'], ['id' => '2', 'link' => 'tasks/9057']],
        'https://brain.proxy.peppermint-digital.com',
    );

    expect(array_column($zeilen, 'link'))->toBe([
        'https://brain.proxy.peppermint-digital.com/bugs/1122',
        // Auch ohne fuehrenden Schraegstrich — eine Quelle, die ihn
        // vergisst, soll keinen doppelten Schraegstrich erzeugen.
        'https://brain.proxy.peppermint-digital.com/tasks/9057',
    ]);
});

it('laesst einen schon absoluten Verweis unberuehrt', function (): void {
    // Der Manager baut seine Verweise seit immer selbst absolut. Ein
    // zweites Praefix davor machte aus einem richtigen einen kaputten —
    // und zwar nur in den Zeilen, die vorher funktionierten.
    $zeilen = Verweise::absolut(
        [['link' => 'https://pm.peppermint-digital.com/tasks/42']],
        'https://brain.proxy.peppermint-digital.com',
    );

    expect($zeilen[0]['link'])->toBe('https://pm.peppermint-digital.com/tasks/42');
});

it('ist mehrfach anwendbar, ohne den Verweis zu veraendern', function (): void {
    // Zwischen Paketversionen kann dieselbe Zeile zweimal durch diese
    // Stelle laufen (Anbieter neu, Gateway alt oder umgekehrt). Das darf
    // nichts kosten.
    $einmal = Verweise::einzeln('/avatar', 'https://brain.proxy.peppermint-digital.com');
    $zweimal = Verweise::einzeln($einmal, 'https://brain.proxy.peppermint-digital.com');

    expect($zweimal)->toBe($einmal)
        ->and($zweimal)->toBe('https://brain.proxy.peppermint-digital.com/avatar');
});

it('laesst andere Schemata und protokollrelative Adressen stehen', function (): void {
    $zeilen = Verweise::absolut(
        [['link' => 'mailto:hallo@peppermint-digital.com'], ['link' => '//vw.peppermint-digital.com/tickets/32']],
        'https://brain.proxy.peppermint-digital.com',
    );

    expect(array_column($zeilen, 'link'))->toBe([
        'mailto:hallo@peppermint-digital.com',
        '//vw.peppermint-digital.com/tickets/32',
    ]);
});

it('laesst eine Zeile ohne Verweis in Ruhe', function (): void {
    // Kein Ziel zu haben ist eine Aussage, kein fehlender Wert: Eine
    // imkr-Meldung gehoert zu einem Pane und zu keiner Seite. Ein
    // erfundenes Ziel waere schlechter als keines.
    $zeilen = Verweise::absolut(
        [['id' => '1', 'link' => null], ['id' => '2', 'title' => 'ohne Feld']],
        'https://brain.proxy.peppermint-digital.com',
    );

    expect($zeilen[0]['link'])->toBeNull()
        ->and($zeilen[1])->not->toHaveKey('link');
});

it('laesst den relativen Verweis stehen, wenn es keine Grundadresse gibt', function (): void {
    // Lieber ein Verweis, der zu Hause stimmt und drueben auffaellt, als
    // einer, der absolut AUSSIEHT und nirgends stimmt.
    expect(Verweise::absolut([['link' => '/bugs/1122']], ''))
        ->toBe([['link' => '/bugs/1122']])
        ->and(Verweise::einzeln('/bugs/1122', null))->toBe('/bugs/1122');
});
