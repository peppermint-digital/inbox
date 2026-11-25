<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Peppermint\Inbox\Arten\InboxArten;
use Peppermint\Inbox\Feed\Abhaken;
use Peppermint\Inbox\Feed\Auspacken;
use Peppermint\Inbox\Feed\Eintrag;
use Peppermint\Inbox\Feed\FremdeEintraege;
use Peppermint\Inbox\Feed\GesamtInbox;
use Peppermint\Inbox\Feed\Loeschen;
use Peppermint\Inbox\Feed\RemoteSource;
use Peppermint\Inbox\Feed\SchreibendeRemoteSource;
use Peppermint\Inbox\Quellen\AbhakbareQuelle;
use Peppermint\Inbox\Quellen\LoeschbareQuelle;
use Peppermint\Inbox\Quellen\Quellenverzeichnis;
use Peppermint\Inbox\Quellen\ZaehlbareQuelle;
use Peppermint\Inbox\Tests\Support\User;

/**
 * Gelesen und Loeschen ueber die Systemgrenze (pm #10227).
 *
 * Bastian, 25.11.2026, im Manager: „wenn ich sie als gelesen markiere,
 * passiert nichts. Die Zahlen veraendern sich nicht. [...] Und ich habe
 * auch keine Buttons, um praktisch die wegzumachen." Gemessen: 1461 im
 * Abzeichen, davon 1320 aus AI Brain; „Alle eigenen als gelesen" nahm 1.
 *
 * Helfer heissen `grenze…`: Pest laedt alle Testdateien in einen Prozess.
 */
beforeEach(function (): void {
    Cache::flush();
    app(InboxArten::class)->vergessen();
    app(Quellenverzeichnis::class)->vergessen();
    app()->forgetInstance(GesamtInbox::class);
    app()->forgetInstance(Abhaken::class);
    app()->forgetInstance(Loeschen::class);

    $this->ich = User::create(['name' => 'Ich', 'email' => 'ich@beispiel.test']);
});

/** Eine eigene Meldungsquelle: abhakbar, loeschbar, zaehlbar. */
function grenzeEigene(string $art = 'notification', int $ungelesen = 1): object
{
    $quelle = new class($art, $ungelesen) implements AbhakbareQuelle, LoeschbareQuelle, ZaehlbareQuelle
    {
        public int $alleGelesen = 0;

        public int $alleGeloescht = 0;

        /** @var array<int, string> */
        public array $geloeschteIds = [];

        public function __construct(private string $art, private int $ungelesen) {}

        public function art(): string
        {
            return $this->art;
        }

        public function eintraege(Model $person, int $grenze): array
        {
            return $this->ungelesen > 0
                ? [new Eintrag(id: '1', art: $this->art, titel: 'eigene', ungelesen: true)]
                : [];
        }

        public function ungelesenGesamt(Model $person): int
        {
            return $this->ungelesen;
        }

        public function alsGelesen(Model $person, array $ids): int
        {
            return count($ids);
        }

        public function allesAlsGelesen(Model $person): int
        {
            $this->alleGelesen++;
            $n = $this->ungelesen;
            $this->ungelesen = 0;

            return $n;
        }

        public function loeschen(Model $person, array $ids): int
        {
            $this->geloeschteIds = array_merge($this->geloeschteIds, $ids);

            return count($ids);
        }

        public function allesLoeschen(Model $person): int
        {
            $this->alleGeloescht++;

            return 5;
        }
    };

    app(Quellenverzeichnis::class)->anmelden($quelle);

    return $quelle;
}

/**
 * AI Brain als Gegenseite: zeigt 855 ungelesene Meldungen, meldet sie
 * abhakbar und loeschbar, und merkt sich jeden Schreibaufruf.
 */
