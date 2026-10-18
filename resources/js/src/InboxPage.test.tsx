/** @vitest-environment jsdom */

import { cleanup, render, screen, fireEvent } from '@testing-library/react';
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
            { key: 'aufgabe', label: 'Neue Aufgaben', icon: 'inbox', color: 'blue', order: 10 },
            { key: 'meldung', label: 'Benachrichtigungen', icon: 'bell', color: 'gray', order: 40 },
        ],
        counts: { aufgabe: 1 },
        unreachable: [],
        failed: [],
        labels: { local: 'Peppermint Manager', sources: { 'peppermint-crm': 'Peppermint CRM' } },
        ...u,
    };
}

const basis = { formatTime: (iso: string) => iso.slice(0, 10), onOpen: vi.fn() };

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
                {...daten({ items: [zeile({ source: 'peppermint-crm', source_label: 'Peppermint CRM' })] })}
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
