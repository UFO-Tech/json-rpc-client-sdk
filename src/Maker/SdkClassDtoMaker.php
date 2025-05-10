<?php

namespace Ufo\RpcSdk\Maker;

use Exception;
use Ufo\DTO\ArrayConstructibleTrait;
use Ufo\DTO\ArrayConvertibleTrait;
use Ufo\DTO\IArrayConstructible;
use Ufo\DTO\IArrayConvertible;
use Ufo\RpcSdk\Maker\Definitions\DtoClassDefinition;

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
