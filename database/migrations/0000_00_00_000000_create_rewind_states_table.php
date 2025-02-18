<?php

use Illuminate\Database\Schema\Blueprint;
use Laragear\Rewind\Models\RewindState;

return RewindState::migration()->with(function (Blueprint $table) {
    // Add here your custom columns
    //
    // $table->boolean('is_cool');
    //
});
