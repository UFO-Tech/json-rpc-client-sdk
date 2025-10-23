<?php

namespace Ufo\RpcSdk\Maker;

use BackedEnum;
use Exception;
use Symfony\Bundle\MakerBundle\Generator;
use Throwable;
use TypeError;
use Ufo\DTO\DTOTransformer;
use Ufo\DTO\Helpers\EnumResolver;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\DTO\VO\EnumVO;
use Ufo\RpcSdk\Exceptions\SdkBuilderException;
use Ufo\RpcSdk\Maker\Definitions\Configs\ConfigsHolder;
use Ufo\RpcSdk\Maker\Definitions\Configs\ParamConfig;
use Ufo\RpcSdk\Maker\Definitions\EnumDefinition;
use Ufo\RpcSdk\Maker\Helpers\ClassHelper;
use Ufo\RpcSdk\Maker\Helpers\ParamToStringConverter;
use Ufo\RpcSdk\Maker\Interfaces\IClassLikeStackHolder;
use Ufo\RpcSdk\Maker\Interfaces\IMaker;
use Ufo\RpcSdk\Maker\Traits\ClassLikeStackHolderTrait;
use Ufo\RpcSdk\Maker\Traits\ProcessCallbackTrait;

use function array_filter;
use function array_find;
use function array_map;
use function array_merge;
use function array_search;
use function implode;
use function md5;
use function preg_replace;
use function str_contains;
use function str_repeat;

/**
 * @property EnumDefinition[] $classStack
 */
class SdkEnumMaker implements IMaker, IClassLikeStackHolder
{
    use ProcessCallbackTrait, ClassLikeStackHolderTrait;

    const string DEFAULT_TEMPLATE = __DIR__.'/../../templates/enum.php.twig';
    const string CALLBACK_REGEX = '/callback: \[\w+::class,\s?[\'"]\w+[\'"]\]/';

    protected string $template = self::DEFAULT_TEMPLATE;

    public function __construct(
        readonly public ConfigsHolder $configsHolder,
        protected Generator $generator,
    ) {}

    protected function prepareData(): array
    {
        $prepared = [];
        foreach ($this->configsHolder->getRpcProcedures() as $procedureData) {

            $prepared = array_merge(
                $prepared,
                array_filter(
                    $procedureData->params,
                    function (ParamConfig $p) {
                        return EnumResolver::schemaHasEnum($p->typeConfig->schema);
                    }
                )
            );
            if (EnumResolver::schemaHasEnum($procedureData->result->schema)) {
                $prepared[] = new ParamConfig(
                    $procedureData,
                    'return',
                    $procedureData->result
                );
            }
        }
        foreach ($this->configsHolder->getDtos() as $dtoName => $dtoConfig) {
            foreach ($dtoConfig->params as $param) {
                if (EnumResolver::schemaHasEnum($param->typeConfig->schema)) {
                    $prepared[] = new ParamConfig($dtoConfig, $param->name, $param->typeConfig);
                }
            }
        }
        return $prepared;
    }

    /**
     * @throws Exception
     */
    public function make(?callable $callbackOutput = null): void
    {
        foreach ($this->prepareData() as $paramData) {
            foreach (EnumResolver::enumsDataFromSchema($paramData->typeConfig->schema, $paramData->name) as $enumVO) {
                $enumDef = $this->createEnum($enumVO, $paramData, $callbackOutput);

                $this->generator->generateClass(
                    $enumDef->getFQCN(),
                    $this->template,
                    [
                        'class_name' => $enumDef->getShortName(),
                        'enum_type' => $enumDef->getType(),
                        'vendor' => [
                            'name' => $this->configsHolder->apiVendorAlias,
                            'url' => $this->configsHolder->apiUrl,
                        ],
                        'uses' => [],
                        'interfaces' => [],
                        'cases' => $enumDef->getCases(),
                        'tab' => function (int $count = 1) {
                            return str_repeat(' ', 4 * $count);
                        }
                    ]
                );
            }
        }
    }

    protected function createEnum(EnumVO $enumConfig, ParamConfig $paramConfig, ?callable $callbackOutput = null):
EnumDefinition
    {
        $config = $this->configsHolder;
        $namespace = $config->namespace . '\\' . $config->apiVendorAlias . '\\' .EnumDefinition::FOLDER;
        $enumName = $enumConfig->name;

        try {
            $enumDef = $this->getFromStack($namespace . '\\' . $enumName);
        } catch (SdkBuilderException) {
            $enumDef = new EnumDefinition(
                $namespace,
                $enumConfig
            );
            ClassHelper::removePreviousClass($enumDef->getFQCN());
            $this->addToStack($enumDef, $namespace . '\\' . $enumName);

            $this->processCallback($enumDef, $callbackOutput);
        }

        $enumWithNS = EnumDefinition::FOLDER . '\\' . $enumDef->getShortName();

        if ($defaultValue = $paramConfig->typeConfig->schema['default'] ?? false) {
            try {
                $defaultValue = $this->changeDefaultValueToEnum($enumConfig, $defaultValue, $enumWithNS);
            } catch (Throwable) {
                if (is_array($defaultValue)) {
                    $defaultValue = $this->changeDefaultValuesToEnums($enumConfig, $defaultValue, $enumWithNS);
                }
            }
            $config->addDefaultValueForParam($paramConfig, $defaultValue);
        }

        $paramConfig->assertions = preg_replace(
            static::CALLBACK_REGEX,
            'callback: [' . $enumWithNS . '::class, \'values\']',
            $paramConfig->assertions ?? ''
        );
        if (str_contains($paramConfig->typeConfig->typeDoc, EnumDefinition::FOLDER)) {
            $paramConfig->assertions = '';
        }
        return $enumDef;
    }

    protected function changeDefaultValueToEnum(EnumVO $enumConfig, string|int $defaultValue, string $enumWithNS): string
    {
        $case = array_search($defaultValue, $enumConfig->values);
        return $enumWithNS . '::' . $case;
    }

    protected function changeDefaultValuesToEnums(EnumVO $enumConfig, array $defaultValues, string $enumWithNS): array
    {
        return array_map(
            fn($value) => $this->changeDefaultValueToEnum($enumConfig, $value, $enumWithNS),
            $defaultValues
        );
    }
}
