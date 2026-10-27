<?php

namespace Peppermint\Inbox\Feed;

use Peppermint\AiBrainBridge\Verbund\Huelle;
use RuntimeException;

/**
 * Die Antwort von `list-inbox-entries-tool` auspacken.
 *
 * Die Huellen-Logik selbst steht in {@see Huelle} — sie ist dieselbe fuer
 * jedes Werkzeug am Komm-Layer, und eine zweite Kopie davon veraltet
 * still.
 */
final class Auspacken
{
    /**
     * Art-Definitionen aus beiden Formen lesen.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function arten(mixed $roh): array
    {
        $arten = [];

        foreach ((array) $roh as $eintrag) {
            if (is_string($eintrag) && $eintrag !== '') {
                // Alte Form: nur der Schluessel. Das Wort fehlt dann, und
                // das Verzeichnis setzt den Schluessel ein — haesslich,
                // aber auffindbar.
                $arten[] = ['key' => $eintrag];

                continue;
            }

            if (is_array($eintrag) && is_string($eintrag['key'] ?? null) && $eintrag['key'] !== '') {
                $arten[] = $eintrag;
            }
        }

        return $arten;
    }

    /**
     * Zahlen je Art — alles, was keine ist, fliegt raus.
     *
     * @return array<string, int>
     */
    private static function zahlen(mixed $roh): array
    {
        $zahlen = [];

        foreach ((array) $roh as $art => $wert) {
            if (is_string($art) && $art !== '' && is_numeric($wert)) {
                $zahlen[$art] = (int) $wert;
            }
        }

        return $zahlen;
    }

    /**
     * @param  array<string, mixed>  $antwort
     * @return array{items: array<int, array<string, mixed>>, kinds: array<int, array<string, mixed>>, failed: array<int, string>, totals: array<string, int>, markable: array<int, string>}
     */
    public static function antwort(array $antwort): array
    {
        $huelle = Huelle::schaelen($antwort);

        return [
            'items' => Huelle::zeilen($huelle),
            // Beide Formen: Bis v0.3.1 kam eine Liste von Schluesseln,
            // seither ganze Definitionen. Ein System, das noch nicht
            // ausgerollt ist, soll seine Zeilen trotzdem loswerden.
            'kinds' => self::arten($huelle['kinds'] ?? []),
            // Arten, die DRUEBEN nicht geliefert werden konnten. Sie
            // fehlen in der Liste, und ohne diese Angabe faellt das
            // niemandem auf.
            'failed' => Huelle::woerter($huelle, 'failed'),
            // Bestand und Abhakbarkeit gibt es erst ab v0.5.0. Ein System
            // auf aelterem Stand schickt beides nicht — dann bleibt es
            // leer, der Sammler zaehlt die gelieferten Zeilen und zeichnet
            // dort keinen Haken. Beides ist die vorsichtige Richtung: zu
            // wenig versprochen, nichts behauptet.
            'totals' => self::zahlen($huelle['totals'] ?? []),
            'markable' => Huelle::woerter($huelle, 'markable'),
        ];
    }

    /**
     * Wie viele Zeilen `mark-inbox-entries-read-tool` abgehakt hat.
     *
     * ## Warum das nicht ueber {@see Huelle} laeuft
     *
     * `Huelle::schaelen()` erkennt die Nutzlast daran, dass der erwartete
     * Schluessel ein ARRAY ist. Hier ist er eine Zahl, und damit faende
     * sie nichts. Die Kandidatenliste muss deshalb hier noch einmal
     * stehen — dieselbe Reihenfolge, anderer Nutzlasttyp.
     *
     * ## Und warum eine fehlende Zahl ein Fehler ist
     *
     * Weil 0 eine Aussage waere: „nichts zu tun gewesen". Eine Antwort,
     * in der die Zahl fehlt, sagt dagegen gar nichts — und wer sie als 0
     * liest, zeigt „abgehakt: 0" an, wo in Wahrheit niemand hingesehen
     * hat. Das ist genau der stille Fehlschlag, gegen den das Abhaken
     * gebaut ist.
     *
     * @param  array<string, mixed>  $antwort
     *
     * @throws RuntimeException wenn die Antwort keine Zahl enthaelt.
     */
    public static function abgehakt(array $antwort): int
    {
        $kandidaten = [
            $antwort,
            $antwort['data'] ?? null,
            $antwort['structuredContent'] ?? null,
            $antwort['content'] ?? null,
            $antwort['result'] ?? null,
            $antwort['antwort'] ?? null,
        ];

        foreach ($kandidaten as $kandidat) {
            if (is_array($kandidat) && is_numeric($kandidat['marked'] ?? null)) {
                return (int) $kandidat['marked'];
            }
        }

        throw new RuntimeException('Die Antwort auf das Abhaken nennt keine Zahl — kam es an?');
    }
}
