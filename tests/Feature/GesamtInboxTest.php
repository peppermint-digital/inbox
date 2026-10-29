<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Peppermint\Inbox\Arten\InboxArt;
use Peppermint\Inbox\Arten\InboxArten;
use Peppermint\Inbox\Feed\Eintrag;
use Peppermint\Inbox\Feed\FremdeEintraege;
use Peppermint\Inbox\Feed\GesamtInbox;
use Peppermint\Inbox\Quellen\AbhakbareQuelle;
use Peppermint\Inbox\Quellen\InboxQuelle;
use Peppermint\Inbox\Quellen\Quellenverzeichnis;
use Peppermint\Inbox\Quellen\ZaehlbareQuelle;
use Peppermint\Inbox\Tests\Support\User;

/**
 * Die Inbox ueber alle Systeme.
 *
 * Eigene Quellen plus geholte — nicht kopierte. Die Holer-Seite wird hier
 * ersetzt; dass sie wirklich ueber Bruecke bzw. Gateway geht, haengt am
 * aufnehmenden System und hat dort seinen Test.
 */
beforeEach(function (): void {
    Cache::flush();
    app(InboxArten::class)->vergessen();
    app(Quellenverzeichnis::class)->vergessen();

    app(InboxArten::class)->registriereViele([
        new InboxArt('aufgabe', 'Neue Aufgaben', reihenfolge: 10),
        new InboxArt('meldung', 'Benachrichtigungen', reihenfolge: 40),
    ]);

    $this->ich = User::create(['name' => 'Ich', 'email' => 'ich@beispiel.test']);
});

/**
 * Eine eigene Quelle, die ihren BESTAND kennt.
 *
 * Der Fall, der die Trennung von `counts` und `totals` ueberhaupt noetig
 * macht: Sie zeigt wenige Zeilen und hat viele.
 */
function zaehlbareQuelle(string $art, array $eintraege, int $bestand): ZaehlbareQuelle
{
    return new class($art, $eintraege, $bestand) implements ZaehlbareQuelle
    {
        public function __construct(
            private string $art,
            private array $eintraege,
            private int $bestand,
        ) {}

        public function art(): string
        {
            return $this->art;
        }

        public function eintraege(Model $person, int $grenze): array
        {
            return array_slice($this->eintraege, 0, $grenze);
        }

        public function ungelesenGesamt(Model $person): int
        {
            return $this->bestand;
        }
    };
}

/** Eine eigene Quelle, an deren Zeilen ein Haken ein Ziel findet. */
function gesamtAbhakbareQuelle(string $art, array $eintraege): AbhakbareQuelle
{
    return new class($art, $eintraege) implements AbhakbareQuelle
    {
        public function __construct(private string $art, private array $eintraege) {}

        public function art(): string
        {
            return $this->art;
        }

        public function eintraege(Model $person, int $grenze): array
        {
            return array_slice($this->eintraege, 0, $grenze);
        }

        public function alsGelesen(Model $person, array $ids): int
        {
            return count($ids);
        }

        public function allesAlsGelesen(Model $person): int
        {
            return count($this->eintraege);
        }
    };
}

/** Eine eigene Quelle mit festen Zeilen. */
function eigeneQuelle(string $art, array $eintraege, ?Throwable $wirft = null): InboxQuelle
{
    return new class($art, $eintraege, $wirft) implements InboxQuelle
    {
        public function __construct(
            private string $art,
            private array $eintraege,
            private ?Throwable $wirft,
        ) {}

        public function art(): string
        {
            return $this->art;
        }

        public function eintraege(Model $person, int $grenze): array
        {
            if ($this->wirft !== null) {
                throw $this->wirft;
            }

            return array_slice($this->eintraege, 0, $grenze);
        }
    };
}

function eintrag(string $art, string $titel, array $u = []): Eintrag
{
    return new Eintrag(
        id: $u['id'] ?? uniqid(),
        art: $art,
        titel: $titel,
        zeitpunkt: $u['zeitpunkt'] ?? now()->toIso8601String(),
        ungelesen: $u['ungelesen'] ?? true,
    );
}

/** Die Holer-Seite durch feste Antworten ersetzen. */
function fremdeLiefern(array $zeilen, array $arten = [], array $stumm = [], array $gescheitert = [], array $bestand = [], array $abhakbar = []): void
{
    $attrappe = Mockery::mock(FremdeEintraege::class);
    $attrappe->shouldReceive('holen')->andReturn([
        'items' => $zeilen,
        'kinds' => $arten,
        'unreachable' => $stumm,
        'failed' => $gescheitert,
        'totals' => $bestand,
        'markable' => $abhakbar,
    ]);

    app()->instance(FremdeEintraege::class, $attrappe);
    app()->forgetInstance(GesamtInbox::class);
}

