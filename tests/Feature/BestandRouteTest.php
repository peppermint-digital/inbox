<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Peppermint\Inbox\Feed\FremdeEintraege;
use Peppermint\Inbox\Feed\GesamtInbox;
use Peppermint\Inbox\Quellen\Quellenverzeichnis;
use Peppermint\Inbox\Quellen\ZaehlbareQuelle;
use Peppermint\Inbox\Tests\Support\User;

/**
 * Der Endpunkt hinter der Zahl am Posteingangs-Symbol (AI Brain #1152).
 *
 * Die Helfer heissen hier absichtlich `bestandRoute…`: Pest laedt alle
 * Testdateien in EINEN Prozess, und ein Helfer auf oberster Ebene mit
 * einem allgemeinen Namen kollidiert still mit dem gleichnamigen einer
 * anderen Datei.
 */
beforeEach(function (): void {
    Cache::flush();
    app(Quellenverzeichnis::class)->vergessen();

    $this->ich = User::create(['name' => 'Ich', 'email' => 'ich@beispiel.test']);
});

/** Eine eigene Quelle, die ihren Bestand kennt. */
function bestandRouteQuelle(string $art, int $bestand): ZaehlbareQuelle
{
    return new class($art, $bestand) implements ZaehlbareQuelle
    {
        public function __construct(private string $art, private int $bestand) {}

        public function art(): string
        {
            return $this->art;
        }

        public function eintraege(Model $person, int $grenze): array
        {
            return [];
        }

        public function ungelesenGesamt(Model $person): int
        {
            return $this->bestand;
        }
    };
}

/** Die Holer-Seite durch eine feste Antwort ersetzen. */
function bestandRouteFremde(array $bestand = []): void
{
    $attrappe = Mockery::mock(FremdeEintraege::class);
    $attrappe->shouldReceive('holen')->andReturn([
        'items' => [],
        'kinds' => [],
        'unreachable' => [],
        'failed' => [],
        'totals' => $bestand,
        'markable' => [],
    ]);

    app()->instance(FremdeEintraege::class, $attrappe);
    app()->forgetInstance(GesamtInbox::class);
}

it('bringt die Route mit, unter dem Namen, den die Leiste erwartet', function (): void {
    // Die Minibar-Kachel und das Symbol in der Kopfzeile haengen an
    // diesem Namen. Ist er anders, zeichnet die Bridge gar keine Kachel —
    // und das faellt erst in Produktion auf.
    expect(Route::has('inbox.ungelesen'))->toBeTrue()
        ->and(route('inbox.ungelesen', absolute: false))->toBe('/posteingang/ungelesen');
});

it('nennt die Zahl in der Form, die beide Kacheln schon lesen', function (): void {
    app(Quellenverzeichnis::class)->anmelden(bestandRouteQuelle('meldung', 4112));
    bestandRouteFremde(['peppermint-crm' => ['aufgabe' => 13]]);

    $this->actingAs($this->ich)
        ->getJson('/posteingang/ungelesen')
        ->assertOk()
        // Dieselbe Form wie der alte Endpunkt: Beide Kacheln lesen
        // `unread_count`, und eine neue Form haette denselben Umbau in
        // zwei Paketen und vier Produkten bedeutet.
        ->assertExactJson(['unread_count' => 4125]);
});

it('laesst niemanden ohne Anmeldung an die Zahl', function (): void {
    // Wie viel in einem Posteingang wartet, ist eine Aussage ueber einen
    // Menschen. Ohne Person koennte sie nur „alle" meinen.
    $this->getJson('/posteingang/ungelesen')->assertStatus(401);
});

it('antwortet auf einen Ausfall mit 503 und NICHT mit einer Null', function (): void {
    // Eine Null heisst „alles gelesen" — die eine Aussage, die dieser
    // Endpunkt nicht aus Versehen machen darf. Bei 503 laesst die Kachel
    // ihren letzten bekannten Wert stehen, und genau so ist sie gebaut.
    $kaputt = Mockery::mock(GesamtInbox::class);
    $kaputt->shouldReceive('bestand')->andThrow(new RuntimeException('Leitung weg'));
    app()->instance(GesamtInbox::class, $kaputt);

    $this->actingAs($this->ich)
        ->getJson('/posteingang/ungelesen')
        ->assertStatus(503)
        ->assertJsonMissingPath('unread_count');
});
