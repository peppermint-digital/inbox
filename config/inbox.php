<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Das Benutzer-Modell
    |--------------------------------------------------------------------------
    |
    | Wird gebraucht, um die handelnde Person aus einer Adresse aufzuloesen,
    | wenn der Aufruf ueber die Bruecke kommt.
    |
    */
    'user_model' => 'App\\Models\\User',

    /*
    |--------------------------------------------------------------------------
    | Wie viele Zeilen je Art UND System
    |--------------------------------------------------------------------------
    |
    | Bewusst je Fach und nicht global. Ein globaler Deckel laesst das
    | lauteste System die Obergrenze allein fuellen; die anderen fallen
    | unsichtbar hinten herunter, und die Liste sieht vollstaendig aus.
    |
    | Genau dieser Fehler hat die Posteingangs-Ansicht schon zweimal
    | ausgehoehlt — einmal im Paket, einmal im Manager.
    |
    */
    'max_items' => 50,

    /*
    |--------------------------------------------------------------------------
    | Der Weg hinter dem Haken und die Zahl am Symbol
    |--------------------------------------------------------------------------
    |
    | Zwei Routen, die das Paket mitbringt. Bewusst unter `posteingang` und
    | NICHT unter `inbox`: `/inbox` ist in der Verwaltung seit laengerem der
    | Belege-Posteingang, `api/inbox` in allen drei Produkten vergeben.
    | Dieselbe Verwechslung hat schon die Faehigkeit `inbox.list` gekostet.
    |
    | `middleware` ist die Stelle, an der die Autorisierung im PRODUKT
    | bleibt: Wer die Seite nicht sehen darf, kommt auch hier nicht durch.
    |
    | `enabled` auf `false` fuer ein Produkt ohne abhakbare Quelle. Dann
    | gehoert in der Ansicht `readHref={null}` dazu — sonst zeichnet sie
    | einen Knopf, der ins Leere zeigt. Achtung: Es schaltet BEIDE Routen
    | ab, also auch die Zahl.
    |
    | `bestand_*` ist die globale Ungelesen-Zahl fuer das Posteingangs-
    | Symbol und die Minibar-Kachel (#1152) — ueber alle Arten und Systeme,
    | nicht nur die Meldungen dieses einen.
    |
    | `loeschen_*` ist der Weg hinter dem Loeschknopf (#9069). Er trifft nur
    | die EIGENEN Zeilen und nur Arten, deren Quelle `LoeschbareQuelle`
    | umsetzt — Aufgaben und Belege also nie.
    |
    */
    'route' => [
        'enabled' => true,
        'path' => 'posteingang/gelesen',
        'name' => 'inbox.gelesen',
        'bestand_path' => 'posteingang/ungelesen',
        'bestand_name' => 'inbox.ungelesen',
        'loeschen_path' => 'posteingang/zeilen',
        'loeschen_name' => 'inbox.loeschen',
        'middleware' => ['web', 'auth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Wie die Systeme heissen
    |--------------------------------------------------------------------------
    |
    | Ueber die Leitung kommt ein Kuerzel, in der Liste soll ein Wort stehen.
    | Dieselbe Zuordnung in jedem System. Ein fehlender Eintrag ist
    | gutmuetig: Dann steht das Kuerzel da, und die Zeilen bleiben
    | erreichbar.
    |
    */
    'labels' => [
        'local' => null,

        'sources' => [
            'ai-brain' => 'AI Brain',
            'peppermint-manager' => 'Peppermint Manager',
            'peppermint-crm' => 'Peppermint CRM',
            'peppermint-verwaltung' => 'Peppermint Verwaltung',
        ],
    ],

];
