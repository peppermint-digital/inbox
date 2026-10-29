import { describe, expect, it } from 'vitest';
import { artLoeschbar, darfLoeschen, eigenerBestandDerArt, loeschFrage } from './loeschen';
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

    it('verweigert es an einer FREMDEN Zeile', () => {
        // Geloescht wird dort, wo die Sache liegt. Das ist die Entscheidung
        // B1 vom 29.10.2026 — kein Schreibweg ueber die Systemgrenze.
        expect(darfLoeschen(zeile({ source: 'peppermint-manager' }), loeschbar)).toBe(false);
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
        expect(loeschFrage('Benachrichtigungen', 4113, 50)).toContain('4113 Benachrichtigungen');
        expect(loeschFrage('Benachrichtigungen', 4113, 50)).toContain('nicht zurücknehmen');
    });

    it('nimmt die gezeigte Menge, wenn der Bestand 0 ist', () => {
        // Der Bestand zaehlt nur Ungelesenes, geloescht wird auch
        // Gelesenes. „0 loeschen?" waere an einer Liste mit sichtbaren
        // Zeilen offensichtlich falsch.
        expect(loeschFrage('Benachrichtigungen', 0, 12)).toContain('12 Benachrichtigungen');
    });

    it('bleibt verstaendlich, wenn gar keine Zahl bekannt ist', () => {
        expect(loeschFrage('Benachrichtigungen', 0, 0)).toContain('Alle Benachrichtigungen');
    });
});
