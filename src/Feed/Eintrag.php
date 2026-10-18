<?php

namespace Peppermint\Inbox\Feed;

/**
 * Eine Zeile in der Inbox — in der Form, die ueber die Leitung geht.
 *
 * ## Warum ein Wert-Objekt und nicht einfach ein Array
 *
 * Weil diese Form ein Vertrag zwischen vier Systemen ist. Ein Produkt, das
 * `titel` statt `title` schickt, faellt sonst erst in der Oberflaeche des
 * ANDEREN Systems auf — und dort sieht es aus wie eine leere Zeile, nicht
 * wie ein Tippfehler.
 *
 * ## `meta` ist der Platz fuer alles Produkteigene
 *
 * Projektname, Faelligkeit, wer es ausgeloest hat: Das interessiert die
 * Liste nicht, aber den Leser schon. Es reist als freies Feld mit und wird
 * unter der Zeile angezeigt, ohne dass dieses Paket wissen muss, was drin
 * steht.
 */
final readonly class Eintrag
{
    public function __construct(
        public string $id,
        public string $art,
        public string $titel,
        public ?string $text = null,
        public ?string $link = null,
        public string $symbol = 'inbox',
        public string $farbe = 'gray',
        public ?string $zeitpunkt = null,
        public bool $ungelesen = true,
        /** @var array<string, mixed> */
        public array $meta = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->art,
            'title' => $this->titel,
            'message' => $this->text,
            'link' => $this->link,
            'icon' => $this->symbol,
            'color' => $this->farbe,
            'created_at' => $this->zeitpunkt,
            'is_unread' => $this->ungelesen,
            'meta' => $this->meta === [] ? null : $this->meta,
        ];
    }

    /**
     * Aus einer Zeile, wie sie ueber die Leitung kam.
     *
     * Gutmuetig: Fehlt etwas, steht ein Ersatz da. Eine Zeile wegzuwerfen,
     * weil ein Symbol fehlt, waere der schlechtere Tausch — die Sache ist
     * trotzdem passiert.
     *
     * @param  array<string, mixed>  $zeile
     */
    public static function ausZeile(array $zeile): ?self
    {
        $titel = trim((string) ($zeile['title'] ?? ''));
        $art = trim((string) ($zeile['kind'] ?? ''));

        if ($titel === '' || $art === '') {
            // Ohne Art weiss niemand, unter welchen Reiter die Zeile
            // gehoert; ohne Titel steht da nichts. Beides ist nicht
            // reparierbar, alles andere schon.
            return null;
        }

        return new self(
            id: (string) ($zeile['id'] ?? ''),
            art: $art,
            titel: $titel,
            text: isset($zeile['message']) ? (string) $zeile['message'] : null,
            link: isset($zeile['link']) ? (string) $zeile['link'] : null,
            symbol: (string) ($zeile['icon'] ?? 'inbox'),
            farbe: (string) ($zeile['color'] ?? 'gray'),
            zeitpunkt: isset($zeile['created_at']) ? (string) $zeile['created_at'] : null,
            ungelesen: (bool) ($zeile['is_unread'] ?? true),
            meta: is_array($zeile['meta'] ?? null) ? $zeile['meta'] : [],
        );
    }
}
