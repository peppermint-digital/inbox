<?php

use Illuminate\Database\Eloquent\Model;
use Peppermint\Inbox\Feed\Abhaken;
use Peppermint\Inbox\Feed\Auspacken;
use Peppermint\Inbox\Feed\RemoteSource;
use Peppermint\Inbox\Quellen\AbhakbareQuelle;
use Peppermint\Inbox\Quellen\InboxQuelle;
use Peppermint\Inbox\Quellen\Quellenverzeichnis;
use Peppermint\Inbox\Tests\Support\User;

/**
 * Abhaken — spiegelbildlich zum Holen.
 *
 * Eine eigene Zeile geht an die Quelle, die sie kennt; eine fremde an das
 * System, in dem sie liegt. Und ein Fehlschlag ist laut: Wer abhakt und es
 * kommt nicht an, muss das sehen — sonst steht die Zeile beim Neuladen
 * wieder da, und der Fehler sieht aus wie der Fix.
 */
beforeEach(function (): void {
    app(Quellenverzeichnis::class)->vergessen();

    $this->ich = User::create(['name' => 'Ich', 'email' => 'ich@beispiel.test']);
});

/** Eine Quelle, die abhaken kann, und die mitschreibt, was sie bekam. */
function abhakenQuelle(string $art, int $bestand = 0): AbhakbareQuelle
{
    return new class($art, $bestand) implements AbhakbareQuelle
    {
        /** @var array<int, array<int, string>> */
        public array $bekommen = [];

        public int $alleAufrufe = 0;

        public function __construct(private string $art, private int $bestand) {}

        public function art(): string
        {
            return $this->art;
        }

        public function eintraege(Model $person, int $grenze): array
        {
            return [];
        }

        public function alsGelesen(Model $person, array $ids): int
        {
            $this->bekommen[] = $ids;

            return count($ids);
        }

        public function allesAlsGelesen(Model $person): int
        {
            $this->alleAufrufe++;

            return $this->bestand;
        }
    };
}

/** Eine Quelle, die NICHT abhaken kann — der Regelfall im Bestand. */
function abhakenStummeQuelle(string $art): InboxQuelle
{
    return new class($art) implements InboxQuelle
    {
        public function __construct(private string $art) {}

        public function art(): string
        {
            return $this->art;
        }

        public function eintraege(Model $person, int $grenze): array
        {
            return [];
        }
    };
}

/** Die Fremd-Seite durch eine Attrappe ersetzen, die mitschreibt. */
function abhakenFremdeSeite(?Throwable $wirft = null): object
{
    $attrappe = new class($wirft) implements RemoteSource
    {
        /** @var array<int, array<string, mixed>> */
        public array $aufrufe = [];

        public function __construct(private ?Throwable $wirft) {}

        public function anbieter(): array
        {
            return [];
        }

        public function holen(string $slug, string $adresse, int $grenze): array
        {
            return ['items' => [], 'kinds' => [], 'failed' => [], 'totals' => [], 'markable' => []];
        }

        public function alsGelesen(string $slug, string $adresse, string $art, array $ids): int
        {
            $this->aufrufe[] = compact('slug', 'adresse', 'art', 'ids');

            if ($this->wirft !== null) {
                throw $this->wirft;
            }

            return count($ids);
        }
    };

    app()->instance(RemoteSource::class, $attrappe);
    app()->forgetInstance(Abhaken::class);

    return $attrappe;
}

it('gibt eine eigene Zeile an die Quelle, die ihre Art fuellt', function (): void {
    $meldungen = abhakenQuelle('meldung');
    $aufgaben = abhakenQuelle('aufgabe');

    app(Quellenverzeichnis::class)->anmeldenViele([$meldungen, $aufgaben]);
    abhakenFremdeSeite();

    expect(app(Abhaken::class)->zeilen($this->ich, null, 'meldung', ['7', '8']))->toBe(2);

    // Und zwar NUR an die passende: Kennungen sind nur innerhalb einer Art
    // eindeutig. Wer alle Quellen mit derselben Liste fragt, hakt
    // womoeglich die Aufgabe 7 mit ab.
    expect($meldungen->bekommen)->toBe([['7', '8']])
        ->and($aufgaben->bekommen)->toBe([]);
});

it('wirft, wenn die Art hier gar nicht abgehakt werden kann', function (): void {
    // Keine Null: „keine Zeile geaendert" und „das kann hier niemand"
    // sehen in der Ansicht gleich aus, und das Zweite ist ein Fehler im
    // Verbund, den jemand sehen soll.
    app(Quellenverzeichnis::class)->anmelden(abhakenStummeQuelle('beleg'));
    abhakenFremdeSeite();

    expect(fn (): int => app(Abhaken::class)->zeilen($this->ich, null, 'beleg', ['1']))
        ->toThrow(RuntimeException::class, "Die Art 'beleg' kann hier nicht abgehakt werden.");
});

it('schickt eine fremde Zeile mit Slug UND Art ins Zielsystem', function (): void {
    $fremd = abhakenFremdeSeite();

    expect(app(Abhaken::class)->zeilen($this->ich, 'peppermint-crm', 'meldung', ['42']))->toBe(1);

    expect($fremd->aufrufe)->toBe([[
        'slug' => 'peppermint-crm',
        'adresse' => 'ich@beispiel.test',
        'art' => 'meldung',
        'ids' => ['42'],
    ]]);
});