function grenzeHub(bool $schreibend = true, ?Throwable $wirft = null, array $loeschbar = ['notification']): object
{
    $basis = new class($wirft, $loeschbar)
    {
        /** @var array<int, array<string, mixed>> */
        public array $aufrufe = [];

        public int $ungelesen = 855;

        public int $abrufe = 0;

        public function __construct(public ?Throwable $wirft, public array $loeschbar) {}

        public function anbieter(): array
        {
            return [['slug' => 'ai-brain', 'key' => 'ai-brain', 'label' => 'AI Brain']];
        }

        public function holen(string $slug, string $adresse, int $grenze): array
        {
            $this->abrufe++;

            return [
                'items' => $this->ungelesen > 0
                    ? [['id' => 'n-1', 'kind' => 'notification', 'title' => 'drueben', 'is_unread' => true]]
                    : [],
                'kinds' => [['key' => 'notification']],
                'failed' => [],
                'totals' => ['notification' => $this->ungelesen],
                'markable' => ['notification'],
                'deletable' => $this->loeschbar,
            ];
        }

        public function alsGelesen(string $slug, string $adresse, string $art, array $ids): int
        {
            return $this->merken('einzeln', compact('slug', 'adresse', 'art', 'ids'), count($ids));
        }

        public function alleAlsGelesen(string $slug, string $adresse, ?string $art = null): int
        {
            $n = $this->ungelesen;

            return $this->merken('alle', compact('slug', 'adresse', 'art'), $n, fn () => $this->ungelesen = 0);
        }

        public function loeschen(string $slug, string $adresse, string $art, array $ids): int
        {
            return $this->merken('loeschen', compact('slug', 'adresse', 'art', 'ids'), count($ids));
        }

        public function allesLoeschen(string $slug, string $adresse, string $art): int
        {
            return $this->merken('alles-loeschen', compact('slug', 'adresse', 'art'), 863);
        }

        private function merken(string $was, array $daten, int $ergebnis, ?Closure $danach = null): int
        {
            $this->aufrufe[] = ['was' => $was] + $daten;

            if ($this->wirft !== null) {
                throw $this->wirft;
            }

            $danach?->__invoke();

            return $ergebnis;
        }
    };

    $huelle = $schreibend
        ? new class($basis) implements SchreibendeRemoteSource
        {
            public function __construct(public object $basis) {}

            public function anbieter(): array
            {
                return $this->basis->anbieter();
            }

            public function holen(string $slug, string $adresse, int $grenze): array
            {
                return $this->basis->holen($slug, $adresse, $grenze);
            }

            public function alsGelesen(string $slug, string $adresse, string $art, array $ids): int
            {
                return $this->basis->alsGelesen($slug, $adresse, $art, $ids);
            }

            public function alleAlsGelesen(string $slug, string $adresse, ?string $art = null): int
            {
                return $this->basis->alleAlsGelesen($slug, $adresse, $art);
            }

            public function loeschen(string $slug, string $adresse, string $art, array $ids): int
            {
                return $this->basis->loeschen($slug, $adresse, $art, $ids);
            }

            public function allesLoeschen(string $slug, string $adresse, string $art): int
            {
                return $this->basis->allesLoeschen($slug, $adresse, $art);
            }
        }
    : new class($basis) implements RemoteSource
    {
        public function __construct(public object $basis) {}

        public function anbieter(): array
        {
            return $this->basis->anbieter();
        }

        public function holen(string $slug, string $adresse, int $grenze): array
        {
            return $this->basis->holen($slug, $adresse, $grenze);
        }

        public function alsGelesen(string $slug, string $adresse, string $art, array $ids): int
        {
            return $this->basis->alsGelesen($slug, $adresse, $art, $ids);
        }
    };

    app()->instance(RemoteSource::class, $huelle);
    app()->forgetInstance(FremdeEintraege::class);
    app()->forgetInstance(GesamtInbox::class);
    app()->forgetInstance(Abhaken::class);
    app()->forgetInstance(Loeschen::class);

    return $basis;
}

