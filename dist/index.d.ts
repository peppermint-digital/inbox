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
/**
 * Der BESTAND, getrennt nach Herkunft.
 *
 * Nicht zu verwechseln mit `counts`: Das ist, was in dieser Liste steht.
 * Hier steht, wie viele es insgesamt sind. Am 26.10.2026 hiess beides
 * dasselbe, und dann stand in der Kopfzeile 3953 gegen 50 Zeilen — wer
 * einzeln abarbeitet, sieht die Zahl kaum sinken und haelt die Seite fuer
 * kaputt.
 */
interface InboxTotals {
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
interface InboxMarkable {
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
interface InboxDeletable {
    /** Arten, die HIER geloescht werden koennen. */
    local: string[];
}
/** Was der Server fuer die Seite liefert. */
interface InboxData {
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
    /**
     * Wohin das Abhaken schreibt.
     *
     * Die Vorgabe ist die Route, die das Paket selbst mitbringt. `null`
     * schaltet das Abhaken ab — fuer ein Produkt, das die Route nicht
     * laedt (`inbox.route.enabled`).
     */
    readHref?: string | null;
    /**
     * Nach dem Abhaken — fuer ein Produkt, das seine Liste frisch holt.
     *
     * Optional: Die Seite stimmt auch ohne. Mit Inertia gehoert hier
     * `router.reload({ only: [...] })` hin, dann sind auch die Zahlen
     * wieder vom Server.
     */
    onMarked?: () => void;
    /**
     * Wohin das Loeschen schreibt (AI Brain #9069).
     *
     * Die Vorgabe ist die Route, die das Paket mitbringt. `null` schaltet
     * das Loeschen ab — fuer ein Produkt, das die Route nicht laedt.
     * Gezeichnet wird der Knopf trotzdem nur dort, wo `deletable` es
     * erlaubt: eigene Zeile, loeschbare Art.
     */
    deleteHref?: string | null;
    /** Nach dem Loeschen — wie `onMarked`. */
    onDeleted?: () => void;
    /**
     * Neu laden auf Knopfdruck (AI Brain #9069).
     *
     * Von der alten Meldungsseite uebernommen. Ohne Angabe erscheint der
     * Knopf NICHT — diese Seite bekommt ihre Daten vom Produkt, und einer,
     * der nichts tut, ist schlechter als keiner. Mit Inertia gehoert hier
     * `router.reload()` hin.
     */
    onRefresh?: () => void;
    /**
     * Die Rueckfrage vor „alle loeschen".
     *
     * Vorgabe `window.confirm`. Als Prop, weil dieses Paket in vier
     * Anwendungen mit vier UI-Baukaesten haengt: Ein eigener Dialog waere
     * hier entweder eine neue Abhaengigkeit oder einer, der neben den
     * anderen fremd aussieht. Das Produkt bringt seinen mit.
     *
     * Darf ein Promise liefern — ein echter React-Dialog ist asynchron,
     * `window.confirm` blockiert. Ohne das kaeme nur der native in Frage.
     *
     * Und als Prop auch deshalb, weil sich eine Rueckfrage sonst nicht
     * pruefen laesst, ohne den Browser zu fragen.
     */
    confirm?: (frage: string) => boolean | Promise<boolean>;
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
 *
 * ## Und warum hier jetzt ein Haken haengt (Bug #1096)
 *
 * Lange stand hier nichts dergleichen, mit guter Begruendung: Die Liste
 * zeigt zum grossen Teil Zeilen aus anderen Systemen, und dort faende ein
 * „gelesen" kein Ziel. Die Begruendung gilt weiter — sie ist nur jetzt
 * eine Frage an den Server statt einer Annahme in der Ansicht. Wo
 * `markable` eine Art nennt, gibt es drueben eine abhakbare Quelle; wo
 * nicht, bleibt der Knopf weg.
 *
 * Bastian hat die Luecke am 26.10.2026 so gemeldet: „die roten bleiben
 * immer da, man kann nichts anklicken." Der Satz beschrieb kein Versehen
 * in der Technik, sondern eine Oberflaeche ohne Griff.
 */
declare function InboxPage({ items, kinds, counts, totals, markable, deletable, unreachable, failed, labels, formatTime, onOpen, renderActions, initialKind, settingsHref, readHref, onMarked, deleteHref, onDeleted, onRefresh, confirm: bestaetigen, title, subtitle, }: Props$1): react.JSX.Element;

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
 * ## Die Aktionen kommen von aussen, ueber `renderActions`
 *
 * Hier stand bis zum 29.10.2026: „Bewusst ohne ‚gelesen'- und
 * ‚loeschen'-Knoepfe: Was hier steht, kommt zu einem grossen Teil aus
 * anderen Systemen, und dort faende eine solche Aktion kein Ziel."
 *
 * Die Begruendung war richtig und ist es noch — nur der Schluss daraus war
 * zu weit. Inzwischen sagt der Server je Art, wo eine Aktion ein Ziel
 * findet (`markable` seit #1096, `deletable` seit #9069), und
 * {@see InboxPage} zeichnet den Knopf genau dort. Der geschuetzte Fall ist
 * damit nicht weggefallen, sondern praeziser geworden: Eine FREMDE Zeile
 * bekommt weiterhin keinen Loeschknopf, und keine Aufgabe und kein Beleg
 * ueberhaupt einen.
 *
 * Diese Komponente selbst bleibt ohne eigene Aktion. Sie zeichnet, was ihr
 * `renderActions` gibt — das ist der Grund, warum der Umbau sie nicht
 * anfassen musste.
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
declare function zeilenSchluessel(item: InboxItem): string;
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
declare function darfAbhaken(item: InboxItem, markable?: InboxMarkable): boolean;
/** Eine eigene Zeile, die „alle eigenen als gelesen" mitnimmt. */
declare function istEigenAbhakbar(item: InboxItem, markable?: InboxMarkable): boolean;
/**
 * Gilt diese Zeile nach dem bisher Abgehakten noch als ungelesen?
 *
 * Bewusst an EINER Stelle: Es ist dieselbe Frage für die Zeile, für die
 * Zahl am Reiter und für den Satz unter der Liste. Drei Antworten darauf
 * wären drei Gelegenheiten, auseinanderzulaufen — und die Zeile, die rot
 * bleibt, während der Zähler schon sinkt, ist genau das Bild, das #1096
 * ausgelöst hat.
 */
declare function nochUngelesen(item: InboxItem, abgehakt: Set<string>, alleEigenenAbgehakt: boolean, markable?: InboxMarkable): boolean;
/**
 * Der Bestand je Art, über alle Systeme zusammen.
 *
 * Für den Satz unter der Liste: Dort geht es um die Art, die gerade offen
 * ist, und nicht darum, aus welchem System sie kommt.
 */
declare function bestandJeArt(totals?: InboxTotals): Record<string, number>;
/**
 * Wie viele EIGENE Zeilen „alle eigenen als gelesen" wirklich nimmt.
 *
 * Nur die abhakbaren Arten, und nur die eigenen. Ein Knopf, der „alle"
 * verspricht und einen Teil erledigt, ist schlimmer als einer, der sagt,
 * wie viele er nimmt — deshalb steht die Zahl in der Beschriftung.
 */
declare function eigenerBestandAbhakbar(totals?: InboxTotals, markable?: InboxMarkable): number;
/**
 * Was unter der Liste steht, wenn der Bestand mehr ist als das Gezeigte.
 *
 * Die Liste zeigt die neuesten je Art und System (Vorgabe 50), der
 * Bestand kann weit darüber liegen. Am 26.10.2026 standen dort 3953 gegen
 * 50 Zeilen, und nichts erklärte den Unterschied — wer einzeln
 * abarbeitet, sieht die Zahl kaum sinken und hält die Seite für kaputt.
 *
 * ## Warum „Zeilen von N ungelesenen" und nicht „N von M"
 *
 * Weil die beiden Zahlen nicht dasselbe zählen, und das ist Absicht. Eine
 * Zeile kann mehrere Einträge **bündeln** — sieben gleichartige Meldungen
 * werden zu einer. Gemessen im Meldungs-Paket: Bestand 7, Liste 1.
 *
 * Der Bestand zählt Einträge, weil „alle als gelesen" ebenfalls Einträge
 * abhakt. Die Zahl am Knopf nennt damit, was er wirklich anfasst — und der
 * Satz hier benennt beide Einheiten, statt sie gleichzusetzen.
 *
 * `null`, wenn beides zusammenpasst: Ein Hinweis, der immer steht, wird
 * nicht gelesen. Er soll nur da sein, wenn er etwas erklärt.
 */
declare function hinweisZurListe(angezeigt: number, bestand: number): string | null;

/**
 * Der Schreibweg zum Endpunkt des Pakets.
 *
 * Eine Stelle, nicht vier: Ohne diesen Haken schriebe jedes Produkt
 * denselben `fetch` mit derselben CSRF-Marke und derselben Fehlerbehandlung
 * — genau die dritte Pruefrage des Paket-Vertrags („Schreibt jedes Produkt
 * denselben Verdrahtungscode?").
 *
 * ## Der eigene Zustand ist Absicht
 *
 * Die Liste kommt als Server-Eigenschaft und weiss von einer Aenderung
 * nichts. Ohne diesen Zwischenstand blieb die Zeile nach dem Klick rot
 * stehen — und der Fix sah genauso aus wie der Fehler. `onChanged` ist der
 * Weg fuer das Produkt, zusaetzlich frisch zu laden (Inertia:
 * `router.reload`); ohne ihn stimmt die Ansicht trotzdem.
 */
interface UseAbhakenOptions {
    /** Wohin geschrieben wird. `null` schaltet das Abhaken ganz ab. */
    href?: string | null;
    /** Nach einer Aenderung — fuer ein Produkt, das seine Liste neu holt. */
    onChanged?: () => void;
}
interface UseAbhakenResult {
    /** Schluessel der Zeilen, die in dieser Sitzung abgehakt wurden. */
    abgehakt: Set<string>;
    /** Ob „alle eigenen" gelaufen ist. */
    alleEigenenAbgehakt: boolean;
    /** Laeuft gerade ein Schreibvorgang? */
    laeuft: boolean;
    /** Was schiefging — oder `null`. Gehoert in die Ansicht, nicht ins Log. */
    fehler: string | null;
    abhaken: (zeilen: InboxItem[]) => Promise<void>;
    alleEigenen: () => Promise<void>;
}
declare function useAbhaken(options?: UseAbhakenOptions): UseAbhakenResult;

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
declare function darfLoeschen(item: InboxItem, deletable?: InboxDeletable): boolean;
/**
 * Lässt sich diese ART hier ganz leeren?
 *
 * Für den Knopf „Alle meine … löschen", der nur im offenen Reiter steht
 * und nur dort, wo die Art es erlaubt.
 */
declare function artLoeschbar(art: string, deletable?: InboxDeletable): boolean;
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
declare function eigenerBestandDerArt(art: string, totals?: InboxTotals): number;
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
declare function loeschFrage(wort: string, bestand: number, angezeigt: number): string;

/**
 * Der Schreibweg hinter dem Löschknopf (AI Brain #9069).
 *
 * Spiegelbildlich zu `useAbhaken`, mit drei Unterschieden, die alle aus der
 * Sache kommen:
 *
 * 1. **`DELETE` statt `POST`.** Hier verschwindet etwas, und das Verb soll
 *    es sagen.
 * 2. **Keine Herkunft in der Nutzlast.** Gelöscht wird nur Eigenes — siehe
 *    `loeschen.ts`. Ein `source`-Feld gäbe es nur, um immer `null` zu sein.
 * 3. **`alle` braucht die ART.** „Alle meine Meldungen löschen" ist eine
 *    Aussage über Meldungen; ohne Art nähme derselbe Knopf beim nächsten
 *    löschbaren Vokabular stillschweigend mehr mit, als draufsteht.
 *
 * ## Der eigene Zustand ist Absicht
 *
 * Wie beim Abhaken: Die Liste kommt als Server-Eigenschaft und weiß von
 * einer Löschung nichts. Ohne diesen Zwischenstand bliebe die Zeile nach
 * dem Klick stehen — und der Fix sähe aus wie der Fehler. `onChanged` ist
 * der Weg für das Produkt, zusätzlich frisch zu laden; ohne ihn stimmt die
 * Ansicht trotzdem.
 */
interface UseLoeschenOptions {
    /** Wohin geschrieben wird. `null` schaltet das Löschen ganz ab. */
    href?: string | null;
    /** Nach einer Löschung — für ein Produkt, das seine Liste neu holt. */
    onChanged?: () => void;
}
interface UseLoeschenResult {
    /** Schlüssel der Zeilen, die in dieser Sitzung gelöscht wurden. */
    geloescht: Set<string>;
    /** Arten, die in dieser Sitzung ganz geleert wurden. */
    geleerteArten: Set<string>;
    /** Läuft gerade ein Schreibvorgang? */
    laeuft: boolean;
    /** Was schiefging — oder `null`. Gehört in die Ansicht, nicht ins Log. */
    fehler: string | null;
    loeschen: (zeilen: InboxItem[]) => Promise<void>;
    alleDerArt: (art: string) => Promise<void>;
}
declare function useLoeschen(options?: UseLoeschenOptions): UseLoeschenResult;

/**
 * Die CSRF-Marke aus dem Dokument — wenn es eine gibt.
 *
 * ## Warum das eine eigene Datei ist
 *
 * Weil es zwei Schreibwege gibt (Abhaken und Löschen) und die Marke für
 * beide dieselbe ist. Als zweite Kopie in `useLoeschen` wäre sie genau die
 * Dopplung, die still veraltet: Wer die Cookie-Variante irgendwann ergänzt,
 * ergänzt sie in einer der beiden Dateien.
 *
 * ## Zwei Quellen, und beide sind nötig
 *
 * Inertia-Anwendungen haben oft **kein** `<meta name="csrf-token">`; ihr
 * Client schickt den `XSRF-TOKEN`-Cookie als Kopfzeile. Wer blind
 * `querySelector` liest, bekommt `null` und schickt einen leeren Kopf.
 *
 * Deshalb wird beides versucht — und der Kopf **weggelassen**, wenn es
 * nichts zu schicken gibt. Ein leerer Kopf täuscht einen Mechanismus vor,
 * den es nicht gibt, und verschiebt den Fehler von „fehlt" auf „ist falsch".
 */
declare function csrfHeaders(): Record<string, string>;

export { type InboxData, type InboxDeletable, type InboxItem, type InboxKind, InboxList, type InboxMarkable, InboxPage, type InboxTotals, type UseAbhakenOptions, type UseAbhakenResult, type UseLoeschenOptions, type UseLoeschenResult, artLoeschbar, bekannteFarben, bekannteSymbole, bestandJeArt, csrfHeaders, darfAbhaken, darfLoeschen, eigenerBestandAbhakbar, eigenerBestandDerArt, hinweisZurListe, inboxIcon, inboxSurfaceClass, inboxTextClass, istEigenAbhakbar, loeschFrage, nochUngelesen, useAbhaken, useLoeschen, zeilenSchluessel };
