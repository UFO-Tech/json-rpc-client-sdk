<?php

namespace Ufo\RpcSdk\Tests\Functional;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\Util\AutoloaderUtil;
use Symfony\Bundle\MakerBundle\Util\ComposerAutoloaderFinder;
use Symfony\Bundle\MakerBundle\Util\MakerFileLinkFormatter;
use Symfony\Component\Filesystem\Filesystem;
use Ufo\RpcSdk\Maker\Definitions\Configs\ConfigsHolder;
use Ufo\RpcSdk\Maker\DocReader\FileReader;
use Ufo\RpcSdk\Maker\Interfaces\IClassLikeDefinition;
use Ufo\RpcSdk\Maker\Interfaces\IHaveMethodsDefinitions;
use Ufo\RpcSdk\Maker\Maker;
use Ufo\RpcSdk\Maker\SdkConfigMaker;
use Ufo\RpcSdk\Maker\SdkDtoMaker;
use Ufo\RpcSdk\Maker\SdkEnumMaker;
use Ufo\RpcSdk\Maker\SdkProcedureMaker;
use Symfony\Component\Yaml\Yaml;

use function var_export;

class GenerateSdkFunctionalTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = __DIR__ . '/../../var/sdk_gen_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
        mkdir($this->tmpDir . '/src', 0777, true);

        $a = var_export(['Ufo\Client\\' => [$this->tmpDir . '/src']], true);

    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    /**
     * @dataProvider schemasProvider
     */
    public function testFullSdkGenerationFromLocalSchema(string $schemaName): void
    {
        $schemaFile = __DIR__ . '/../fixtures/schemas/' . $schemaName . '.json';
        $docReader = new FileReader($schemaFile);

        $holder = new ConfigsHolder(
            $docReader,
            projectRootDir: $this->tmpDir,
            apiVendorAlias: 'demo_vendor',
            namespace: 'Ufo\Client',
        );

        $generator = new Generator(
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
                new SdkDtoMaker($holder, $generator),
                new SdkEnumMaker($holder, $generator),
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

        // Перевіряємо, що створився YAML-конфіг
        $configPath = $configMaker->sdkConfigs->getConfigDistPath();
        $this->assertFileExists($configPath, 'Yaml config file must be generated');
        $data = Yaml::parseFile($configPath);
        $this->assertArrayHasKey('DemoVendor', $data);

        // Перевіряємо, що є директорія src/Client

        // Якщо схема містить components.schemas, перевіряємо наявність хоча б одного DTO
        $schemaArr = json_decode(file_get_contents($schemaFile), true);
        if (isset($schemaArr['components']['schemas']) && is_array($schemaArr['components']['schemas']) && count($schemaArr['components']['schemas']) > 0) {
            $dtoDir = $this->tmpDir . '/src/Client/Dto';
            $files = glob($dtoDir . '/*.php');
            $this->assertNotEmpty($files, 'DTO classes should be generated');
        }

        unset($maker);
        $clientDir = $this->tmpDir . '/src/Client';
        $this->assertDirectoryExists($clientDir, 'Client directory must be generated');

    }

    public static function schemasProvider(): array
    {
        return [
            ['simple'],
//            ['dto'],
//            ['enum'],
//            ['nested_dto'],
//            ['full_transport'],
        ];
    }
}