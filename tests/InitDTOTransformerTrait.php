<?php

namespace Ufo\RpcSdk\Tests;

use Ufo\DTO\DTOTransformer;
use Ufo\DTO\Factory\DefaultDTOTransformerFactory;

trait InitDTOTransformerTrait
{
    protected function setUp(): void
    {
        if (!DTOTransformer::isInitialized()) {
            DTOTransformer::boot(DefaultDTOTransformerFactory::default()->create());
        }
    }
}
