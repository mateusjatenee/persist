<?php

declare(strict_types=1);

namespace Mateusjatenee\Persist;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use ReflectionMethod;

/** @mixin Persist */
trait HandlesRelationships
{
    protected function relationManager(): RelationManager
    {
        return RelationManager::for($this);
    }

    /**
     * Determine if the given key is a relationship method on the model.
     *
     * @param  string  $key
     * @return bool
     *
     * @throws \ReflectionException
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
}