it('hakt mit „alle" die eigenen UND die fremden ab, und das Abzeichen sinkt auf null', function (): void {
    $eigene = grenzeEigene(ungelesen: 1);
    $hub = grenzeHub();

    expect(app(GesamtInbox::class)->bestand($this->ich))->toBe(856);

    $ergebnis = app(Abhaken::class)->alle($this->ich);

    expect($ergebnis)->toBe(['marked' => 856, 'failed' => []])
        ->and($eigene->alleGelesen)->toBe(1)
        ->and($hub->aufrufe)->toHaveCount(1)
        ->and($hub->aufrufe[0])->toMatchArray(['was' => 'alle', 'slug' => 'ai-brain', 'adresse' => 'ich@beispiel.test', 'art' => null])
        // Der Puffer ist verworfen: die Zahl kommt sofort frisch, nicht
        // eine Minute lang die alte.
        ->and(app(GesamtInbox::class)->bestand($this->ich))->toBe(0);
});

it('laesst bei „alle" ein System aus, das nichts abhakbares meldet', function (): void {
    grenzeEigene();
    $hub = grenzeHub();
    $hub->ungelesen = 0;

    // Ein System, das zwar antwortet, aber nichts abhakbar meldet, bekommt
    // keinen Aufruf — hier ueber ein leeres `markable`.
    app()->instance(RemoteSource::class, new class implements SchreibendeRemoteSource
    {
        public int $alle = 0;

        public function anbieter(): array
        {
            return [['slug' => 'crm']];
        }

        public function holen(string $slug, string $adresse, int $grenze): array
        {
            return ['items' => [], 'kinds' => [], 'failed' => [], 'totals' => [], 'markable' => [], 'deletable' => []];
        }

        public function alsGelesen(string $slug, string $adresse, string $art, array $ids): int
        {
            return 0;
        }

        public function alleAlsGelesen(string $slug, string $adresse, ?string $art = null): int
        {
            $this->alle++;

            return 0;
        }

        public function loeschen(string $slug, string $adresse, string $art, array $ids): int
        {
            return 0;
        }

        public function allesLoeschen(string $slug, string $adresse, string $art): int
        {
            return 0;
        }
    });
    app()->forgetInstance(FremdeEintraege::class);
    app()->forgetInstance(GesamtInbox::class);
    app()->forgetInstance(Abhaken::class);

    app(Abhaken::class)->alle($this->ich);

    expect(app(RemoteSource::class)->alle)->toBe(0);
});

it('nennt ein System, das „alle" nicht annimmt, und erledigt die anderen trotzdem', function (): void {
    $eigene = grenzeEigene(ungelesen: 3);
    grenzeHub(wirft: new RuntimeException('Missing "kind"'));

    $ergebnis = app(Abhaken::class)->alle($this->ich);

    expect($ergebnis)->toBe(['marked' => 3, 'failed' => ['ai-brain']])
        ->and($eigene->alleGelesen)->toBe(1);
});

it('bleibt bei einer alten Gegenseite ohne Schreibvertrag bei den eigenen', function (): void {
    grenzeEigene(ungelesen: 2);
    $hub = grenzeHub(schreibend: false);

    expect(app(Abhaken::class)->alle($this->ich))->toBe(['marked' => 2, 'failed' => []])
        ->and($hub->aufrufe)->toBe([]);
});

it('verwirft den Puffer nach dem Abhaken EINER fremden Zeile', function (): void {
    grenzeEigene(ungelesen: 0);
    $hub = grenzeHub();

    app(GesamtInbox::class)->for($this->ich);
    expect($hub->abrufe)->toBe(1);

    app(Abhaken::class)->zeilen($this->ich, 'ai-brain', 'notification', ['n-1']);
    app(GesamtInbox::class)->for($this->ich);

    // Ohne das kam eine Minute lang die alte Fassung — die Zeile stand
    // wieder als ungelesen da.
    expect($hub->abrufe)->toBe(2);
});

