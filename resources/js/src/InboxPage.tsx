import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { InboxList } from './InboxList';
import { inboxIcon } from './icons';
import { Check, CheckCheck, Settings as Zahnrad } from 'lucide-react';
import { cn } from './ui/utils';
import { bestandJeArt, darfAbhaken, eigenerBestandAbhakbar, hinweisZurListe, nochUngelesen } from './abhaken';
import { useAbhaken } from './useAbhaken';
import type { InboxData, InboxItem } from './types';

interface Props extends InboxData {
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
export function InboxPage({
    items,
    kinds,
    counts,
    totals,
    markable,
    unreachable,
    failed,
    labels,
    formatTime,
    onOpen,
    renderActions,
    initialKind,
    settingsHref = '/settings/notifications',
    readHref = '/posteingang/gelesen',
    onMarked,
    title = 'Posteingang',
    subtitle = 'Alles, was auf dich wartet — aus allen Systemen',
}: Props) {
    const haken = useAbhaken({ href: readHref, onChanged: onMarked });

    const ersterMitInhalt = useMemo(
        () => kinds.find((k) => (counts[k.key] ?? 0) > 0)?.key ?? kinds[0]?.key ?? '',
        [kinds, counts],
    );

    const [offen, setOffen] = useState(initialKind ?? ersterMitInhalt);

    /*
     * Die Liste mit dem, was in dieser Sitzung schon abgehakt wurde.
     *
     * Sie kommt als Server-Eigenschaft und weiss von einem Klick nichts.
     * Ohne diesen Zwischenstand blieb die Zeile rot stehen, und der Fix
     * sah genauso aus wie der Fehler.
     */
    const zeilen = useMemo(
        () =>
            items.map((item) => ({
                ...item,
                is_unread: nochUngelesen(item, haken.abgehakt, haken.alleEigenenAbgehakt, markable),
            })),
        [items, haken.abgehakt, haken.alleEigenenAbgehakt, markable],
    );

    // Die Zahlen an den Reitern kommen vom Server (sie zaehlen die Liste)
    // und werden um das verringert, was seither abgehakt wurde. Neu
    // auszuzaehlen waere falsch: Eine Zeile, die beim Laden schon gelesen
    // war, zaehlt in `counts` nicht mit.
    const zahlen = useMemo(() => {
        const angepasst: Record<string, number> = { ...counts };

        items.forEach((item, i) => {
            if (item.is_unread && zeilen[i]?.is_unread === false) {
                angepasst[item.kind] = Math.max(0, (angepasst[item.kind] ?? 0) - 1);
            }
        });

        return angepasst;
    }, [counts, items, zeilen]);

    const sichtbar = useMemo(() => zeilen.filter((i) => i.kind === offen), [zeilen, offen]);

    // „Alle eigenen" nennt seine Zahl. Ein Knopf, der „alle" verspricht
    // und einen Teil erledigt, ist schlimmer als einer, der sagt, wie
    // viele er nimmt — was in einem anderen System liegt, bleibt dort.
    const eigeneOffen = haken.alleEigenenAbgehakt ? 0 : eigenerBestandAbhakbar(totals, markable);

    // Der Satz unter der Liste, und zwar fuer die Art, die offen ist:
    // Die Liste zeigt die neuesten, der Bestand kann weit darueber liegen.
    const satz = hinweisZurListe(sichtbar.length, bestandJeArt(totals)[offen] ?? 0);

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-semibold">{title}</h1>
                    <p className="text-sm text-muted-foreground">{subtitle}</p>
                </div>

                <div className="flex shrink-0 flex-wrap items-center gap-2">
                    {readHref !== null && eigeneOffen > 0 && (
                        <button
                            type="button"
                            disabled={haken.laeuft}
                            onClick={() => void haken.alleEigenen()}
                            className="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted/50 hover:text-foreground disabled:opacity-60"
                        >
                            <CheckCheck className="h-4 w-4" />
                            <span>Alle {eigeneOffen} eigenen als gelesen</span>
                        </button>
                    )}

                    {/* Der Weg zu den Einstellungen gehoert HIERHER.
                        Bisher fuehrte er ueber Avatar → Settings → den Reiter
                        „Benachrichtigungen" zwischen sechs anderen. Drei Klicks
                        fuer etwas, das man genau in dem Moment sucht, in dem
                        man auf diese Liste sieht und sie zu voll findet. */}
                    {settingsHref !== null && (
                        <a
                            href={settingsHref}
                            className="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted/50 hover:text-foreground"
                        >
                            <Zahnrad className="h-4 w-4" />
                            <span>Einstellungen</span>
                        </a>
                    )}
                </div>
            </div>

            {haken.fehler !== null && (
                // Wer abhakt und es kommt nicht an, muss das sehen. Sonst
                // steht die Zeile beim Neuladen wieder da — und der Fehler
                // sieht aus wie der Fix.
                <div role="alert" className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    Das Abhaken kam nicht an: {haken.fehler}
                </div>
            )}

            {(unreachable.length > 0 || failed.length > 0) && (
                // Eine still verkuerzte Liste sieht aus wie „da ist
                // nichts" — und dann sucht man im falschen System.
                <div className="rounded-md bg-warning-subtle px-3 py-2 text-sm text-warning-foreground">
                    {unreachable.length > 0 && <p>Nicht erreichbar: {unreachable.join(', ')}. Von dort fehlen gerade Einträge.</p>}
                    {failed.length > 0 && <p>Hier konnte gerade nicht geladen werden: {failed.join(', ')}.</p>}
                </div>
            )}

            <div className="flex flex-wrap gap-1 rounded-lg bg-muted/50 p-1" role="tablist">
                {kinds.map((art) => {
                    const Symbol = inboxIcon(art.icon);
                    const zahl = zahlen[art.key] ?? 0;
                    const aktiv = art.key === offen;

                    return (
                        <button
                            key={art.key}
                            type="button"
                            role="tab"
                            aria-selected={aktiv}
                            onClick={() => setOffen(art.key)}
                            className={cn(
                                // KEIN `flex-1`: Bei sechs Reitern quetscht das
                                // jeden auf gleiche Breite, und der Zaehler wird
                                // zu einem leeren roten Punkt zusammengedrueckt.
                                // Genau so sah es im ersten Browser-Durchgang aus.
                                'flex items-center gap-2 rounded-md px-3 py-2 text-sm whitespace-nowrap transition-colors',
                                aktiv ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <Symbol className="h-4 w-4 shrink-0" />
                            <span>{art.label}</span>
                            {zahl > 0 && (
                                // `text-white`, nicht `text-destructive-foreground`:
                                // Das ist in den Produkt-Themes nicht gesetzt und
                                // erbt dann das Grau des Reiters — Grau auf Rot bei
                                // fast gleicher Helligkeit. Die Zahl stand da und
                                // war trotzdem unsichtbar; gefunden erst beim
                                // Nachmessen der berechneten Farbe, nicht beim
                                // Hinsehen.
                                <span className="inline-flex min-w-5 shrink-0 items-center justify-center rounded-full bg-destructive px-1.5 py-0.5 text-xs leading-none font-medium text-white tabular-nums">
                                    {zahl}
                                </span>
                            )}
                        </button>
                    );
                })}
            </div>

            <div role="tabpanel">
                <InboxList
                    items={sichtbar}
                    formatTime={formatTime}
                    localLabel={labels.local}
                    sourceLabels={labels.sources}
                    onOpen={(eintrag) => {
                        /*
                         * Eine Zeile zu OEFFNEN heisst, sie gesehen zu haben
                         * (#1096).
                         *
                         * Vorher navigierte der Klick nur, und die rote Zeile
                         * stand beim Zurueckkommen wieder da — das war die
                         * Haelfte von Bastians Satz „die roten bleiben immer
                         * da". Ohne `await`: Die Marke soll den Seitenwechsel
                         * nicht aufhalten, und sie ist auch dann richtig
                         * gesetzt, wenn die Antwort erst nach dem Wechsel
                         * eintrifft.
                         *
                         * Und bewusst HIER und nicht im Produkt: Es waere in
                         * allen vier derselbe Code.
                         */
                        if (readHref !== null && darfAbhaken(eintrag, markable)) {
                            void haken.abhaken([eintrag]);
                        }

                        onOpen(eintrag);
                    }}
                    renderActions={(eintrag) => (
                        <>
                            {readHref !== null && darfAbhaken(eintrag, markable) && (
                                <button
                                    type="button"
                                    title="Als gelesen markieren"
                                    aria-label="Als gelesen markieren"
                                    disabled={haken.laeuft}
                                    onClick={(event) => {
                                        // Sonst oeffnet derselbe Klick die Zeile,
                                        // und die Seite ist weg, bevor man sieht,
                                        // dass markiert wurde.
                                        event.stopPropagation();
                                        void haken.abhaken([eintrag]);
                                    }}
                                    className="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:opacity-60"
                                >
                                    <Check className="h-4 w-4" />
                                </button>
                            )}
                            {/* Was nur EIN Produkt an einer Zeile kann, bleibt
                                beim Produkt. Der Haken ist das Gegenteil: Er
                                waere in allen vier derselbe Code. */}
                            {renderActions?.(eintrag)}
                        </>
                    )}
                />
            </div>

            {satz !== null && <p className="text-center text-xs text-muted-foreground">{satz}</p>}
        </div>
    );
}

export default InboxPage;
