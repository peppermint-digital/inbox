<?php

namespace Peppermint\Inbox\Quellen;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Welche Quellen dieses System angemeldet hat.
 *
 * ## Eine Quelle, die faellt, nimmt die Liste nicht mit
 *
 * Jede wird einzeln gefragt und einzeln abgesichert. Eine Aufgabentabelle,
 * die gerade in einer Wanderung steckt, darf nicht dazu fuehren, dass auch
 * die Meldungen verschwinden — das waere genau die Art Ausfall, die wie
 * „es ist nichts passiert" aussieht.
 *
 * Was ausfiel, steht im Ergebnis: Die Oberflaeche sagt es, statt eine
 * verkuerzte Liste als vollstaendig auszugeben.
 */
class Quellenverzeichnis
{
    /** @var array<int, class-string<InboxQuelle>|InboxQuelle> */
    private array $quellen = [];

    public function __construct(private readonly Container $container) {}

    /** @param class-string<InboxQuelle>|InboxQuelle $quelle */
    public function anmelden(string|InboxQuelle $quelle): self
    {
        $this->quellen[] = $quelle;

        return $this;
    }

    /** @param array<int, class-string<InboxQuelle>|InboxQuelle> $quellen */
    public function anmeldenViele(array $quellen): self
    {
        foreach ($quellen as $quelle) {
            $this->anmelden($quelle);
        }

        return $this;
    }

    /** @return array<int, InboxQuelle> */
    public function alle(): array
    {
        return array_map(
            fn (string|InboxQuelle $q): InboxQuelle => is_string($q) ? $this->container->make($q) : $q,
            $this->quellen,
        );
    }

    /**
     * Alle eigenen Eintraege, je Quelle gekappt.
     *
     * @return array{items: array<int, array<string, mixed>>, kinds: array<int, string>, failed: array<int, string>}
     */
    public function sammeln(Model $person, int $grenze): array
    {
        $zeilen = [];
        $arten = [];
        $gescheitert = [];

        foreach ($this->alle() as $quelle) {
            $art = $quelle->art();
            $arten[] = $art;

            try {
                foreach ($quelle->eintraege($person, $grenze) as $eintrag) {
                    $zeilen[] = $eintrag->toArray();
                }
            } catch (\Throwable $fehler) {
                $gescheitert[] = $art;

                Log::warning('Inbox-Quelle hat nicht geantwortet.', [
                    'kind' => $art,
                    'source' => $quelle::class,
                    'error' => $fehler->getMessage(),
                ]);
            }
        }

        return [
            'items' => $zeilen,
            'kinds' => array_values(array_unique($arten)),
            'failed' => $gescheitert,
        ];
    }

    /** Fuer Tests. */
    public function vergessen(): void
    {
        $this->quellen = [];
    }
}
