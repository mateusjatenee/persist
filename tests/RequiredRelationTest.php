<?php

declare(strict_types=1);

namespace Mateusjatenee\Persist\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Mateusjatenee\Persist\ModelMissingRequiredRelationshipException;
use Mateusjatenee\Persist\Persist;
use Mateusjatenee\Persist\RequiredRelationship;

class RequiredRelationTest extends TestCase
{
    use DatabaseMigrations;

    protected function defineDatabaseMigrationsAfterDatabaseRefreshed(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title');
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

    /** @test */
    public function it_fails_if_a_relationship_is_missing(): void
    {
        $this->expectException(ModelMissingRequiredRelationshipException::class);
        $post = PostX::make(['title' => 'test']);
        $post->comments->push(CommentX::make(['comment' => 'test']));
        $post->persist();
    }

    /** @test */
    public function it_fails_if_a_has_many_relationship_is_missing(): void
    {
        $this->expectException(ModelMissingRequiredRelationshipException::class);
        $post = PostX::make(['title' => 'test']);
        $post->comments;
        $post->details = PostDetailsX::make(['description' => 'test']);
        $post->persist();
    }

    /** @test */
    public function it_persists_a_model_with_required_relationships(): void
    {
        $post = PostX::make(['title' => 'test']);
        $post->details = PostDetailsX::make(['description' => 'test']);
        $post->comments->push(CommentX::make(['comment' => 'test']));
        $post->persist();

        $this->assertDatabaseHas('posts', ['title' => 'test']);
        $this->assertDatabaseHas('post_details', ['description' => 'test']);
    }
}

class PostX extends Model
{
    use Persist;

    protected $table = 'posts';

    protected $guarded = [];

    public $timestamps = false;

    #[RequiredRelationship]
    public function details()
    {
        return $this->hasOne(PostDetailsX::class, 'post_id');
    }

    #[RequiredRelationship]
    public function comments()
    {
        return $this->morphMany(CommentX::class, 'commentable');
    }
}

class PostDetailsX extends Model
{
    use Persist;

    protected $table = 'post_details';

    protected $guarded = [];

    public $timestamps = false;

    public function post()
    {
        return $this->belongsTo(PostX::class, 'post_id');
    }
}

class CommentX extends Model
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