function fremdeZeile(array $u = []): array
{
    return array_merge([
        'id' => 'crm-1',
        'kind' => 'meldung',
        'title' => 'Im CRM passiert',
        'message' => null,
        'link' => 'https://crm.beispiel.test/x',
        'icon' => 'bell',
        'color' => 'blue',
        'created_at' => now()->toIso8601String(),
        'is_unread' => true,
        'source' => 'peppermint-crm',
        'source_label' => 'Peppermint CRM',
    ], $u);
}

it('legt eigene und geholte Eintraege in eine Liste', function (): void {
    app(Quellenverzeichnis::class)->anmelden(eigeneQuelle('aufgabe', [eintrag('aufgabe', 'Hier zu tun')]));
    fremdeLiefern([fremdeZeile()], [['key' => 'meldung', 'label' => 'Benachrichtigungen']]);

    $inbox = app(GesamtInbox::class)->for($this->ich);

    expect($inbox['items'])->toHaveCount(2)
        ->and(array_column($inbox['items'], 'title'))
        ->toContain('Hier zu tun')
        ->toContain('Im CRM passiert');
});

it('laesst eigene Zeilen ohne Herkunft und fremde mit', function (): void {
    // `null` heisst „von hier". Die Oberflaeche setzt dafuer das eigene
    // Wort ein — ohne diese Unterscheidung muesste der Leser raten, ob
    // eine Zeile von hier kommt oder ihre Marke verlorenging.
    app(Quellenverzeichnis::class)->anmelden(eigeneQuelle('aufgabe', [eintrag('aufgabe', 'Hier zu tun')]));
    fremdeLiefern([fremdeZeile()], [['key' => 'meldung', 'label' => 'Benachrichtigungen']]);

    $zeilen = collect(app(GesamtInbox::class)->for($this->ich)['items'])->keyBy('title');

    expect($zeilen['Hier zu tun']['source'])->toBeNull()
        ->and($zeilen['Im CRM passiert']['source'])->toBe('peppermint-crm');
});

it('kappt je Art UND System, nicht global', function (): void {
    // Der Fehler, der diese Ansicht schon zweimal ausgehoehlt hat: Ein
    // globaler Deckel laesst das lauteste System die Obergrenze allein
    // fuellen, und die anderen fallen unsichtbar hinten herunter.
    config(['inbox.max_items' => 3]);

    $viele = [];
    for ($i = 0; $i < 10; $i++) {
        $viele[] = eintrag('aufgabe', "Eigene $i");
    }

    app(Quellenverzeichnis::class)->anmelden(eigeneQuelle('aufgabe', $viele));
    fremdeLiefern([
        fremdeZeile(['id' => 'c1', 'title' => 'Fremd 1', 'created_at' => now()->subYear()->toIso8601String()]),
        fremdeZeile(['id' => 'c2', 'title' => 'Fremd 2', 'created_at' => now()->subYear()->toIso8601String()]),
    ], [['key' => 'meldung', 'label' => 'Benachrichtigungen']]);

    $titel = array_column(app(GesamtInbox::class)->for($this->ich)['items'], 'title');

    expect(array_filter($titel, fn ($t): bool => str_starts_with($t, 'Eigene')))->toHaveCount(3)
        // Trotz zehn lauterer eigener Zeilen stehen die fremden noch da.
        ->and($titel)->toContain('Fremd 1')->toContain('Fremd 2');
});

it('zaehlt je Art nur das Ungelesene', function (): void {
    app(Quellenverzeichnis::class)->anmelden(eigeneQuelle('aufgabe', [
        eintrag('aufgabe', 'Offen'),
        eintrag('aufgabe', 'Schon gesehen', ['ungelesen' => false]),
    ]));
    fremdeLiefern([fremdeZeile()], [['key' => 'meldung', 'label' => 'Benachrichtigungen']]);

    expect(app(GesamtInbox::class)->for($this->ich)['counts'])
        ->toBe(['aufgabe' => 1, 'meldung' => 1]);
});

