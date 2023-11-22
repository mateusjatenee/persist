<?php

namespace Mateusjatenee\Persist;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Support\Collection;
use ReflectionMethod;

/** @mixin \Illuminate\Database\Eloquent\Model */
trait Persist
{
    public function persist(): bool
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
        // First we will check for the presence of a mutator for the set operation
        // which simply lets the developers tweak the attribute as it is set on
        // this model, such as "json_encoding" a listing of data for storage.
        if ($this->hasSetMutator($key)) {
            return $this->setMutatedAttributeValue($key, $value);
        } elseif ($this->hasAttributeSetMutator($key)) {
            return $this->setAttributeMarkedMutatedAttributeValue($key, $value);
        }

        // If an attribute is listed as a "date", we'll convert it from a DateTime
        // instance into a form proper for storage on the database tables using
        // the connection grammar's date format. We will auto set the values.
        elseif (! is_null($value) && $this->isDateAttribute($key)) {
            $value = $this->fromDateTime($value);
        }

        if ($this->isEnumCastable($key)) {
            $this->setEnumCastableAttribute($key, $value);

            return $this;
        }

        if ($this->isClassCastable($key)) {
            $this->setClassCastableAttribute($key, $value);

            return $this;
        }

        if (! is_null($value) && $this->isJsonCastable($key)) {
            $value = $this->castAttributeAsJson($key, $value);
        }

        // If this attribute contains a JSON ->, we'll set the proper value in the
        // attribute's underlying array. This takes care of properly nesting an
        // attribute in the array's value in the case of deeply nested items.
        if (str_contains($key, '->')) {
            return $this->fillJsonAttribute($key, $value);
        }

        if (! is_null($value) && $this->isEncryptedCastable($key)) {
            $value = $this->castAttributeAsEncryptedString($key, $value);
        }

        if (! is_null($value) && $this->hasCast($key, 'hashed')) {
            $value = $this->castAttributeAsHashedString($key, $value);
        }

        if ($this->isRelation($key)) {
            return $this->setRelation($key, $value);
        }

        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Determine if the given key is a relationship method on the model.
     *
     * @param  string  $key
     * @return bool
     */
    public function isRelation($key)
    {
        $hasMethodOrResolver = method_exists($this, $key) || $this->relationResolver(static::class, $key) !== null;

        if (! $hasMethodOrResolver || $this->hasAttributeMutator($key)) {
            return false;
        }

        if ($this->relationResolver(static::class, $key) !== null) {
            return true;
        }

        // If the method does not exist on the superclass, we know it's not an Eloquent internal method.
        // If it also does not have parameters, we can assume it's a relationship method.
        return ! method_exists(Model::class, $key)
            && count((new ReflectionMethod($this, $key))->getParameters()) === 0;
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Relations\Relation>  ...$types
     * @return \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Relations\Relation>
     */
    public function getRelationsOfType(string ...$types): Collection
    {
        return collect($this->getRelations())->filter(function ($models, $relation) use ($types) {
            return $this->isRelationshipOfType($relation, $types);
        });
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Relations\Relation>  ...$types
     * @return \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Relations\Relation>
     */
    public function getRelationsExceptOfType(string ...$types): Collection
    {
        return collect($this->getRelations())->reject(function ($models, $relation) use ($types) {
            return $this->isRelationshipOfType($relation, $types);
        });
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Relations\Relation>[]  $types
     */
    public function isRelationshipOfType(string $relation, array $types): bool
    {
        $relationObject = $this->$relation();

        if (! $relationObject) {
            return false;
        }

        foreach ($types as $type) {
            if ($relationObject instanceof $type) {
                return true;
            }
        }

        return false;
    }
}
