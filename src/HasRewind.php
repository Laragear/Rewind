<?php

namespace Laragear\Rewind;

use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 * @mixin \Illuminate\Database\Eloquent\Model
 *
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 */
trait HasRewind
{
    /**
     * Boot the current trait.
     */
    protected static function bootHasRewind(): void
    {
        static::created(
        /** @var \Illuminate\Database\Eloquent\Model&\Laragear\Rewind\HasRewind $model */
            static function (Model $model): void {
                if ($model->shouldCreateRewindStateOnCreated()) {
                    // Call push with no pruning, since there is nothing to prune.
                    $model->rewind()->create($model->shouldKeepFirstRewindState(), false);
                }
            }
        );

        static::updated(
        /** @var \Illuminate\Database\Eloquent\Model&\Laragear\Rewind\HasRewind $model */
            static function (Model $model): void {
                if ($model->shouldCreateRewindStateOnUpdated()) {
                    $model->rewind()->create(false, $model->shouldPruneOldRewindStatesOnUpdated());
                }
            }
        );
    }

    /**
     * Execute a callback without pushing rewindable states.
     */
    public static function withoutCreatingStates(callable $closure): mixed
    {
        Rewind::$enabled = false;

        // This will always roll back the static property if the closure fails or succeeds.
        try {
            return $closure();
        } finally {
            Rewind::$enabled = true;
        }
    }

    /**
     * Create a new rewind instance.
     *
     * @return \Laragear\Rewind\Rewind<TModel>
     */
    public function rewind(): Rewind
    {
        return new Rewind($this);
    }

    /**
     * Determines the limit of rewind states for this model.
     *
     * @return \DateTimeInterface|int|array{0: int, 1: \DateTimeInterface}|null|false
     */
    public function rewindLimit(): DateTimeInterface|int|array|null|false
    {
        return 10;
    }

    /**
     * Determines if each time the model is created a new state should be pushed.
     */
    public function shouldCreateRewindStateOnCreated(): bool
    {
        return true;
    }

    /**
     * Determines if each time the model is updated a new state should be pushed.
     */
    public function shouldCreateRewindStateOnUpdated(): bool
    {
        return true;
    }

    /**
     * Determine it should prune old states when updating the model.
     */
    public function shouldPruneOldRewindStatesOnUpdated(): bool
    {
        return true;
    }

    /**
     * If it should save the first state and keep it forever.
     */
    public function shouldKeepFirstRewindState(): bool
    {
        return false;
    }

    /**
     * Get the attributes that should be persisted into each state.
     */
    public function getAttributesForRewindState(): Arrayable|array
    {
        // Return all the current raw attributes for the model.
        return $this->attributes;
    }

    /**
     * Sets the incoming raw attributes from the state to restore in this model instance.
     */
    public function setAttributesFromRewindState(array $attributes): void
    {
        // Here you can make any arrangement to the incoming raw attributes.
        $this->setRawAttributes($attributes);
    }
}
