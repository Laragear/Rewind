<?php

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Laragear\Rewind\HasRewind;

class TestModel extends Model
{
    use HasRewind;

    protected $fillable = ['title', 'invites', 'starts_at', 'ends_at'];
}
