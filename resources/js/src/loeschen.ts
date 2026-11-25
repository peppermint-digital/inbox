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
 * - **Ihr System kann es.** Bis v0.8.0 hiess das „sie ist von hier":
 *   Löschen ging nicht über die Systemgrenze. Seit pm #10227 meldet jedes
 *   System in `deletable.sources`, was bei ihm löschbar ist, und der Klick
 *   geht dorthin, wo die Zeile liegt.
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
    if (!deletable) {
        return false;
    }

    // Seit v0.8.0 (pm #10227) auch eine fremde Zeile — aber nur, wenn ihr
    // System die Art selbst als loeschbar meldet. Bis dahin stand hier
    // `if (item.source) return false`, und bei Bastian liess sich so der
    // groesste Teil seines Posteingangs nicht wegmachen.
    const arten = item.source ? (deletable.sources?.[item.source] ?? []) : (deletable.local ?? []);

    return arten.includes(item.kind);
}

/**
 * Lässt sich diese ART hier ganz leeren?
 *
 * Für den Knopf „Alle meine … löschen", der nur im offenen Reiter steht
 * und nur dort, wo die Art es erlaubt.
 */
export function artLoeschbar(art: string, deletable?: InboxDeletable): boolean {
    if ((deletable?.local ?? []).includes(art)) {
        return true;
    }

    return Object.values(deletable?.sources ?? {}).some((arten) => (arten ?? []).includes(art));
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
 * Was „Alle … loeschen" trifft: der eigene Bestand der Art plus der jedes
 * Systems, das die Art loeschbar meldet (pm #10227). Fuer die Zahl in der
 * Rueckfrage — sie soll sagen, was wirklich verschwindet.
 */
export function loeschbarerBestandDerArt(art: string, totals?: InboxTotals, deletable?: InboxDeletable): number {
    let summe = (deletable?.local ?? []).includes(art) ? eigenerBestandDerArt(art, totals) : 0;

    for (const [herkunft, arten] of Object.entries(deletable?.sources ?? {})) {
        if ((arten ?? []).includes(art)) {
            summe += totals?.sources?.[herkunft]?.[art] ?? 0;
        }
    }

    return summe;
}

/**
 * Der Satz in der Rückfrage.
 *
 * Mit der Zahl und mit dem Wort der Art, nicht „alle Einträge": Wer den
 * Knopf im Meldungs-Reiter drückt, soll lesen, dass es um Meldungen geht —
 * daneben stehen Reiter für Aufgaben und Belege, und „alles löschen" liest
 * sich dort wie „meinen ganzen Posteingang".
 *
 * ## „mindestens", und gelesene ausdrücklich (pm #10227)
 *
 * Der Bestand zählt nur UNGELESENES, gelöscht werden aber auch gelesene und
 * ältere Zeilen, die nicht in der Liste stehen. Bis v0.8.0 stand hier
 * deshalb eine zu kleine Zahl als genaue: Im E2E mit AI-Brain-Zeilen fragte
 * die Rückfrage nach 53, gelöscht wurden 61. Die vollständige Zahl kennt die
 * Seite nicht — also sagt der Satz „mindestens" und nennt die gelesenen.
 */
export function loeschFrage(wort: string, bestand: number, angezeigt: number): string {
    const zahl = Math.max(bestand, angezeigt);

    return zahl > 0
        ? `Alle ${wort} endgültig löschen, auch die gelesenen — mindestens ${zahl}? Das lässt sich nicht zurücknehmen.`
        : `Alle ${wort} endgültig löschen, auch die gelesenen? Das lässt sich nicht zurücknehmen.`;
}
