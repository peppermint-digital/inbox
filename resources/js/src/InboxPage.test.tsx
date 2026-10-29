/** @vitest-environment jsdom */

import { cleanup, render, screen, fireEvent, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

// Ohne das bleibt der Baum der vorigen Pruefung stehen, und die naechste
// findet jeden Reiter doppelt — eine Fehlermeldung, die nach einem Fehler
// in der Komponente aussieht und keiner ist.
afterEach(cleanup);
import { InboxPage } from './InboxPage';
import type { InboxData, InboxItem } from './types';

function zeile(u: Partial<InboxItem> = {}): InboxItem {
    return {
        id: '1',
        kind: 'aufgabe',
        title: 'Angebot nachfassen',
        icon: 'inbox',
        color: 'blue',
        created_at: '2026-10-18T10:00:00+00:00',
        is_unread: true,
        ...u,
    };
}

function daten(u: Partial<InboxData> = {}): InboxData {
    return {
        items: [zeile()],
        kinds: [
            {
                key: 'aufgabe',
                label: 'Neue Aufgaben',
                icon: 'inbox',
                color: 'blue',
                order: 10,
            },
            {
                key: 'meldung',
                label: 'Benachrichtigungen',
                icon: 'bell',
                color: 'gray',
                order: 40,
            },
        ],
        counts: { aufgabe: 1 },
        unreachable: [],
        failed: [],
        labels: {
            local: 'Peppermint Manager',
            sources: { 'peppermint-crm': 'Peppermint CRM' },
        },
        ...u,
    };
}

const basis = {
    formatTime: (iso: string) => iso.slice(0, 10),
    onOpen: vi.fn(),
};

describe('InboxPage', () => {
    it('zeigt einen Reiter je Art, auch wenn er leer ist', () => {
        // „Keine offenen Aufgaben" ist eine Aussage, ein fehlender Reiter
        // ist keine — wer ihn gestern sah und heute nicht, sucht den
        // Fehler bei sich.
        render(<InboxPage {...daten()} {...basis} />);

        expect(screen.getByRole('tab', { name: /Neue Aufgaben/ })).toBeDefined();
        expect(screen.getByRole('tab', { name: /Benachrichtigungen/ })).toBeDefined();
    });

    it('oeffnet den ersten Reiter, der etwas enthaelt', () => {
        render(
            <InboxPage
                {...daten({
                    items: [zeile({ kind: 'meldung', title: 'Etwas gemeldet' })],
                    counts: { meldung: 1 },
                })}
                {...basis}
            />,
        );

        expect(screen.getByRole('tab', { name: /Benachrichtigungen/ }).getAttribute('aria-selected')).toBe('true');
    });

    it('schreibt das eigene System an eine Zeile ohne Herkunft', () => {
        // Sonst muesste der Leser raten, ob die Marke fehlt oder die Zeile
        // von hier ist — bei vier Systemen in einer Liste raet man falsch.
        render(<InboxPage {...daten()} {...basis} />);

        expect(screen.getByText('Peppermint Manager')).toBeDefined();
    });

    it('schreibt die Herkunft an eine fremde Zeile', () => {
        render(
            <InboxPage
                {...daten({
                    items: [zeile({ source: 'peppermint-crm', source_label: 'Peppermint CRM' })],
                })}
                {...basis}
            />,
        );

        expect(screen.getByText('Peppermint CRM')).toBeDefined();
    });

    it('sagt, welches System nicht geantwortet hat', () => {
        render(<InboxPage {...daten({ unreachable: ['Peppermint Verwaltung'] })} {...basis} />);

        expect(screen.getByText(/Nicht erreichbar: Peppermint Verwaltung/)).toBeDefined();
    });

    it('zeigt beim Wechsel nur die Zeilen der gewaehlten Art', () => {
        render(
            <InboxPage
                {...daten({
                    items: [zeile(), zeile({ id: '2', kind: 'meldung', title: 'Etwas gemeldet' })],
                    counts: { aufgabe: 1, meldung: 1 },
                })}
                {...basis}
            />,
        );

        expect(screen.queryByText('Etwas gemeldet')).toBeNull();

        fireEvent.click(screen.getByRole('tab', { name: /Benachrichtigungen/ }));

        expect(screen.getByText('Etwas gemeldet')).toBeDefined();
        expect(screen.queryByText('Angebot nachfassen')).toBeNull();
    });
});

describe('Die Reiterleiste', () => {
    it('zeigt die Zahl am Reiter, nicht nur einen Punkt', () => {
        // Beim ersten Browser-Durchgang quetschte `flex-1` sechs Reiter auf
        // gleiche Breite, und aus jedem Zaehler wurde ein leerer roter
        // Punkt. Die Zahl stand im Markup — sichtbar war sie nicht.
        render(<InboxPage {...daten({ counts: { aufgabe: 13 } })} {...basis} />);

        const reiter = screen.getByRole('tab', { name: /Neue Aufgaben/ });

        expect(reiter.textContent).toContain('13');
        expect(reiter.className).not.toContain('flex-1');
    });

    it('heisst auf Deutsch Posteingang', () => {
        render(<InboxPage {...daten()} {...basis} />);

        expect(screen.getByText('Posteingang')).toBeDefined();
    });
});

it('schreibt den Zaehler in einer Farbe, die auf Rot steht', () => {
    // `text-destructive-foreground` ist in den Produkt-Themes nicht
    // gesetzt und erbt das Grau des Reiters — Grau auf Rot bei fast
    // gleicher Helligkeit. Die Zahl war im Markup und trotzdem
    // unsichtbar.
    render(<InboxPage {...daten({ counts: { aufgabe: 13 } })} {...basis} />);

    const spans = Array.from(screen.getByRole('tab', { name: /Neue Aufgaben/ }).querySelectorAll('span'));
    const pille = spans[spans.length - 1];

    expect(pille?.className).toContain('text-white');
    expect(pille?.className).not.toContain('text-destructive-foreground');
});

describe('Der Weg zu den Einstellungen', () => {
    it('steht als Knopf auf der Seite', () => {
        // Bisher fuehrte er ueber Avatar → Settings → den Reiter
        // „Benachrichtigungen" zwischen sechs anderen. Drei Klicks fuer
        // etwas, das man genau dann sucht, wenn man auf diese Liste sieht
        // und sie zu voll findet.
        render(<InboxPage {...daten()} {...basis} />);

        const knopf = screen.getByRole('link', { name: /Einstellungen/ });

        expect(knopf.getAttribute('href')).toBe('/settings/notifications');
    });

    it('bleibt weg, wenn ein Produkt die Maske nicht hat', () => {
        // Ein Knopf, der ins Leere fuehrt, ist schlechter als keiner.
        //
        // Nach dem TEXT suchen, nicht nach der Link-Rolle: Ein `<a>` ohne
        // `href` hat gar keine Rolle `link`. Die erste Fassung dieser
        // Pruefung bestand deshalb auch dann, wenn der Riegel ausgehebelt
        // war — sie mass nichts.
        render(<InboxPage {...daten()} {...basis} settingsHref={null} />);

        expect(screen.queryByText('Einstellungen')).toBeNull();
    });
});

describe('Der Haken an einer Zeile (#1096)', () => {
    const markable = {
        local: ['aufgabe'],
        sources: { 'peppermint-crm': ['aufgabe'] },
    };

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    /** `fetch` ersetzen und mitschreiben, was geschickt wurde. */
    function fetchAttrappe(antwort: { ok: boolean; status?: number; body?: unknown }) {
        const aufrufe: Array<{ url: string; body: unknown }> = [];

        vi.stubGlobal(
            'fetch',
            vi.fn(async (url: string, init: RequestInit) => {
                aufrufe.push({ url, body: JSON.parse(String(init.body)) });

                return {
                    ok: antwort.ok,
                    status: antwort.status ?? (antwort.ok ? 200 : 502),
                    json: async () => antwort.body ?? { marked: 1 },
                };
            }),
        );

        return aufrufe;
    }

    it('zeichnet ihn nur, wo der Server sagt, dass er ankommt', () => {
        // Bis zum 27.10.2026 stand diese Regel im Produkt und lautete
        // „von hier, ungelesen, Art notification" — eine Regel, die jedes
        // der vier Produkte haette abschreiben muessen.
        render(
            <InboxPage
                {...daten({
                    items: [zeile(), zeile({ id: '2', kind: 'meldung', title: 'Etwas gemeldet' })],
                    counts: { aufgabe: 1, meldung: 1 },
                    markable,
                })}
                {...basis}
            />,
        );

        expect(screen.getAllByRole('button', { name: 'Als gelesen markieren' })).toHaveLength(1);

        fireEvent.click(screen.getByRole('tab', { name: /Benachrichtigungen/ }));

        expect(screen.queryByRole('button', { name: 'Als gelesen markieren' })).toBeNull();
    });

    it('bleibt weg, wenn das Produkt die Route nicht laedt', () => {
        render(<InboxPage {...daten({ markable })} {...basis} readHref={null} />);

        expect(screen.queryByRole('button', { name: 'Als gelesen markieren' })).toBeNull();
    });

    it('schickt Herkunft, Art und Kennung an den Endpunkt', async () => {
        // Die Art MUSS mit: Kennungen sind nur innerhalb einer Art
        // eindeutig, und ohne sie traefe das Zielsystem womoeglich die
        // Meldung 42 statt der Aufgabe 42.
        const aufrufe = fetchAttrappe({ ok: true, body: { marked: 1 } });

        render(
            <InboxPage
                {...daten({
                    items: [zeile({ id: '42', source: 'peppermint-crm' })],
                    markable,
                })}
                {...basis}
            />,
        );

        fireEvent.click(screen.getByRole('button', { name: 'Als gelesen markieren' }));

        await vi.waitFor(() => expect(aufrufe).toHaveLength(1));

        expect(aufrufe[0]?.url).toBe('/posteingang/gelesen');
        expect(aufrufe[0]?.body).toEqual({
            source: 'peppermint-crm',
            kind: 'aufgabe',
            ids: ['42'],
        });
    });

    it('nimmt die Zeile sofort aus dem Ungelesenen', async () => {
        // Die Liste kommt als Server-Eigenschaft und weiss von einem Klick
        // nichts. Ohne diesen Zwischenstand blieb die Zeile rot stehen —
        // und der Fix sah genauso aus wie der Fehler.
        fetchAttrappe({ ok: true, body: { marked: 1 } });

        render(<InboxPage {...daten({ counts: { aufgabe: 1 }, markable })} {...basis} />);

        expect(screen.getByRole('tab', { name: /Neue Aufgaben/ }).textContent).toContain('1');

        fireEvent.click(screen.getByRole('button', { name: 'Als gelesen markieren' }));

        await vi.waitFor(() =>
            expect(screen.getByRole('tab', { name: /Neue Aufgaben/ }).textContent).not.toContain('1'),
        );

        // Und der Haken ist weg: Ein zweiter Klick wuerde den Lesezeitpunkt
        // neu setzen.
        expect(screen.queryByRole('button', { name: 'Als gelesen markieren' })).toBeNull();
    });

    it('sagt es, wenn das Abhaken NICHT ankam', async () => {
        /*
         * Der wichtigste Fall. Das Holen wirft absichtlich eine Ausnahme
         * statt einer leeren Liste, damit die Ansicht „nicht erreichbar"
         * von „nichts da" trennt. Beim Schreiben gilt dasselbe: Wer abhakt
         * und es kommt nicht an, muss das sehen — sonst steht die Zeile
         * beim Neuladen wieder da.
         */
        fetchAttrappe({
            ok: false,
            status: 502,
            body: { message: 'unreachable: CRM antwortet nicht' },
        });

        render(<InboxPage {...daten({ markable })} {...basis} />);

        fireEvent.click(screen.getByRole('button', { name: 'Als gelesen markieren' }));

        await vi.waitFor(() =>
            expect(screen.getByRole('alert').textContent).toContain('unreachable: CRM antwortet nicht'),
        );

        // Und die Zeile bleibt ungelesen — nichts wird vorgetaeuscht.
        expect(screen.getByRole('button', { name: 'Als gelesen markieren' })).toBeDefined();
    });
});

describe('„Alle eigenen als gelesen"', () => {
    const markable = { local: ['aufgabe'], sources: {} };
    const totals = {
        local: { aufgabe: 3953 },
        sources: { 'peppermint-crm': { aufgabe: 40 } },
    };

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('nennt die Zahl, die er wirklich nimmt', () => {
        // 3953, nicht 3993: Die 40 im CRM bleiben dort liegen. Ein Knopf,
        // der „alle" verspricht und einen Teil erledigt, ist schlimmer als
        // einer, der sagt, wie viele er nimmt.
        render(<InboxPage {...daten({ totals, markable })} {...basis} />);

        expect(screen.getByRole('button', { name: /Alle 3953 eigenen als gelesen/ })).toBeDefined();
    });

    it('bleibt weg, wenn hier nichts ungelesen ist', () => {
        render(<InboxPage {...daten({ totals: { local: {}, sources: {} }, markable })} {...basis} />);

        expect(screen.queryByText(/eigenen als gelesen/)).toBeNull();
    });

    it('schickt „alle" und nicht eine Liste von Kennungen', async () => {
        // Bei 3953 Zeilen waere die Liste der Kennungen eine Fahrt durch
        // den Browser, und der Deckel je Aufruf wuerde sie kappen — der
        // Knopf haette dann einen Teil erledigt und „alle" gesagt.
        const aufrufe: Array<unknown> = [];

        vi.stubGlobal(
            'fetch',
            vi.fn(async (_url: string, init: RequestInit) => {
                aufrufe.push(JSON.parse(String(init.body)));

                return { ok: true, status: 200, json: async () => ({ marked: 3953 }) };
            }),
        );

        render(<InboxPage {...daten({ totals, markable })} {...basis} />);

        fireEvent.click(screen.getByRole('button', { name: /Alle 3953 eigenen als gelesen/ }));

        await vi.waitFor(() => expect(aufrufe).toEqual([{ alle: true }]));

        // Danach ist der Knopf weg — und die eigene Zeile gelesen.
        //
        // Auch das in `waitFor`: Die Nutzlast liegt schon vor, wenn React
        // den Zustand noch nicht uebernommen hat. Die erste Fassung dieser
        // Pruefung war genau deshalb rot — nicht weil der Knopf blieb.
        await vi.waitFor(() => expect(screen.queryByText(/eigenen als gelesen/)).toBeNull());
    });
});

describe('Der Satz „N von M"', () => {
    it('erklaert, warum die Liste kuerzer ist als der Zaehler', () => {
        render(<InboxPage {...daten({ totals: { local: { aufgabe: 3953 }, sources: {} } })} {...basis} />);

        expect(screen.getByText('Hier stehen die 1 neuesten Zeilen von 3953 ungelesenen.')).toBeDefined();
    });

    it('bleibt weg, wenn die Liste vollstaendig ist', () => {
        render(<InboxPage {...daten({ totals: { local: { aufgabe: 1 }, sources: {} } })} {...basis} />);

        expect(screen.queryByText(/Hier stehen die/)).toBeNull();
    });
});

describe('Eine Zeile oeffnen heisst, sie gesehen zu haben (#1096)', () => {
    const markable = { local: ['aufgabe'], sources: {} };

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('hakt sie beim Klick auf den Titel mit ab', async () => {
        // Vorher navigierte der Klick nur, und die rote Zeile stand beim
        // Zurueckkommen wieder da — das war die Haelfte von Bastians Satz
        // „die roten bleiben immer da".
        const geschickt: Array<unknown> = [];
        vi.stubGlobal(
            'fetch',
            vi.fn(async (_url: string, init: RequestInit) => {
                geschickt.push(JSON.parse(String(init.body)));

                return { ok: true, status: 200, json: async () => ({ marked: 1 }) };
            }),
        );

        const oeffnen = vi.fn();

        render(<InboxPage {...daten({ markable })} {...basis} onOpen={oeffnen} />);

        fireEvent.click(screen.getByText('Angebot nachfassen'));

        // Das Oeffnen wartet NICHT auf die Marke: Der Seitenwechsel soll
        // nicht am Netz haengen.
        expect(oeffnen).toHaveBeenCalledTimes(1);

        await vi.waitFor(() => expect(geschickt).toEqual([{ source: null, kind: 'aufgabe', ids: ['1'] }]));
    });

    it('oeffnet eine nicht abhakbare Zeile ohne irgendetwas zu schicken', async () => {
        const fetchen = vi.fn();
        vi.stubGlobal('fetch', fetchen);

        const oeffnen = vi.fn();

        render(<InboxPage {...daten({ markable: { local: [], sources: {} } })} {...basis} onOpen={oeffnen} />);

        fireEvent.click(screen.getByText('Angebot nachfassen'));

        expect(oeffnen).toHaveBeenCalledTimes(1);
        expect(fetchen).not.toHaveBeenCalled();
    });
});

describe('Zeile ohne Ziel (AI Brain #1151)', () => {
    it('verspricht keinen Klick, wo kein Verweis ist', () => {
        /*
         * Bastians Satz dazu war „kann irgendwas nicht druecken". In
         * Produktion betraf das alle 59 imkr-Zeilen: Sie gehoeren zu einem
         * Pane auf einem Rechner und zu keiner Seite, sahen aber aus wie
         * ein Link und taten beim Klick scheinbar nichts.
         *
         * Geprueft wird die GESTE, nicht die Farbe: Zeigefinger und
         * unterstrichener Titel sind das Versprechen, das hier nicht
         * eingehalten werden kann.
         */
        render(<InboxPage {...daten({ items: [zeile({ title: 'claude is done', link: null })] })} {...basis} />);

        const titel = screen.getByText('claude is done');

        expect(titel.className).not.toContain('hover:underline');
        expect(titel.parentElement?.parentElement?.className).toContain('cursor-default');
    });

    it('verspricht den Klick, wo ein Verweis ist', () => {
        render(
            <InboxPage
                {...daten({
                    items: [zeile({ link: 'https://pm.beispiel.test/tasks/42' })],
                })}
                {...basis}
            />,
        );

        const titel = screen.getByText('Angebot nachfassen');

        expect(titel.className).toContain('hover:underline');
        expect(titel.parentElement?.parentElement?.className).toContain('cursor-pointer');
    });

    it('hakt eine Zeile ohne Ziel trotzdem ab', () => {
        /*
         * Der Klick bleibt — nur das Versprechen eines Ziels faellt weg.
         * „Eine Zeile oeffnen heisst, sie gesehen zu haben" (#1096) gilt
         * auch dort, wo es nichts zu oeffnen gibt: Sonst waere die einzige
         * Art, eine imkr-Zeile loszuwerden, der Haken daneben.
         */
        const holen = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ marked: 1 }) });
        vi.stubGlobal('fetch', holen);

        render(
            <InboxPage
                {...daten({
                    items: [zeile({ kind: 'meldung', title: 'claude is done', link: null })],
                    counts: { meldung: 1 },
                    markable: { local: ['meldung'], sources: {} },
                })}
                {...basis}
            />,
        );

        fireEvent.click(screen.getByText('claude is done'));

        expect(holen).toHaveBeenCalled();

        vi.unstubAllGlobals();
    });
});

