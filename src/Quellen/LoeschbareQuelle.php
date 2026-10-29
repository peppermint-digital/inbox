<?php

namespace Peppermint\Inbox\Quellen;

use Illuminate\Database\Eloquent\Model;
use Peppermint\Inbox\Feed\Abhaken;
use Peppermint\Inbox\Feed\RemoteSource;

/**
 * Eine Quelle, deren Zeilen man loeschen kann.
 *
 * Spiegelbildlich zu {@see AbhakbareQuelle} und aus demselben Grund ein
 * EIGENES Interface: Eine Pflichtmethode im bestehenden Vertrag haette jede
 * vorhandene Quelle beim Starten erschlagen. Wer es nicht umsetzt, hat an
 * seinen Zeilen keinen Loeschknopf, und alles andere bleibt, wie es war.
 *
 * ## Warum Loeschen NICHT dasselbe wie Abhaken ist
 *
 * Abhaken ist eine Aussage ueber mich („gesehen"). Loeschen ist eine
 * Aussage ueber die Sache, und bei den meisten Arten im Posteingang waere
 * sie falsch oder zerstoerend:
 *
 * | Art | loeschbar? | warum |
 * |---|---|---|
 * | Meldung | ja | Sie existiert nur, um gesehen zu werden. Danach darf sie weg. |
 * | Aufgabe | **nie** | Die Aufgabe ist der Gegenstand, nicht die Zeile. „Aus dem Posteingang loeschen" waere entweder wirkungslos oder ein Datenverlust im Fremdsystem. |
 * | Beleg | **nie** | Dasselbe, mit Buchhaltungsfolgen. |
 * | imkr-Meldung | nein (heute) | Sie wird abgehakt und raeumt sich nach 30 Tagen selbst auf. |
 *
 * Deshalb raet die Oberflaeche nicht, sondern fragt: Welche Arten loeschbar
 * sind, sagt der Server als `deletable`. Ein Knopf, der an einer Aufgabe
 * steht und entweder nichts tut oder zu viel, ist schlimmer als keiner.
 *
 * Die Entscheidung dazu ist vom 29.10.2026 (AI Brain #9069): Bastian wollte
 * die alte Meldungsseite abschaffen und ihre Funktionen im gemeinsamen
 * Posteingang haben. „Aufgaben und Belege nie" ist seine Vorgabe.
 *
 * ## Nur die EIGENEN Zeilen (Stand 29.10.2026)
 *
 * Es gibt hier bewusst kein Gegenstueck zu {@see Abhaken},
 * das ueber die Systemgrenze schreibt. Abhaken kann das (Faehigkeit
 * `inbox.read` ueber den Gateway); Loeschen koennte es analog mit einer
 * neuen Faehigkeit `inbox.delete` samt Freigabe je Produkt.
 *
 * Bewusst nicht gebaut: Der Hauptfall ist, seine eigenen Meldungen dort
 * loszuwerden, wo sie liegen — und bei 4113 Meldungen von AI Brain ist das
 * AI Brain. Einen Schreibweg quer durch den Verbund fuer einen Knopf zu
 * bauen, von dem noch niemand gesagt hat, dass er ihn vermisst, waere die
 * teurere Haelfte zuerst. Kommt er, ist er additiv: ein Interface mehr am
 * {@see RemoteSource}, eine Faehigkeit mehr, und
 * `deletable.sources` fuellt sich.
 *
 * ## Die Einschraenkung auf die Person IST die Rechtepruefung
 *
 * Wie beim Abhaken: Eine fremde Kennung in der Liste laeuft ins Leere statt
 * den Aufruf zu sprengen. Bei gebuendelten Zeilen steckt mehr als ein
 * Datensatz dahinter, und schon mal eine veraltete Kennung.
 */
interface LoeschbareQuelle extends InboxQuelle
{
    /**
     * Diese Zeilen dieser Person loeschen.
     *
     * Mehrere Kennungen, nicht eine: Hinter einer Zeile steckt oft mehr als
     * ein Datensatz (Buendelung). Eine Loeschung, die nur einen trifft,
     * laesst die Zeile stehen — und beim naechsten Laden sieht es aus, als
     * haette der Knopf nicht funktioniert.
     *
     * @param  array<int, string>  $ids
     * @return int Wie viele Zeilen wirklich verschwunden sind.
     */
    public function loeschen(Model $person, array $ids): int;

    /**
     * Alles, was diese Person hier hat — auch das Gelesene.
     *
     * Bewusst eine eigene Methode und nicht „alle Kennungen holen und
     * durchgeben": Bei tausenden Zeilen waere das eine Liste durch den
     * Browser, und die Hoechstzahl je Aufruf wuerde sie kappen. Ein Knopf,
     * der „alle" sagt und 200 trifft, ist eine Luege.
     *
     * Und bewusst AUCH das Gelesene: Der Knopf heisst „alle meine …
     * loeschen", nicht „alle ungelesenen". Genau deshalb steht davor eine
     * Rueckfrage mit der Zahl — siehe `InboxPage`.
     *
     * @return int Wie viele Zeilen wirklich verschwunden sind.
     */
    public function allesLoeschen(Model $person): int;
}
