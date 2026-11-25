/// <reference types="vite/client" />
import { describe, expect, it } from 'vitest';

/**
 * Das Paket nimmt seine Farben aus dem Theme des Produkts (pm #10236).
 *
 * Es laeuft in vier Produkten (Manager, CRM, Verwaltung, AI Brain), und jedes
 * kann ein eigenes Theme haben. Eine feste Tailwind-Farbe wie `bg-amber-50`
 * folgt keinem davon — im dunklen oder farbigen Theme steht dann ein
 * hellgelber Kasten. Deshalb: Statusfarben nur ueber die Tokens, die ALLE
 * vier Produkte fuehren.
 *
 * Gemeinsam haben sie (25.11.2026 gemessen) neben den shadcn-Grundfarben nur
 * success, warning, info und destructive, je mit -foreground, -subtle,
 * -subtle-foreground und -border. `ai` und `grade-*` fehlen in der
 * Verwaltung — die darf das Paket also nicht benutzen.
 */
/**
 * Ueber Vite statt node:fs: Dieses Paket hat keine Node-Typen, und die Tests
 * in src laufen durch tsc.
 */
const QUELLEN = import.meta.glob<string>(['./**/*.{ts,tsx,css}', '!./**/*.test.{ts,tsx}'], { query: '?raw', import: 'default', eager: true });

const ROHE_FARBE =
    /\b(?:bg|text|border|ring|fill|stroke|from|to|via|divide|outline)-(?:slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-\d{2,3}\b/g;

const NUR_EINIGE_PRODUKTE = /\b(?:bg|text|border|ring)-(?:ai|grade-\d)(?:-[a-z-]+)?\b/g;

/**
 * Keine Ausnahme mehr: Auch die Symbolfarben der Arten (icons.ts) lesen seit
 * F18 ihre Kategorie-Tokens. Fest steht dort nur der Rueckfall innerhalb von
 * var(...), und den erkennt dieser Ausdruck nicht als Klasse.
 */
const AUSNAHMEN: string[] = [];

function dateien(): [string, string][] {
    return Object.entries(QUELLEN)
        .map(([pfad, inhalt]): [string, string] => [pfad.replace(/^\.\//, ''), inhalt])
        .filter(([pfad]) => !AUSNAHMEN.includes(pfad));
}

describe('Farben folgen dem Theme des Produkts', () => {
    it('nutzt keine feste Tailwind-Palette fuer Statusfarben', () => {
        const funde = dateien().flatMap(([pfad, inhalt]) =>
            [...inhalt.matchAll(ROHE_FARBE)].map((treffer) => `${pfad}: ${treffer[0]}`),
        );

        expect(funde).toEqual([]);
    });

    it('nutzt nur Tokens, die alle Produkte fuehren', () => {
        const funde = dateien().flatMap(([pfad, inhalt]) =>
            [...inhalt.matchAll(NUR_EINIGE_PRODUKTE)].map((treffer) => `${pfad}: ${treffer[0]}`),
        );

        expect(funde).toEqual([]);
    });
});
