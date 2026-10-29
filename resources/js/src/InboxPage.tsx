import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { InboxList } from './InboxList';
import { inboxIcon } from './icons';
import { Check, CheckCheck, RefreshCw, Settings as Zahnrad, Trash2 } from 'lucide-react';
import { cn } from './ui/utils';
import {
    bestandJeArt,
    darfAbhaken,
    eigenerBestandAbhakbar,
    hinweisZurListe,
    nochUngelesen,
    zeilenSchluessel,
} from './abhaken';
import { artLoeschbar, darfLoeschen, eigenerBestandDerArt, loeschFrage } from './loeschen';
import { useAbhaken } from './useAbhaken';
import { useLoeschen } from './useLoeschen';
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
     * Vorgabe `window.confirm`. Als Prop, weil ein Produkt einen eigenen
     * Dialog haben kann — und weil sich eine Rueckfrage sonst nicht
     * pruefen laesst, ohne den Browser zu fragen.
     */
    confirm?: (frage: string) => boolean;
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
    deletable,
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
    deleteHref = '/posteingang/zeilen',
    onDeleted,
    onRefresh,
    confirm: bestaetigen,
    title = 'Posteingang',
    subtitle = 'Alles, was auf dich wartet — aus allen Systemen',
}: Props) {
    const haken = useAbhaken({ href: readHref, onChanged: onMarked });
    const papierkorb = useLoeschen({ href: deleteHref, onChanged: onDeleted });

    /*
     * Die Rueckfrage. Vorgabe `window.confirm` (AI Brain #9069).
     *
     * Die alte Meldungsseite hatte „Alle loeschen" OHNE Rueckfrage — ein
     * Klick, und alle Meldungen der Person waren weg, auch die gelesenen.
     * Auf einer Seite, die nur Meldungen zeigte, war das vertretbar. Hier
     * steht derselbe Knopf neben Reitern fuer Aufgaben und Belege, und bei
     * Bastian waeren es ueber 4000 Zeilen gewesen.
     *
     * Ohne `window` (serverseitiges Rendern) wird NICHT geloescht: Ein
     * Rueckfall auf „ja" waere genau an der Stelle falsch, an der die
     * Rueckfrage der Schutz ist.
     */
    const fragen = bestaetigen ?? ((frage: string) => (typeof window === 'undefined' ? false : window.confirm(frage)));

    /*
     * Die zwei Filter, die von der alten Meldungsseite mitkommen (#9069).
     *
     * Beide sind DARSTELLUNG und kein Abruf: Es wird nichts nachgeladen,
     * nur ausgeblendet. Die Zahlen an den Reitern bleiben deshalb, was sie
     * sind, und springen nicht, waehrend man filtert.
     *
     * Die Herkunft ist eine ANDERE Achse als die Reiter: Die Reiter
     * trennen nach ART (Aufgaben, Belege, Meldungen), dieser Filter nach
     * SYSTEM. Keine der beiden ersetzt die andere — „zeig mir, was im
     * Manager passiert ist" ist ohne ihn nicht beantwortbar.
     */
    const [nurUngelesen, setNurUngelesen] = useState(false);
    const [herkunft, setHerkunft] = useState<string | null>(null);

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
            items
                /*
                 * Was in dieser Sitzung geloescht wurde, ist weg (#9069).
                 *
                 * Und zwar VOR allem anderen: Eine geloeschte Zeile darf
                 * nicht mehr in den Zahlen, im Filter oder im Satz unter
                 * der Liste auftauchen. Dieselbe Begruendung wie beim
                 * Abhaken, nur strenger — abgehakt heisst „gesehen",
                 * geloescht heisst „nicht mehr da".
                 */
                .filter(
                    (item) =>
                        !papierkorb.geloescht.has(zeilenSchluessel(item)) &&
                        !(papierkorb.geleerteArten.has(item.kind) && !item.source),
                )
                .map((item) => ({
                    ...item,
                    is_unread: nochUngelesen(item, haken.abgehakt, haken.alleEigenenAbgehakt, markable),
                })),
        [items, haken.abgehakt, haken.alleEigenenAbgehakt, markable, papierkorb.geloescht, papierkorb.geleerteArten],
    );

    // Die Zahlen an den Reitern kommen vom Server (sie zaehlen die Liste)
    // und werden um das verringert, was seither abgehakt wurde. Neu
    // auszuzaehlen waere falsch: Eine Zeile, die beim Laden schon gelesen
    // war, zaehlt in `counts` nicht mit.
    const zahlen = useMemo(() => {
        const angepasst: Record<string, number> = { ...counts };

        // Nach SCHLUESSEL und nicht nach Position: `zeilen` ist seit #9069
        // auch gefiltert (Geloeschtes faellt heraus), die Indizes passen
        // also nicht mehr aufeinander. Mit `forEach((item, i) => zeilen[i])`
        // haette diese Rechnung nach der ersten Loeschung die falsche Zeile
        // verglichen.
        const nachSchluessel = new Map(zeilen.map((z) => [zeilenSchluessel(z), z]));

        items.forEach((item) => {
            if (!item.is_unread) {
                return;
            }

            const noch = nachSchluessel.get(zeilenSchluessel(item));

            // Entweder gelesen geworden ODER verschwunden — beides nimmt
            // die Zeile aus der Zahl am Reiter.
            if (!noch || noch.is_unread === false) {
                angepasst[item.kind] = Math.max(0, (angepasst[item.kind] ?? 0) - 1);
            }
        });

        return angepasst;
    }, [counts, items, zeilen]);

    /** Die Zeilen der offenen Art — vor den Filtern, fuer Zahlen und Knoepfe. */
    const derArt = useMemo(() => zeilen.filter((i) => i.kind === offen), [zeilen, offen]);

    /**
     * Welche Systeme in dieser Art ueberhaupt vorkommen.
     *
     * Aus den ZEILEN und nicht aus `labels`: Ein Filter auf ein System
     * ohne Zeilen fuehrt zu einer leeren Liste und sieht aus wie ein
     * Fehler. Gezeigt wird nur, was auch etwas uebrig laesst.
     */
    const systeme = useMemo(() => {
        const vorhanden = new Map<string, string>();

        derArt.forEach((item) => {
            const schluessel = item.source ?? '';
            vorhanden.set(
                schluessel,
                item.source ? (item.source_label ?? labels.sources[item.source] ?? item.source) : labels.local,
            );
        });

        return [...vorhanden.entries()];
    }, [derArt, labels]);

    // Ein Filter, der nichts uebrig laesst, soll nicht wie ein leerer
    // Posteingang aussehen — deshalb unten ein eigener Satz dafuer.
    const sichtbar = useMemo(
        () =>
            derArt.filter(
                (item) => (!nurUngelesen || item.is_unread) && (herkunft === null || (item.source ?? '') === herkunft),
            ),
        [derArt, nurUngelesen, herkunft],
    );

    /*
     * „Neu" und „Frueher" (#9069).
     *
     * Von der alten Meldungsseite uebernommen, weil es die Liste lesbar
     * macht: Ungelesenes zuerst, mit Zahl, und darunter der Verlauf. Die
     * Reihenfolge innerhalb der Gruppen kommt vom Server und bleibt.
     *
     * Getrennt wird nur, wenn BEIDE Gruppen etwas haben — zwei
     * Ueberschriften ueber einer einzigen Gruppe sind Laerm.
     */
    const neue = useMemo(() => sichtbar.filter((i) => i.is_unread), [sichtbar]);
    const frueher = useMemo(() => sichtbar.filter((i) => !i.is_unread), [sichtbar]);
    const gruppiert = neue.length > 0 && frueher.length > 0;

    // „Alle eigenen" nennt seine Zahl. Ein Knopf, der „alle" verspricht
    // und einen Teil erledigt, ist schlimmer als einer, der sagt, wie
    // viele er nimmt — was in einem anderen System liegt, bleibt dort.
    const eigeneOffen = haken.alleEigenenAbgehakt ? 0 : eigenerBestandAbhakbar(totals, markable);

    // Der Satz unter der Liste, und zwar fuer die Art, die offen ist:
    // Die Liste zeigt die neuesten, der Bestand kann weit darueber liegen.
    // Mit `derArt` und nicht `sichtbar`: Der Satz erklaert die KAPPUNG,
    // nicht den Filter — sonst behauptete er bei gesetztem Filter eine
    // Luecke, die der Filter gemacht hat.
    const satz = hinweisZurListe(derArt.length, bestandJeArt(totals)[offen] ?? 0);

    /*
     * „Alle meine … loeschen" — nur im offenen Reiter, nur wo es geht.
     *
     * Das Wort der ART in der Beschriftung und in der Rueckfrage: Der
     * Knopf steht neben Reitern fuer Aufgaben und Belege, und „alles
     * loeschen" liest sich dort wie „meinen ganzen Posteingang".
     */
    const offeneArt = kinds.find((k) => k.key === offen);
    const darfArtLeeren = deleteHref !== null && artLoeschbar(offen, deletable) && !papierkorb.geleerteArten.has(offen);
    const bestandDerArt = eigenerBestandDerArt(offen, totals);
    const wortDerArt = offeneArt?.label ?? 'Einträge';

    /*
     * Oeffnen und die Aktionen je Zeile — einmal definiert (#9069).
     *
     * Seit die Liste in „Neu" und „Frueher" geteilt wird, gibt es zwei
     * {@see InboxList}-Aufrufe. Beide dieselben Eigenschaften abschreiben
     * zu lassen waere die Dopplung, die still auseinanderlaeuft — ein
     * Loeschknopf, den nur eine der beiden Gruppen hat, faellt niemandem
     * auf, der gerade keine gelesenen Zeilen hat.
     */
    const oeffnenMitHaken = (eintrag: InboxItem): void => {
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
    };

    const zeilenAktionen = (eintrag: InboxItem): ReactNode => (
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
            {/*
                                Loeschen je Zeile (#9069).

                                Ohne Rueckfrage, und das ist bewusst: Es
                                trifft genau die eine Zeile, die man
                                angeklickt hat, und steht als Symbol daneben
                                — anders als „alle", wo die Zahl erst in der
                                Rueckfrage sichtbar wird. Bastians Vorgabe:
                                „Einzelloeschen ohne Rueckfrage ist ok."
                            */}
            {deleteHref !== null && darfLoeschen(eintrag, deletable) && (
                <button
                    type="button"
                    title="Löschen"
                    aria-label="Löschen"
                    disabled={papierkorb.laeuft}
                    onClick={(event) => {
                        // Sonst oeffnet derselbe Klick die
                        // Zeile, und die Seite ist weg, bevor
                        // man sieht, dass geloescht wurde.
                        event.stopPropagation();
                        void papierkorb.loeschen([eintrag]);
                    }}
                    className="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive disabled:opacity-60"
                >
                    <Trash2 className="h-4 w-4" />
                </button>
            )}
            {/* Was nur EIN Produkt an einer Zeile kann, bleibt
                                beim Produkt. Der Haken ist das Gegenteil: Er
                                waere in allen vier derselbe Code. */}
            {renderActions?.(eintrag)}
        </>
    );

    /** Was die Liste gemeinsam braucht — unabhaengig von der Gruppe. */
    const listenGrundlage = {
        formatTime,
        localLabel: labels.local,
        sourceLabels: labels.sources,
        onOpen: oeffnenMitHaken,
        renderActions: zeilenAktionen,
    };

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

                    {/*
                        „Alle meine … loeschen" (#9069).

                        Nur wenn die offene Art es erlaubt — Aufgaben und
                        Belege also nie. Und nur, wenn ueberhaupt etwas da
                        ist: Ein Knopf, der nichts zu loeschen hat, ist
                        eine Einladung ohne Inhalt.
                    */}
                    {darfArtLeeren && derArt.some((i) => !i.source) && (
                        <button
                            type="button"
                            disabled={papierkorb.laeuft}
                            onClick={() => {
                                // Die Rueckfrage ist der Schutz, nicht die
                                // Hoeflichkeit: Hier verschwinden auch die
                                // GELESENEN, und bei Bastian waeren das
                                // ueber 4000 Zeilen auf einen Klick.
                                if (
                                    fragen(
                                        loeschFrage(wortDerArt, bestandDerArt, derArt.filter((i) => !i.source).length),
                                    )
                                ) {
                                    void papierkorb.alleDerArt(offen);
                                }
                            }}
                            className="inline-flex items-center gap-2 rounded-md border border-destructive/40 px-3 py-2 text-sm text-destructive transition-colors hover:bg-destructive/10 disabled:opacity-60"
                        >
                            <Trash2 className="h-4 w-4" />
                            <span>Alle meine {wortDerArt} löschen</span>
                        </button>
                    )}

                    {/* Neu laden — nur, wenn das Produkt es kann. */}
                    {onRefresh && (
                        <button
                            type="button"
                            title="Neu laden"
                            aria-label="Neu laden"
                            onClick={() => onRefresh()}
                            className="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted/50 hover:text-foreground"
                        >
                            <RefreshCw className="h-4 w-4" />
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
                    {unreachable.length > 0 && (
                        <p>Nicht erreichbar: {unreachable.join(', ')}. Von dort fehlen gerade Einträge.</p>
                    )}
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

            {/*
                Die zwei Filter von der alten Meldungsseite (#9069).

                Darstellung, kein Abruf — die Zahlen an den Reitern bleiben
                stehen und springen nicht, waehrend man filtert.

                Die Herkunftsknoepfe nur, wenn in dieser Art MEHR ALS EIN
                System vorkommt: Zwischen „alles" und „alles" zu filtern
                ist kein Filter, sondern ein Knopf, der nichts tut.
            */}
            {(derArt.length > 0 || nurUngelesen || herkunft !== null) && (
                <div className="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        aria-pressed={nurUngelesen}
                        onClick={() => setNurUngelesen((an) => !an)}
                        className={cn(
                            'rounded-md border px-3 py-1.5 text-sm transition-colors',
                            nurUngelesen
                                ? 'border-transparent bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground',
                        )}
                    >
                        Nur ungelesene
                    </button>

                    {systeme.length > 1 && (
                        <div className="flex flex-wrap items-center gap-1">
                            <button
                                type="button"
                                aria-pressed={herkunft === null}
                                onClick={() => setHerkunft(null)}
                                className={cn(
                                    'rounded-md border px-3 py-1.5 text-sm transition-colors',
                                    herkunft === null
                                        ? 'border-transparent bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground',
                                )}
                            >
                                Alle Systeme
                            </button>
                            {systeme.map(([schluessel, wort]) => (
                                <button
                                    key={schluessel || 'hier'}
                                    type="button"
                                    aria-pressed={herkunft === schluessel}
                                    onClick={() => setHerkunft(herkunft === schluessel ? null : schluessel)}
                                    className={cn(
                                        'rounded-md border px-3 py-1.5 text-sm transition-colors',
                                        herkunft === schluessel
                                            ? 'border-transparent bg-primary text-primary-foreground'
                                            : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground',
                                    )}
                                >
                                    {wort}
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            )}

            {/*
                Ein Fehlschlag beim Loeschen gehoert SICHTBAR hierher.

                Dieselbe Regel wie beim Abhaken: Wer loescht und es kommt
                nicht an, muss das sehen — sonst steht die Zeile beim
                Neuladen wieder da, und der Fehler sieht aus wie der Fix.
            */}
            {papierkorb.fehler !== null && (
                <div className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {papierkorb.fehler}
                </div>
            )}

            <div role="tabpanel" className="space-y-4">
                {gruppiert ? (
                    <>
                        {/*
                            „Neu" und „Frueher" (#9069), von der alten
                            Meldungsseite uebernommen. Nur wenn beide
                            Gruppen etwas haben — zwei Ueberschriften ueber
                            einer einzigen Gruppe sind Laerm.
                        */}
                        <div className="space-y-2">
                            <p className="text-xs font-semibold text-muted-foreground">Neu ({neue.length})</p>
                            <InboxList items={neue} {...listenGrundlage} />
                        </div>
                        <div className="space-y-2">
                            <p className="text-xs font-semibold text-muted-foreground">Früher ({frueher.length})</p>
                            <InboxList items={frueher} {...listenGrundlage} />
                        </div>
                    </>
                ) : (
                    <InboxList
                        items={sichtbar}
                        {...listenGrundlage}
                        empty={
                            // Ein Filter, der nichts uebrig laesst, sieht
                            // sonst aus wie ein leerer Posteingang — und man
                            // sucht den Fehler bei sich statt beim Filter.
                            derArt.length > 0 ? 'Nichts, das zu diesem Filter passt.' : undefined
                        }
                    />
                )}
            </div>

            {satz !== null && <p className="text-center text-xs text-muted-foreground">{satz}</p>}
        </div>
    );
}

export default InboxPage;
