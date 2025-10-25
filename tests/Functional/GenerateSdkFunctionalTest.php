<?php

namespace Ufo\RpcSdk\Tests\Functional;

use Generator;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\Generator as SymfonyGenerator;
use Symfony\Bundle\MakerBundle\Util\AutoloaderUtil;
use Symfony\Bundle\MakerBundle\Util\ComposerAutoloaderFinder;
use Symfony\Bundle\MakerBundle\Util\MakerFileLinkFormatter;
use Symfony\Component\Filesystem\Filesystem;
use Ufo\DTO\Helpers\EnumResolver;
use Ufo\RpcSdk\Maker\Definitions\Configs\ConfigsHolder;
use Ufo\RpcSdk\Maker\Definitions\Configs\ProcedureConfig;
use Ufo\RpcSdk\Maker\Definitions\DtoClassDefinition;
use Ufo\RpcSdk\Maker\DocReader\FileReader;
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
use function count;
use function glob;
use function is_dir;
use function pathinfo;
use function scandir;
use function uniqid;
use function var_export;

use const PATHINFO_BASENAME;
use const PATHINFO_DIRNAME;
use const PATHINFO_FILENAME;

class GenerateSdkFunctionalTest extends TestCase
{
    const string SCHEMA_DIR = __DIR__ . '/../Fixtures/schemas';
    const string DEMO_VENDOR_NAME = 'demo_vendor';
    const string DEMO_VENDOR_NS = 'DemoVendor';
    private string $testDir;
    private string $clientDir;
    private string $dtoDir;
    private string $namespace;

    protected function setUp(): void
    {
        $testDir = 'Test_' . uniqid();
        $this->testDir = __DIR__ . '/../../var/sdk/' . $testDir;
        mkdir($this->testDir, 0777, true);
        $this->clientDir = $this->testDir . '/' . static::DEMO_VENDOR_NS . '/' ;
        $this->dtoDir = $this->clientDir . DtoClassDefinition::FOLDER;
        $this->namespace = 'FunctionalTest\SDK\\' . $testDir;

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
            $full = "$path/$file";
            if (is_dir($full)) {
                yield from static::getSchemaFile($full);
            } else {
                yield [pathinfo($full)];
            }
        }
    }

    /**
     * @dataProvider schemasProvider
     */
    public function testFullSdkGenerationFromLocalSchema(array $fileInfo): void
    {
        $schemaName = $fileInfo['filename'];;
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

        $testMethodName = $schemaName . 'Test';
        if (!method_exists($this, $testMethodName)) throw new \Exception("Test for schema '$schemaName.json' not found");

        $this->{$testMethodName}(
            holder: $holder,
            configMaker: $configMaker
        );
    }

    protected function dtoTest(ConfigsHolder $holder, SdkConfigMaker $configMaker): void
    {
        $files = glob($this->dtoDir . '/*.php');
        $this->assertNotEmpty($files, 'DTO classes should be generated');
    }

    protected function nestedDtoTest(ConfigsHolder $holder, SdkConfigMaker $configMaker): void
    {
//        $files = glob($this->dtoDir . '/*.php');
//        $this->assertNotEmpty($files, 'DTO classes should be generated');
    }

    protected function enumTest(ConfigsHolder $holder, SdkConfigMaker $configMaker): void
    {
//        $files = glob($this->clientDir . '/*.php');
//        $this->assertNotEmpty($files, 'Client classes should be generated');
    }

    protected function emptyMethodsTest(ConfigsHolder $holder, SdkConfigMaker $configMaker): void
    {
        $this->assertDirectoryDoesNotExist($this->clientDir);
    }

    protected function fullTransportTest(ConfigsHolder $holder, SdkConfigMaker $configMaker): void
    {
        $configPath = $configMaker->sdkConfigs->getConfigDistPath();
        $data = Yaml::parseFile($configPath);
        $this->assertArrayHasKey(SdkConfigs::ASYNC, $data[static::DEMO_VENDOR_NS]);
    }

    protected function simpleTest(ConfigsHolder $holder, SdkConfigMaker $configMaker): void
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