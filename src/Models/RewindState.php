<?php

namespace Laragear\Rewind\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Schema\Blueprint;
use Laragear\MetaModel\CustomMigration;
use Laragear\MetaModel\HasCustomization;
use Laragear\Rewind\Migrations\RewindStateMigration;

/**
 * @property-read array $data
 * @property-read bool $is_kept
 */
class RewindState extends Model
{
    use HasCustomization;

    /**
     * The name of the "updated at" column.
     *
     * @var string|null
     */
    public const UPDATED_AT = null;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'data' => 'array',
        'is_kept' => 'boolean'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['data', 'is_kept'];

    /**
     * Return the rewindable relation.
     */
    public function rewindable(): MorphTo
    {
        return $this->morphTo('rewindable');
    }

    /**
     * Returns the underlying rewindable model instance from this state.
     */
    public function instanceRewindable(): Model
    {
        $model = $this->rewindable()->createModelByType(
            $this->getAttribute($this->rewindable()->getMorphType())
        );

        $model->setAttributesFromRewindState($this->getAttribute('data')); // @phpstan-ignore-line

        return $model;
    }

    /**
     * @inheritDoc
     */
    public static function migration(): CustomMigration
    {
        $createdAt = static::CREATED_AT;

        return new CustomMigration(new static, function (Blueprint $table) use ($createdAt) { // @phpstan-ignore-line
            $table->id();

            $this->createMorph($table, 'rewindable'); // @phpstan-ignore-line

            $table->json('data');
            $table->boolean('is_kept')->default(false);

            $table->timestamp($createdAt);
        });
    }
}
