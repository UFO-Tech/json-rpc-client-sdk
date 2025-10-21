<?php

namespace Ufo\RpcSdk\Procedures\ResponseTransformer\Traits;

use Ufo\RpcSdk\Maker\Definitions\DtoClassDefinition;

use function end;
use function explode;

trait DtoNameExtractorTrait
{
    /**
     * @param string $name
     * @return class-string
     */
    protected function extractClassName(string $name): string
    {
        $parts = explode('/', $name);
        return DtoClassDefinition::FOLDER . '\\' . end($parts);
    }

}