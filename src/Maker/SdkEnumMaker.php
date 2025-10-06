<?php

namespace Ufo\RpcSdk\Maker;

use Exception;
use Symfony\Bundle\MakerBundle\Generator;
use Ufo\DTO\DTOTransformer;
use Ufo\DTO\Helpers\EnumResolver;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\DTO\VO\EnumVO;
use Ufo\RpcSdk\Exceptions\SdkBuilderException;
use Ufo\RpcSdk\Maker\Definitions\Configs\ConfigsHolder;
use Ufo\RpcSdk\Maker\Definitions\Configs\ParamConfig;
use Ufo\RpcSdk\Maker\Definitions\EnumDefinition;
use Ufo\RpcSdk\Maker\Helpers\ClassHelper;
use Ufo\RpcSdk\Maker\Interfaces\IClassLikeStackHolder;
use Ufo\RpcSdk\Maker\Interfaces\IMaker;
use Ufo\RpcSdk\Maker\Traits\ClassLikeStackHolderTrait;
use Ufo\RpcSdk\Maker\Traits\ProcessCallbackTrait;

use function array_filter;
use function array_map;
use function array_merge;
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
                    'return',
                    $procedureData->result
                );
            }
        }
        foreach ($this->configsHolder->getDtos() as $dtoName => $dtoConfig) {
            foreach ($dtoConfig->params as $param) {
                if (EnumResolver::schemaHasEnum($param->typeConfig->schema)) {
                    $prepared[] = new ParamConfig($param->name, $param->typeConfig);
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

    protected function createEnum(EnumVO $enumConfig, ParamConfig $paramConfig, ?callable $callbackOutput = null): EnumDefinition
    {
        $hash = md5(implode(',', $enumConfig->values));

        try {
            $enumDef = $this->getFromStack($hash);
        } catch (SdkBuilderException) {
            $enumDef = new EnumDefinition(
                $this->configsHolder->namespace . '\\' . $this->configsHolder->apiVendorAlias . '\\' . EnumDefinition::FOLDER,
                $enumConfig
            );
            ClassHelper::removePreviousClass($enumDef->getFQCN());
            $this->addToStack($enumDef, $hash);

            $this->processCallback($enumDef, $callbackOutput);
        }

        $paramConfig->assertions = preg_replace(
            static::CALLBACK_REGEX,
            'callback: [' . EnumDefinition::FOLDER . '\\' . $enumDef->getShortName() . '::class, \'values\']',
            $paramConfig->assertions ?? ''
        );
        if (str_contains($paramConfig->typeConfig->typeDoc, EnumDefinition::FOLDER)) {
            $paramConfig->assertions = '';
        }
        return $enumDef;
    }
}
