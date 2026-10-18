<?php

namespace Peppermint\Inbox;

use Illuminate\Support\ServiceProvider;
use Peppermint\Inbox\Arten\InboxArten;
use Peppermint\Inbox\Feed\BridgeRemoteSource;
use Peppermint\Inbox\Feed\FremdeEintraege;
use Peppermint\Inbox\Feed\GesamtInbox;
use Peppermint\Inbox\Feed\RemoteSource;
use Peppermint\Inbox\Quellen\Quellenverzeichnis;

/**
 * Die Inbox anmelden.
 *
 * ## Was hier NICHT passiert
 *
 * Es wird keine einzige Art und keine einzige Quelle registriert. Das
 * Paket weiss nicht, was eine Aufgabe ist und was eine Meldung — das
 * entscheidet jedes Produkt in seinem eigenen Dienstanbieter, und genau
 * darin liegt die Erweiterbarkeit.
 *
 * Ein Produkt, das dieses Paket installiert und nichts anmeldet, hat eine
 * leere Inbox. Das ist der richtige Ausgangszustand.
 */
class InboxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/inbox.php', 'inbox');

        $this->app->singleton(InboxArten::class);
        $this->app->singleton(Quellenverzeichnis::class);
        $this->app->singleton(FremdeEintraege::class);
        $this->app->singleton(GesamtInbox::class);

        // Woher die fremden Eintraege kommen, entscheidet das aufnehmende
        // System: Ein Produkt fragt ueber die Bruecke, AI Brain ueber
        // seinen eigenen Gateway. Wer es anders braucht, bindet eine eigene
        // Umsetzung — diese hier ist nur die Voreinstellung.
        $this->app->bindIf(RemoteSource::class, BridgeRemoteSource::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/inbox.php' => config_path('inbox.php'),
        ], 'inbox-config');
    }
}
