<?php

namespace Ufo\RpcSdk\Procedures\ResponseTransformer\Traits;

use Ufo\DTO\Helpers\EnumResolver;
use Ufo\RpcSdk\Maker\Definitions\EnumDefinition;

use function end;
use function explode;

trait EnumNameExtractorTrait
{
    /**
     * @param string $name
     * @return class-string
     */
    protected function extractClassName(string $name): string
    {
        $parts = explode('/', $name);
        return EnumDefinition::FOLDER . '\\' . end($parts);
    }

    protected function getEnumName(array $schema, string $namespace): ?string
    {
        $enumName = null;
        if (EnumResolver::schemaHasEnum($schema)) {
            $enumName = $this->extractClassName(EnumResolver::findEnumNameInJsonSchema($schema));
            $enumName = $namespace . '\\'. $enumName;
        }
        return $enumName;
    }
}