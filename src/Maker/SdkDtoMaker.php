<?php

namespace Ufo\RpcSdk\Maker;

use Exception;
use Symfony\Bundle\MakerBundle\Generator;
use Ufo\DTO\ArrayConstructibleTrait;
use Ufo\DTO\ArrayConvertibleTrait;
use Ufo\DTO\Interfaces\IArrayConstructible;
use Ufo\DTO\Interfaces\IArrayConvertible;
use Ufo\RpcSdk\Exceptions\SdkBuilderException;
use Ufo\RpcSdk\Maker\Definitions\Configs\ConfigsHolder;
use Ufo\RpcSdk\Maker\Definitions\DtoClassDefinition;
use Ufo\RpcSdk\Maker\Definitions\EnumDefinition;
use Ufo\RpcSdk\Maker\Helpers\ClassHelper;
use Ufo\RpcSdk\Maker\Helpers\DocHelper;
use Ufo\RpcSdk\Maker\Interfaces\IMaker;
use Ufo\RpcSdk\Maker\Interfaces\IClassLikeStackHolder;
use Ufo\RpcSdk\Maker\Traits\ClassLikeStackHolderTrait;
use Ufo\RpcSdk\Maker\Traits\ProcessCallbackTrait;

use function sprintf;
use function str_repeat;

/**
 * @property DtoClassDefinition[] $classStack
 */
class SdkDtoMaker implements IMaker, IClassLikeStackHolder
{
    use ProcessCallbackTrait, ClassLikeStackHolderTrait;

    const string DEFAULT_TEMPLATE = __DIR__.'/../../templates/dto.php.twig';

    protected string $template = self::DEFAULT_TEMPLATE;

    public function __construct(
        readonly public ConfigsHolder $configsHolder,
        protected Generator $generator,
    ) {}

    /**
     * @param callable|null $callbackOutput
     * @throws Exception
     */
    public function make(?callable $callbackOutput = null): void
    {
        foreach ($this->configsHolder->getDtos() as $dtoName => $dtoConfig) {
            $className = ClassHelper::toUpperCamelCase($dtoName);
            try {
                $classDefinition = $this->getFromStack($className);
                $this->processCallback($classDefinition, $callbackOutput);
            } catch (SdkBuilderException) {
                $classDefinition = new DtoClassDefinition(
                    sprintf(
                        '%s\\%s\\%s',
                        $this->configsHolder->namespace,
                        $this->configsHolder->apiVendorAlias,
                        DtoClassDefinition::FOLDER
                    ),
                    $className,
                    $this->configsHolder,
                );

                try {
                    ClassHelper::removePreviousClass($classDefinition->getFQCN());
                } catch (\Throwable $e) {}

                $classDefinition->setProperties($dtoConfig->params);

                $this->generator->generateClass(
                    $classDefinition->getFQCN(),
                    $this->template,
                    [
                        'class_name' => $classDefinition->getShortName(),
                        'vendor' => [
                            'name' => $this->configsHolder->apiVendorAlias,
                            'url' => $this->configsHolder->apiUrl,
                        ],
                        'uses' => [
                            IArrayConstructible::class,
                            IArrayConvertible::class,
                            ArrayConstructibleTrait::class,
                            ArrayConvertibleTrait::class,
                            $this->configsHolder->namespace . '\\' . $this->configsHolder->apiVendorAlias . '\\' .
                            EnumDefinition::FOLDER,
                        ],
                        'interfaces' => [],
                        'extends' => '',
                        'methods' => [],
                        'propertiesDocs' => $classDefinition->getDocs(),
                        'propertiesDefaults' => $classDefinition->getDefaultValues(),
                        'properties' => $classDefinition->getProperties(),
                        'tab' => function (int $count = 1) {
                            return str_repeat(' ', 4 * $count);
                        }
                    ]
                );
                $this->processCallback($classDefinition, $callbackOutput);
            }

        }
    }
}
