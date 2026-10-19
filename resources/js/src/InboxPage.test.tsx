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

    const spans = Array.from(screen.getByRole("tab", { name: /Neue Aufgaben/ }).querySelectorAll("span"));
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
