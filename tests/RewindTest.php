<?php

namespace Tests;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laragear\Rewind\Events\StateCreated;
use Laragear\Rewind\Events\StateDeleted;
use Laragear\Rewind\Events\StateRetrieved;
use Laragear\Rewind\Events\StatesCleared;
use Laragear\Rewind\Events\StatesPruned;
use Laragear\Rewind\Models\RewindState;
use Tests\Fixtures\TestModel;
use function now;

class RewindTest extends TestCase
{
    use RefreshDatabase;

    protected TestModel $model;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->app
            ->make(SchemaBuilder::class)
            ->create('test_models', static function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->json('invites')->default('"[]"');
                $table->string('private_notes')->default('');
                $table->timestamp('starts_at');
                $table->timestamp('ends_at')->nullable();
                $table->timestamps();
            });

        $this->model = TestModel::create([
            'title' => 'test_title',
            'starts_at' => now()
        ]);
    }

    public function test_to(): void
    {
        RewindState::query()->whereKey(1)->update([
            'data->title' => 'first_title'
        ]);

        $this->model->rewind()->to(1);

        static::assertSame('first_title', $this->model->title);
    }

    public function test_to_with_attributes(): void
    {
        RewindState::query()->whereKey(1)->update([
            'data->title' => 'first_title',
            'data->foo' => 'bar'
        ]);

        $this->model->rewind()->to(1, 'foo');

        static::assertSame('bar', $this->model->foo);
    }

    public function test_to_fails_if_state_not_found(): void
    {
        $rewind = $this->model->rewind();

        $this->expectException(ModelNotFoundException::class);

        $rewind->to(2);
    }

    public function test_to_latest(): void
    {
        RewindState::forceCreate([
            'rewindable_type' => TestModel::class,
            'rewindable_id' => 1,
            'data' => [
                'title' => 'latest_title'
            ]
        ]);

        $this->model->rewind()->toLatest();

        static::assertSame('latest_title', $this->model->title);
    }

    public function test_to_latest_with_attributes(): void
    {
        RewindState::forceCreate([
            'rewindable_type' => TestModel::class,
            'rewindable_id' => 1,
            'data' => [
                'title' => 'latest_title',
                'foo' => 'bar'
            ]
        ]);

        $this->model->rewind()->toLatest('foo');

        static::assertSame('bar', $this->model->foo);
    }

    public function test_to_latest_fails_if_state_not_found(): void
    {
        RewindState::query()->delete();

        $rewind = $this->model->rewind();

        $this->expectException(ModelNotFoundException::class);

        $rewind->toLatest();
    }

    public function test_to_oldest(): void
    {
        RewindState::forceCreate([
            'rewindable_type' => TestModel::class,
            'rewindable_id' => 1,
            'data' => [
                'title' => 'latest_title'
            ]
        ]);

        RewindState::query()->whereKey(1)->update([
            'data->title' => 'first_title'
        ]);

        $this->model->rewind()->toOldest();

        static::assertSame('first_title', $this->model->title);
    }

    public function test_to_oldest_with_attributes(): void
    {
        RewindState::forceCreate([
            'rewindable_type' => TestModel::class,
            'rewindable_id' => 1,
            'data' => [
                'title' => 'latest_title',
                'foo' => 'bar'
            ]
        ]);

        RewindState::query()->whereKey(1)->update([
            'data->title' => 'first_title',
            'data->baz' => 'qux'
        ]);

        $this->model->rewind()->toOldest('baz');

        static::assertSame('qux', $this->model->baz);
    }

    public function test_to_oldest_fails_if_state_not_found(): void
    {
        RewindState::query()->delete();

        $rewind = $this->model->rewind();

        $this->expectException(ModelNotFoundException::class);

        $rewind->toOldest();
    }

    public function test_find(): void
    {
        $event = Event::fake(StateRetrieved::class);

        RewindState::query()->whereKey(1)->update([
            'data->title' => 'first_title',
            'data->baz' => 'qux'
        ]);

        $model = $this->model->rewind()->find(1);

        static::assertSame('qux', $model->baz);

        $event->assertDispatched(StateRetrieved::class, function (StateRetrieved $event) use ($model): bool {
            return $event->model->is($model)
                && $event->state->is($model)
                && $event->state->title === 'first_title';
        });
    }

    public function test_find_fails_if_id_doesnt_exists(): void
    {
        $rewind = $this->model->rewind();

        $this->expectException(ModelNotFoundException::class);

        $rewind->find(2);
    }

    public function test_counts_rewind_states(): void
    {
        RewindState::forceCreate([
            'rewindable_type' => TestModel::class,
            'rewindable_id' => 1,
            'data' => [
                'title' => 'latest_title',
                'foo' => 'bar'
            ]
        ]);

        RewindState::query()->whereKey(1)->update([
            'data->title' => 'first_title',
            'data->baz' => 'qux'
        ]);

        static::assertSame(2, $this->model->rewind()->count());
    }

    public function test_exists(): void
    {
        static::assertTrue($this->model->rewind()->exists());

        RewindState::query()->delete();

        static::assertFalse($this->model->rewind()->exists());
    }

    public function test_missing(): void
    {
        static::assertFalse($this->model->rewind()->missing());

        RewindState::query()->delete();

        static::assertTrue($this->model->rewind()->missing());
    }

    public function test_all(): void
    {
        RewindState::forceCreate([
            'rewindable_type' => TestModel::class,
            'rewindable_id' => 1,
            'data' => [
                'title' => 'latest_title',
                'foo' => 'bar'
            ]
        ]);

        RewindState::query()->whereKey(1)->update([
            'data->title' => 'first_title',
            'data->baz' => 'qux'
        ]);

        $all = $this->model->rewind()->all();

        static::assertCount(2, $all);
        static::assertSame('qux', $all->find(1)->baz);
        static::assertSame('bar', $all->find(0)->foo);
    }

    public function test_delete(): void
    {
        $event = Event::fake();

        $this->model->rewind()->delete(1);

        $this->assertDatabaseEmpty(RewindState::class);

        $event->assertDispatched(StateDeleted::class, function (StateDeleted $event): bool {
            return $event->model->is($this->model)
                && $event->stateId === 1;
        });
    }

    public function test_delete_latest(): void
    {
        RewindState::forceCreate([
            'rewindable_type' => TestModel::class,
            'rewindable_id' => 1,
            'data' => [
                'title' => 'latest_title',
                'foo' => 'bar'
            ]
        ]);

        $event = Event::fake();

        $this->model->rewind()->deleteLatest();

        $this->assertDatabaseHas(RewindState::class, ['id' => 1]);
        $this->assertDatabaseMissing(RewindState::class, ['id' => 2]);

        $event->assertDispatched(StateDeleted::class, function (StateDeleted $event): bool {
            return $event->model->is($this->model)
                && $event->stateId === 2;
        });
    }

    public function test_delete_latest_doesnt_delete_kept(): void
    {
        RewindState::forceCreate([
            'rewindable_type' => TestModel::class,
            'rewindable_id' => 1,
            'data' => [
                'title' => 'latest_title',
                'foo' => 'bar'
            ],
            'is_kept' => true
        ]);

        $event = Event::fake();

        $this->model->rewind()->deleteLatest();

        $this->assertDatabaseHas(RewindState::class, ['id' => 1]);
        $this->assertDatabaseHas(RewindState::class, ['id' => 2]);

        $event->assertNotDispatched(StateDeleted::class);
    }

    public function test_delete_latest_deletes_kept(): void
    {
        RewindState::forceCreate([
            'rewindable_type' => TestModel::class,
            'rewindable_id' => 1,
            'data' => [
                'title' => 'latest_title',
                'foo' => 'bar'
            ],
            'is_kept' => true
        ]);

        $event = Event::fake();

        $this->model->rewind()->deleteLatest(true);

        $this->assertDatabaseHas(RewindState::class, ['id' => 1]);
        $this->assertDatabaseMissing(RewindState::class, ['id' => 2]);

        $event->assertDispatched(StateDeleted::class, function (StateDeleted $event): bool {
            return $event->model->is($this->model)
                && $event->stateId === 2;
        });
    }

    public function test_delete_oldest(): void
    {
        RewindState::forceCreate([
            'rewindable_type' => TestModel::class,
            'rewindable_id' => 1,
            'data' => [
                'title' => 'latest_title',
            ]
        ]);

        $event = Event::fake();

        $this->model->rewind()->deleteOldest();

        $this->assertDatabaseMissing(RewindState::class, ['id' => 1]);
        $this->assertDatabaseHas(RewindState::class, ['id' => 2]);

        $event->assertDispatched(StateDeleted::class, function (StateDeleted $event): bool {
            return $event->model->is($this->model)
                && $event->stateId === 1;
        });
    }

    public function test_delete_oldest_doesnt_delete_kept(): void
    {
        RewindState::forceCreate([
            'rewindable_type' => TestModel::class,
            'rewindable_id' => 1,
            'data' => [
                'title' => 'latest_title',
            ],
        ]);

        RewindState::query()->whereKey(1)->update(['is_kept' => true]);

        $event = Event::fake();

        $this->model->rewind()->deleteOldest();

        $this->assertDatabaseHas(RewindState::class, ['id' => 1]);
        $this->assertDatabaseHas(RewindState::class, ['id' => 2]);

        $event->assertNotDispatched(StateDeleted::class);
    }

    public function test_delete_oldest_deletes_kept(): void
    {
        RewindState::forceCreate([
            'rewindable_type' => TestModel::class,
            'rewindable_id' => 1,
            'data' => [
                'title' => 'latest_title',
            ],
        ]);

        RewindState::query()->whereKey(1)->update(['is_kept' => true]);

        $event = Event::fake();

        $this->model->rewind()->deleteOldest(true);

        $this->assertDatabaseMissing(RewindState::class, ['id' => 1]);
        $this->assertDatabaseHas(RewindState::class, ['id' => 2]);

        $event->assertDispatched(StateDeleted::class, function (StateDeleted $event): bool {
            return $event->model->is($this->model)
                && $event->stateId === 1;
        });
    }

    public function test_clears(): void
    {
        RewindState::forceCreate([
            'rewindable_type' => TestModel::class,
            'rewindable_id' => 1,
            'data' => [
                'title' => 'latest_title',
            ],
        ]);

        $event = Event::fake();

        $this->model->rewind()->clear();

        $this->assertDatabaseEmpty(RewindState::class);

        $event->assertDispatched(StatesCleared::class, function (StatesCleared $event): bool {
            return $event->model->is($this->model)
                && $event->includesKept === false;
        });
    }

    public function test_clears_doesnt_delete_kept_state(): void
    {
        RewindState::forceCreate([
            'rewindable_type' => TestModel::class,
            'rewindable_id' => 1,
            'data' => [
                'title' => 'latest_title',
            ],
        ]);

        RewindState::query()->whereKey(1)->update(['is_kept' => true]);

        $event = Event::fake();

        $this->model->rewind()->clear();

        $this->assertDatabaseHas(RewindState::class, ['id' => 1]);
        $this->assertDatabaseMissing(RewindState::class, ['id' => 2]);

        $event->assertDispatched(StatesCleared::class, function (StatesCleared $event): bool {
            return $event->model->is($this->model)
                && $event->includesKept === false;
        });
    }

    public function test_clears_deletes_kept_states(): void
    {
        RewindState::forceCreate([
            'rewindable_type' => TestModel::class,
            'rewindable_id' => 1,
            'data' => [
                'title' => 'latest_title',
            ],
        ]);

        RewindState::query()->whereKey(1)->update(['is_kept' => true]);

        $event = Event::fake();

        $this->model->rewind()->clear(true);

        $this->assertDatabaseEmpty(RewindState::class);

        $event->assertDispatched(StatesCleared::class, function (StatesCleared $event): bool {
            return $event->model->is($this->model)
                && $event->includesKept === true;
        });
    }

    public function test_force_clear_deletes_kept_states(): void
    {
        RewindState::forceCreate([
            'rewindable_type' => TestModel::class,
            'rewindable_id' => 1,
            'data' => [
                'title' => 'latest_title',
            ],
        ]);

        RewindState::query()->whereKey(1)->update(['is_kept' => true]);

        $event = Event::fake();

        $this->model->rewind()->forceClear();

        $this->assertDatabaseEmpty(RewindState::class);

        $event->assertDispatched(StatesCleared::class, function (StatesCleared $event): bool {
            return $event->model->is($this->model)
                && $event->includesKept === true;
        });
    }

    public function test_creates_state(): void
    {
        $this->model->fill([
            'title' => 'manual_state'
        ]);

        $event = Event::fake();

        $model = $this->model->rewind()->create();

        static::assertSame('manual_state', $model->title);

        $this->assertDatabaseHas(RewindState::class, [
            'id' => 2,
            'data->title' => 'manual_state'
        ]);

        $event->assertDispatched(StateCreated::class, function (StateCreated $event): bool {
            return $event->model->is($this->model)
                && $event->state->data['title'] === 'manual_state';
        });

        $event->assertDispatched(StatesPruned::class, function (StatesPruned $event): bool {
            return $event->model->is($this->model)
                && $event->includesKept === false;
        });
    }

    public function test_creates_state_as_kept(): void
    {
        $this->model->fill([
            'title' => 'manual_state'
        ]);

        $event = Event::fake();

        $model = $this->model->rewind()->create(true);

        static::assertSame('manual_state', $model->title);

        $this->assertDatabaseHas(RewindState::class, [
            'id' => 2,
            'data->title' => 'manual_state',
            'is_kept' => true,
        ]);

        $event->assertDispatched(StateCreated::class, function (StateCreated $event): bool {
            return $event->model->is($this->model)
                && $event->state->data['title'] === 'manual_state';
        });

        $event->assertDispatched(StatesPruned::class, function (StatesPruned $event): bool {
            return $event->model->is($this->model)
                && $event->includesKept === false;
        });
    }

    public function test_creates_state_without_pruning(): void
    {
        $this->model->fill([
            'title' => 'manual_state'
        ]);


        $event = Event::fake();

        $model = $this->model->rewind()->create(false, false);

        static::assertSame('manual_state', $model->title);

        $this->assertDatabaseHas(RewindState::class, [
            'id' => 2,
            'data->title' => 'manual_state',
            'is_kept' => false,
        ]);

        $event->assertDispatched(StateCreated::class, function (StateCreated $event): bool {
            return $event->model->is($this->model)
                && $event->state->data['title'] === 'manual_state';
        });

        $event->assertNotDispatched(StatesPruned::class);
    }

    public function test_creates_state_pruning_kept_states(): void
    {
        $this->model->fill([
            'title' => 'manual_state'
        ]);

        RewindState::query()->whereKey(1)->update(['is_kept' => true]);

        for ($i = 0; $i < 10; ++$i) {
            RewindState::forceCreate([
                'rewindable_type' => TestModel::class,
                'rewindable_id' => 1,
                'data' => [
                    'title' => 'latest_title',
                ],
            ]);
        }

        $event = Event::fake();

        $model = $this->model->rewind()->create(false, true, true);

        static::assertSame('manual_state', $model->title);

        $this->assertDatabaseHas(RewindState::class, [
            'id' => 12,
            'data->title' => 'manual_state',
            'is_kept' => false,
        ]);

        $this->assertDatabaseMissing(RewindState::class, ['id' => 1]);

        $event->assertDispatched(StateCreated::class, function (StateCreated $event): bool {
            return $event->model->is($this->model)
                && $event->state->data['title'] === 'manual_state';
        });

        $event->assertDispatched(StatesPruned::class, function (StatesPruned $event): bool {
            return $event->model->is($this->model)
                && $event->includesKept === true;
        });
    }

}
