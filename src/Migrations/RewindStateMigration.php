<?php

namespace Laragear\Rewind\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Laragear\MetaModel\CustomizableMigration;
use Laragear\Rewind\Models\RewindState;

class RewindStateMigration extends CustomizableMigration
{
    /**
     * @inheritDoc
     */
    public function create(Blueprint $table): void
    {
        $table->id();

        $this->createMorph($table, 'rewindable');

        $table->json('data');
        $table->boolean('is_kept')->default(false);

        $table->timestamp(RewindState::CREATED_AT);
    }
}
