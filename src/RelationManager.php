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
        private readonly Model $model
    ) {
    }

    public static function for(Model $model): static
    {
        return new static($model);
    }

    public function verifyRequiredRelationships(): void
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
        $reflector = new ReflectionClass($this->model);
        $methods = $reflector->getMethods();

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
