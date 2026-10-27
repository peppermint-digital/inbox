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
 *
 * ## Beim SCHREIBEN gilt das Gegenteil
 *
 * Ein Ausfall beim Lesen kostet Zeilen, und die Ansicht sagt es. Ein
 * Ausfall beim Abhaken darf nicht abgefangen werden: Wer abhakt und es
 * kommt nicht an, muss das sehen — sonst steht die Zeile beim Neuladen
 * wieder da, und der Fehler sieht aus wie der Fix.
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
     * `totals` ist der BESTAND je Art, `markable` die Arten, an denen ein
     * Haken ein Ziel findet. Beides gehoert hierher und nicht in die
     * Oberflaeche: Nur die Quelle weiss, wie viele sie hat und ob sie
     * Abhaken kann.
     *
     * @return array{items: array<int, array<string, mixed>>, kinds: array<int, string>, failed: array<int, string>, totals: array<string, int>, markable: array<int, string>}
     */
    public function sammeln(Model $person, int $grenze): array
    {
        $zeilen = [];
        $arten = [];
        $gescheitert = [];
        $bestand = [];
        $abhakbar = [];

        foreach ($this->alle() as $quelle) {
            $art = $quelle->art();
            $arten[] = $art;

            if ($quelle instanceof AbhakbareQuelle) {
                $abhakbar[] = $art;
            }

            try {
                $gezeigt = 0;

                foreach ($quelle->eintraege($person, $grenze) as $eintrag) {
                    $zeilen[] = $eintrag->toArray();

                    if ($eintrag->ungelesen) {
                        $gezeigt++;
                    }
                }

                // Nur wer MEHR hat als er zeigt, muss es sagen. Fuer alle
                // anderen ist das Gezeigte der Bestand — eine Zahl, die
                // nie zu hoch ist und damit nie etwas verspricht, was die
                // Liste nicht halten kann.
                $bestand[$art] = ($bestand[$art] ?? 0) + ($quelle instanceof ZaehlbareQuelle
                    ? $quelle->ungelesenGesamt($person)
                    : $gezeigt);
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
            'totals' => $bestand,
            'markable' => array_values(array_unique($abhakbar)),
        ];
    }

    /**
     * Diese Zeilen dieser Person auf gelesen setzen.
     *
     * Die Art kommt MIT und wird nicht geraten: Kennungen sind nur
     * innerhalb einer Art eindeutig. Wer alle Quellen mit derselben Liste
     * fragt, trifft womoeglich die Meldung 42 UND die Aufgabe 42 — und
     * hakt etwas ab, das niemand angesehen hat.
     *
     * @param  array<int, string>  $ids
     * @return int Wie viele Zeilen sich tatsaechlich geaendert haben.
     *
     * @throws \RuntimeException wenn diese Art hier nicht abhakbar ist.
     */
    public function alsGelesen(Model $person, string $art, array $ids): int
    {
        $quellen = $this->abhakbare($art);

        if ($quellen === []) {
            // Bewusst eine Ausnahme und keine Null: „keine Zeile geaendert"
            // und „diese Art kann das hier gar nicht" sehen in der
            // Oberflaeche gleich aus, und das Zweite ist ein Fehler im
            // Verbund, den jemand sehen soll.
            throw new \RuntimeException("Die Art '{$art}' kann hier nicht abgehakt werden.");
        }

        $geaendert = 0;

        foreach ($quellen as $quelle) {
            $geaendert += $quelle->alsGelesen($person, $ids);
        }

        return $geaendert;
    }

    /**
     * Alles, was diese Person in diesem System ungelesen hat.
     *
     * Nur die EIGENEN: Was in einem anderen System liegt, kann dieses hier
     * weder zeigen noch abraeumen. Ein Knopf, der „alle" verspricht und
     * einen Teil erledigt, ist schlimmer als einer, der sagt, wie viele er
     * nimmt — deshalb nennt die Oberflaeche die Zahl daneben.
     *
     * @return int Wie viele Zeilen sich tatsaechlich geaendert haben.
     */
    public function allesAlsGelesen(Model $person): int
    {
        $geaendert = 0;

        foreach ($this->alle() as $quelle) {
            if ($quelle instanceof AbhakbareQuelle) {
                $geaendert += $quelle->allesAlsGelesen($person);
            }
        }

        return $geaendert;
    }

    /**
     * Die abhakbaren Quellen einer Art.
     *
     * Mehrere sind erlaubt: Zwei Quellen duerfen dieselbe Art fuellen (so
     * steht es schon im Vertrag von {@see InboxQuelle}), und dann gehoert
     * die Kennung in genau eine von beiden. Welche, weiss keiner von aussen
     * — also werden beide gefragt, und jede filtert selbst.
     *
     * @return array<int, AbhakbareQuelle>
     */
    private function abhakbare(string $art): array
    {
        return array_values(array_filter(
            $this->alle(),
            fn (InboxQuelle $quelle): bool => $quelle instanceof AbhakbareQuelle && $quelle->art() === $art,
        ));
    }

    /** Fuer Tests. */
    public function vergessen(): void
    {
        $this->quellen = [];
    }
}
