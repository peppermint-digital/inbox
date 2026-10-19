<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Peppermint\Inbox\Arten\InboxArt;
use Peppermint\Inbox\Arten\InboxArten;
use Peppermint\Inbox\Feed\Eintrag;
use Peppermint\Inbox\Feed\FremdeEintraege;
use Peppermint\Inbox\Feed\GesamtInbox;
use Peppermint\Inbox\Quellen\InboxQuelle;
use Peppermint\Inbox\Quellen\Quellenverzeichnis;
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
function fremdeLiefern(array $zeilen, array $arten = [], array $stumm = [], array $gescheitert = []): void
{
    $attrappe = Mockery::mock(FremdeEintraege::class);
    $attrappe->shouldReceive('holen')->andReturn([
        'items' => $zeilen,
        'kinds' => $arten,
        'unreachable' => $stumm,
        'failed' => $gescheitert,
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
    fremdeLiefern([fremdeZeile()], ['meldung']);

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
    fremdeLiefern([fremdeZeile()], ['meldung']);

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
    ], ['meldung']);

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
    fremdeLiefern([fremdeZeile()], ['meldung']);

    expect(app(GesamtInbox::class)->for($this->ich)['counts'])
        ->toBe(['aufgabe' => 1, 'meldung' => 1]);
});

it('nimmt eine Art auf, die nur ein anderes System kennt', function (): void {
    // Die Erweiterbarkeit ueber Systemgrenzen: Wer eine neue Kategorie
    // einfuehrt, bringt sie den anderen mit — ohne Deploy drueben.
    fremdeLiefern([fremdeZeile(['kind' => 'rechnung', 'title' => 'Beleg prüfen'])], ['rechnung']);

    $inbox = app(GesamtInbox::class)->for($this->ich);

    expect(array_column($inbox['kinds'], 'key'))->toContain('rechnung')
        ->and(array_column($inbox['items'], 'title'))->toContain('Beleg prüfen');
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
