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

## Abhaken — spiegelbildlich zum Holen

Eine Zeile wird da erledigt, wo sie liegt. Dass der Klick dafür **in dieser
Liste** steht, ändert daran nichts: Das Paket schickt ihn dorthin.

| Vorgang | Fähigkeit | Werkzeug |
|---|---|---|
| holen | `inbox.entries` | `list-inbox-entries-tool` |
| abhaken | `inbox.read` | `mark-inbox-entries-read-tool` |

Beide Seiten liegen im Paket. Ein Produkt registriert nur die Werkzeuge in
seinem MCP-Server und meldet seine Quellen an — es schreibt keinen
Verdrahtungscode.

### Zwei freiwillige Verträge

```php
// Kann abgehakt werden.
class MeldungsQuelle implements AbhakbareQuelle
{
    public function alsGelesen(Model $person, array $ids): int { … }
    public function allesAlsGelesen(Model $person): int { … }
}

// Weiss, wie viele es INSGESAMT sind.
class MeldungsQuelle implements ZaehlbareQuelle
{
    public function ungelesenGesamt(Model $person): int { … }
}
```

Beide erben von `InboxQuelle` und sind **zusätzlich**, nicht ersetzend. Eine
Pflichtmethode im bestehenden Vertrag hätte jede vorhandene Quelle erschlagen
— gemessen am 27.10.2026: fünf Umsetzungen in den Produkten, die beim Ziehen
der neuen Fassung mit „must implement" gestorben wären, und zwar beim Starten,
nicht beim Abhaken.

Wer sie nicht umsetzt, hat an seinen Zeilen keinen Haken und zeigt das
Gezeigte als Bestand. Beides ist die vorsichtige Richtung.

### Die Ansicht rät nicht, sie fragt

`markable` sagt je Herkunft und Art, wo ein Haken ein Ziel findet — `local`
für die eigenen, `sources` für die anderen Systeme. Fehlt die Angabe (System
auf älterem Stand), wird kein Knopf gezeichnet: Einer, der ins Leere zeigt,
ist schlechter als keiner.

Vorher stand diese Regel im Produkt und lautete „von hier, ungelesen, Art
`notification`". Drei Produkte hätten sie abschreiben müssen, und bei einer
neuen abhakbaren Quelle wäre sie still falsch geworden.

### `counts` ist nicht `totals`

Zwei Fragen, und sie hießen eine Zeit lang gleich:

| Feld | Bedeutet |
|---|---|
| `counts` | Ungelesene **in dieser Liste** — daran hängen die Zahlen an den Reitern |
| `totals` | Der **Bestand**, je Art (`local`) und je fremdem System (`sources`) |

Am 26.10.2026 stand in der Kopfzeile 3953 gegen 50 Zeilen in der Liste, und
nichts erklärte die Lücke — wer einzeln abarbeitet, sieht die Zahl kaum sinken
und hält die Seite für kaputt. Aus beiden Feldern sagt die Ansicht jetzt „N
von M", und die Kachel zieht aus derselben Quelle statt aus einem zweiten
Endpunkt.

### Der Fehlschlag ist der wichtige Fall

Beim Holen kostet ein stummes System seine Zeilen, nicht die Liste. Beim
Abhaken gibt es diese Milde nicht: Der Endpunkt antwortet mit **502 und einem
Satz**, nie mit `marked: 0`, und die Ansicht schreibt ihn hin. Wer abhakt und
es kommt nicht an, muss das sehen — sonst steht die Zeile beim Neuladen wieder
da, und der Fehler sieht aus wie der Fix.

### Die Route

Das Paket bringt sie mit: `POST /posteingang/gelesen`. Bewusst **nicht**
`/inbox` — das ist in der Verwaltung seit längerem der Belege-Posteingang,
und `api/inbox` ist in allen drei Produkten vergeben. Dieselbe Verwechslung
hat schon die Fähigkeit `inbox.list` gekostet.

Pfad, Name und Middleware stehen in `config/inbox.php`. Die Middleware ist
die Stelle, an der die Autorisierung im Produkt bleibt.

## Freigabe im Komm-Layer nicht vergessen

Eine Fähigkeit ohne Eintrag in `comm_layer.gateway_grants.<slug>` ist nicht
freigeschaltet, und „nicht erlaubt" sieht in der Ansicht aus wie „nichts da".
Für den Verbund braucht jedes Produkt neben `inbox.entries` jetzt auch
`inbox.read`.
