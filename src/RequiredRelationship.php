<?php

declare(strict_types=1);

namespace Mateusjatenee\Persist;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class RequiredRelationship
{
    public function __construct()
    {
    }
}