it('meldet fremde loeschbare Arten je Herkunft', function (): void {
    grenzeEigene();
    grenzeHub();

    $daten = app(GesamtInbox::class)->for($this->ich);

    expect($daten['deletable'])->toBe([
        'local' => ['notification'],
        'sources' => ['ai-brain' => ['notification']],
    ]);
});

it('loescht eine fremde Zeile dort, wo sie liegt', function (): void {
    $eigene = grenzeEigene();
    $hub = grenzeHub();

    $n = app(Loeschen::class)->zeilen($this->ich, 'ai-brain', 'notification', ['n-1', '', 'n-2']);

    expect($n)->toBe(2)
        ->and($eigene->geloeschteIds)->toBe([])
        ->and($hub->aufrufe[0])->toMatchArray(['was' => 'loeschen', 'slug' => 'ai-brain', 'art' => 'notification', 'ids' => ['n-1', 'n-2']]);
});

it('loescht eine eigene Zeile weiter nur hier', function (): void {
    $eigene = grenzeEigene();
    $hub = grenzeHub();

    app(Loeschen::class)->zeilen($this->ich, null, 'notification', ['7']);

    expect($eigene->geloeschteIds)->toBe(['7'])
        ->and($hub->aufrufe)->toBe([]);
});

it('loescht „alle der Art" hier und in jedem System, das die Art loeschbar meldet', function (): void {
    $eigene = grenzeEigene();
    $hub = grenzeHub();

    expect(app(Loeschen::class)->alleDerArt($this->ich, 'notification'))->toBe(['deleted' => 868, 'failed' => []])
        ->and($eigene->alleGeloescht)->toBe(1)
        ->and($hub->aufrufe[0])->toMatchArray(['was' => 'alles-loeschen', 'art' => 'notification']);
});

it('fragt drueben nicht, wenn das System die Art nicht loeschbar meldet', function (): void {
    grenzeEigene();
    $hub = grenzeHub(loeschbar: []);

    app(Loeschen::class)->alleDerArt($this->ich, 'notification');

    expect($hub->aufrufe)->toBe([]);
});

it('wirft bei einer Art, die nirgends loeschbar ist', function (): void {
    grenzeEigene();
    grenzeHub(loeschbar: []);

    expect(fn () => app(Loeschen::class)->alleDerArt($this->ich, 'task'))->toThrow(RuntimeException::class);
});

it('loescht ueber die Route auch eine fremde Zeile', function (): void {
    grenzeEigene();
    $hub = grenzeHub();

    $this->actingAs($this->ich)
        ->deleteJson(route('inbox.loeschen'), ['source' => 'ai-brain', 'kind' => 'notification', 'ids' => ['n-1']])
        ->assertOk()
        ->assertJson(['deleted' => 1]);

    expect($hub->aufrufe[0]['was'])->toBe('loeschen');
});

it('hakt ueber die Route „alle" ab und nennt gescheiterte Systeme', function (): void {
    grenzeEigene(ungelesen: 4);
    grenzeHub(wirft: new RuntimeException('drueben nicht erreichbar'));

    $this->actingAs($this->ich)
        ->postJson(route('inbox.gelesen'), ['alle' => true])
        ->assertOk()
        ->assertExactJson(['marked' => 4, 'failed' => ['ai-brain']]);
});

it('liest „deleted" aus der Antwort und macht aus einer Antwort ohne Zahl einen Fehler', function (): void {
    expect(Auspacken::geloescht(['structuredContent' => ['deleted' => 12]]))->toBe(12)
        ->and(fn () => Auspacken::geloescht(['content' => [['type' => 'text', 'text' => 'ok']]]))
        ->toThrow(RuntimeException::class);
});

it('packt `deletable` aus einer fremden Antwort aus', function (): void {
    expect(Auspacken::antwort(['items' => [], 'deletable' => ['notification']])['deletable'])->toBe(['notification'])
        ->and(Auspacken::antwort(['items' => []])['deletable'])->toBe([]);
});
