import type { ReactNode } from 'react';
import { Badge } from './ui/Badge';
import { cn } from './ui/utils';
import { inboxIcon, inboxSurfaceClass, inboxTextClass } from './icons';
import type { InboxItem } from './types';

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
export function InboxList({ items, formatTime, localLabel, sourceLabels, onOpen, renderActions, empty }: Props) {
    if (items.length === 0) {
        return (
            <div className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                {empty ?? 'Nichts offen.'}
            </div>
        );
    }

    return (
        <div className="space-y-2">
            {items.map((item) => (
                <Zeile
                    key={`${item.source ?? 'hier'}:${item.kind}:${item.id}`}
                    item={item}
                    formatTime={formatTime}
                    localLabel={localLabel}
                    sourceLabels={sourceLabels}
                    onOpen={onOpen}
                    renderActions={renderActions}
                />
            ))}
        </div>
    );
}

function Zeile({
    item,
    formatTime,
    localLabel,
    sourceLabels,
    onOpen,
    renderActions,
}: Omit<Props, 'items' | 'empty'> & { item: InboxItem }) {
    const Symbol = inboxIcon(item.icon);
    const frisch = item.is_unread;

    // Eine Zeile ohne Herkunft kommt von hier. Das Wort steht trotzdem da:
    // Sonst muesste der Leser raten, ob die Marke fehlt oder die Zeile
    // eigen ist — und bei vier Systemen in einer Liste raet man falsch.
    const herkunft = item.source ? (item.source_label ?? sourceLabels[item.source] ?? item.source) : localLabel;

    const zusatz = Object.entries(item.meta ?? {})
        .filter(([, wert]) => wert !== null && wert !== '' && typeof wert !== 'object')
        .map(([, wert]) => String(wert));

    /*
     * Fuehrt diese Zeile irgendwohin? (AI Brain #1151)
     *
     * Der Klick bleibt in beiden Faellen: Er hakt die Zeile ab, und das
     * ist auch ohne Ziel richtig. Was wegfaellt, ist das VERSPRECHEN
     * eines Ziels — Zeigefinger und unterstrichener Titel.
     *
     * Bastians Satz dazu war „kann irgendwas nicht druecken". Genau das
     * entsteht hier: 59 imkr-Zeilen haben kein Ziel (sie gehoeren zu einem
     * Pane auf einem Rechner, zu keiner Seite), sahen aber aus wie ein
     * Link und taten beim Klick scheinbar nichts. Eine Zeile, die nicht
     * weiterfuehrt, soll das zeigen statt es zu verschweigen.
     */
    const hatZiel = typeof item.link === 'string' && item.link !== '';

    return (
        <div
            className={cn(
                'group rounded-lg border p-3',
                frisch ? inboxSurfaceClass(item.color) : 'transition-colors hover:bg-muted/30',
            )}
        >
            <div className="flex items-start gap-3">
                <div
                    className={cn(
                        'mt-0.5 shrink-0 rounded-full p-1.5',
                        frisch ? inboxSurfaceClass(item.color) : 'bg-muted',
                    )}
                >
                    <Symbol className={cn('h-4 w-4', frisch ? inboxTextClass(item.color) : 'text-muted-foreground')} />
                </div>

                <div
                    className={cn('min-w-0 flex-1', hatZiel ? 'cursor-pointer' : 'cursor-default')}
                    onClick={() => onOpen(item)}
                >
                    <div className="flex flex-wrap items-center gap-2">
                        <p
                            className={cn(
                                'font-medium',
                                hatZiel && 'hover:underline',
                                frisch ? inboxTextClass(item.color) : 'truncate',
                            )}
                        >
                            {item.title}
                        </p>
                        <Badge variant="outline">{herkunft}</Badge>
                    </div>

                    {item.message && <p className="mt-1 line-clamp-2 text-xs text-muted-foreground">{item.message}</p>}

                    <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                        {item.created_at && <span>{formatTime(item.created_at)}</span>}
                        {zusatz.map((wert, i) => (
                            <span key={i} className="truncate">
                                · {wert}
                            </span>
                        ))}
                    </div>
                </div>

                <div className="flex shrink-0 items-center gap-1">{renderActions?.(item)}</div>
            </div>
        </div>
    );
}

export default InboxList;
