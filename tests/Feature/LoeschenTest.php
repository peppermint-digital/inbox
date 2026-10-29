<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Peppermint\Inbox\Quellen\InboxQuelle;
use Peppermint\Inbox\Quellen\LoeschbareQuelle;
use Peppermint\Inbox\Quellen\Quellenverzeichnis;
use Peppermint\Inbox\Tests\Support\User;

/**
 * Der Loeschknopf im gemeinsamen Posteingang (AI Brain #9069).
 *
 * Die Helfer heissen hier `loeschTest…`: Pest laedt alle Testdateien in
 * EINEN Prozess, und ein Helfer auf oberster Ebene mit einem allgemeinen
 * Namen kollidiert still mit dem gleichnamigen einer anderen Datei.
 */
beforeEach(function (): void {
    app(Quellenverzeichnis::class)->vergessen();

    $this->ich = User::create(['name' => 'Ich', 'email' => 'ich@beispiel.test']);
});

/** Eine Quelle, deren Zeilen man loeschen kann — sie merkt sich, was sie traf. */
function loeschTestQuelle(string $art): LoeschbareQuelle
{
    return new class($art) implements LoeschbareQuelle
    {
        /** @var array<int, string> */
        public array $geloeschteIds = [];

        public bool $allesGeloescht = false;

        public function __construct(private string $art) {}

        public function art(): string
        {
            return $this->art;
        }

        public function eintraege(Model $person, int $grenze): array
        {
            return [];
        }

        public function loeschen(Model $person, array $ids): int
        {
            $this->geloeschteIds = array_merge($this->geloeschteIds, $ids);

            return count($ids);
        }

        public function allesLoeschen(Model $person): int
        {
            $this->allesGeloescht = true;

            return 4113;
        }
    };
}

/** Eine gewoehnliche Quelle ohne Loeschvertrag — eine Aufgabe etwa. */
function loeschTestGewoehnlich(string $art): InboxQuelle
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

it('nennt nur die Arten loeschbar, deren Quelle den Vertrag umsetzt', function (): void {
    // Die Oberflaeche raet nicht. Ein Loeschknopf an einer Aufgabe waere
    // entweder wirkungslos oder ein Datenverlust im Fremdsystem — Bastians
    // Vorgabe vom 29.10.2026 dazu: „Aufgaben und Belege nie."
    app(Quellenverzeichnis::class)->anmeldenViele([
        loeschTestQuelle('notification'),
        loeschTestGewoehnlich('task'),
        loeschTestGewoehnlich('document'),
    ]);

    $gesammelt = app(Quellenverzeichnis::class)->sammeln($this->ich, 50);

    expect($gesammelt['deletable'])->toBe(['notification'])
        ->and($gesammelt['deletable'])->not->toContain('task')
        ->and($gesammelt['deletable'])->not->toContain('document');
});

it('reicht die Kennungen an die Quelle der Art durch', function (): void {
    $quelle = loeschTestQuelle('notification');
    app(Quellenverzeichnis::class)->anmelden($quelle);

    $getroffen = app(Quellenverzeichnis::class)->loeschen($this->ich, 'notification', ['a', 'b']);

    expect($getroffen)->toBe(2)
        ->and($quelle->geloeschteIds)->toBe(['a', 'b']);
});

it('wirft bei einer Art, die hier nicht loeschbar ist', function (): void {
    // Bewusst eine Ausnahme und keine Null: „keine Zeile geloescht" und
    // „diese Art kann das hier gar nicht" sehen in der Oberflaeche gleich
    // aus, und das Zweite ist ein Fehler im Verbund.
    app(Quellenverzeichnis::class)->anmelden(loeschTestGewoehnlich('task'));

    expect(fn () => app(Quellenverzeichnis::class)->loeschen($this->ich, 'task', ['1']))
        ->toThrow(RuntimeException::class);
});

it('loescht mit „alle" nur die genannte ART', function (): void {
    // „Alle meine Meldungen loeschen" ist eine Aussage ueber Meldungen.
    // Ohne die Art naehme derselbe Knopf beim naechsten loeschbaren
    // Vokabular stillschweigend mehr mit, als draufsteht.
    $meldungen = loeschTestQuelle('notification');
    $andere = loeschTestQuelle('imkr');
    app(Quellenverzeichnis::class)->anmeldenViele([$meldungen, $andere]);

    $getroffen = app(Quellenverzeichnis::class)->allesLoeschen($this->ich, 'notification');

    expect($getroffen)->toBe(4113)
        ->and($meldungen->allesGeloescht)->toBeTrue()
        ->and($andere->allesGeloescht)->toBeFalse();
});

/* ------------------------------------------------------------ die Route */

it('bringt die Route mit, unter dem Namen, den die Oberflaeche erwartet', function (): void {
    expect(Route::has('inbox.loeschen'))->toBeTrue()
        ->and(route('inbox.loeschen', absolute: false))->toBe('/posteingang/zeilen');
});

it('loescht ueber die Route und nennt die Zahl', function (): void {
    app(Quellenverzeichnis::class)->anmelden(loeschTestQuelle('notification'));

    $this->actingAs($this->ich)
        ->deleteJson('/posteingang/zeilen', ['kind' => 'notification', 'ids' => ['a', 'b']])
        ->assertOk()
        ->assertExactJson(['deleted' => 2]);
});

it('verlangt die ART auch bei „alle"', function (): void {
    app(Quellenverzeichnis::class)->anmelden(loeschTestQuelle('notification'));

    $this->actingAs($this->ich)
        ->deleteJson('/posteingang/zeilen', ['alle' => true])
        ->assertStatus(422);
});

it('antwortet auf eine nicht loeschbare Art mit 502 und dem Grund', function (): void {
    // Nicht `deleted: 0`: Wer loescht und es kommt nicht an, muss das
    // sehen. Sonst steht die Zeile beim Neuladen wieder da, und der Fehler
    // sieht aus wie der Fix.
    app(Quellenverzeichnis::class)->anmelden(loeschTestGewoehnlich('task'));

    $this->actingAs($this->ich)
        ->deleteJson('/posteingang/zeilen', ['kind' => 'task', 'ids' => ['1']])
        ->assertStatus(502)
        ->assertJsonStructure(['message'])
        ->assertJsonMissingPath('deleted');
});

it('laesst niemanden ohne Anmeldung loeschen', function (): void {
    $this->deleteJson('/posteingang/zeilen', ['kind' => 'notification', 'ids' => ['a']])
        ->assertStatus(401);
});
