<?php

namespace Ufo\RpcSdk\Maker\Helpers;

use ReflectionClass;
use Symfony\Bundle\MakerBundle\Str;
use Throwable;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\RpcSdk\Exceptions\SdkBuilderException;

use function array_map;
use function class_exists;
use function count;
use function explode;
use function file_exists;
use function implode;
use function preg_match;
use function preg_split;
use function unlink;
use function usleep;

final readonly class ClassHelper
{

    private function __construct(
        public string $className,
        public string $apiMethod,
        public string $separator,
        public string $zoneName,
    ) {}

    public static function convertMethodToClassname(string $procedureName, bool $async = false): self
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
        $apiMethod = Str::asLowerCamelCase($apiMethod);
        return new self($className . 'SDK' , $apiMethod, $separator, $className);
    }

    public static function toUpperCamelCase(string $string): string
    {
        $words = preg_split('/[\s_\-]+/', $string);
        $upperCamelCase = array_map('ucfirst', $words);
        return implode('', $upperCamelCase);
    }

    /**
     * @param string $className
     * @return void
     * @throws SdkBuilderException
     */
    public static function removePreviousClass(string $className): void
    {
        if (class_exists($className)) {
            try {
                $reflection = new ReflectionClass($className);
                if (file_exists($reflection->getFileName())) {
                    unlink($reflection->getFileName());
                    usleep(300);
                }
            } catch (Throwable $e) {
                throw new SdkBuilderException(
                    'Can`t remove previous version for class "' . $className . '"',
                    previous: $e
                );
            }
        }
    }

    public static function classNameNormalizer(array $schema): array
    {
        return TypeHintResolver::applyToSchema($schema, function ($schema) {
            if ($schema[TypeHintResolver::REF] ?? false) {
                $ref = explode('/', $schema[TypeHintResolver::REF]);
                $ref[count($ref)-1] = Str::asClassName($ref[count($ref)-1]);
                $schema[TypeHintResolver::REF] = implode('/', $ref);
            }
            return $schema;
        });
    }
}