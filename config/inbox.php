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