it('nimmt eine Art auf, die nur ein anderes System kennt — MIT ihrem Wort', function (): void {
    // Die Erweiterbarkeit ueber Systemgrenzen: Wer eine neue Kategorie
    // einfuehrt, bringt sie den anderen mit — ohne Deploy drueben.
    //
    // Und zwar samt Beschriftung. Reiste nur der Schluessel, stuende hier
    // „rechnung" statt „Belege sichten", und die Erweiterbarkeit waere an
    // der Beschriftung zu Ende.
    fremdeLiefern(
        [fremdeZeile(['kind' => 'rechnung', 'title' => 'Beleg prüfen'])],
        [['key' => 'rechnung', 'label' => 'Belege sichten', 'icon' => 'file-text', 'color' => 'orange', 'order' => 25]],
    );

    $inbox = app(GesamtInbox::class)->for($this->ich);
    $art = collect($inbox['kinds'])->firstWhere('key', 'rechnung');

    expect($art)->not->toBeNull()
        ->and($art['label'])->toBe('Belege sichten')
        ->and($art['icon'])->toBe('file-text')
        ->and(array_column($inbox['items'], 'title'))->toContain('Beleg prüfen');
});

it('laesst die EIGENE Benennung einer Art gewinnen', function (): void {
    // Zwei Systeme koennen dieselbe Art verschieden nennen. Wer sie hier
    // anmeldet, meint sein eigenes Wort.
    fremdeLiefern([], [['key' => 'aufgabe', 'label' => 'Fremdes Wort']]);

    $art = collect(app(GesamtInbox::class)->for($this->ich)['kinds'])->firstWhere('key', 'aufgabe');

    expect($art['label'])->toBe('Neue Aufgaben');
});

it('nennt ein System, das nicht geantwortet hat', function (): void {
    fremdeLiefern([], [], ['Peppermint Verwaltung']);

    expect(app(GesamtInbox::class)->for($this->ich)['unreachable'])
        ->toBe(['Peppermint Verwaltung']);
});

it('laesst eine gescheiterte EIGENE Quelle die Liste nicht mitnehmen', function (): void {
    // Eine Aufgabentabelle mitten in einer Wanderung darf nicht dazu
    // fuehren, dass auch die Meldungen verschwinden.
    app(Quellenverzeichnis::class)->anmeldenViele([
        eigeneQuelle('aufgabe', [], new RuntimeException('Tabelle gerade weg')),
        eigeneQuelle('meldung', [eintrag('meldung', 'Steht trotzdem')]),
    ]);
    fremdeLiefern([], []);

    $inbox = app(GesamtInbox::class)->for($this->ich);

    expect(array_column($inbox['items'], 'title'))->toBe(['Steht trotzdem'])
        ->and($inbox['failed'])->toBe(['aufgabe']);
});

it('stellt Ungelesenes vor Gelesenes, dann das Neuere', function (): void {
    app(Quellenverzeichnis::class)->anmelden(eigeneQuelle('aufgabe', [
        eintrag('aufgabe', 'Alt und gelesen', ['ungelesen' => false, 'zeitpunkt' => now()->toIso8601String()]),
        eintrag('aufgabe', 'Alt und offen', ['zeitpunkt' => now()->subDay()->toIso8601String()]),
    ]));
    fremdeLiefern([], []);

    expect(array_column(app(GesamtInbox::class)->for($this->ich)['items'], 'title'))
        ->toBe(['Alt und offen', 'Alt und gelesen']);
});

it('nennt auch eine Quelle, die in einem ANDEREN System gescheitert ist', function (): void {
    // Ohne diese Weitergabe sieht der Aufrufer nur eine kuerzere Liste —
    // und das sieht aus wie „da ist nichts", nicht wie ein Ausfall. Beim
    // ersten Livetest fehlten genau so die Aktivitaeten des Managers, und
    // nichts sagte warum.
    fremdeLiefern([], [], [], ['Peppermint Manager: activity']);

    expect(app(GesamtInbox::class)->for($this->ich)['failed'])
        ->toBe(['Peppermint Manager: activity']);
});

it('kappt im Werkzeug NICHT ueber die Gesamtliste', function (): void {
    // Der Fehler vom 19.10.2026, und zwar der, vor dem das README
    // ausdruecklich warnt — von mir selbst wieder eingebaut:
    //
    // Das Werkzeug schnitt die zusammengefuegte Liste auf `limit`. Der
    // Manager meldet Aufgaben, Aenderungen und Liegengebliebenes VOR den
    // Meldungen an; zusammen 49. Die 50er-Grenze liess danach genau EINE
    // Meldung durch, und in den anderen drei Systemen fehlten seine 50
    // Meldungen vollstaendig. Ohne Fehler, ohne Hinweis.
    //
    // Jede Quelle wendet die Grenze fuer SICH an — ein zweiter Schnitt
    // kappt nach Anmelde-Reihenfolge statt nach Inhalt.
    app(Quellenverzeichnis::class)->anmeldenViele([
        eigeneQuelle('aufgabe', array_map(fn (int $i): Eintrag => eintrag('aufgabe', "A$i"), range(1, 40))),
        eigeneQuelle('meldung', array_map(fn (int $i): Eintrag => eintrag('meldung', "M$i"), range(1, 40))),
    ]);

    $gesammelt = app(Quellenverzeichnis::class)->sammeln($this->ich, 50);

    $h = [];
    foreach ($gesammelt['items'] as $z) {
        $h[$z['kind']] = ($h[$z['kind']] ?? 0) + 1;
    }

    // Beide Arten vollstaendig: 80 Zeilen, nicht 50.
    expect($h)->toBe(['aufgabe' => 40, 'meldung' => 40]);
});

