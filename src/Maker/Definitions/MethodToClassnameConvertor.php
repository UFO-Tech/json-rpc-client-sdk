<?php

namespace Ufo\RpcSdk\Maker\Definitions;

use Symfony\Bundle\MakerBundle\Str;

use function count;
use function preg_match;

final readonly class MethodToClassnameConvertor
{

    private function __construct(
        public string $className,
        public string $apiMethod,
        public string $separator
    ) {}

    public static function convert(string $procedureName, bool $async = false): self
    {
        $pMatch = [];
        preg_match("/(\w+)(\W+)(\w+)/", $procedureName, $pMatch);

        $prefixAsync = $async ? 'Async' : '';
        $className = $prefixAsync . 'Main';
        $apiMethod = $procedureName;
        $separator = '';
        if (count($pMatch) > 0) {
            $className = $prefixAsync . Str::asCamelCase($pMatch[1]);
            $apiMethod = $pMatch[3];
            $separator = $pMatch[2];
        }
        return new self($className . 'SDK', $apiMethod, $separator);
    }
}