describe('Löschen im gemeinsamen Posteingang (AI Brain #9069)', () => {
    const loeschbar = { local: ['notification'] };

    function mitFetch() {
        const holen = vi.fn().mockResolvedValue({
            ok: true,
            status: 200,
            json: async () => ({ deleted: 1 }),
        });
        vi.stubGlobal('fetch', holen);

        return holen;
    }

    afterEach(() => vi.unstubAllGlobals());

    it('zeichnet den Löschknopf an einer eigenen Meldung', () => {
        render(
            <InboxPage
                {...daten({
                    items: [zeile({ kind: 'meldung', title: 'Bug behoben' })],
                    counts: { meldung: 1 },
                    deletable: { local: ['meldung'] },
                })}
                {...basis}
            />,
        );

        expect(screen.getByLabelText('Löschen')).toBeDefined();
    });

    it('zeichnet KEINEN Löschknopf an einer fremden Zeile', () => {
        // Gelöscht wird dort, wo die Sache liegt (Entscheidung B1).
        render(
            <InboxPage
                {...daten({
                    items: [zeile({ kind: 'meldung', source: 'peppermint-crm' })],
                    counts: { meldung: 1 },
                    deletable: { local: ['meldung'] },
                })}
                {...basis}
            />,
        );

        expect(screen.queryByLabelText('Löschen')).toBeNull();
    });

    it('zeichnet KEINEN Löschknopf an einer Aufgabe', () => {
        // Bastians Vorgabe: „Aufgaben und Belege nie."
        render(<InboxPage {...daten({ deletable: { local: ['meldung'] } })} {...basis} />);

        expect(screen.queryByLabelText('Löschen')).toBeNull();
    });

    it('löscht eine Zeile ohne Rückfrage', () => {
        const holen = mitFetch();
        const fragen = vi.fn(() => true);

        render(
            <InboxPage
                {...daten({
                    items: [zeile({ kind: 'meldung' })],
                    counts: { meldung: 1 },
                    deletable: { local: ['meldung'] },
                })}
                {...basis}
                confirm={fragen}
            />,
        );

        fireEvent.click(screen.getByLabelText('Löschen'));

        expect(fragen).not.toHaveBeenCalled();
        expect(holen).toHaveBeenCalledWith('/posteingang/zeilen', expect.objectContaining({ method: 'DELETE' }));
    });

    it('fragt vor „alle löschen" nach und nennt die Zahl', async () => {
        const holen = mitFetch();
        const fragen = vi.fn(() => true);

        render(
            <InboxPage
                {...daten({
                    items: [zeile({ kind: 'meldung' })],
                    counts: { meldung: 1 },
                    totals: { local: { meldung: 4113 }, sources: {} },
                    deletable: { local: ['meldung'] },
                })}
                {...basis}
                confirm={fragen}
            />,
        );

        fireEvent.click(screen.getByText(/Alle meine .* löschen/));

        expect(fragen).toHaveBeenCalledWith(expect.stringContaining('4113'));
        // `waitFor`, weil die Rueckfrage ein Promise liefern DARF (ein
        // gestalteter Dialog ist asynchron) und das Loeschen deshalb erst
        // danach laeuft.
        await waitFor(() => expect(holen).toHaveBeenCalled());
    });

    it('wartet auf eine Rückfrage, die ein Promise liefert', async () => {
        /*
         * Der Grund, warum `confirm` ein Promise liefern darf: Ein
         * gestalteter Dialog (AlertDialog) ist asynchron, `window.confirm`
         * blockiert. Ohne `await` wäre `[object Promise]` wahr und JEDE
         * Rückfrage still übergangen — bei über 4000 unwiderruflich
         * gelöschten Zeilen genau der Schutz, der hier zählt.
         */
        const holen = mitFetch();

        render(
            <InboxPage
                {...daten({
                    items: [zeile({ kind: 'meldung' })],
                    counts: { meldung: 1 },
                    deletable: { local: ['meldung'] },
                })}
                {...basis}
                confirm={() => Promise.resolve(true)}
            />,
        );

        fireEvent.click(screen.getByText(/Alle meine .* löschen/));

        await waitFor(() => expect(holen).toHaveBeenCalled());
    });

    it('löscht NICHT, wenn eine Promise-Rückfrage verneint wird', async () => {
        // Die wichtigere Richtung: Ein „Abbrechen" im Dialog muss halten.
        const holen = mitFetch();

        render(
            <InboxPage
                {...daten({
                    items: [zeile({ kind: 'meldung' })],
                    counts: { meldung: 1 },
                    deletable: { local: ['meldung'] },
                })}
                {...basis}
                confirm={() => Promise.resolve(false)}
            />,
        );

        fireEvent.click(screen.getByText(/Alle meine .* löschen/));

        // Kurz warten, damit ein faelschlich gestartetes Loeschen Zeit
        // haette anzukommen — sonst beweist „nicht aufgerufen" nur, dass
        // die Pruefung schneller war.
        await new Promise((fertig) => setTimeout(fertig, 20));

        expect(holen).not.toHaveBeenCalled();
    });

    it('löscht NICHT, wenn die Rückfrage verneint wird', () => {
        // Die Rückfrage ist der Schutz, nicht die Höflichkeit: Hier
        // verschwinden auch die gelesenen Zeilen.
        const holen = mitFetch();

        render(
            <InboxPage
                {...daten({
                    items: [zeile({ kind: 'meldung' })],
                    counts: { meldung: 1 },
                    deletable: { local: ['meldung'] },
                })}
                {...basis}
                confirm={() => false}
            />,
        );

        fireEvent.click(screen.getByText(/Alle meine .* löschen/));

        expect(holen).not.toHaveBeenCalled();
    });

    it('zeigt „alle löschen" nicht im Reiter einer nicht löschbaren Art', () => {
        render(<InboxPage {...daten({ deletable: { local: ['meldung'] } })} {...basis} />);

        // Offen ist „aufgabe" — dort gibt es den Knopf nicht.
        expect(screen.queryByText(/Alle meine .* löschen/)).toBeNull();
    });

    it('zeichnet gar nichts, wenn deleteHref null ist', () => {
        render(
            <InboxPage
                {...daten({
                    items: [zeile({ kind: 'meldung' })],
                    counts: { meldung: 1 },
                    deletable: { local: ['meldung'] },
                })}
                {...basis}
                deleteHref={null}
            />,
        );

        expect(screen.queryByLabelText('Löschen')).toBeNull();
    });
});

