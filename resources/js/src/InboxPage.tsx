import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { InboxList } from './InboxList';
import { inboxIcon } from './icons';
import { cn } from './ui/utils';
import type { InboxData, InboxItem } from './types';

interface Props extends InboxData {
    formatTime: (iso: string) => string;
    onOpen: (item: InboxItem) => void;
    renderActions?: (item: InboxItem) => ReactNode;
    /** Welcher Reiter zuerst offen ist. Ohne Angabe der erste mit Inhalt. */
    initialKind?: string;
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
export function InboxPage({
    items,
    kinds,
    counts,
    unreachable,
    failed,
    labels,
    formatTime,
    onOpen,
    renderActions,
    initialKind,
    title = 'Posteingang',
    subtitle = 'Alles, was auf dich wartet — aus allen Systemen',
}: Props) {
    const ersterMitInhalt = useMemo(
        () => kinds.find((k) => (counts[k.key] ?? 0) > 0)?.key ?? kinds[0]?.key ?? '',
        [kinds, counts],
    );

    const [offen, setOffen] = useState(initialKind ?? ersterMitInhalt);

    const sichtbar = useMemo(() => items.filter((i) => i.kind === offen), [items, offen]);

    return (
        <div className="space-y-4">
            <div>
                <h1 className="text-2xl font-semibold">{title}</h1>
                <p className="text-sm text-muted-foreground">{subtitle}</p>
            </div>

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
                    const zahl = counts[art.key] ?? 0;
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
                                <span className="inline-flex min-w-5 shrink-0 items-center justify-center rounded-full bg-destructive px-1.5 py-0.5 text-xs leading-none text-destructive-foreground tabular-nums">
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
                    onOpen={onOpen}
                    renderActions={renderActions}
                />
            </div>
        </div>
    );
}

export default InboxPage;
