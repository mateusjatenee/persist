<?php

namespace Mateusjatenee\Persist;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Support\Collection;

/** @mixin \Illuminate\Database\Eloquent\Model */
trait Persist
{
    use HandlesRelationships;

    /** @var array<string|object> */
    protected $events = [];

    /**
     * @throws \Throwable
     */
    public function persist(): bool
    {
        $this->relationManager()->verifyRequiredRelationships();

        return $this->getConnection()->transaction(function () {
            if (! $this->persistModels()) {
                return false;
            }

            $this->dispatchEvents();

            return true;
        });
    }

    public function recordEvent(string|object $event): void
    {
        $this->events[] = $event;
    }

    public function flushEvents(): void
    {
        $this->events = [];
    }

    protected function dispatchEvents(): void
    {
        foreach ($this->events as $event) {
            $event = $event instanceof \Closure ? $event($this) : $event;

            static::$dispatcher->dispatch($event);
        }

        $this->flushEvents();
    }

    protected function persistModels(): bool
    {
        // BelongsTo relationships must be handled earlier than the other relationships.
        // Since record creation will fail unless the foreign key is set on the base
        // model, we need to ensure that the BelongsTo relationships are created.

        if (! $this->persistRelations(
            $this->relationManager()->getRelationsOfType(BelongsTo::class))
        ) {
            return false;
        }

        if (! $this->save()) {
            return false;
        }

        // To sync all of the relationships to the database, we will simply spin through
        // the relationships and save each model via this "push" method, which allows
        // us to recurse into all of these nested relations for the model instance.
        if (! $this->persistRelations(
            $this->relationManager()->getRelationsExceptOfType(BelongsTo::class)
        )) {
            return false;
        }

        return true;
    }

    protected function persistRelations($relations): bool
    {
        foreach ($relations as $relationName => $models) {
            $models = $models instanceof Collection
               ? $models->all() : [$models];

            foreach (array_filter($models) as $model) {
                $relation = $this->{$relationName}();

                // MorphOneOrMany and HasOneOrMany relationships require the foreign key
                // and morph type to be set on the model before it can be persisted.
                if ($relation instanceof MorphOneOrMany) {
                    $model->setAttribute($relation->getForeignKeyName(), $relation->getParentKey())
                        ->setAttribute($relation->getMorphType(), $relation->getMorphClass());
                } elseif ($relation instanceof HasOneOrMany) {
                    $model->setAttribute($relation->getForeignKeyName(), $relation->getParentKey());
                }

                if (! $model->persist()) {
                    return false;
                }

                // BelongsTo and BelongsToMany relationships require the related model to be persisted beforehand.
                // e.g Post -> belongsTo User. We need to persist the User before we can set the foreign key.
                if ($relation instanceof BelongsTo) {
                    $relation->associate($model);
                } elseif ($relation instanceof BelongsToMany) {
                    $relation->attach($model);
                }
            }
        }

        return true;
    }

    /**
     * Set a given attribute on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     *
     * @throws \ReflectionException
     */
    public function setAttribute($key, $value)
    {
        if (($value instanceof Model || $value instanceof Collection) && $this->isRelation($key)) {
            return $this->setRelation($key, $value);
        }

        return parent::setAttribute($key, $value);
    }
}