describe('Filter und Gruppierung (AI Brain #9069)', () => {
    it('filtert auf „nur ungelesene", ohne die Zahl am Reiter zu ändern', () => {
        render(
            <InboxPage
                {...daten({
                    items: [zeile({ id: '1', title: 'Offen' }), zeile({ id: '2', title: 'Gelesen', is_unread: false })],
                    counts: { aufgabe: 1 },
                })}
                {...basis}
            />,
        );

        expect(screen.getByText('Gelesen')).toBeDefined();

        fireEvent.click(screen.getByText('Nur ungelesene'));

        expect(screen.queryByText('Gelesen')).toBeNull();
        expect(screen.getByText('Offen')).toBeDefined();
        // Der Filter ist Darstellung, kein Abruf — die Zahl bleibt stehen.
        expect(screen.getByRole('tab', { name: /Neue Aufgaben/ }).textContent).toContain('1');
    });

    it('filtert nach HERKUNFT — die andere Achse als die Reiter', () => {
        render(
            <InboxPage
                {...daten({
                    items: [
                        zeile({ id: '1', title: 'Von hier' }),
                        zeile({ id: '2', title: 'Aus dem CRM', source: 'peppermint-crm' }),
                    ],
                    counts: { aufgabe: 2 },
                })}
                {...basis}
            />,
        );

        // Ueber die ROLLE, nicht ueber den Text: Die Marke an der Zeile
        // tragt dasselbe Wort, und `getByText` findet dann zwei Treffer.
        fireEvent.click(screen.getByRole('button', { name: 'Peppermint CRM' }));

        expect(screen.getByText('Aus dem CRM')).toBeDefined();
        expect(screen.queryByText('Von hier')).toBeNull();
    });

    it('zeigt die Herkunftsknöpfe nicht, wenn es nur EIN System gibt', () => {
        // Zwischen „alles" und „alles" zu filtern ist kein Filter.
        render(<InboxPage {...daten()} {...basis} />);

        expect(screen.queryByText('Alle Systeme')).toBeNull();
    });

    it('sagt es, wenn ein Filter nichts übrig lässt', () => {
        // Sonst sieht es aus wie ein leerer Posteingang, und man sucht den
        // Fehler bei sich statt beim Filter.
        render(
            <InboxPage
                {...daten({
                    items: [zeile({ title: 'Gelesen', is_unread: false })],
                    counts: {},
                })}
                {...basis}
            />,
        );

        fireEvent.click(screen.getByText('Nur ungelesene'));

        expect(screen.getByText(/zu diesem Filter passt/)).toBeDefined();
    });

    it('gruppiert in „Neu" und „Früher", wenn es beides gibt', () => {
        render(
            <InboxPage
                {...daten({
                    items: [zeile({ id: '1', title: 'Offen' }), zeile({ id: '2', title: 'Gelesen', is_unread: false })],
                    counts: { aufgabe: 1 },
                })}
                {...basis}
            />,
        );

        expect(screen.getByText('Neu (1)')).toBeDefined();
        expect(screen.getByText('Früher (1)')).toBeDefined();
    });

    it('gruppiert NICHT, wenn alles in eine Gruppe fällt', () => {
        // Zwei Überschriften über einer einzigen Gruppe sind Lärm.
        render(<InboxPage {...daten()} {...basis} />);

        expect(screen.queryByText(/^Neu \(/)).toBeNull();
        expect(screen.queryByText(/^Früher \(/)).toBeNull();
    });

    it('zeigt den Neu-laden-Knopf nur, wenn das Produkt nachladen kann', () => {
        // Ein Knopf, der nichts tut, ist schlechter als keiner.
        const { unmount } = render(<InboxPage {...daten()} {...basis} />);
        expect(screen.queryByLabelText('Neu laden')).toBeNull();
        unmount();

        const laden = vi.fn();
        render(<InboxPage {...daten()} {...basis} onRefresh={laden} />);

        fireEvent.click(screen.getByLabelText('Neu laden'));

        expect(laden).toHaveBeenCalled();
    });
});
