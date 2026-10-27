import { describe, expect, it } from 'vitest';
import {
    bestandJeArt,
    darfAbhaken,
    eigenerBestandAbhakbar,
    hinweisZurListe,
    istEigenAbhakbar,
    nochUngelesen,
    zeilenSchluessel,
} from './abhaken';
import type { InboxItem, InboxMarkable, InboxTotals } from './types';

/**
 * Die Entscheidungen hinter dem Haken — ohne DOM.
 *
 * Bewusst ohne `jsdom`: Eine reine Regel braucht keines, und ein Test, der
 * eines braucht, prueft am Ende die Umgebung mit.
 */
function zeile(u: Partial<InboxItem> = {}): InboxItem {
    return {
        id: '1',
        kind: 'notification',
        title: 'Etwas gemeldet',
        icon: 'bell',
        color: 'gray',
        is_unread: true,
        ...u,
    };
}

const markable: InboxMarkable = {
    local: ['notification'],
    sources: { 'peppermint-crm': ['notification'] },
};

describe('darfAbhaken', () => {
    it('setzt einen Haken an eine eigene Zeile einer abhakbaren Art', () => {
        expect(darfAbhaken(zeile(), markable)).toBe(true);
    });

    it('setzt KEINEN an eine Art, die hier niemand abhaken kann', () => {
        // Fuer eine Aufgabe oder einen Beleg zeigte der Endpunkt ins Leere.
        expect(darfAbhaken(zeile({ kind: 'aufgabe' }), markable)).toBe(false);
    });

    it('setzt KEINEN an eine gelesene Zeile', () => {
        // Sonst setzt ein zweiter Klick den Lesezeitpunkt neu, und der
        // Verlauf behauptet, die Meldung sei eben erst gelesen worden.
        expect(darfAbhaken(zeile({ is_unread: false }), markable)).toBe(false);
    });

    it('setzt einen an eine FREMDE Zeile, wenn ihr System es beantwortet', () => {
        // Das ist die Aenderung vom 27.10.2026: Vorher war „von hier" die
        // Bedingung, weil niemand wusste, ob ein Haken drueben ankommt.
        expect(darfAbhaken(zeile({ source: 'peppermint-crm' }), markable)).toBe(true);
    });

    it('setzt keinen an eine Zeile aus einem System, das es nicht kann', () => {
        expect(darfAbhaken(zeile({ source: 'peppermint-manager' }), markable)).toBe(false);
    });

    it('setzt keinen, wenn der Server gar nichts dazu sagt', () => {
        // Ein System auf aelterem Paketstand schickt `markable` nicht. Ein
        // Knopf, der ins Leere zeigt, ist schlechter als keiner.
        expect(darfAbhaken(zeile(), undefined)).toBe(false);
    });
});

describe('istEigenAbhakbar', () => {
    it('trennt die eigenen von den fremden', () => {
        // „Alle eigenen" nimmt nur, was hier liegt — was in einem anderen
        // System ungelesen ist, bleibt dort.
        expect(istEigenAbhakbar(zeile(), markable)).toBe(true);
        expect(istEigenAbhakbar(zeile({ source: 'peppermint-crm' }), markable)).toBe(false);
    });
});

describe('nochUngelesen', () => {
    it('nimmt eine abgehakte Zeile aus dem Ungelesenen', () => {
        // Ohne diesen Zwischenstand blieb die Zeile nach dem Klick rot
        // stehen, und der Fix sah genauso aus wie der Fehler.
        const eine = zeile();

        expect(nochUngelesen(eine, new Set([zeilenSchluessel(eine)]), false, markable)).toBe(false);
        expect(nochUngelesen(eine, new Set(), false, markable)).toBe(true);
    });

    it('nimmt nach „alle eigenen" die eigenen, aber nicht die fremden', () => {
        expect(nochUngelesen(zeile(), new Set(), true, markable)).toBe(false);
        expect(nochUngelesen(zeile({ source: 'peppermint-crm' }), new Set(), true, markable)).toBe(true);
    });

    it('unterscheidet Zeilen gleicher Kennung aus zwei Systemen', () => {
        // Der Schluessel traegt die Herkunft, sonst waere ein Haken am
        // falschen Ort zu sehen.
        const hier = zeile({ id: '42' });
        const drueben = zeile({ id: '42', source: 'peppermint-crm' });

        const abgehakt = new Set([zeilenSchluessel(hier)]);

        expect(nochUngelesen(hier, abgehakt, false, markable)).toBe(false);
        expect(nochUngelesen(drueben, abgehakt, false, markable)).toBe(true);
    });
});

describe('Der Bestand', () => {
    const totals: InboxTotals = {
        local: { notification: 3953, aufgabe: 2 },
        sources: { 'peppermint-crm': { notification: 40 }, 'peppermint-manager': { notification: 7 } },
    };

    it('legt je Art alle Systeme zusammen', () => {
        expect(bestandJeArt(totals)).toEqual({ notification: 4000, aufgabe: 2 });
    });

    it('zaehlt fuer „alle eigenen" nur die eigenen abhakbaren Arten', () => {
        // 3953, nicht 4000 und nicht 3955: Die 47 der anderen Systeme
        // bleiben dort liegen, und die 2 Aufgaben kann hier niemand
        // abhaken. Ein Knopf, der „alle" verspricht und einen Teil
        // erledigt, ist schlimmer als einer, der sagt, wie viele er nimmt.
        expect(eigenerBestandAbhakbar(totals, markable)).toBe(3953);
    });

    it('verspricht ohne Angaben vom Server nichts', () => {
        expect(bestandJeArt(undefined)).toEqual({});
        expect(eigenerBestandAbhakbar(undefined, markable)).toBe(0);
        expect(eigenerBestandAbhakbar(totals, undefined)).toBe(0);
    });
});

describe('hinweisZurListe', () => {
    it('erklaert die Luecke zwischen Liste und Bestand', () => {
        // Am 26.10.2026 standen dort 3953 gegen 50 Zeilen, und nichts
        // erklaerte den Unterschied — wer einzeln abarbeitet, sieht die
        // Zahl kaum sinken und haelt die Seite fuer kaputt.
        expect(hinweisZurListe(50, 3953)).toBe('Hier stehen die 50 neuesten von 3953.');
    });

    it('schweigt, wenn beides zusammenpasst', () => {
        // Ein Hinweis, der immer steht, wird nicht gelesen.
        expect(hinweisZurListe(50, 50)).toBeNull();
        expect(hinweisZurListe(50, 3)).toBeNull();
    });
});
