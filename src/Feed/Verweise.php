<?php

namespace Peppermint\Inbox\Feed;

use Peppermint\Inbox\Mcp\ListInboxEntriesTool;

/**
 * Verweise absolut machen, bevor eine Zeile das System verlaesst.
 *
 * ## Warum das sein muss
 *
 * Eine Inbox-Zeile wird in VIER Oberflaechen gezeigt, aber nur in einer
 * davon steht die Sache wirklich. Ein Verweis wie `/bugs/1122` ist deshalb
 * nur zu Hause richtig: Drueben loest ihn der Browser gegen den dortigen
 * Host auf, und dann fuehrt er irgendwohin — nur nicht dorthin.
 *
 * Am 29.10.2026 in Produktion gemessen (AI Brain #1151): AI Brain gab
 * `/bugs/1122`, `/tasks/9057`, `/avatar`, `/websites/21` und `/companion`
 * heraus. Auf `vw`, `crm` und `pm` antworteten alle fuenf mit 404, auf
 * `brain` mit 302. Betroffen waren 4112 Meldungs- und 59 imkr-Zeilen —
 * also praktisch der ganze Posteingang in drei von vier Systemen.
 *
 * ## Der Fall, der schlimmer ist als 404
 *
 * `/tasks/9057` antwortete im Manager und im CRM mit **302**, nicht 404:
 * Beide haben selbst eine Route `/tasks/{id}`. Der Klick auf „AI Brain:
 * Aufgabe #9057 erledigt" oeffnete dort also die EIGENE Aufgabe 9057 — eine
 * andere Sache mit derselben Nummer. Ein 404 sagt „hier ist nichts"; das
 * hier sagt nichts und zeigt etwas Falsches.
 *
 * Deshalb reicht es nicht, fehlende Ziele abzufangen. Der Verweis muss
 * schon richtig sein, wenn er losgeschickt wird.
 *
 * ## Warum auf der Anbieter-Seite und nicht beim Empfaenger
 *
 * Weil nur der Anbieter seine eigene Adresse kennt. {@see RemoteSource}
 * nennt dem Empfaenger Kuerzel, Slug und Wort eines Systems — keine
 * Grundadresse. Er koennte sie also nur raten.
 *
 * ## Und warum nicht in `Quellenverzeichnis::sammeln()`
 *
 * Dieselben Zeilen tragen die EIGENE Seite, und dort ist der relative
 * Verweis der richtige: Er bleibt im Inertia-Fluss, und die Anwendung
 * behaelt ihren Zustand. Absolut gemacht wird deshalb genau auf dem Weg
 * nach draussen — in {@see ListInboxEntriesTool}.
 */
final class Verweise
{
    /**
     * Jede Zeile mit absolutem Verweis.
     *
     * Zeilen ohne Verweis bleiben, wie sie sind: Dass eine Sache kein Ziel
     * hat, ist eine Aussage und kein fehlender Wert — eine imkr-Meldung
     * etwa gehoert zu einem Pane und zu keiner Seite.
     *
     * @param  array<int, array<string, mixed>>  $zeilen
     * @return array<int, array<string, mixed>>
     */
    public static function absolut(array $zeilen, ?string $basis): array
    {
        if (self::basis($basis) === null) {
            return $zeilen;
        }

        return array_map(static function (array $zeile) use ($basis): array {
            if (! isset($zeile['link'])) {
                return $zeile;
            }

            $zeile['link'] = self::einzeln((string) $zeile['link'], $basis);

            return $zeile;
        }, $zeilen);
    }

    /**
     * Ein einzelner Verweis, gegen eine Grundadresse gelegt.
     *
     * Idempotent: Ein Verweis, der schon absolut ist, bleibt unberuehrt.
     * Das ist nicht Bequemlichkeit, sondern notwendig — der Manager baut
     * seine Verweise seit immer selbst absolut, und ein zweites Praefix
     * davor machte aus einem richtigen Verweis einen kaputten.
     */
    public static function einzeln(?string $verweis, ?string $basis): ?string
    {
        $grund = self::basis($basis);

        if ($verweis === null || $verweis === '' || $grund === null) {
            return $verweis;
        }

        // Schon absolut? In Ruhe lassen. Neben `http`/`https` auch alles
        // andere mit Schema (`mailto:`, `tel:`) und das protokollrelative
        // `//host/pfad` — ein Praefix davor waere in jedem dieser Faelle
        // falsch.
        if (str_starts_with($verweis, '//') || preg_match('#^[a-z][a-z0-9+.-]*:#i', $verweis) === 1) {
            return $verweis;
        }

        return $grund.'/'.ltrim($verweis, '/');
    }

    /** Die Grundadresse, oder `null` wenn es keine brauchbare gibt. */
    private static function basis(?string $basis): ?string
    {
        $grund = rtrim(trim((string) $basis), '/');

        // Ohne Grundadresse lieber den relativen Verweis durchlassen als
        // einen, der auf `/bugs/1122` zeigt und dabei absolut AUSSIEHT.
        // Zu Hause stimmt er dann noch; drueben faellt er auf, und das ist
        // die bessere Richtung.
        return $grund === '' ? null : $grund;
    }
}
