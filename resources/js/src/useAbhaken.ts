import { useCallback, useState } from 'react';
import { zeilenSchluessel } from './abhaken';
import { csrfHeaders } from './csrf';
import { meldePosteingangGeaendert } from './ereignis';
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
    /**
     * Ob „alle" gelaufen ist — hier und in jedem System, das abhaken kann
     * (seit v0.8.0, pm #10227).
     */
    alleAbgehakt: boolean;
    /** @deprecated Seit v0.8.0 gleich {@link alleAbgehakt}. */
    alleEigenenAbgehakt: boolean;
    /** Laeuft gerade ein Schreibvorgang? */
    laeuft: boolean;
    /** Was schiefging — oder `null`. Gehoert in die Ansicht, nicht ins Log. */
    fehler: string | null;
    abhaken: (zeilen: InboxItem[]) => Promise<void>;
    /** Alles abhaken — hier und drüben. */
    alle: () => Promise<void>;
    /** @deprecated Seit v0.8.0 gleich {@link alle}. */
    alleEigenen: () => Promise<void>;
}

interface Antwort {
    marked?: number;
    /** Systeme, die „alle" nicht angenommen haben. */
    failed?: string[];
}

export function useAbhaken(options: UseAbhakenOptions = {}): UseAbhakenResult {
    const { href = '/posteingang/gelesen', onChanged } = options;

    const [abgehakt, setAbgehakt] = useState<Set<string>>(() => new Set());
    const [alleAbgehakt, setAlleAbgehakt] = useState(false);
    const [laeuft, setLaeuft] = useState(false);
    const [fehler, setFehler] = useState<string | null>(null);

    const schreiben = useCallback(
        async (nutzlast: Record<string, unknown>): Promise<Antwort | null> => {
            if (!href) {
                return null;
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
                    /*
                     * `keepalive` (pm #10227): Wer eine Zeile OEFFNET,
                     * verlaesst bei einer fremden Zeile die Seite. Ohne
                     * diese Angabe bricht der Browser die Anfrage mit dem
                     * Seitenwechsel ab — die Zeile stand beim Zurueckkommen
                     * wieder als ungelesen da, und die Zahl war dieselbe.
                     * Bastian: „Auch wenn ich in einem drin war und wieder
                     * zurueckgehe, bleibt trotzdem die Anzahl gleich."
                     */
                    keepalive: true,
                });

                if (antwort.status === 419) {
                    // Eine abgelaufene Sitzung meldet sich als 419. Ohne
                    // Neuladen liefe die Oberflaeche danach still ins
                    // Leere — jeder Klick quittungslos.
                    window.location.reload();

                    return null;
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

                    return null;
                }

                const daten = ((await antwort.json().catch(() => null)) ?? {}) as Antwort;

                // „Alle" erledigt, was ging, und nennt, was nicht ging.
                // Das gehoert gesagt — sonst steht dort weiter etwas, und
                // der Knopf hat „alle" behauptet.
                if (Array.isArray(daten.failed) && daten.failed.length > 0) {
                    setFehler(`Nicht überall angekommen: ${daten.failed.join(', ')}`);
                }

                onChanged?.();
                meldePosteingangGeaendert();

                return daten;
            } catch (ursache) {
                setFehler(ursache instanceof Error ? ursache.message : 'Das Abhaken kam nicht an.');

                return null;
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

            if (erfolg !== null) {
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

    const alle = useCallback(async (): Promise<void> => {
        const daten = await schreiben({ alle: true });

        // Nur wenn NIRGENDS etwas scheiterte, gilt die ganze Liste als
        // gelesen. Sonst kommt der wahre Stand mit `onChanged` vom Server.
        if (daten !== null && (daten.failed ?? []).length === 0) {
            setAlleAbgehakt(true);
        }
    }, [schreiben]);

    return {
        abgehakt,
        alleAbgehakt,
        alleEigenenAbgehakt: alleAbgehakt,
        laeuft,
        fehler,
        abhaken,
        alle,
        alleEigenen: alle,
    };
}
