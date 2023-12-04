<?php

namespace Mateusjatenee\Persist;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionMethod;

/** @mixin \Illuminate\Database\Eloquent\Model */
trait Persist
{
    use HandlesRelationships;

    /** @var array<string|object> */
    protected $events = [];

    public function persist(): bool
    {
        $this->verifyRequiredRelationships();

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

        if (! $this->persistRelations($this->getRelationsOfType(BelongsTo::class)->all())) {
            return false;
        }

        if (! $this->save()) {
            return false;
        }

        // To sync all of the relationships to the database, we will simply spin through
        // the relationships and save each model via this "push" method, which allows
        // us to recurse into all of these nested relations for the model instance.
        if (! $this->persistRelations(
            $this->getRelationsOfType(MorphOneOrMany::class, HasOneOrMany::class, BelongsToMany::class)->all()
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

                if ($relation instanceof MorphOneOrMany) {
                    $model->setAttribute($relation->getForeignKeyName(), $relation->getParentKey())
                        ->setAttribute($relation->getMorphType(), $relation->getMorphClass());
                } elseif ($relation instanceof HasOneOrMany) {
                    $model->setAttribute($relation->getForeignKeyName(), $relation->getParentKey());
                }

                if (! $model->persist()) {
                    return false;
                }

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
     */
    public function setAttribute($key, $value)
    {
        if (($value instanceof Model || $value instanceof Collection) && $this->isRelation($key)) {
            return $this->setRelation($key, $value);
        }

        return parent::setAttribute($key, $value);
    }

    protected function verifyRequiredRelationships(): void
    {
        $requiredRelationships = $this->getRequiredRelationships();

        foreach ($requiredRelationships as $relationship) {
            if (! $this->isRelationLoadedAndFilled($relationship)) {
                throw ModelMissingRequiredRelationshipException::make(static::class, $relationship);
            }
        }
    }

    protected function getRequiredRelationships(): Collection
    {
        $reflector = new ReflectionClass($this);
        $methods = $reflector->getMethods();

        return (new Collection($methods))
            ->filter(fn (ReflectionMethod $method) => count($method->getAttributes(RequiredRelationship::class)) !== 0)
            ->map(fn ($method) => $method->getName())
            ->values();
    }

    protected function isRelationLoadedAndFilled(string $relation): bool
    {
        if ($this->{$relation} instanceof Collection) {
            return $this->{$relation}->isNotEmpty();
        }

        return $this->relationLoaded($relation)
            && ! is_null($this->getRelation($relation));
    }
}
