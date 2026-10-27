<?php

namespace Peppermint\Inbox\Feed;

/**
 * Woher die Eintraege der ANDEREN Systeme kommen.
 *
 * Es gibt zwei Wege, und beide sind richtig — je nachdem, wo man steht:
 *
 * - Ein **Produkt** fragt ueber die Bruecke beim AI Brain an, und das Brain
 *   reicht an das Zielsystem weiter ({@see BridgeRemoteSource}).
 * - Das **Brain** fragt selbst, ueber seinen eigenen Gateway. Es hat keine
 *   Bruecke, es IST die Gegenstelle.
 *
 * Deshalb eine Schnittstelle und keine Klasse: Das aufnehmende System
 * bindet die Umsetzung, die zu seiner Lage passt.
 */
interface RemoteSource
{
    /**
     * Welche Systeme ueberhaupt antworten koennen.
     *
     * @return array<int, array{slug: string, key?: string, label?: string}>
     */
    public function anbieter(): array;

    /**
     * Die Eintraege eines Systems fuer diese Adresse.
     *
     * @return array{items: array<int, array<string, mixed>>, kinds: array<int, array<string, mixed>>, failed: array<int, string>, totals: array<string, int>, markable: array<int, string>}
     */
    public function holen(string $slug, string $adresse, int $grenze): array;

    /**
     * Zeilen eines ANDEREN Systems auf gelesen setzen.
     *
     * Spiegelbildlich zu {@see holen()}: dieselbe Richtung, dieselbe
     * Adresse als Schluessel fuer den Menschen, nur die Faehigkeit
     * wechselt von `inbox.entries` zu `inbox.read`.
     *
     * ## Warum die Art mitkommt
     *
     * Kennungen sind nur innerhalb einer Art eindeutig. Ohne `$art` muesste
     * das Zielsystem alle seine Quellen mit derselben Liste fragen — und
     * traefe womoeglich die Meldung 42 UND die Aufgabe 42. Eine Zeile, die
     * niemand angesehen hat, waere dann abgehakt.
     *
     * ## Und warum es hier keine Ausnahme abfaengt
     *
     * Weil ein stiller Fehlschlag das Schlimmere ist: Die Zeile stuende
     * beim Neuladen wieder da, und der Fix saehe aus wie der Fehler. Wer
     * abhakt und es kommt nicht an, muss das sehen.
     *
     * @param  array<int, string>  $ids
     * @return int Wie viele Zeilen sich drueben geaendert haben.
     */
    public function alsGelesen(string $slug, string $adresse, string $art, array $ids): int;
}
