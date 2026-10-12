<?php

namespace FilaHQ\Statify\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $guarded = [];

    public $timestamps = false;
}