it('laesst einen Fehlschlag aus dem Zielsystem NICHT verschwinden', function (): void {
    abhakenFremdeSeite(new RuntimeException('unreachable: CRM antwortet nicht'));

    expect(fn (): int => app(Abhaken::class)->zeilen($this->ich, 'peppermint-crm', 'meldung', ['42']))
        ->toThrow(RuntimeException::class, 'unreachable: CRM antwortet nicht');
});

it('fragt ohne Adresse gar nicht erst beim anderen System an', function (): void {
    // Ohne Adresse findet das Zielsystem den Menschen nicht — es wuerde
    // entweder nichts tun oder, schlimmer, irgendetwas.
    $ohne = User::create(['name' => 'Ohne', 'email' => '']);
    $fremd = abhakenFremdeSeite();

    expect(fn (): int => app(Abhaken::class)->zeilen($ohne, 'peppermint-crm', 'meldung', ['42']))
        ->toThrow(RuntimeException::class);

    expect($fremd->aufrufe)->toBe([]);
});

it('nimmt bei „alle eigenen" nur die abhakbaren Quellen', function (): void {
    $meldungen = abhakenQuelle('meldung', bestand: 3953);
    $stumm = abhakenStummeQuelle('beleg');

    app(Quellenverzeichnis::class)->anmeldenViele([$meldungen, $stumm]);
    abhakenFremdeSeite();

    expect(app(Abhaken::class)->alleEigenen($this->ich))->toBe(3953)
        ->and($meldungen->alleAufrufe)->toBe(1);
});

it('deckelt die Zahl der Kennungen je Aufruf', function (): void {
    $meldungen = abhakenQuelle('meldung');
    app(Quellenverzeichnis::class)->anmelden($meldungen);
    abhakenFremdeSeite();

    $viele = array_map('strval', range(1, Abhaken::MAX_IDS + 50));

    app(Abhaken::class)->zeilen($this->ich, null, 'meldung', $viele);

    expect($meldungen->bekommen[0])->toHaveCount(Abhaken::MAX_IDS);
});

it('wirft leere Kennungen weg, statt sie durchzureichen', function (): void {
    $meldungen = abhakenQuelle('meldung');
    app(Quellenverzeichnis::class)->anmelden($meldungen);
    abhakenFremdeSeite();

    expect(app(Abhaken::class)->zeilen($this->ich, null, 'meldung', ['', ' 0 ', '5']))->toBe(2);
    expect($meldungen->bekommen[0])->toBe([' 0 ', '5']);
});

it('macht aus einer Antwort ohne Zahl einen Fehler und keine Null', function (): void {
    // Null heisst „es war nichts zu tun". Eine Antwort, in der die Zahl
    // fehlt, sagt gar nichts — und wer sie als Null liest, zeigt
    // „abgehakt: 0" an, wo niemand hingesehen hat.
    expect(fn (): int => Auspacken::abgehakt(['irgendwas' => true]))
        ->toThrow(RuntimeException::class);

    expect(Auspacken::abgehakt(['marked' => 7]))->toBe(7)
        ->and(Auspacken::abgehakt(['structuredContent' => ['marked' => '3']]))->toBe(3);
});

it('antwortet am Endpunkt mit der Zahl der geaenderten Zeilen', function (): void {
    $meldungen = abhakenQuelle('meldung');
    app(Quellenverzeichnis::class)->anmelden($meldungen);
    abhakenFremdeSeite();

    $this->actingAs($this->ich)
        ->postJson('/posteingang/gelesen', ['kind' => 'meldung', 'ids' => ['7', '8']])
        ->assertOk()
        ->assertJson(['marked' => 2]);
});

it('antwortet am Endpunkt mit 502, wenn es nicht ankam', function (): void {
    // Und nicht mit 200 und `marked: 0`. Der Unterschied ist der ganze
    // Sinn: Die Ansicht muss „nicht angekommen" sagen koennen.
    abhakenFremdeSeite(new RuntimeException('unreachable: CRM antwortet nicht'));

    $this->actingAs($this->ich)
        ->postJson('/posteingang/gelesen', ['source' => 'peppermint-crm', 'kind' => 'meldung', 'ids' => ['42']])
        ->assertStatus(502)
        ->assertJson(['message' => 'unreachable: CRM antwortet nicht']);
});

it('haengt den Endpunkt hinter die Anmeldung', function (): void {
    abhakenFremdeSeite();

    // Ohne Person hat ein Posteingang kein Subjekt, nur eine Richtung.
    $this->postJson('/posteingang/gelesen', ['kind' => 'meldung', 'ids' => ['7']])
        ->assertStatus(401);
});

it('nimmt am Endpunkt auch „alle eigenen" an', function (): void {
    $meldungen = abhakenQuelle('meldung', bestand: 12);
    app(Quellenverzeichnis::class)->anmelden($meldungen);
    abhakenFremdeSeite();

    $this->actingAs($this->ich)
        ->postJson('/posteingang/gelesen', ['alle' => true])
        ->assertOk()
        ->assertJson(['marked' => 12]);
});
