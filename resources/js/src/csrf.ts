/**
 * Die CSRF-Marke aus dem Dokument — wenn es eine gibt.
 *
 * ## Warum das eine eigene Datei ist
 *
 * Weil es zwei Schreibwege gibt (Abhaken und Löschen) und die Marke für
 * beide dieselbe ist. Als zweite Kopie in `useLoeschen` wäre sie genau die
 * Dopplung, die still veraltet: Wer die Cookie-Variante irgendwann ergänzt,
 * ergänzt sie in einer der beiden Dateien.
 *
 * ## Zwei Quellen, und beide sind nötig
 *
 * Inertia-Anwendungen haben oft **kein** `<meta name="csrf-token">`; ihr
 * Client schickt den `XSRF-TOKEN`-Cookie als Kopfzeile. Wer blind
 * `querySelector` liest, bekommt `null` und schickt einen leeren Kopf.
 *
 * Deshalb wird beides versucht — und der Kopf **weggelassen**, wenn es
 * nichts zu schicken gibt. Ein leerer Kopf täuscht einen Mechanismus vor,
 * den es nicht gibt, und verschiebt den Fehler von „fehlt" auf „ist falsch".
 */
export function csrfHeaders(): Record<string, string> {
    if (typeof document === 'undefined') {
        return {};
    }

    const meta = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    if (meta) {
        return { 'X-CSRF-TOKEN': meta };
    }

    const cookie = document.cookie
        .split('; ')
        .find((teil) => teil.startsWith('XSRF-TOKEN='))
        ?.slice('XSRF-TOKEN='.length);

    return cookie ? { 'X-XSRF-TOKEN': decodeURIComponent(cookie) } : {};
}
