<?php

namespace Peppermint\Inbox\Tests\Support;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';

    protected $guarded = [];
}
