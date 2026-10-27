import type { InboxItem, InboxMarkable, InboxTotals } from './types';

/**
 * Die Entscheidungen hinter dem Haken — ohne DOM.
 *
 * ## Warum das eine eigene Datei ist
 *
 * Damit sie pruefbar sind, ohne die Seite zu zeichnen. Der erste Versuch
 * (noch im Produkt) hatte sie in der Seitenkomponente, und der Test
 * scheiterte beim Import: Die Seite zieht das Layout mit, das Layout die
 * Seitenleiste, und die ruft beim Laden `window.matchMedia` — in der
 * Testumgebung nicht vorhanden. Eine reine Entscheidung braucht kein DOM,
 * und ein Test, der eines braucht, prueft am Ende die Umgebung mit.
 */

/**
 * Der Schluessel einer Zeile.
 *
 * Herkunft MIT drin: Zwei Systeme koennen dieselbe Kennung in derselben
 * Art haben, und dann waere ein abgehakter Haken am falschen Ort zu sehen.
 * Dieselbe Zusammensetzung wie der React-Key der Liste.
 */
export function zeilenSchluessel(item: InboxItem): string {
    return `${item.source ?? ''}:${item.kind}:${item.id}`;
}

/**
 * Darf an dieser Zeile ein „gelesen" hängen? (Bug #1096)
 *
 * Zwei Bedingungen, und beide haben einen Grund:
 *
 * - **Ungelesen.** Ein Haken an einer gelesenen Zeile setzt den
 *   Lesezeitpunkt neu, und der Verlauf behauptet dann, sie sei eben erst
 *   gelesen worden.
 * - **Das System, dem sie gehört, kann es.** Früher hieß diese Bedingung
 *   „von hier" — eine fremde Zeile hatte keinen Haken, weil niemand wusste,
 *   ob er ankommt. Seit `inbox.read` sagt es das System selbst, und zwar je
 *   Art: Nur dort, wo eine abhakbare Quelle steht, findet der Klick ein Ziel.
 *
 * Ohne `markable` wird nichts gezeichnet. Das ist die vorsichtige
 * Richtung: Ein System auf älterem Paketstand schickt die Angabe nicht,
 * und ein Knopf, der ins Leere zeigt, ist schlechter als keiner.
 */
export function darfAbhaken(item: InboxItem, markable?: InboxMarkable): boolean {
    if (!item.is_unread || !markable) {
        return false;
    }

    const arten = item.source ? (markable.sources?.[item.source] ?? []) : (markable.local ?? []);

    return arten.includes(item.kind);
}

/** Eine eigene Zeile, die „alle eigenen als gelesen" mitnimmt. */
export function istEigenAbhakbar(item: InboxItem, markable?: InboxMarkable): boolean {
    return !item.source && darfAbhaken(item, markable);
}

/**
 * Gilt diese Zeile nach dem bisher Abgehakten noch als ungelesen?
 *
 * Bewusst an EINER Stelle: Es ist dieselbe Frage für die Zeile, für die
 * Zahl am Reiter und für den Satz unter der Liste. Drei Antworten darauf
 * wären drei Gelegenheiten, auseinanderzulaufen — und die Zeile, die rot
 * bleibt, während der Zähler schon sinkt, ist genau das Bild, das #1096
 * ausgelöst hat.
 */
export function nochUngelesen(
    item: InboxItem,
    abgehakt: Set<string>,
    alleEigenenAbgehakt: boolean,
    markable?: InboxMarkable,
): boolean {
    if (!item.is_unread || abgehakt.has(zeilenSchluessel(item))) {
        return false;
    }

    return !(alleEigenenAbgehakt && istEigenAbhakbar(item, markable));
}

/**
 * Der Bestand je Art, über alle Systeme zusammen.
 *
 * Für den Satz unter der Liste: Dort geht es um die Art, die gerade offen
 * ist, und nicht darum, aus welchem System sie kommt.
 */
export function bestandJeArt(totals?: InboxTotals): Record<string, number> {
    if (!totals) {
        return {};
    }

    const summe: Record<string, number> = { ...(totals.local ?? {}) };

    for (const jeArt of Object.values(totals.sources ?? {})) {
        for (const [art, zahl] of Object.entries(jeArt ?? {})) {
            summe[art] = (summe[art] ?? 0) + zahl;
        }
    }

    return summe;
}

/**
 * Wie viele EIGENE Zeilen „alle eigenen als gelesen" wirklich nimmt.
 *
 * Nur die abhakbaren Arten, und nur die eigenen. Ein Knopf, der „alle"
 * verspricht und einen Teil erledigt, ist schlimmer als einer, der sagt,
 * wie viele er nimmt — deshalb steht die Zahl in der Beschriftung.
 */
export function eigenerBestandAbhakbar(totals?: InboxTotals, markable?: InboxMarkable): number {
    if (!totals || !markable) {
        return 0;
    }

    return (markable.local ?? []).reduce((summe, art) => summe + (totals.local?.[art] ?? 0), 0);
}

/**
 * Was unter der Liste steht, wenn der Bestand mehr ist als das Gezeigte.
 *
 * Die Liste zeigt die neuesten je Art und System (Vorgabe 50), der
 * Bestand kann weit darüber liegen. Am 26.10.2026 standen dort 3953 gegen
 * 50 Zeilen, und nichts erklärte den Unterschied — wer einzeln
 * abarbeitet, sieht die Zahl kaum sinken und hält die Seite für kaputt.
 *
 * `null`, wenn beides zusammenpasst: Ein Hinweis, der immer steht, wird
 * nicht gelesen. Er soll nur da sein, wenn er etwas erklärt.
 */
export function hinweisZurListe(angezeigt: number, bestand: number): string | null {
    if (bestand <= angezeigt) {
        return null;
    }

    return `Hier stehen die ${angezeigt} neuesten von ${bestand}.`;
}
