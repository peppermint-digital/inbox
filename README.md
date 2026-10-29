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

### Der Verweis wird dabei absolut

Was eine Quelle als Verweis schreibt, ist zu Hause richtig und nur dort.
`list-inbox-entries-tool` legt deshalb `config('app.url')` davor, bevor die
Zeile das System verlässt — idempotent, ein schon absoluter Verweis bleibt
unberührt (`Feed\Verweise`).

Bewusst **nicht** in `Quellenverzeichnis::sammeln()`: Dieselben Zeilen tragen
die eigene Seite, und dort gehört der relative Verweis hin — er bleibt im
Inertia-Fluss, und die Anwendung behält ihren Zustand.

Und bewusst beim **Anbieter**: Nur er kennt seine eigene Adresse.
`RemoteSource::anbieter()` nennt dem Empfänger Kürzel, Slug und Wort — keine
Grundadresse. Er könnte sie also nur raten.

Am 29.10.2026 kostete das Fehlen dieser Regel praktisch den ganzen
Posteingang in drei von vier Systemen (AI Brain #1151): AI Brain gab
`/bugs/1122`, `/avatar`, `/websites/21` heraus, und die antworteten auf
`vw`, `crm` und `pm` mit 404. Der schlimmere Fall war `/tasks/9057` — Manager
und CRM haben selbst eine Route `/tasks/{id}` und öffneten ihre **eigene**
Aufgabe mit derselben Nummer.

Eine Quelle darf ihren Verweis weiter absolut bauen; mehrere Produkte tun
das. Nötig ist es nicht mehr.

### Und beim Einsammeln noch einmal — für die Ausrollücke

Beim Ausrollen hat die Gegenseite diese Fassung immer irgendwo noch nicht.
`FremdeEintraege` macht deshalb einen **noch relativen** fremden Verweis mit
der Adresse des Anbieters absolut (`anbieter()['url']`, aus `web_url` im
Verzeichnis — dieselbe Angabe, die der Kalender für „öffne den Termin dort"
benutzt).

Das ist kein Gürtel-und-Hosenträger ohne Anlass. Am 29.10.2026, als nur AI
Brain die neue Fassung hatte, gab der Manager weiter `/emails?account=3`
heraus. Im Posteingang von AI Brain sah die Zeile dadurch aus wie eine
eigene: Der Inertia-Router forderte `brain…/emails?account=3` an, bekam keine
Inertia-Antwort — und tat **sichtbar nichts**. Zeigefinger, Klick ins Leere.
Vorher wäre eine falsche 404-Seite erschienen; das ist schlechter, aber
sichtbar.

Fehlt die Adresse des Anbieters, bleibt der Verweis relativ. Eine geratene
Adresse wäre schlechter als eine falsche Seite — sie zeigte mit Nachdruck
irgendwohin.

Dazu in der Oberfläche: Eine Zeile mit `source` gehört **nie** in den Router
der aufnehmenden Anwendung. `oeffneVerweis` aus `peppermint/notifications`
entscheidet am Ziel, und das ist richtig — aber nur, solange das Ziel stimmt.
Für eine fremde Zeile also kein Router, sondern ein voller Seitenaufruf.

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

### Eine Zeile öffnen heißt, sie gesehen zu haben

Der Klick auf den Titel hakt sie mit ab — wo `markable` das zulässt, und ohne
auf die Antwort zu warten: Der Seitenwechsel soll nicht am Netz hängen.
Vorher navigierte der Klick nur, und die rote Zeile stand beim Zurückkommen
wieder da.

### `counts` ist nicht `totals`

Zwei Fragen, und sie hießen eine Zeit lang gleich:

| Feld | Bedeutet |
|---|---|
| `counts` | Ungelesene **in dieser Liste** — daran hängen die Zahlen an den Reitern |
| `totals` | Der **Bestand**, je Art (`local`) und je fremdem System (`sources`) |

Am 26.10.2026 stand in der Kopfzeile 3953 gegen 50 Zeilen in der Liste, und
nichts erklärte die Lücke — wer einzeln abarbeitet, sieht die Zahl kaum sinken
und hält die Seite für kaputt. Jetzt steht unter der Liste ein Satz, der beide
Zahlen verbindet, und die Kachel zieht aus derselben Quelle statt aus einem
zweiten Endpunkt.

**`totals` zählt Einträge, die Liste zeigt Zeilen** — und das ist nicht
dasselbe, weil eine Zeile bündeln kann: Im Meldungs-Paket werden sieben
gleichartige Meldungen zu **einer** Zeile, und der Bestand sagt dort 7.
Einträge sind die richtige Einheit, weil „alle als gelesen" ebenfalls Einträge
abhakt; die Zahl am Knopf nennt damit, was er wirklich anfasst. Der Satz unter
der Liste benennt deshalb beide Einheiten, statt sie gleichzusetzen.

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

## Die Zahl am Symbol: `GET /posteingang/ungelesen`

Der Posteingang hängt in der Kopfzeile und in der Minibar je an einem
Symbol mit einer Zahl. Diese Route liefert sie, als
`{"unread_count": n}` — den **globalen** Bestand über alle Arten und alle
Systeme (`GesamtInbox::bestand()`).

Bis zum 29.10.2026 holten beide Symbole ihre Zahl aus
`/api/inbox/unread-count`, also aus `NotificationFeed::unreadCount()`: den
ungelesenen **Meldungen dieses einen Systems**. Geöffnet haben sie aber den
gemeinsamen Posteingang, in dem auch Aufgaben, Belege und imkr-Zeilen aus
vier Systemen stehen. Die Zahl sprach über etwas anderes als die Seite
dahinter (AI Brain #1152).

Drei Dinge, die dabei absichtlich so sind:

- **Der Bestand, nicht die Liste.** Die Liste ist je Art und System gekappt;
  eine Zahl, die der Kappung folgte, stünde bei 50 und bliebe dort.
- **Kein zweiter Rundruf.** Die eigenen Quellen nennen ihren Bestand ohne
  Zeilen aufzubauen (`Quellenverzeichnis::bestand()`), die fremden kommen aus
  **demselben** Zwischenspeicher wie die Seite. Die Zahl im Takt abzufragen
  fragt also nicht vier Systeme, sondern liest mit.
- **Ein Ausfall ist kein `0`.** Eine Null hieße „alles gelesen". Fällt die
  Abfrage, kommt ein 503 ohne Zahl, und das Symbol lässt seinen letzten
  bekannten Wert stehen.

`route.enabled => false` schaltet **beide** Routen ab, also auch diese.

## Löschen — und warum es nicht wie Abhaken funktioniert

Seit AI Brain #9069 kann der gemeinsame Posteingang auch löschen: einzelne
Zeilen und „alle meine … löschen". Damit wird die alte, produkteigene
Meldungsseite entbehrlich.

Drei Entscheidungen prägen das, und alle drei sind Vorgaben von Bastian vom
29.10.2026:

### 1. Nicht jede Art ist löschbar — „Aufgaben und Belege nie"

Abhaken ist eine Aussage über **mich** („gesehen"). Löschen ist eine Aussage
über die **Sache**, und bei den meisten Arten wäre sie falsch:

| Art | löschbar? | warum |
|---|---|---|
| Meldung | ja | Sie existiert nur, um gesehen zu werden. |
| Aufgabe | **nie** | Die Aufgabe ist der Gegenstand, nicht die Zeile. |
| Beleg | **nie** | Dasselbe, mit Buchhaltungsfolgen. |
| imkr | nein | Wird abgehakt und räumt sich nach 30 Tagen selbst auf. |

Deshalb ein eigener, **freiwilliger** Vertrag — genau wie `AbhakbareQuelle`:

```php
class MeldungsQuelle implements AbhakbareQuelle, ZaehlbareQuelle, LoeschbareQuelle
{
    public function loeschen(Model $person, array $ids): int { … }
    public function allesLoeschen(Model $person): int { … }
}
```

Welche Arten es können, sagt der Server als `deletable`. Die Ansicht rät
nicht: Wo der Vertrag fehlt, gibt es keinen Knopf.

### 2. Nur die EIGENEN Zeilen

Abhaken geht über die Systemgrenze (`inbox.read` über den Gateway). Löschen
**nicht**. Eine fremde Zeile hat keinen Löschknopf, und das ist kein Mangel,
sondern die Aussage: Was dort liegt, wird dort gelöscht.

Analog ginge es mit einer Fähigkeit `inbox.delete` samt Freigabe je Produkt.
Bewusst nicht gebaut: Der Hauptfall ist, seine eigenen Meldungen dort
loszuwerden, wo sie liegen — bei 4113 Meldungen von AI Brain ist das AI Brain.
Einen Schreibweg quer durch den Verbund für einen Knopf zu bauen, den noch
niemand vermisst hat, wäre die teurere Hälfte zuerst. Kommt er, ist er
additiv: `deletable` bekommt ein `sources`.

### 3. „Alle" fragt nach, einzeln nicht

Die alte Meldungsseite hatte „Alle löschen" **ohne Rückfrage** — ein Klick,
und alle Meldungen der Person waren weg, auch die gelesenen. Auf einer Seite,
die nur Meldungen zeigte, war das vertretbar. Hier steht derselbe Knopf neben
Reitern für Aufgaben und Belege und liest sich als „meinen ganzen Posteingang
löschen"; bei Bastian wären es über 4000 Zeilen gewesen.

Deshalb:

- **Einzeln:** kein Dialog. Es trifft genau die angeklickte Zeile.
- **Alle:** `window.confirm` mit der **Zahl** und dem **Wort der Art** —
  „4113 Benachrichtigungen endgültig löschen?". Der Knopf heißt „Alle meine
  Benachrichtigungen löschen" und erscheint nur im Reiter dieser Art.
- Die Rückfrage ist als `confirm`-Prop überschreibbar — für ein Produkt mit
  eigenem Dialog, und damit sie sich testen lässt.

### Die Route

`DELETE /posteingang/zeilen` (`inbox.loeschen`). `kind` ist **Pflicht**, auch
bei `alle: true`: „Alle meine Meldungen löschen" ist eine Aussage über
Meldungen, und ohne Art nähme derselbe Knopf beim nächsten löschbaren
Vokabular stillschweigend mehr mit, als draufsteht.

Ein Fehlschlag kommt als **502 mit dem Satz der Quelle**, nicht als
`deleted: 0` — wer löscht und es kommt nicht an, muss das sehen.

## Was von der alten Meldungsseite NICHT mitkam

Vollständig verglichen am 29.10.2026, Funktion für Funktion. Sechs Lücken
wurden gebaut (Einzellöschen, alle löschen, Filter „nur ungelesene", Filter
nach Herkunft, Neu-laden-Knopf, Gruppierung „Neu"/„Früher"). Vier Dinge
bewusst **nicht**, weil kein einziges Produkt sie verdrahtet hat:

| | warum nicht |
|---|---|
| `isDuplicate` / Doppelungen aus- und einblenden | Kein Produkt übergibt das Prädikat — die Funktion lief nirgends. |
| `renderDetails` | Ungenutzter Erweiterungspunkt. |
| `renderActions` am Meldungs-Bauteil | Gibt es hier schon; das alte blieb ungenutzt. |
| „Als ungelesen markieren" | Route und Hook existieren, aber es gab **gar keinen Knopf** dafür. Keine Lücke, sondern ein Endpunkt ohne Aufrufer. |

Das sind vier Dinge, die man sonst nachgebaut hätte, ohne dass sie je jemand
benutzt hat. Wer eine davon doch braucht, hat damit die Begründung zur Hand,
warum sie fehlt — und nicht den Eindruck, sie sei vergessen worden.

## Die Filter sind Darstellung, kein Abruf

„Nur ungelesene" und der Herkunftsfilter laden **nichts** nach. Die Zahlen an
den Reitern bleiben deshalb stehen und springen nicht, während man filtert.

Wichtig dabei: Die Reiter trennen nach **Art**, der Herkunftsfilter nach
**System**. Das sind zwei Achsen, und keine ersetzt die andere — „zeig mir,
was im Manager passiert ist" ist ohne ihn nicht beantwortbar. Die
Herkunftsknöpfe erscheinen nur, wenn in der offenen Art mehr als ein System
vorkommt; zwischen „alles" und „alles" zu filtern ist kein Filter.

## Freigabe im Komm-Layer nicht vergessen

Eine Fähigkeit ohne Eintrag in `comm_layer.gateway_grants.<slug>` ist nicht
freigeschaltet, und „nicht erlaubt" sieht in der Ansicht aus wie „nichts da".
Für den Verbund braucht jedes Produkt neben `inbox.entries` jetzt auch
`inbox.read`.
