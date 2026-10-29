import { useCallback, useState } from 'react';
import { istEigenAbhakbar, zeilenSchluessel } from './abhaken';
import { csrfHeaders } from './csrf';
import type { InboxItem, InboxMarkable } from './types';

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

export interface UseAbhakenOptions {
    /** Wohin geschrieben wird. `null` schaltet das Abhaken ganz ab. */
    href?: string | null;
    /** Nach einer Aenderung — fuer ein Produkt, das seine Liste neu holt. */
    onChanged?: () => void;
}

export interface UseAbhakenResult {
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

export function useAbhaken(options: UseAbhakenOptions = {}): UseAbhakenResult {
    const { href = '/posteingang/gelesen', onChanged } = options;

    const [abgehakt, setAbgehakt] = useState<Set<string>>(() => new Set());
    const [alleEigenenAbgehakt, setAlleEigenenAbgehakt] = useState(false);
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
                    method: 'POST',
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
                    // Neuladen liefe die Oberflaeche danach still ins
                    // Leere — jeder Klick quittungslos.
                    window.location.reload();

                    return false;
                }

                if (!antwort.ok) {
                    // Der Satz des Servers, nicht ein eigener: Er nennt
                    // das System und den Grund. Und er gehoert SICHTBAR in
                    // die Ansicht — wer abhakt und es kommt nicht an, muss
                    // das sehen, sonst steht die Zeile beim Neuladen
                    // wieder da und der Fehler sieht aus wie der Fix.
                    const daten = (await antwort.json().catch(() => null)) as {
                        message?: string;
                    } | null;

                    setFehler(daten?.message ?? `Das Abhaken kam nicht an (${antwort.status}).`);

                    return false;
                }

                onChanged?.();

                return true;
            } catch (ursache) {
                setFehler(ursache instanceof Error ? ursache.message : 'Das Abhaken kam nicht an.');

                return false;
            } finally {
                setLaeuft(false);
            }
        },
        [href, onChanged],
    );

    // Der Typ steht ausdruecklich da, weil die Funktion sich selbst
    // aufruft: Ohne Annotation kann TypeScript den Rueckgabetyp nicht aus
    // einem Initialisierer herleiten, der sich selbst erwaehnt.
    const abhaken: (zeilen: InboxItem[]) => Promise<void> = useCallback(
        async (zeilen: InboxItem[]): Promise<void> => {
            if (zeilen.length === 0) {
                return;
            }

            // Eine Nutzlast je Herkunft UND Art: Kennungen sind nur
            // innerhalb einer Art eindeutig, und ein anderes System kann
            // nur seine eigenen abhaken. Das zusammenzufassen hiesse
            // raten, wohin welche Kennung gehoert.
            const erste = zeilen[0];

            if (!erste) {
                return;
            }

            const gleiche = zeilen.filter(
                (z) => z.kind === erste.kind && (z.source ?? null) === (erste.source ?? null),
            );

            const erfolg = await schreiben({
                source: erste.source ?? null,
                kind: erste.kind,
                ids: gleiche.map((z) => z.id),
            });

            if (erfolg) {
                setAbgehakt((bisher) => {
                    const neu = new Set(bisher);
                    gleiche.forEach((z) => neu.add(zeilenSchluessel(z)));

                    return neu;
                });
            }

            // Zeilen anderer Herkunft oder Art bekommen ihren eigenen
            // Aufruf — rekursiv, damit „alle sichtbaren" aus einer
            // gemischten Liste nicht die Haelfte verliert.
            const rest = zeilen.filter((z) => !gleiche.includes(z));

            if (rest.length > 0) {
                await abhaken(rest);
            }
        },
        [schreiben],
    );

    const alleEigenen = useCallback(async (): Promise<void> => {
        if (await schreiben({ alle: true })) {
            setAlleEigenenAbgehakt(true);
        }
    }, [schreiben]);

    return {
        abgehakt,
        alleEigenenAbgehakt,
        laeuft,
        fehler,
        abhaken,
        alleEigenen,
    };
}
