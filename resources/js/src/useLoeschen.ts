import { useCallback, useState } from 'react';
import { csrfHeaders } from './csrf';
import { zeilenSchluessel } from './abhaken';
import type { InboxItem } from './types';

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

export interface UseLoeschenOptions {
    /** Wohin geschrieben wird. `null` schaltet das Löschen ganz ab. */
    href?: string | null;
    /** Nach einer Löschung — für ein Produkt, das seine Liste neu holt. */
    onChanged?: () => void;
}

export interface UseLoeschenResult {
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

export function useLoeschen(options: UseLoeschenOptions = {}): UseLoeschenResult {
    const { href = '/posteingang/zeilen', onChanged } = options;

    const [geloescht, setGeloescht] = useState<Set<string>>(() => new Set());
    const [geleerteArten, setGeleerteArten] = useState<Set<string>>(() => new Set());
    const [laeuft, setLaeuft] = useState(false);
    const [fehler, setFehler] = useState<string | null>(null);

    const schreiben = useCallback(
        async (nutzlast: Record<string, unknown>): Promise<boolean> => {
            if (!href) {
                return false;
            }

            setLaeuft(true);
            setFehler(null);

            try {
                const antwort = await fetch(href, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...csrfHeaders(),
                    },
                    body: JSON.stringify(nutzlast),
                });

                if (antwort.status === 419) {
                    // Eine abgelaufene Sitzung meldet sich als 419. Ohne
                    // Neuladen liefe die Oberfläche danach still ins Leere
                    // — jeder Klick quittungslos.
                    window.location.reload();

                    return false;
                }

                if (!antwort.ok) {
                    // Der Satz des Servers, nicht ein eigener: Er nennt den
                    // Grund. Und er gehört SICHTBAR in die Ansicht — wer
                    // löscht und es kommt nicht an, muss das sehen, sonst
                    // steht die Zeile beim Neuladen wieder da.
                    const daten = (await antwort.json().catch(() => null)) as {
                        message?: string;
                    } | null;

                    setFehler(daten?.message ?? `Das Löschen kam nicht an (${antwort.status}).`);

                    return false;
                }

                onChanged?.();

                return true;
            } catch (ursache) {
                setFehler(ursache instanceof Error ? ursache.message : 'Das Löschen kam nicht an.');

                return false;
            } finally {
                setLaeuft(false);
            }
        },
        [href, onChanged],
    );

    // Der Typ steht ausdrücklich da, weil die Funktion sich selbst aufruft:
    // Ohne Annotation kann TypeScript den Rückgabetyp nicht aus einem
    // Initialisierer herleiten, der sich selbst erwähnt.
    const loeschen: (zeilen: InboxItem[]) => Promise<void> = useCallback(
        async (zeilen: InboxItem[]): Promise<void> => {
            if (zeilen.length === 0) {
                return;
            }

            // Eine Nutzlast je ART: Kennungen sind nur innerhalb einer Art
            // eindeutig. Das zusammenzufassen hieße raten, wohin welche
            // Kennung gehört — und träfe womöglich die Meldung 42 UND die
            // Aufgabe 42.
            const erste = zeilen[0];

            if (!erste) {
                return;
            }

            const gleiche = zeilen.filter((z) => z.kind === erste.kind && !z.source);

            if (gleiche.length > 0) {
                const erfolg = await schreiben({
                    kind: erste.kind,
                    ids: gleiche.map((z) => z.id),
                });

                if (erfolg) {
                    setGeloescht((bisher) => {
                        const neu = new Set(bisher);
                        gleiche.forEach((z) => neu.add(zeilenSchluessel(z)));

                        return neu;
                    });
                }
            }

            // Zeilen anderer Art bekommen ihren eigenen Aufruf — rekursiv,
            // damit eine gemischte Auswahl nicht die Hälfte verliert.
            const rest = zeilen.filter((z) => !gleiche.includes(z));

            if (rest.length > 0) {
                await loeschen(rest);
            }
        },
        [schreiben],
    );

    const alleDerArt = useCallback(
        async (art: string): Promise<void> => {
            if (await schreiben({ alle: true, kind: art })) {
                setGeleerteArten((bisher) => new Set(bisher).add(art));
            }
        },
        [schreiben],
    );

    return { geloescht, geleerteArten, laeuft, fehler, loeschen, alleDerArt };
}
