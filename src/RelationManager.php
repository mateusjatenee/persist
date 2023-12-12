<?php

declare(strict_types=1);

namespace Mateusjatenee\Persist;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionMethod;

class RelationManager
{
    public function __construct(
        protected readonly Model $model
    ) {
    }

    public static function for(Model $model): RelationManager
    {
        return new RelationManager($model);
    }

    public function verifyRequiredRelationships(): void
    {
        $requiredRelationships = $this->getRequiredRelationships();

        foreach ($requiredRelationships as $relationship) {
            if (! $this->isRelationLoadedAndFilled($relationship)) {
                throw ModelMissingRequiredRelationshipException::make($this->model::class, $relationship);
            }
        }
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Relations\Relation>  ...$types
     * @return \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Relations\Relation>
     */
    public function getRelationsOfType(string ...$types): Collection
    {
        return (new Collection($this->model->getRelations()))
            ->filter(fn ($models, $relation) => $this->isRelationshipOfType($relation, $types));
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Relations\Relation>  ...$types
     * @return \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Relations\Relation>
     */
    public function getRelationsExceptOfType(string ...$types): Collection
    {
        return (new Collection($this->model->getRelations()))
            ->reject(fn ($models, $relation) => $this->isRelationshipOfType($relation, $types));
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Relations\Relation>[]  $types
     */
    public function isRelationshipOfType(string $relation, array $types): bool
    {
        if (! $relationObject = $this->model->$relation()) {
            return false;
        }

        foreach ($types as $type) {
            if ($relationObject instanceof $type) {
                return true;
            }
        }

        return false;
    }

    protected function getRequiredRelationships(): Collection
    {
        $methods = (new ReflectionClass($this->model))->getMethods();

        return (new Collection($methods))
            ->filter(fn (ReflectionMethod $method) => count($method->getAttributes(RequiredRelationship::class)) !== 0)
            ->map(fn ($method) => $method->getName())
            ->values();
    }

    protected function isRelationLoadedAndFilled(string $relation): bool
    {
        if ($this->model->{$relation} instanceof Collection) {
            return $this->model->{$relation}->isNotEmpty();
        }

        return $this->model->relationLoaded($relation)
            && ! is_null($this->model->getRelation($relation));
    }
}
