<?php

declare(strict_types=1);

namespace Mateusjatenee\Persist;

use RuntimeException;

class ModelMissingRequiredRelationshipException extends RuntimeException
{
    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    public static function make(string $model, string $relationship): self
    {
        return new self("The model [{$model}] is missing the required relationship [{$relationship}]");
    }
}
