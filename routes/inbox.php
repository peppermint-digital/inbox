<?php

use Illuminate\Support\Facades\Route;
use Peppermint\Inbox\Http\Controllers\AbhakenController;
use Peppermint\Inbox\Http\Controllers\BestandController;

/*
|--------------------------------------------------------------------------
| Der Weg hinter dem Haken und die Zahl am Symbol
|--------------------------------------------------------------------------
|
| Beide liegen bewusst unter `posteingang` und NICHT unter `inbox`:
| `/inbox` ist in der Verwaltung seit laengerem der Belege-Posteingang, und
| `api/inbox` ist in allen drei Produkten vergeben. Dieselbe Verwechslung
| hat schon die Faehigkeit `inbox.list` gekostet.
|
| Pfade und Middleware stehen in `config/inbox.php` — ein Produkt, das die
| Seite anders schuetzt oder anders nennt, braucht hier nichts zu aendern.
|
*/

Route::middleware((array) config('inbox.route.middleware', ['web', 'auth']))
    ->group(function (): void {
        Route::post(
            (string) config('inbox.route.path', 'posteingang/gelesen'),
            AbhakenController::class,
        )->name((string) config('inbox.route.name', 'inbox.gelesen'));

        /*
         * Die GLOBALE Ungelesen-Zahl (#1152).
         *
         * Fuer das Posteingangs-Symbol in der Kopfzeile und die Kachel in
         * der Minibar. Beide holten sie bis zum 29.10.2026 aus
         * `/api/inbox/unread-count` — den Meldungen DIESES Systems, waehrend
         * sie den gemeinsamen Posteingang oeffnen. Siehe
         * {@see BestandController}.
         */
        Route::get(
            (string) config('inbox.route.bestand_path', 'posteingang/ungelesen'),
            BestandController::class,
        )->name((string) config('inbox.route.bestand_name', 'inbox.ungelesen'));
    });
