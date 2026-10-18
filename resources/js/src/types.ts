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

/** Was der Server fuer die Seite liefert. */
export interface InboxData {
    items: InboxItem[];
    kinds: InboxKind[];
    /** Ungelesene je Art — die Zahlen an den Reitern. */
    counts: Record<string, number>;
    /** Systeme, die nicht geantwortet haben. */
    unreachable: string[];
    /** Eigene Quellen, die nicht konnten. */
    failed: string[];
    labels: {
        local: string;
        sources: Record<string, string>;
    };
}
