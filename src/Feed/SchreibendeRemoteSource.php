<?php

namespace Peppermint\Inbox\Feed;

/**
 * Was ein ANDERES System ausser dem Abhaken einzelner Zeilen kann (pm #10227).
 *
 * ## Warum es das gibt
 *
 * Bis v0.8.0 ging über die Systemgrenze genau ein Schreibweg: einzelne
 * Kennungen abhaken. Im Manager hiess das am 25.11.2026: Bastian sah 1461
 * im Abzeichen, davon 1320 aus AI Brain. Der Knopf „Alle eigenen als
 * gelesen" nahm 1 davon, und die Liste zeigte je Art nur 50 — die übrigen
 * waren weder abzuhaken noch wegzumachen. Bastian: „wenn ich sie als
 * gelesen markiere, passiert nichts. Die Zahlen verändern sich nicht."
 *
 * ## Warum eine eigene Schnittstelle und keine neuen Methoden an RemoteSource
 *
 * Eine Methode mehr an {@see RemoteSource} bricht jede Umsetzung in einem
 * Produkt, das das Paket nur anhebt (im Manager umhüllt `VerbundenerPosteingang`
 * die Brücke). So bleibt eine alte Umsetzung gültig — sie kann eben nur
 * einzeln abhaken, und die Ansicht zeigt dann auch nur das an.
 *
 * ## Löschen über die Grenze
 *
 * Bis v0.8.0 stand am Löschen „bewusst nur lokal". Gelöscht wird weiter nur
 * auf ausdrücklichen Klick der Person und nur, was das Zielsystem selbst
 * als löschbar meldet (`deletable` in seiner Antwort). Ein System auf
 * älterem Stand meldet nichts — dann gibt es dort keinen Knopf.
 */
interface SchreibendeRemoteSource extends RemoteSource
{
    /**
     * Alles Abhakbare der Person in diesem System auf gelesen — oder nur
     * die eine Art, wenn sie genannt ist.
     *
     * @return int Wie viele Zeilen sich drüben geändert haben.
     */
    public function alleAlsGelesen(string $slug, string $adresse, ?string $art = null): int;

    /**
     * Einzelne Zeilen einer Art drüben löschen.
     *
     * @param  array<int, string>  $ids
     * @return int Wie viele Zeilen drüben gelöscht wurden.
     */
    public function loeschen(string $slug, string $adresse, string $art, array $ids): int;

    /**
     * Alle Zeilen einer Art der Person drüben löschen, auch die gelesenen.
     *
     * @return int Wie viele Zeilen drüben gelöscht wurden.
     */
    public function allesLoeschen(string $slug, string $adresse, string $art): int;
}
