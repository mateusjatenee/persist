<?php

declare(strict_types=1);

namespace Mateusjatenee\Persist\Tests;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Mateusjatenee\Persist\Persist;
use Mockery;

class PersistTest extends TestCase
{
    use DatabaseMigrations;

    protected function defineDatabaseMigrationsAfterDatabaseRefreshed(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title');
            $table->unsignedInteger('user_id');
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->increments('id');
            $table->string('tag');
        });

        Schema::create('post_tag', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('post_id');
            $table->unsignedInteger('tag_id');
        });

        Schema::create('post_details', function (Blueprint $table) {
            $table->increments('id');
            $table->string('description');
            $table->unsignedInteger('post_id');
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->increments('id');
            $table->string('comment');
            $table->nullableMorphs('commentable');
        });
    }

    public function testPersistSavesAHasOneRelationship(): void
    {
        $user = User::create(['name' => 'Mateus']);
        $post = Post::make(['title' => 'Test title', 'user_id' => $user->id]);
        $details = PostDetails::make(['description' => 'Test description']);

        $post->details = $details;
        $post->persist();

        $this->assertEquals(1, $post->id);
        $this->assertEquals(1, $details->id);
        $this->assertTrue($details->fresh()->post->is($post));
    }

    public function testPersistSavesAHasManyRelationship()
    {
        User::create(['name' => 'First test']); // So user starts at ID 2
        $user = User::make(['name' => 'Test']);
        $post = Post::make(['title' => 'Test title']);
        $user->posts->push($post);

        $user->persist();

        $this->assertEquals(2, $user->id);
        $this->assertEquals(1, $post->id);
        $this->assertTrue($post->fresh()->user->is($user));
    }

    public function testPersistSavesABelongsToRelationship(): void
    {
        $post = Post::make(['title' => 'Test title']);
        $post->user()->associate($user = User::make(['name' => 'Test']));

        $post->persist();

        $this->assertEquals(1, $user->id);
        $this->assertEquals(1, $post->id);
        $this->assertTrue($post->fresh()->user->is($user));
    }

    public function testPersistSavesAMorphOneRelationship(): void
    {
        $user = User::create(['name' => 'Mateus']);
        $post = Post::make(['title' => 'Test title', 'user_id' => $user->id]);
        $comment = Comment::make(['comment' => 'Test comment']);
        $post->comment = $comment;

        $post->persist();

        $this->assertEquals(1, $post->id);
        $this->assertEquals(1, $comment->id);
        $this->assertTrue($post->fresh()->comment->is($comment));
    }

    public function testPersistSavesAMorphManyRelationship(): void
    {
        $user = User::create(['name' => 'Mateus']);
        $post = Post::make(['title' => 'Test title', 'user_id' => $user->id]);
        $post->comments->push($comment = Comment::make(['comment' => 'Test comment']));

        $post->persist();

        $this->assertEquals(1, $post->id);
        $this->assertEquals(1, $comment->id);
        $this->assertTrue($post->comments->first()->is($comment));
    }

    public function testPersistSavesAMorphToRelationship(): void
    {
        $user = User::create(['name' => 'Mateus']);
        $post = Post::make(['title' => 'Test title', 'user_id' => $user->id]);
        $comment = Comment::make(['comment' => 'Test comment']);
        $comment->commentable()->associate($post);
        $comment->persist();

        $this->assertEquals(1, $post->id);
        $this->assertEquals(1, $comment->id);
        $this->assertTrue($comment->commentable->is($post));
    }

    public function testPersistSavesABelongsToManyRelationship(): void
    {
        $user = User::create(['name' => 'Mateus']);
        $post = Post::make(['title' => 'Test title', 'user_id' => $user->id]);
        $tag = Tag::make(['tag' => 'Test tag']);
        $post->tags->push($tag);

        $post->persist();

        $this->assertEquals(1, $post->id);
        $this->assertEquals(1, $tag->id);
        $this->assertTrue($post->tags->first()->is($tag));
    }

    public function testPersistReturnsFalseIfBelongsToSaveFails(): void
    {
        $post = Post::make(['title' => 'Test title']);
        $user = User::make(['name' => 'Test']);
        $user->setEventDispatcher($events = Mockery::mock(Dispatcher::class));
        $events->expects('until')->with('eloquent.saving: '.get_class($user), $user)->andReturns(false);

        $post->user()->associate($user);

        $this->assertFalse($post->persist());
    }

    public function testPersistReturnsFalseIfRelationshipsFail(): void
    {
        $post = Post::make(['title' => 'Test title']);
        $user = User::make(['name' => 'Test']);
        Model::setEventDispatcher($events = Mockery::mock(Dispatcher::class));
        $events->makePartial();
        $events->expects('dispatch')->times(2)->andReturn();
        $events->expects('until')->times(2)->andReturn(true);
        $events->expects('until')->with('eloquent.saving: '.get_class($post), $post)->andReturn(false);

        $user->posts->push($post);
        $this->assertFalse($user->persist());
    }
}

class User extends Model
{
    use Persist;

    public $timestamps = false;

    protected $guarded = [];

    protected $table = 'users';

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}

class Post extends Model
{
    use Persist;

    public $timestamps = false;

    protected $guarded = [];

    protected $table = 'posts';

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function details(): HasOne
    {
        return $this->hasOne(PostDetails::class);
    }

    public function comment(): MorphOne
    {
        return $this->morphOne(Comment::class, 'commentable');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

class PostDetails extends Model
{
    use Persist;

    public $timestamps = false;

    protected $guarded = [];

    protected $table = 'post_details';

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}

class Comment extends Model
{
    use Persist;

    public $timestamps = false;

    protected $guarded = [];

    protected $table = 'comments';

    public function commentable(): MorphTo
    {
        return $this->morphTo('commentable');
    }
}

class Tag extends Model
{
    use Persist;

    public $timestamps = false;

    protected $guarded = [];

    protected $table = 'tags';

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }
}
