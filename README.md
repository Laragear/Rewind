# Rewind

[![Latest Version on Packagist](https://img.shields.io/packagist/v/laragear/rewind.svg)](https://packagist.org/packages/laragear/webauthn)
[![Latest stable test run](https://github.com/Laragear/Rewind/actions/workflows/php.yml/badge.svg?branch=1.x)](https://github.com/Laragear/Rewind/actions/workflows/php.yml)
[![Codecov coverage](https://codecov.io/gh/Laragear/Rewind/branch/1.x/graph/badge.svg?token=kgZpKLKR1j)](https://codecov.io/gh/Laragear/Rewind)
[![CodeClimate Maintainability](https://api.codeclimate.com/v1/badges/76106373d3eb60101bf6/maintainability)](https://codeclimate.com/github/Laragear/Rewind/maintainability)
[![Sonarcloud Status](https://sonarcloud.io/api/project_badges/measure?project=Laragear_Rewind&metric=alert_status)](https://sonarcloud.io/dashboard?id=Laragear_Rewind)
[![Laravel Octane Compatibility](https://img.shields.io/badge/Laravel%20Octane-Compatible-success?style=flat&logo=laravel)](https://laravel.com/docs/11.x/octane#introduction)


Travel back in time to see past model states, and restore them in one line.

```php
use App\Models\Article;

$article = Article::find(1);

$article->rewind()->toLatest();
```

## Become a sponsor

[![](.github/assets/support.png)](https://github.com/sponsors/DarkGhostHunter)

Your support allows me to keep this package free, up-to-date and maintainable. Alternatively, you can **[spread the word!](http://twitter.com/share?text=I%20am%20using%20this%20cool%20PHP%20package&url=https://github.com%2FLaragear%2FWebAuthn&hashtags=PHP,Laravel)**

## Requirements

Call Composer to retrieve the package.

```bash
composer require laragear/rewind
```

## Setup

First, install the migration file. This migration will create a table to save all previous states from your models.

```shell
php artisan vendor:publish --provider="Laragear\Rewind\RewindServiceProvider" --tag="migrations"
```

> [!TIP]
>
> You can [edit the migration](MIGRATIONS.md) by adding new columns before migrating, and also [change the table name](MIGRATIONS.md#custom-table-name).

```shell
php artisan migrate
```

Finally, add the `HasRewind` trait into your models you want its state to be saved when created and updated.

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laragear\Rewind\HasRewind;

class Article extends Model
{
    use HasRewind;
    
    // ...
}
```

That's it. Next time you want restore the previous state of a model, use the `rewind()` method.

```php
use App\Models\Article;

$article = Article::find(1);

$article->rewind()->toLatest();
```

## How it works?

When your model is created, and subsequently updated, a new _state_ is saved into the database. That state in comprised of the raw attributes of your original model.

With the `rewind()` helper method, you can easily peek and restore a previous model states like the last one, or have a list of states ready to be restored from. Additionally, the `HasRewind` trait allows to control _when_ to save states, and _what_ to save and restore.

States are _reactive_, not _proactive_. In other words, states are saved _after_ the original model is saved, not _before_.

## Saving States

States are created automatically when the model is created or updated. There is nothing you need to do to ensure the state has been persisted, but you can [hear for the `StatePushed` event](#events).

```php
use App\Models\Article;
use Illuminate\Http\Request;

public function store(Request $request)
{
    $validated = $request->validate([
        // ...
    ]);
    
    return Article::create($validated); // First state created automatically.
}

public function update(Article $article, Request $request)
{
    $validated = $request->validate([
        // ...
    ]);
    
    $article->update($validated); // State pushed automatically.
    
    return $article;
}
```

### Without creating states

Sometimes you will want to avoid creating a replica when a model is created or updated.  

To do that, use the `withoutCreatingStates()` method of your model with a callback. Inside the callback, the states won't be pushed to the database.

```php
use App\Models\Article;
use Illuminate\Http\Request;

public function store(Request $request)
{
    $validated = $request->validate([
        // ...
    ]);
    
    // Create an article but don't save the first state.
    return Article::withoutCreatingStates(fn() => Article::create($validated));
}
```

### Manually creating States

If you have disabled [automatic states creation](#automatic-state-saving), then you may save a state manually using the `create()` method.

```php
use App\Models\Article;
use Illuminate\Http\Request;

public function store(Request $request)
{
    $validated = $request->validate([
        // ...
    ]);
    
    $article = Article::create($validated);
    
    $article->rewind()->create(); // Save the first state.
    
    return $article;
}

public function update(Article $article, Request $request)
{
    $validated = $request->validate([
        // ...
    ]);
    
    $article->update($validated);
    
    $article->rewind()->create(); // Push a new state.
    
    return $article;
}
``` 

The `create()` method allows [keep the state](#keep-state) from automatic pruning, and also skip automatic pruning.

```php
$article->rewind()->create(
    keep: true,
    prune: false,
);
```

If you want, you can save a state without updating the original model. For example, you can create a bunch of articles "drafts".

```php
public function draft(Article $article, Request $request)
{
    $validated = $request->validate([
        // ...
    ]);
    
    // Push a state from the updated article without persisting it.
    $article->fill($validated)->rewind()->create();
    
    return back();
}
```

## Listing States

To get a list of all prior models states use the `all()` method. It will return an [Eloquent Collection](https://laravel.com/docs/11.x/eloquent-collections#available-methods) of all past models.

```php
use App\Models\Article;

$pastArticles = Article::find(1)->rewind()->all();
```

> [!TIP]
> 
> You can use the state ID to later [restore a given state ID](#restoring-states).

### Count

To count all the saved states, use the `count()` method. It will return the number of persisted states.

```php
use App\Models\Article;

$count = Article::find(1)->rewind()->count();
```

### Existence

To avoid counting all the states and only check if there is at least one state made for the model, use the `exists()` method.

```php
use App\Models\Article;

$hasAtLeastOneState = Article::find(1)->rewind()->exists();
```

Alternatively, the `missing()` method checks if there are no states saved for the model.

```php
use App\Models\Article;

$hasNoStates = Article::find(1)->rewind()->missing();
```

### Latest and Oldest states

You may also use `findLatest()` and `findOldest()` if you need to find the first or the last model state, respectively.

```php
use App\Models\Article;

$latest = Article::find(1)->rewind()->getLatest();

$oldest = Article::find(1)->rewind()->getOldest();
```

### Retrieving a State ID

To retrieve a model instance by its state ID, use the `find()` method.

```php
use App\Models\Article;

$pastArticle = Article::find(1)->rewind()->find(456);
```

> [!CAUTION]
> 
> Because the State ID is expected to exist, a `ModelNotFoundException` will be thrown if id doesn't exist. 

## Restoring States

The easiest way to restore a prior state data into the same model instance is using the `to()` method with the ID of the state to restore, and just calling `save()` to persist the changes in the database.

```php
use App\Models\Article;

$article = Article::find(1);

$article->title; // "Happy cavations!"

$article->rewind()->to(468)->save();

$article->title; // "Happy vacations!"
```

Alternatively, you can always restore the model to the latest or oldest state using `toLatest()` or `toOldest()`, respectively.

```php
use App\Models\Article;

Article::find(1)->rewind()->toLatest()->save();

Article::find(1)->rewind()->toOldest()->save();
```

> [!IMPORTANT]
> 
> When the model restored is updated, it will create a new state. To avoid this, use [`withoutCreatingStates()`](#without-creating-states).

### Restoring states alongside the original model

If you retrieve prior model state, you will virtually have two instances in your code: the current one, and the past state model.

Saving the past state will replace the data of the original in the database. The original instance will not be aware of the changes made, so you should [refresh the model](https://laravel.com/docs/11.x/eloquent#refreshing-models), or discard it.

```php
use App\Models\Article;

$stale = Article::find(1);

$stale->title; // "Happy vatacions!"

$past = $original->rewind()->getLatest();

$past->save();

$stale->title; // "Happy vatacions!"

$stale->fresh()->title; // "Happy vacations!"
```

## Deleting states

You may delete a state the `delete()` and the ID of the state.

```php
use App\Models\Article;

Article::find(1)->rewind()->remove(765);
```

You may also use the `deleteLatest()` or `deleteOldest()` to delete the latest or oldest state, respectively.

```php
use App\Models\Article;

$article = Article::find(1);

$article->rewind()->removeLatest();
$article->rewind()->removeOldest();
```

> [!IMPORTANT]
> 
> Using `deleteLatest()` and `deleteOldest()` do not delete [kept states](#keep-state), you will need to issue `true` to force its deletion.
> 
> ```php
> Article::find(3)->rewind()->deleteOldest(true);
> ```

### Deleting all states

You may call the `clear()` method to delete all the states from a model.

```php
use App\Models\Article;

Article::find(1)->rewind()->clear();
```

Since this won't include [kept states](#keep-state), you can use `forceClear()` to include them.

```php
use App\Models\Article;

Article::find(1)->rewind()->forceClear();
```

### Pruning states

Every time the model is updated, it automatically prunes old model states to keep a [limited number of states](#limiting-number-of-saved-states). If you have disabled it, you may need to call the `prune()` method manually to remove stale states.

```php
use App\Models\Article;

Article::find(1)->rewind()->prune();
```

> [!NOTE]
> 
> When retrieving states, states to-be-pruned are automatically left out from the query.

## Events

This package fires the following events:

| Event name                                    | Trigger                                         |
|-----------------------------------------------|-------------------------------------------------|
| [StateRestored](src/Events/StateRestored.php) | At developer discretion                         |
| [StateDeleted](src/Events/StateDeleted.php)   | Fires when a state is deleted from the database |
| [StatePushed](src/Events/StateCreated.php)    | Fires when a state is pushed to the database    |
| [StatesCleared](src/Events/StatesCleared.php) | Fires when states are cleared from the database |
| [StatesPruned](src/Events/StatesPruned.php)   | Fires when states are pruned from the database  |

For example, you can listen to the `StatePushed` event in your application with a [Listener](https://laravel.com/docs/11.x/events#registering-events-and-listeners).

```php
use Laragear\Rewind\Events\StateCreated;

class MyListener
{
    public function handle(StateCreated $event)
    {
        $event->model; // The target model.
        $event->state; // The RewindState Model with the data. 
    }
}
```

The `StateRestored` event is included for convenience. If you decide to restore a model to a previous state, you may trigger it using `StateRestored::dispatch()` manually with the model instance.

```php
use App\Models\Article;
use Illuminate\Support\Facades\Route;
use Laragear\Rewind\Events\StateRestored;

Route::post('article/{article}/restore', function (Article $article) {
    $article->rewind()->toOldest()->save();
    
    StateRestored::dispatch($article);
    
    return back();
});
```

## Raw States

Model States data are saved into the `Laragear\Rewind\Models\RewindState` model. This model is mostly responsable for creating a new model instance from the raw data it holds.

### Querying Raw States

Use the `query()` method to start a query for the given model. You may use this, for example, to paginate results.

```php
use App\Models\Article;

$page = Article::find(1)->rewind()->query()->paginate();
```

You may also use the model instance query directly for further custom queries.

```php
use Laragear\Rewind\Models\RewindState;

RewindState::query()->where('created_at', '<', now()->subMonth())->delete();
```

## Configuration

Sometimes you will want to change how the rewind procedure works on your model. You can configure when and how states are saved using the trait methods.

### Limiting number of saved states

By default, the limit of rewind states is 10, regardless of how old these are. You may change the amount by returning an integer in the `rewindLimit()` method.

```php
public function rewindLimit()
{
    return 10;
}
```

You may also change the limit to a moment in time. States created before the given moment won't be considered on queries and will be pruned automatically when saving a model.

```php
public function rewindLimit()
{
    return now()->subMonth();
}
```

To limit by both an amount and a date at the same time, return an array with both. 

```php
public function rewindLimit()
{
    return [10, now()->subMonth()];
}
```

Finally, you may disable limits by returning _falsy_, which will ensure all states are saved.

```php
public function rewindLimit()
{
    return false;
}
```

### Automatic State saving

Every time you create or update a model, a new state is created in the database. You may change this behaviour using `shouldCreateRewindStateOnCreated()` and `shouldCreateRewindStateOnUpdated()`, respectively.

```php
public function shouldCreateRewindStateOnCreated(): bool
{
    // Don't save states if the article body is below a certain threshold.
    return strlen($this->body) < 500;
}

public function shouldCreateRewindStateOnUpdated(): bool
{
    // Only let subscribed users to have states saved. 
    return $this->user->hasSubscription();
}
```

This may be useful if you want to not save a model state, for example, if an Article doesn't have enough characters in its body, or if the User is not [subscribed to a plan](https://github.com/Laragear/SubscriptionsDemo).

### Automatic pruning

By default, everytime a new state is pushed into the database, it prunes the old states. You may disable this programmatically using `shouldPruneOldRewindStatesOnUpdated()`.

```php
public function shouldPruneOldRewindStatesOnUpdated(): bool
{
    return true;
}
```

### Keep State

When you create a model, the initial state can be lost after pushing subsequent new states. To avoid this, you can always _protect_ the first state, or any state, with `shouldKeepFirstRewindState()`.

```php
public function shouldKeepFirstRewindState(): bool
{
    return true;
}
```

> [!TIP]
> 
> If the first state is protected, it won't be pruned, so only newer states will rotate. 

### Attributes to save in a State

By default, all raw attributes of the model are added into the state data. You can override this by setting your own attributes to use in the state to save by returning an associative array or an `\Illuminate\Contracts\Support\Arrayable` instance.

```php
use Illuminate\Contracts\Support\Arrayable;

public function getAttributesForRewindState(): Arrayable|array
{
    return [
        'author_id' => $this->author_id,
        'title' => $this->title,
        'slug' => $this->slug,
        'body' => $this->body,
        'private_notes' => null,
        'published_at' => $this->published_at,
     ];
}
```

### Attributes to restore from a State

To modify _how_ the model should be filled with the attributes from the state, use the `setAttributesFromRewindState()`. It receives the raw attributes from the state as an array.

```php
use Illuminate\Support\Arr;

public function setAttributesFromRewindState(array $attributes): void
{
    $this->setRawAttributes(Arr::except($attributes, 'excerpt'));
}
``` 

## [Migrations](MIGRATIONS.md)

## Laravel Octane compatibility

- There are no singletons using a stale application instance.
- There are no singletons using a stale config instance.
- There are no singletons using a stale request instance.
- There are no static properties written on every request.

There should be no problems using this package with Laravel Octane.

## Security

If you discover any security related issues, please email darkghosthunter@gmail.com instead of using the issue tracker.

# License

This specific package version is licensed under the terms of the [MIT License](LICENSE.md), at time of publishing.

[Laravel](https://laravel.com) is a Trademark of [Taylor Otwell](https://github.com/TaylorOtwell/). Copyright © 2011-2024 Laravel LLC.
