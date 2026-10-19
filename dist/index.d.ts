import * as react from 'react';
import { ReactNode } from 'react';
import { LucideIcon } from 'lucide-react';

/** Eine Art von Eintrag — ein Reiter in der Leiste. */
interface InboxKind {
    key: string;
    label: string;
    icon: string;
    color: string;
    order: number;
}
/** Eine Zeile in der Inbox. */
interface InboxItem {
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
interface InboxData {
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

interface Props$1 extends InboxData {
    formatTime: (iso: string) => string;
    onOpen: (item: InboxItem) => void;
    renderActions?: (item: InboxItem) => ReactNode;
    /** Welcher Reiter zuerst offen ist. Ohne Angabe der erste mit Inhalt. */
    initialKind?: string;
    /**
     * Wohin der Einstellungs-Knopf fuehrt.
     *
     * Die Vorgabe ist in allen vier Systemen derselbe Pfad. `null`
     * blendet den Knopf aus — fuer ein Produkt, das die Maske nicht hat.
     */
    settingsHref?: string | null;
    title?: string;
    subtitle?: string;
}
/**
 * Die Inbox — dieselbe Seite in jedem System.
 *
 * ## Was die Reiter bestimmt
 *
 * Nicht diese Datei. Die Arten kommen vom Server, und zwar
 * zusammengelegt aus dem, was DIESES System anmeldet und dem, was die
 * anderen mitgeschickt haben. Ein Produkt, das eine neue Kategorie
 * einfuehrt, bringt sie den uebrigen mit — ohne dass hier etwas zu
 * aendern waere. Genau dafuer ist die Seite so gebaut.
 *
 * ## Warum ein leerer Reiter trotzdem steht
 *
 * „Keine offenen Aufgaben" ist eine Aussage; ein fehlender Reiter ist
 * keine. Wer den Reiter gestern gesehen hat und heute nicht mehr, sucht
 * den Fehler bei sich.
 */
declare function InboxPage({ items, kinds, counts, unreachable, failed, labels, formatTime, onOpen, renderActions, initialKind, settingsHref, title, subtitle, }: Props$1): react.JSX.Element;

interface Props {
    items: InboxItem[];
    /** Wie ein Zeitpunkt geschrieben wird — das Produkt bringt sein Format mit. */
    formatTime: (iso: string) => string;
    /** Wie das eigene System heisst, fuer Zeilen ohne Herkunft. */
    localLabel: string;
    sourceLabels: Record<string, string>;
    onOpen: (item: InboxItem) => void;
    /** Zusaetzliche Knoepfe je Zeile — fuer das, was nur ein Produkt kann. */
    renderActions?: (item: InboxItem) => ReactNode;
    empty?: ReactNode;
}
/**
 * Die Liste einer Art.
 *
 * Bewusst ohne „gelesen"- und „loeschen"-Knoepfe: Was hier steht, kommt zu
 * einem grossen Teil aus anderen Systemen, und dort faende eine solche
 * Aktion kein Ziel. Erledigt wird eine Sache da, wo sie liegt — der Klick
 * fuehrt hin.
 */
declare function InboxList({ items, formatTime, localLabel, sourceLabels, onOpen, renderActions, empty }: Props): react.JSX.Element;

/**
 * Die Symbole und Farben der Benachrichtigungsarten — an EINER Stelle.
 *
 * Die Vorgeschichte, die diese Datei begründet: Im Manager lieferte der Server
 * für jede der 39 Arten einen Symbolnamen. Die Glocke kannte davon 15, alles
 * andere fiel stillschweigend auf das Glocken-Symbol zurück — eine
 * Mail-Warnung sah aus wie eine Erinnerung. Beim Übertragen nach React kam
 * genau dieser Fehler zurück: Die Glocke bekam wieder ihre eigene 15er-Liste,
 * der Posteingang die vollständige.
 *
 * Deshalb: **wer eine weitere Ansicht baut, importiert von hier.**
 *
 * Ein Symbolname, den die Zuordnung nicht kennt, bekommt bewusst weiterhin
 * einen Rückfall statt eines Fehlers — eine Benachrichtigung ohne passendes
 * Symbol ist besser als keine.
 */

declare function inboxIcon(name: string): LucideIcon;
/** Die Symbolnamen, die diese Zuordnung kennt — für Prüfungen im Produkt. */
declare function bekannteSymbole(): string[];
declare function inboxTextClass(color: string): string;
declare function inboxSurfaceClass(color: string): string;
/** Die Farbworte mit einer Schriftfarbe. */
declare function bekannteFarben(): string[];

export { type InboxData, type InboxItem, type InboxKind, InboxList, InboxPage, bekannteFarben, bekannteSymbole, inboxIcon, inboxSurfaceClass, inboxTextClass };
