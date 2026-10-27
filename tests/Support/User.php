<?php

namespace Peppermint\Inbox\Tests\Support;

use Illuminate\Foundation\Auth\User as Authentifizierbar;

/**
 * Die Testperson.
 *
 * Erbt von `Foundation\Auth\User` und nicht von `Model`, damit
 * `actingAs()` sie annimmt — der Endpunkt fuers Abhaken haengt hinter
 * `auth`, und ohne anmeldbare Person liesse sich genau das nicht pruefen.
 */
class User extends Authentifizierbar
{
    protected $table = 'users';

    protected $guarded = [];
}
