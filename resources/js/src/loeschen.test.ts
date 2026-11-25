import { describe, expect, it } from 'vitest';
import { artLoeschbar, darfLoeschen, eigenerBestandDerArt, loeschbarerBestandDerArt, loeschFrage } from './loeschen';
import type { InboxDeletable, InboxItem } from './types';

/**
 * Die Entscheidungen hinter dem Loeschknopf (AI Brain #9069).
 *
 * Bastians Vorgabe dazu: „Aufgaben und Belege nie."
 */
function zeile(u: Partial<InboxItem> = {}): InboxItem {
    return {
        id: '1',
        kind: 'notification',
        title: 'Bug behoben',
        icon: 'bell',
        color: 'blue',
        is_unread: true,
        ...u,
    };
}

const loeschbar: InboxDeletable = { local: ['notification'] };

describe('darfLoeschen', () => {
    it('erlaubt es an einer eigenen Zeile einer loeschbaren Art', () => {
        expect(darfLoeschen(zeile(), loeschbar)).toBe(true);
    });

    it('erlaubt es auch an einer GELESENEN Zeile', () => {
        // Anders als beim Haken: Die gelesene Meldung ist genau die, die
        // man loswerden will.
        expect(darfLoeschen(zeile({ is_unread: false }), loeschbar)).toBe(true);
    });

    it('verweigert es an einer FREMDEN Zeile, deren System nichts loeschbar meldet', () => {
        // Bis v0.8.0 galt das fuer jede fremde Zeile (Entscheidung B1 vom
        // 29.10.2026). Seit pm #10227 entscheidet das System der Zeile —
        // meldet es nichts, bleibt es beim alten Verhalten.
        expect(darfLoeschen(zeile({ source: 'peppermint-manager' }), loeschbar)).toBe(false);
    });

    it('erlaubt es an einer fremden Zeile, wenn ihr System die Art loeschbar meldet (pm #10227)', () => {
        // Bastian: „Und ich habe auch keine Buttons, um praktisch die
        // wegzumachen." 1320 seiner 1461 Zeilen kamen aus AI Brain.
        const mitHub: InboxDeletable = { local: ['notification'], sources: { 'ai-brain': ['notification'] } };

        expect(darfLoeschen(zeile({ source: 'ai-brain' }), mitHub)).toBe(true);
        expect(darfLoeschen(zeile({ source: 'ai-brain', kind: 'imkr' }), mitHub)).toBe(false);
        expect(darfLoeschen(zeile({ source: 'peppermint-crm' }), mitHub)).toBe(false);
    });

    it('verweigert es an einer Aufgabe und an einem Beleg', () => {
        // „Aus dem Posteingang loeschen" waere dort wirkungslos oder ein
        // Datenverlust im Fremdsystem.
        expect(darfLoeschen(zeile({ kind: 'task' }), loeschbar)).toBe(false);
        expect(darfLoeschen(zeile({ kind: 'document' }), loeschbar)).toBe(false);
    });

    it('zeichnet nichts, wenn der Server keine Angabe macht', () => {
        // Ein System auf aelterem Paketstand schickt `deletable` nicht —
        // und ein Knopf ins Leere ist schlechter als keiner.
        expect(darfLoeschen(zeile(), undefined)).toBe(false);
    });
});

describe('artLoeschbar', () => {
    it('unterscheidet die Arten', () => {
        expect(artLoeschbar('notification', loeschbar)).toBe(true);
        expect(artLoeschbar('task', loeschbar)).toBe(false);
        expect(artLoeschbar('notification', undefined)).toBe(false);
    });
});

describe('eigenerBestandDerArt', () => {
    it('nimmt nur die EIGENEN, weil nur die loeschbar sind', () => {
        const totals = {
            local: { notification: 4113 },
            sources: { 'peppermint-manager': { notification: 42 } },
        };

        expect(eigenerBestandDerArt('notification', totals)).toBe(4113);
    });

    it('ist 0 ohne Angabe', () => {
        expect(eigenerBestandDerArt('notification', undefined)).toBe(0);
    });
});

describe('loeschFrage', () => {
    it('nennt die Zahl und das Wort der Art', () => {
        // Der Knopf steht neben Reitern fuer Aufgaben und Belege. „Alles
        // loeschen" laese sich dort wie „meinen ganzen Posteingang".
        expect(loeschFrage('Benachrichtigungen', 4113, 50)).toContain('Alle Benachrichtigungen');
        expect(loeschFrage('Benachrichtigungen', 4113, 50)).toContain('mindestens 4113');
        expect(loeschFrage('Benachrichtigungen', 4113, 50)).toContain('nicht zurücknehmen');
    });

    it('nimmt die gezeigte Menge, wenn der Bestand 0 ist', () => {
        // Der Bestand zaehlt nur Ungelesenes, geloescht wird auch
        // Gelesenes. „0 loeschen?" waere an einer Liste mit sichtbaren
        // Zeilen offensichtlich falsch.
        expect(loeschFrage('Benachrichtigungen', 0, 12)).toContain('mindestens 12');
    });

    it('sagt „mindestens" und nennt die gelesenen (pm #10227)', () => {
        // E2E 25.11.2026: Rueckfrage 53, geloescht 61 — gelesene und
        // aeltere Zeilen standen nicht in der Zahl. Die ganze Zahl kennt die
        // Seite nicht, also behauptet sie keine.
        expect(loeschFrage('Benachrichtigungen', 3, 53)).toContain('mindestens 53');
        expect(loeschFrage('Benachrichtigungen', 3, 53)).toContain('auch die gelesenen');
    });

    it('bleibt verstaendlich, wenn gar keine Zahl bekannt ist', () => {
        expect(loeschFrage('Benachrichtigungen', 0, 0)).toContain('Alle Benachrichtigungen');
    });
});

describe('loeschbarerBestandDerArt (pm #10227)', () => {
    it('zaehlt hier und jedes System, das die Art loeschbar meldet', () => {
        const totals = { local: { notification: 1 }, sources: { 'ai-brain': { notification: 855, imkr: 465 }, crm: { notification: 9 } } };
        const deletable: InboxDeletable = { local: ['notification'], sources: { 'ai-brain': ['notification'] } };

        // 1 + 855 — die 9 im CRM nicht (nicht loeschbar gemeldet), imkr nicht (andere Art).
        expect(loeschbarerBestandDerArt('notification', totals, deletable)).toBe(856);
        expect(loeschbarerBestandDerArt('imkr', totals, deletable)).toBe(0);
    });

    it('sagt bei einer Art, die nur drueben loeschbar ist, auch nur die fremde Zahl', () => {
        expect(
            loeschbarerBestandDerArt('imkr', { local: {}, sources: { 'ai-brain': { imkr: 465 } } }, { local: [], sources: { 'ai-brain': ['imkr'] } }),
        ).toBe(465);
        expect(artLoeschbar('imkr', { local: [], sources: { 'ai-brain': ['imkr'] } })).toBe(true);
    });
});
