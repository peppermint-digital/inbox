<?php

namespace Peppermint\Inbox\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Base;
use Peppermint\Inbox\InboxServiceProvider;

abstract class TestCase extends Base
{
    protected function getPackageProviders($app): array
    {
        return [InboxServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('inbox.user_model', Support\User::class);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        // Das Paket bringt keine eigene Tabelle mit — es liest, was die
        // Quellen der Produkte liefern. Fuer die Tests reicht eine
        // Personentabelle.
        Schema::create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->timestamps();
        });
    }
}
