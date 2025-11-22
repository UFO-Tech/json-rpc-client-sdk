<?php

namespace Ufo\RpcSdk\Tests\Functional;

use Exception;
use Generator;
use phpDocumentor\Reflection\DocBlockFactory;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\Generator as SymfonyGenerator;
use Symfony\Bundle\MakerBundle\Util\AutoloaderUtil;
use Symfony\Bundle\MakerBundle\Util\ComposerAutoloaderFinder;
use Symfony\Bundle\MakerBundle\Util\MakerFileLinkFormatter;
use Symfony\Component\Filesystem\Filesystem;
use Ufo\DTO\DTOTransformer;
use Ufo\DTO\Helpers\EnumResolver;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\DTO\Interfaces\IArrayConstructible;
use Ufo\DTO\Interfaces\IArrayConvertible;
use Ufo\RpcSdk\Maker\Definitions\Configs\ConfigsHolder;
use Ufo\RpcSdk\Maker\Definitions\Configs\ProcedureConfig;
use Ufo\RpcSdk\Maker\Definitions\DtoClassDefinition;
use Ufo\RpcSdk\Maker\DocReader\FileReader;
use Ufo\RpcSdk\Maker\Helpers\ClassHelper;
use Ufo\RpcSdk\Maker\Interfaces\IClassLikeDefinition;
use Ufo\RpcSdk\Maker\Interfaces\IHaveMethodsDefinitions;
use Ufo\RpcSdk\Maker\Maker;
use Ufo\RpcSdk\Maker\SdkConfigMaker;
use Ufo\RpcSdk\Maker\SdkDtoMaker;
use Ufo\RpcSdk\Maker\SdkEnumMaker;
use Ufo\RpcSdk\Maker\SdkProcedureMaker;
use Symfony\Component\Yaml\Yaml;
use Ufo\RpcSdk\Procedures\SdkConfigs;

use function array_filter;
use function class_exists;
use function count;
use function current;
use function file_exists;
use function glob;
use function in_array;
use function is_dir;
use function is_subclass_of;
use function method_exists;
use function pathinfo;
use function scandir;
use function time;
use function uniqid;
use function usleep;

use const DIRECTORY_SEPARATOR;

class GenerateSdkFunctionalTest extends TestCase
{
    const string SCHEMA_DIR = __DIR__ . '/../Fixtures/schemas';
    const string DEMO_VENDOR_NAME = 'demo_vendor';
    const string DEMO_VENDOR_NS = 'DemoVendor';
    private string $testDir;
    private string $clientDir;
    private string $dtoDir;
    private string $dtoNS;
    private string $namespace;

    private const array EXCLUDE_SCHEMA = [
        'notUfo1',
        'notUfo2',
        'notUfo3',
        'withAllKeys',
    ];

