/**
 * Das Ereignis „im Posteingang hat sich etwas geändert" (pm #10227).
 *
 * Die Seite löst es nach jedem erfolgreichen Abhaken und Löschen aus. Der
 * Posteingangs-Knopf in der Kopfzeile (`InboxButton` aus
 * `@peppermint-digital/notifications`) hört darauf und zählt sofort neu,
 * statt bis zum nächsten Takt — bis zu einer Minute — die alte Zahl zu
 * zeigen. Bastian, 25.11.2026: „Die Zahlen verändern sich nicht."
 *
 * Ein Fensterereignis und kein gemeinsamer Zustand: Die beiden Pakete
 * kennen sich nicht, und der Knopf hängt in jeder Seite, die Liste nur in
 * einer. Der Name steht deshalb in beiden Paketen wörtlich gleich.
 */
export const POSTEINGANG_GEAENDERT = 'peppermint:posteingang-geaendert';

export function meldePosteingangGeaendert(): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.dispatchEvent(new CustomEvent(POSTEINGANG_GEAENDERT));
}
