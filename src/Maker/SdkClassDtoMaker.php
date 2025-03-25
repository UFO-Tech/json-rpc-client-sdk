<?php

namespace Ufo\RpcSdk\Maker;

use Exception;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Ufo\RpcObject\DTO\ArrayConstructibleTrait;
use Ufo\RpcObject\DTO\ArrayConvertibleTrait;
use Ufo\RpcObject\DTO\IArrayConstructible;
use Ufo\RpcObject\DTO\IArrayConvertible;
use Ufo\RpcObject\RpcResponse;
use Ufo\RpcSdk\Interfaces\ISdkMethodClass;
use Ufo\RpcSdk\Maker\Definitions\ClassDefinition;
use Ufo\RpcSdk\Maker\Definitions\DtoClassDefinition;
use Ufo\RpcSdk\Maker\Definitions\MethodToClassnameConvertor;
use Ufo\RpcSdk\Procedures\AbstractProcedure;
use Ufo\RpcSdk\Procedures\ApiMethod;
use Ufo\RpcSdk\Procedures\ApiUrl;

class SdkClassDtoMaker
{
    const string DEFAULT_TEMPLATE = __DIR__.'/../../templates/dto.php.twig';

    protected string $template = self::DEFAULT_TEMPLATE;

    public function __construct(
        protected Maker  $maker,
        protected DtoClassDefinition $classDefinition
    ) {}

    /**
     * @throws Exception
     */
    public function generate(): void
    {
        $generator = $this->maker->getGenerator();
        $generator->generateClass(
            $this->classDefinition->getFullName(),
            $this->template,
            [
                'class_name' => $this->classDefinition->getClassName(),
                'vendor' => [
                    'name' => $this->maker->getApiVendorAlias(),
                    'url' => $this->maker->getApiUrl(),
                ],
                'uses' => [
                    IArrayConstructible::class,
                    IArrayConvertible::class,
                    ArrayConstructibleTrait::class,
                    ArrayConvertibleTrait::class,
                ],
                'interfaces' => [],
                'extends' => '',
                'methods' => $this->classDefinition->getMethods(),
                'propertiesDocs' => $this->classDefinition->getDocs(),
                'properties' => $this->classDefinition->getProperties(),
                'tab' => function (int $count = 1) {
                    return str_repeat(' ', 4 * $count);
                }
            ]
        );

        $generator->writeChanges();
    }
}