    protected function setUp(): void
    {
        $testDir = 'Test_' . time();// . uniqid();
        $this->testDir = __DIR__ . '/../../var/sdk/' . $testDir;
        mkdir($this->testDir, 0777, true);
        $this->clientDir = $this->testDir . '/' . static::DEMO_VENDOR_NS . '/' ;
        $this->dtoDir = $this->clientDir . DtoClassDefinition::FOLDER;
        $this->namespace = 'FunctionalTest\SDK\\' . $testDir;
        $this->dtoNS = $this->namespace . '\\' . static::DEMO_VENDOR_NS . '\\' .DtoClassDefinition::FOLDER;

    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->testDir));
    }

    public static function schemasProvider(): iterable
    {
        return static::getSchemaFile();
    }

    protected static function getSchemaFile(?string $path = null): iterable
    {
        $path = $path ?? static::SCHEMA_DIR;
        foreach (scandir($path) as $file) {
            if ($file === '.' || $file === '..') continue;
            $full = $path . DIRECTORY_SEPARATOR . $file;
            if (is_dir($full)) {
                yield from static::getSchemaFile($full);
            } else {
                yield [pathinfo($full)];
            }
        }
    }

    private function requireAllPhpFiles(?string $dir = null): void
    {
        $dir ??= $this->clientDir;
        if (file_exists($dir)) {
            foreach (scandir($dir) as $item) {
                if ($item === '.' || $item === '..') continue;
                $path = $dir . DIRECTORY_SEPARATOR . $item;
                if (is_dir($path)) {
                    $this->requireAllPhpFiles($path);
                } elseif (str_ends_with($path, '.php')) {
                    require_once $path;
                }
            }
        }
    }

    /**
     * @dataProvider schemasProvider
     */
    public function testFullSdkGenerationFromLocalSchema(array $fileInfo): void
    {
        $schemaName = $fileInfo['filename'];
        $schemaFile =  $fileInfo['dirname'] . '/' . $fileInfo['basename'];
        $docReader = new FileReader($schemaFile);

        $holder = new ConfigsHolder(
            $docReader,
            projectRootDir: $this->testDir,
            apiVendorAlias: static::DEMO_VENDOR_NAME,
            namespace: $this->namespace,
        );

        $generator = new SymfonyGenerator(
            new FileManager(
                new Filesystem(),
                new AutoloaderUtil(new ComposerAutoloaderFinder($holder->namespace)),
                new MakerFileLinkFormatter(),
                $holder->projectRootDir
            ),
            $holder->namespace
        );

        $maker = new Maker(
            configsHolder: $holder,
            generator: $generator,
            makers: [
                new SdkEnumMaker($holder, $generator),
                new SdkDtoMaker($holder, $generator),
                new SdkProcedureMaker($holder, $generator),
                $configMaker = new SdkConfigMaker($holder, $generator),
            ]
        );

        $maker->make(function (IClassLikeDefinition $classDef) {
            $fqcn = $classDef->getFQCN();
            $this->assertNotEmpty($fqcn, "FQCN cannot be empty");
            if ($classDef instanceof IHaveMethodsDefinitions) {
                foreach ($classDef->getMethods() as $method) {
                    $this->assertNotEmpty($method->getName(), "Method name should not be empty");
                }
            }
            return $classDef;
        });
        unset($maker);

        $this->requireAllPhpFiles();

        $testMethodName = $schemaName . 'Test';

        $this->baseDtoTest($configMaker);

        if (!in_array($schemaName,static::EXCLUDE_SCHEMA)) {
            if (!method_exists($this, $testMethodName))
                throw new Exception("Test for schema '$schemaName.json' not found. Write test method: $testMethodName, or exclude this schema from test");

            $this->{$testMethodName}(
                configMaker: $configMaker
            );
        }

    }

    protected function baseDtoTest(SdkConfigMaker $configMaker): void
    {
        $files = glob($this->dtoDir . '/*.php');
        $dtoSchema = $configMaker->configsHolder->rpcSchema["components"]["schemas"] ?? [];
        $dtoSchema = array_filter($dtoSchema, fn($schema) => $schema['type'] === TypeHintResolver::OBJECT->value);

        $this->assertCount(
            count($dtoSchema),
            $files,
            'DTO classes should be generated'
        );

        foreach ($configMaker->configsHolder->getDtos() as $dtoName => $dtoConfig) {
            $dto = $this->dtoNS . '\\' . $dtoName;
            $this->assertTrue(class_exists($dto), 'DTO class should be generated');
            $this->assertTrue(is_subclass_of($dto, IArrayConvertible::class), 'DTO class should implement IArrayConvertible');
            $this->assertTrue(is_subclass_of($dto, IArrayConstructible::class), 'DTO class should implement IArrayConstructible');

            $dtoRef = new ReflectionClass($dto);
            foreach ($dtoSchema[$dtoName]['properties'] as $propertyName => $propertySchema) {
                $propertySchema = ClassHelper::classNameNormalizer($propertySchema);
                $this->assertTrue(
                    $dtoRef->hasProperty($propertyName),
                    $dtoName . ' class should have property "' . $propertyName . '"'
                );
                $this->assertEquals(
                    TypeHintResolver::jsonSchemaToPhp(
                        $propertySchema, ['dto' => $this->dtoNS]
                    ),
                    $dtoRef->getProperty($propertyName)->getType()->getName(),
                    'Property "' . $propertyName . '" should have type ' . $dtoRef->getProperty($propertyName)->getType()->getName()
                );
                $this->assertTrue(
                    $dtoRef->getProperty($propertyName)->isPublic(),
                    'Property "' . $propertyName . '" should be public'
                );

                if (!in_array($propertyName, $dtoSchema[$dtoName]['required'] ?? [], true)
                    && array_key_exists('default', $propertySchema)
                ) {

                    $this->assertTrue(
                        $dtoRef->getProperty($propertyName)->hasDefaultValue(),
                        "$dtoName::$propertyName should have default value"
                    );

                    $this->assertEquals(
                        $propertySchema['default'] ?? null,
                        $dtoRef->getProperty($propertyName)->getDefaultValue(),
                        "Default value for $dtoName::$propertyName should be equal to to schema default value"
                    );
                }

                if ($doc = $dtoRef->getProperty($propertyName)->getDocComment()) {
                    $docBlock = DocBlockFactory::createInstance()->create($doc);
                    $dataFromSDK = TypeHintResolver::typeDescriptionToJsonSchema(
                        (string)current($docBlock->getTagsByName('var'))->getType(),
                        [
                            DTOTransformer::DTO_NS_KEY => $this->dtoNS,
                        ]
                    );
                    $dtoParamType = TypeHintResolver::jsonSchemaToTypeDescription(
                        $dtoSchema[$dtoName]['properties'][$propertyName],
                        ['dto' => $this->dtoNS]
                    );
                    $dataFromSchema = TypeHintResolver::typeDescriptionToJsonSchema($dtoParamType, [
                        DTOTransformer::DTO_NS_KEY => $this->dtoNS,
                    ]);
                    $this->assertEquals($dataFromSDK, $dataFromSchema, 'SDK data should be equal to schema data for property ' . $dtoName . '::' . $propertyName);
                }
            }
            usleep(400000);
        }
    }

    protected function enumTest(SDKConfigMaker $configMaker): void
    {
        $files = glob($this->clientDir . '/*.php');
        $this->assertNotEmpty($files, 'Client classes should be generated');
    }

    protected function emptyMethodsTest(SdkConfigMaker $configMaker): void
    {
        $this->assertDirectoryDoesNotExist($this->clientDir);
    }

    protected function fullTransportTest(SdkConfigMaker $configMaker): void
    {
        $configPath = $configMaker->sdkConfigs->getConfigDistPath();
        $data = Yaml::parseFile($configPath);
        $this->assertArrayHasKey(SdkConfigs::ASYNC, $data[static::DEMO_VENDOR_NS]);
    }

    protected function simpleTest(SdkConfigMaker $configMaker): void
    {
        // Перевіряємо, що створився YAML-конфіг
        $configPath = $configMaker->sdkConfigs->getConfigDistPath();
        $this->assertFileExists($configPath, 'Yaml config file must be generated');
        $data = Yaml::parseFile($configPath);
        $this->assertArrayHasKey(static::DEMO_VENDOR_NS, $data);
        $this->assertArrayHasKey(SdkConfigs::SYNC, $data[static::DEMO_VENDOR_NS]);

        $this->assertDirectoryExists($this->clientDir, 'Client directory must be generated');
    }
}