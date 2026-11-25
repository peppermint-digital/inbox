<?php

use Illuminate\Database\Eloquent\Model;
use Peppermint\AiBrainBridge\Facades\AiBrain;
use Peppermint\Inbox\Feed\BridgeRemoteSource;
use Peppermint\Inbox\Feed\Eintrag;
use Peppermint\Inbox\Quellen\AbhakbareQuelle;
use Peppermint\Inbox\Quellen\LoeschbareQuelle;
use Peppermint\Inbox\Quellen\NachTypenFilterbar;
use Peppermint\Inbox\Quellen\Quellenverzeichnis;
use Peppermint\Inbox\Quellen\ZaehlbareQuelle;
use Peppermint\Inbox\Tests\Support\User;

/**
 * Nur die gezeigten Arten eines anderen Systems (pm #10227).
 *
 * Bastian, 25.11.2026: Betriebsmeldungen aus AI Brain (app.health,
 * infra.* ...) gehoeren nicht in die Glocke des Managers. Weil AI Brain die
 * Zahlen rechnet, wirkt der Filter dort — ueber `types`.
 */
beforeEach(function (): void {
    app(Quellenverzeichnis::class)->vergessen();
    $this->ich = User::create(['name' => 'Ich', 'email' => 'ich@beispiel.test']);
});

/** Eine Meldungsquelle mit zwei Arten, die sich einschraenken laesst. */
function typenQuelle(?array $typen = null): object
{
    return new class($typen) implements AbhakbareQuelle, LoeschbareQuelle, NachTypenFilterbar, ZaehlbareQuelle
    {
        /** @var array<int, array{id: string, typ: string}> */
        public static array $zeilen = [];

        public static array $aufrufe = [];

        public function __construct(public ?array $typen) {}

        public function nurTypen(?array $typen): static
        {
            return new self($typen);
        }

        private function gezeigt(): array
        {
            return array_values(array_filter(self::$zeilen, fn ($z) => $this->typen === null || in_array($z['typ'], $this->typen, true)));
        }

        public function art(): string
        {
            return 'notification';
        }

        public function eintraege(Model $person, int $grenze): array
        {
            return array_map(fn ($z) => new Eintrag(id: $z['id'], art: 'notification', titel: $z['typ']), $this->gezeigt());
        }

        public function ungelesenGesamt(Model $person): int
        {
            return count($this->gezeigt());
        }

        public function alsGelesen(Model $person, array $ids): int
        {
            return 0;
        }

        public function allesAlsGelesen(Model $person): int
        {
            self::$aufrufe[] = ['gelesen', $this->typen];

            return count($this->gezeigt());
        }

        public function loeschen(Model $person, array $ids): int
        {
            return 0;
        }

        public function allesLoeschen(Model $person): int
        {
            self::$aufrufe[] = ['loeschen', $this->typen];

            return count($this->gezeigt());
        }
    };
}

beforeEach(function (): void {
    $klasse = get_class(typenQuelle());
    $klasse::$zeilen = [
        ['id' => '1', 'typ' => 'task.done'],
        ['id' => '2', 'typ' => 'app.health'],
        ['id' => '3', 'typ' => 'app.health'],
        ['id' => '4', 'typ' => ''],
    ];
    $klasse::$aufrufe = [];
    app(Quellenverzeichnis::class)->anmelden(typenQuelle());
});

/** Die Bruecke ohne Netz: merkt sich jeden Werkzeug-Aufruf. */
function typenHub(): object
{
    $hub = new class
    {
        public array $aufrufe = [];

        public function call(string $werkzeug, array $argumente = []): array
        {
            $this->aufrufe[] = [$werkzeug, $argumente];

            return ['items' => []];
        }
    };

    AiBrain::swap($hub);

    return $hub;
}

it('liefert ohne types alles wie bisher', function (): void {
    $gesammelt = app(Quellenverzeichnis::class)->sammeln($this->ich, 50);

    expect($gesammelt['items'])->toHaveCount(4)
        ->and($gesammelt['totals'])->toBe(['notification' => 4]);
});

it('schraenkt Liste UND Bestand auf die genannten Arten ein', function (): void {
    $gesammelt = app(Quellenverzeichnis::class)->sammeln($this->ich, 50, ['task.done', '']);

    expect(array_column($gesammelt['items'], 'id'))->toBe(['1', '4'])
        // Die Zahl sinkt mit — sonst stuende 4 am Abzeichen und 2 in der Liste.
        ->and($gesammelt['totals'])->toBe(['notification' => 2])
        ->and(app(Quellenverzeichnis::class)->bestand($this->ich, 50, ['task.done', '']))->toBe(['notification' => 2]);
});

it('haekt und loescht bei „alle" nur die gezeigten Arten', function (): void {
    $verzeichnis = app(Quellenverzeichnis::class);

    expect($verzeichnis->allesAlsGelesen($this->ich, null, ['task.done']))->toBe(1)
        ->and($verzeichnis->allesLoeschen($this->ich, 'notification', ['task.done']))->toBe(1);

    $klasse = get_class(typenQuelle());
    expect($klasse::$aufrufe)->toBe([['gelesen', ['task.done']], ['loeschen', ['task.done']]]);
});

it('schickt die gezeigten Arten beim Holen an AI Brain mit', function (): void {
    config(['inbox.fremde_typen' => ['ai-brain' => ['task.done', '']]]);

    $hub = typenHub();

    app(BridgeRemoteSource::class)->holen('ai-brain', 'ich@beispiel.test', 50);

    expect($hub->aufrufe)->toBe([['list-inbox-entries-tool', ['limit' => 50, 'types' => ['task.done', '']]]]);
});

it('schickt ohne Einstellung fuer das System keine types', function (): void {
    config(['inbox.fremde_typen' => []]);

    $hub = typenHub();

    app(BridgeRemoteSource::class)->holen('ai-brain', 'ich@beispiel.test', 50);

    expect($hub->aufrufe)->toBe([['list-inbox-entries-tool', ['limit' => 50]]]);
});

it('zeigt AI Brain in der Vorgabe keine Betriebsarten, aber Meldungen ohne Art', function (): void {
    $vorgabe = (require __DIR__.'/../../config/inbox.php')['fremde_typen']['ai-brain'];

    expect($vorgabe)->toContain('task.done', 'review.pending', '')
        ->not->toContain('app.health')
        ->not->toContain('email.health')
        ->not->toContain('schedule.overdue')
        ->not->toContain('codebase.audit')
        ->not->toContain('infra.server');
});
