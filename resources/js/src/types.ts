/** Eine Art von Eintrag — ein Reiter in der Leiste. */
export interface InboxKind {
    key: string;
    label: string;
    icon: string;
    color: string;
    order: number;
}

/** Eine Zeile in der Inbox. */
export interface InboxItem {
    id: string;
    /** Unter welchen Reiter die Zeile gehoert. */
    kind: string;
    title: string;
    message?: string | null;
    link?: string | null;
    icon: string;
    color: string;
    created_at?: string | null;
    is_unread: boolean;
    /** Aus welchem SYSTEM die Zeile kommt. `null` heisst „von hier". */
    source?: string | null;
    source_label?: string | null;
    /**
     * Produkteigene Zusaetze — Projektname, Faelligkeit, wer es ausloeste.
     *
     * Die Liste zeigt sie als Unterzeile an, ohne wissen zu muessen, was
     * drin steht. Ein Produkt, das ein Feld ergaenzt, braucht dafuer keine
     * Aenderung an diesem Paket.
     */
    meta?: Record<string, unknown> | null;
}

/**
 * Der BESTAND, getrennt nach Herkunft.
 *
 * Nicht zu verwechseln mit `counts`: Das ist, was in dieser Liste steht.
 * Hier steht, wie viele es insgesamt sind. Am 26.10.2026 hiess beides
 * dasselbe, und dann stand in der Kopfzeile 3953 gegen 50 Zeilen — wer
 * einzeln abarbeitet, sieht die Zahl kaum sinken und haelt die Seite fuer
 * kaputt.
 */
export interface InboxTotals {
    /** Bestand je Art, nur die EIGENEN Zeilen. */
    local: Record<string, number>;
    /** Bestand je fremdem System und Art — Schluessel wie `source`. */
    sources: Record<string, Record<string, number>>;
}

/**
 * Wo ein Haken ein Ziel findet.
 *
 * Die Ansicht raet das nicht. Ein Knopf an einer Zeile, deren System das
 * Abhaken nicht beantwortet, waere eine Luege — und bis zum 27.10.2026
 * stand die Regel dafuer im Produkt, wo jedes der vier sie haette
 * abschreiben muessen.
 */
export interface InboxMarkable {
    /** Arten, die HIER abgehakt werden koennen. */
    local: string[];
    /** Arten je fremdem System — Schluessel wie `source`. */
    sources: Record<string, string[]>;
}

/**
 * Wo ein LOESCHKNOPF ein Ziel findet (AI Brain #9069).
 *
 * Nur `local`, und das ist eine Entscheidung: Geloescht wird dort, wo die
 * Sache liegt — es gibt bewusst keinen Schreibweg ueber die Systemgrenze.
 * Deshalb fehlt hier ein `sources`, statt leer dabeizustehen; ein leeres
 * Feld saehe aus wie „gerade nichts loeschbar" und damit wie ein Zustand,
 * der sich aendern koennte.
 *
 * Und nur Arten, deren Quelle den Vertrag umsetzt. Eine Aufgabe oder einen
 * Beleg „aus dem Posteingang zu loeschen" waere wirkungslos oder ein
 * Datenverlust im Fremdsystem.
 */
export interface InboxDeletable {
    /** Arten, die HIER geloescht werden koennen. */
    local: string[];
}

/** Was der Server fuer die Seite liefert. */
export interface InboxData {
    items: InboxItem[];
    kinds: InboxKind[];
    /** Ungelesene IN DIESER LISTE — die Zahlen an den Reitern. */
    counts: Record<string, number>;
    /**
     * Der Bestand. Optional, weil ein System auf aelterem Paketstand ihn
     * nicht mitschickt — dann bleibt der Satz „N von M" einfach weg,
     * statt „N von undefined" zu behaupten.
     */
    totals?: InboxTotals;
    /** Wo abgehakt werden kann. Fehlt sie, wird kein Haken gezeichnet. */
    markable?: InboxMarkable;
    /**
     * Wo geloescht werden kann. Fehlt sie, wird kein Loeschknopf
     * gezeichnet — ein System auf aelterem Paketstand schickt sie nicht,
     * und ein Knopf ins Leere ist schlechter als keiner.
     */
    deletable?: InboxDeletable;
    /** Systeme, die nicht geantwortet haben. */
    unreachable: string[];
    /** Eigene Quellen, die nicht konnten. */
    failed: string[];
    labels: {
        local: string;
        sources: Record<string, string>;
    };
}
