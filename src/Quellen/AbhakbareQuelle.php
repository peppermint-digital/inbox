<?php

namespace Peppermint\Inbox\Quellen;

use Illuminate\Database\Eloquent\Model;

/**
 * Eine Quelle, deren Zeilen man abhaken kann.
 *
 * Spiegelbildlich zum Holen: Gelesen wird dort entschieden, wo die Sache
 * liegt. Das Paket kennt die Mechanik nicht — ob eine Meldung ein
 * `is_read` setzt, ein imkr-Ereignis sein `unseen` zuruecknimmt oder eine
 * Aufgabe quittiert wird, weiss nur die Quelle selbst.
 *
 * ## Warum ein ZWEITES Interface und nicht zwei Methoden mehr in InboxQuelle
 *
 * Weil eine Pflichtmethode im bestehenden Vertrag jede vorhandene Quelle
 * erschlagen haette. Gemessen am 27.10.2026: fuenf Umsetzungen von
 * {@see InboxQuelle} in den Produkten (vier im Manager, eine in der
 * Verwaltung) plus die Meldungsquelle. Die neue Paketfassung zu ziehen
 * haette dort „must implement" bedeutet — ein Fehler beim Starten, nicht
 * beim Abhaken, und zwar in drei Produkten gleichzeitig.
 *
 * Als eigenes Interface ist der Zuwachs freiwillig: Wer es nicht umsetzt,
 * hat an seinen Zeilen keinen Haken, und alles andere bleibt wie es war.
 * Welche Arten abhakbar sind, sagt der Server der Oberflaeche (`markable`)
 * — sie raet nicht und zeichnet keinen Knopf, der kein Ziel hat.
 *
 * ## Nur die eigenen Zeilen, nur die eigene Person
 *
 * Die Einschraenkung auf die handelnde Person IST die Rechtepruefung. Eine
 * fremde Kennung in der Liste laeuft damit ins Leere statt den ganzen
 * Aufruf zu sprengen — wichtig bei gebuendelten Zeilen, hinter denen
 * mehrere Datensaetze stecken und schon mal eine veraltete Kennung ist.
 */
interface AbhakbareQuelle extends InboxQuelle
{
    /**
     * Diese Zeilen dieser Person auf gelesen setzen.
     *
     * Mehrere Kennungen, nicht eine: Hinter einer Zeile steckt oft mehr als
     * ein Datensatz (Buendelung). Eine Aktion, die nur einen trifft, laesst
     * die Zeile stehen und den Zaehler falsch.
     *
     * @param  array<int, string>  $ids
     * @return int Wie viele Zeilen sich tatsaechlich geaendert haben.
     */
    public function alsGelesen(Model $person, array $ids): int;

    /**
     * Alles, was diese Person hier ungelesen hat.
     *
     * Bewusst eine eigene Methode und nicht „alle Kennungen holen und
     * durchgeben": Bei 3953 ungelesenen Meldungen waere das eine Liste
     * durch den Browser, und die Hoechstzahl je Aufruf wuerde sie kappen.
     *
     * @return int Wie viele Zeilen sich tatsaechlich geaendert haben.
     */
    public function allesAlsGelesen(Model $person): int;
}
