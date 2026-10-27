<?php

use Illuminate\Support\Facades\Route;
use Peppermint\Inbox\Http\Controllers\AbhakenController;

/*
|--------------------------------------------------------------------------
| Der Weg hinter dem Haken
|--------------------------------------------------------------------------
|
| Eine Route, und sie liegt bewusst unter `posteingang` und NICHT unter
| `inbox`: `/inbox` ist in der Verwaltung seit laengerem der Belege-
| Posteingang, und `api/inbox` ist in allen drei Produkten vergeben.
| Dieselbe Verwechslung hat schon die Faehigkeit `inbox.list` gekostet.
|
| Pfad und Middleware stehen in `config/inbox.php` — ein Produkt, das die
| Seite anders schuetzt oder anders nennt, braucht hier nichts zu aendern.
|
*/

Route::middleware((array) config('inbox.route.middleware', ['web', 'auth']))
    ->post(
        (string) config('inbox.route.path', 'posteingang/gelesen'),
        AbhakenController::class,
    )
    ->name((string) config('inbox.route.name', 'inbox.gelesen'));
