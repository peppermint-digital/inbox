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
     * @return array{items: array<int, array<string, mixed>>, kinds: array<int, string>, failed: array<int, string>}
     */
    public function holen(string $slug, string $adresse, int $grenze): array;
}
