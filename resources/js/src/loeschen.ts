import type { InboxDeletable, InboxItem, InboxTotals } from './types';

/**
 * Die Entscheidungen hinter dem Löschknopf — ohne DOM (AI Brain #9069).
 *
 * Eigene Datei aus demselben Grund wie `abhaken.ts`: Eine reine
 * Entscheidung braucht keine gezeichnete Seite, und ein Test, der eine
 * braucht, prüft am Ende die Umgebung mit.
 */

/**
 * Darf an dieser Zeile ein Löschknopf hängen?
 *
 * Zwei Bedingungen, beide mit Grund:
 *
 * - **Sie ist von hier.** Löschen geht bewusst nicht über die
 *   Systemgrenze — die Begründung steht in `LoeschbareQuelle`. Eine fremde
 *   Zeile hat deshalb keinen Löschknopf, und das ist kein Mangel, sondern
 *   die Aussage: Was dort liegt, wird dort gelöscht.
 * - **Die Art erlaubt es.** Nur wo eine löschbare Quelle steht, findet der
 *   Klick ein Ziel. Eine Aufgabe oder einen Beleg „aus dem Posteingang zu
 *   löschen" wäre wirkungslos oder ein Datenverlust im Fremdsystem.
 *   Bastians Vorgabe vom 29.10.2026: „Aufgaben und Belege nie."
 *
 * Anders als beim Haken ist „ungelesen" **keine** Bedingung: Eine gelesene
 * Meldung ist genau die, die man loswerden will.
 *
 * Ohne `deletable` wird nichts gezeichnet — ein System auf älterem
 * Paketstand schickt die Angabe nicht, und ein Knopf ins Leere ist
 * schlechter als keiner.
 */
export function darfLoeschen(item: InboxItem, deletable?: InboxDeletable): boolean {
    if (item.source || !deletable) {
        return false;
    }

    return (deletable.local ?? []).includes(item.kind);
}

/**
 * Lässt sich diese ART hier ganz leeren?
 *
 * Für den Knopf „Alle meine … löschen", der nur im offenen Reiter steht
 * und nur dort, wo die Art es erlaubt.
 */
export function artLoeschbar(art: string, deletable?: InboxDeletable): boolean {
    return (deletable?.local ?? []).includes(art);
}

/**
 * Wie viele EIGENE Zeilen dieser Art es insgesamt gibt.
 *
 * Steht in der Rückfrage vor dem Löschen. Die Zahl ist nicht Kosmetik: Ein
 * Knopf, der „alle" sagt, muss sagen wie viele — bei Bastian waren das am
 * 29.10.2026 über 4000 Meldungen, und ein Klick darauf ist unwiderruflich.
 *
 * Nur `local`, weil nur Eigenes löschbar ist. Die Zahl zählt dabei den
 * BESTAND und nicht die gezeigten Zeilen: Der Knopf nimmt auch das, was
 * unter der Kappung liegt — und das Gelesene dazu.
 */
export function eigenerBestandDerArt(art: string, totals?: InboxTotals): number {
    return totals?.local?.[art] ?? 0;
}

/**
 * Der Satz in der Rückfrage.
 *
 * Mit der Zahl und mit dem Wort der Art, nicht „alle Einträge": Wer den
 * Knopf im Meldungs-Reiter drückt, soll lesen, dass es um Meldungen geht —
 * daneben stehen Reiter für Aufgaben und Belege, und „alles löschen" liest
 * sich dort wie „meinen ganzen Posteingang".
 *
 * Steht der Bestand auf 0, nennt der Satz die gezeigte Menge: Der Bestand
 * zählt nur Ungelesenes, gelöscht wird aber auch Gelesenes. „0 löschen?"
 * wäre an einer Liste mit sichtbaren Zeilen offensichtlich falsch.
 */
export function loeschFrage(wort: string, bestand: number, angezeigt: number): string {
    const zahl = bestand > 0 ? bestand : angezeigt;

    return zahl > 0
        ? `${zahl} ${wort} endgültig löschen? Das lässt sich nicht zurücknehmen.`
        : `Alle ${wort} endgültig löschen? Das lässt sich nicht zurücknehmen.`;
}
