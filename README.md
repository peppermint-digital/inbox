# peppermint/inbox

Eine gemeinsame Inbox für die Peppermint-Systeme: **eine Liste, viele Arten,
alle Systeme.**

Das Paket weiß nicht, was eine Aufgabe ist und was eine Benachrichtigung. Es
kennt nur Einträge mit einer **Art** — und wer eine neue Kategorie einführt,
meldet eine Art und eine Quelle an. Mehr braucht es nicht; kein neues Rohr
durch den Verbund, keine Änderung in den anderen Systemen.

## Die drei Teile

| Teil | Wofür |
|---|---|
| `InboxArten` | Welche Reiter es gibt — Schlüssel, Wort, Symbol, Reihenfolge |
| `InboxQuelle` | Was **dieses** System zu einer Art beiträgt |
| `GesamtInbox` | Eigene Quellen plus die der anderen Systeme, zusammengelegt |

## Anmelden

```php
// Im Dienstanbieter des Produkts — Art und Quelle gehören nebeneinander.
app(InboxArten::class)->registrieren(
    new InboxArt('aufgabe', 'Neue Aufgaben', symbol: 'inbox', reihenfolge: 10)
);

app(Quellenverzeichnis::class)->anmelden(OffeneAufgaben::class);
```

Ein Produkt, das das Paket installiert und nichts anmeldet, hat eine leere
Inbox. Das ist der richtige Ausgangszustand.

## Über die Systemgrenze

Jedes System gibt seine **eigenen** Einträge über das MCP-Werkzeug
`list-inbox-entries-tool` heraus (Fähigkeit `inbox.entries`). Das fragende
System legt sie mit seinen zusammen.

Nicht `inbox.list` — die ist seit längerem für den Aufgaben-Posteingang des
Managers und der Verwaltung vergeben, mit einer ganz anderen Antwortform
(`inbox`/`unread` statt `items`/`kinds`). Beides „Inbox" zu nennen wäre
richtig und trotzdem falsch: Wer die eine erwartet und die andere bekommt,
sieht eine leere Liste und keinen Fehler.

Bewusst nur die eigenen: Lieferte ein System die geholten mit, fragte der
Verbund sich im Kreis und jede Zeile käme mehrfach an.

Die Arten reisen mit. Führt ein System eine neue ein, erscheint sie in den
anderen, ohne dass dort etwas zu tun wäre.

## Zwei Fallen, in die diese Ansicht schon gelaufen ist

**Global kappen.** Ein Deckel über die zusammengefügte Liste lässt das
lauteste System die Obergrenze allein füllen; die anderen fallen unsichtbar
hinten herunter, und die Liste sieht vollständig aus. Deshalb wird **je Art
und System** gekappt.

**Ein Feld, zwei Bedeutungen.** `source` ist das Herkunftssystem — nichts
anderes. Wer dort die Zeilenart unterbringt, kostet jeder fremden Zeile ihre
Herkunft, und zwar unbemerkt, solange nur eigene Zeilen in der Liste stehen.

## Ausfälle werden gesagt

Ein System, das nicht antwortet, kostet seine Zeilen — nicht die Liste
(`unreachable`). Eine eigene Quelle, die scheitert, nimmt die anderen nicht
mit (`failed`). Beides steht in der Ansicht: Eine still verkürzte Liste sieht
aus wie „da ist nichts", und dann sucht man im falschen System.