it('trennt „in dieser Liste" vom BESTAND', function (): void {
    /*
     * Der Fehler, der die Seite kaputt aussehen liess (#1096): Am
     * 26.10.2026 nannte die Kachel in der Kopfzeile 3953 und die Liste
     * zeigte 50 Zeilen — zwei Bedeutungen unter einem Namen, und beim
     * Abarbeiten sank die Zahl scheinbar nicht.
     *
     * `counts` ist seither die Liste, `totals` der Bestand. Nur so kann
     * die Ansicht „N von M" sagen.
     */
    config(['inbox.max_items' => 2]);

    app(Quellenverzeichnis::class)->anmelden(
        zaehlbareQuelle('meldung', [
            eintrag('meldung', 'Eine'),
            eintrag('meldung', 'Zwei'),
            eintrag('meldung', 'Drei'),
        ], bestand: 3953)
    );
    fremdeLiefern([]);

    $inbox = app(GesamtInbox::class)->for($this->ich);

    expect($inbox['counts']['meldung'])->toBe(2)
        ->and($inbox['totals']['local']['meldung'])->toBe(3953);
});

it('nimmt fuer eine Quelle ohne eigene Zaehlung das Gezeigte als Bestand', function (): void {
    // Nie zu VIEL: Eine Zahl, die kleiner ist als die Wahrheit, verspricht
    // nichts, was die Liste nicht halten kann. Eine zu grosse waere genau
    // die Luecke, die niemand erklaeren konnte.
    app(Quellenverzeichnis::class)->anmelden(eigeneQuelle('aufgabe', [
        eintrag('aufgabe', 'Offen'),
        eintrag('aufgabe', 'Schon gesehen', ['ungelesen' => false]),
    ]));
    fremdeLiefern([]);

    expect(app(GesamtInbox::class)->for($this->ich)['totals']['local']['aufgabe'])->toBe(1);
});

it('haelt den Bestand der anderen Systeme je Herkunft auseinander', function (): void {
    // Je System, nicht zusammengelegt: Die Oberflaeche muss von einer
    // Zeile aus nachsehen koennen, wie viele IHR System noch hat.
    fremdeLiefern(
        [fremdeZeile()],
        [['key' => 'meldung', 'label' => 'Benachrichtigungen']],
        bestand: ['peppermint-crm' => ['meldung' => 40]],
    );

    expect(app(GesamtInbox::class)->for($this->ich)['totals']['sources'])
        ->toBe(['peppermint-crm' => ['meldung' => 40]]);
});

it('sagt, wo ein Haken ein Ziel findet — hier und drueben', function (): void {
    /*
     * Die Ansicht raet das nicht. Bis zum 27.10.2026 stand die Regel im
     * Produkt („von hier, ungelesen, Art notification") — eine Regel, die
     * jedes der vier Produkte haette abschreiben muessen und die bei einer
     * neuen abhakbaren Quelle still falsch geworden waere.
     */
    app(Quellenverzeichnis::class)->anmeldenViele([
        gesamtAbhakbareQuelle('meldung', [eintrag('meldung', 'Von hier')]),
        eigeneQuelle('aufgabe', [eintrag('aufgabe', 'Nicht abhakbar')]),
    ]);
    fremdeLiefern(
        [fremdeZeile()],
        [['key' => 'meldung', 'label' => 'Benachrichtigungen']],
        abhakbar: ['peppermint-crm' => ['meldung']],
    );

    $inbox = app(GesamtInbox::class)->for($this->ich);

    expect($inbox['markable']['local'])->toBe(['meldung'])
        ->and($inbox['markable']['sources'])->toBe(['peppermint-crm' => ['meldung']]);
});

it('gibt Bestand und Abhakbarkeit mit, was ueber die Systemgrenze geht', function (): void {
    // `sammeln()` ist, was `list-inbox-entries-tool` herausgibt. Ohne
    // diese beiden Felder sieht der Aufrufer 50 Zeilen und weiss nicht,
    // dass 3953 dahinterstehen — und er weiss nicht, ob ein Haken an
    // einer fremden Zeile ankommt.
    app(Quellenverzeichnis::class)->anmeldenViele([
        gesamtAbhakbareQuelle('meldung', [eintrag('meldung', 'Eine')]),
        zaehlbareQuelle('aufgabe', [eintrag('aufgabe', 'Offen')], bestand: 77),
    ]);

    $gesammelt = app(Quellenverzeichnis::class)->sammeln($this->ich, 50);

    expect($gesammelt['totals'])->toBe(['meldung' => 1, 'aufgabe' => 77])
        ->and($gesammelt['markable'])->toBe(['meldung']);
});

/*
|--------------------------------------------------------------------------
| Die globale Ungelesen-Zahl (AI Brain #1152)
|--------------------------------------------------------------------------
|
| Die Zahl am Posteingangs-Symbol und an der Minibar-Kachel. Sie holte
| bis zum 29.10.2026 die ungelesenen MELDUNGEN dieses einen Systems,
| waehrend der Klick den gemeinsamen Posteingang aufmachte.
|
*/

it('zaehlt den Bestand ueber alle Arten UND alle Systeme', function (): void {
    app(Quellenverzeichnis::class)->anmeldenViele([
        zaehlbareQuelle('meldung', [eintrag('meldung', 'Eine')], bestand: 4112),
        eigeneQuelle('aufgabe', [eintrag('aufgabe', 'Offen'), eintrag('aufgabe', 'Auch offen')]),
    ]);

    fremdeLiefern(
        [fremdeZeile()],
        bestand: ['peppermint-crm' => ['meldung' => 12], 'peppermint-manager' => ['aufgabe' => 13, 'meldung' => 5]],
    );

    // 4112 eigene Meldungen + 2 eigene Aufgaben + 12 + 13 + 5 aus zwei
    // fremden Systemen.
    expect(app(GesamtInbox::class)->bestand($this->ich))->toBe(4144);
});

it('zaehlt den BESTAND und nicht die gezeigten Zeilen', function (): void {
    // Die Liste ist je Art und System gekappt. Eine Zahl, die der Kappung
    // folgte, stuende bei 50 und bliebe dort — genau die Luecke aus #1096,
    // nur eine Ebene hoeher.
    app(Quellenverzeichnis::class)->anmelden(
        zaehlbareQuelle('meldung', [eintrag('meldung', 'Eine')], bestand: 3987)
    );
    fremdeLiefern([]);

    expect(app(GesamtInbox::class)->bestand($this->ich))->toBe(3987);
});

it('zaehlt bei einer Quelle ohne Zaehl-Vertrag nur die UNGELESENEN', function (): void {
    // Dieselbe Regel wie in `sammeln()`, und absichtlich dieselbe Stelle
    // im Code: Zwei Rechnungen fuer dieselbe Zahl laufen auseinander, und
    // dann widerspricht das Abzeichen der Seite, die es aufmacht.
    app(Quellenverzeichnis::class)->anmelden(eigeneQuelle('aufgabe', [
        eintrag('aufgabe', 'Offen'),
        eintrag('aufgabe', 'Schon gesehen', ['ungelesen' => false]),
    ]));
    fremdeLiefern([]);

    expect(app(GesamtInbox::class)->bestand($this->ich))->toBe(1);
});

it('zaehlt weiter, wenn eine eigene Quelle faellt', function (): void {
    // Eine Null waere die falsche Aussage — sie hiesse „alles gelesen".
    // Was ausfiel, kostet seinen Beitrag, nicht die Zahl.
    app(Quellenverzeichnis::class)->anmeldenViele([
        eigeneQuelle('aufgabe', [], wirft: new RuntimeException('Tabelle wandert')),
        zaehlbareQuelle('meldung', [], bestand: 9),
    ]);
    fremdeLiefern([]);

    expect(app(GesamtInbox::class)->bestand($this->ich))->toBe(9);
});

it('uebersieht ein System, das noch keinen Bestand mitschickt, ohne zu rechnen', function (): void {
    // Ein Produkt auf aelterem Paketstand schickt `totals` nicht mit.
    // Dann fehlt sein Beitrag, und das ist die richtige Richtung: Zu
    // niedrig zaehlen heisst, dass jemand nachsieht und MEHR findet.
    app(Quellenverzeichnis::class)->anmelden(zaehlbareQuelle('meldung', [], bestand: 4));
    fremdeLiefern([fremdeZeile()], bestand: ['peppermint-crm' => []]);

    expect(app(GesamtInbox::class)->bestand($this->ich))->toBe(4);
});
