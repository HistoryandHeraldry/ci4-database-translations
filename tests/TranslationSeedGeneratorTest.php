<?php declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use HistoryHeraldry\DatabaseTranslations\Config\DatabaseTranslations;
use HistoryHeraldry\DatabaseTranslations\Services\TranslationSeedGenerator;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class TranslationSeedGeneratorTest extends CIUnitTestCase
{
    private string $output;

    protected function setUp(): void
    {
        parent::setUp();
        $this->output = WRITEPATH . 'generator-' . bin2hex(random_bytes(4));
        mkdir($this->output, 0775, true);
        mkdir($this->output . '/Language/en', 0775, true);
        mkdir($this->output . '/Language/en-GB', 0775, true);
        copy(__DIR__ . '/Fixtures/Language/en/Common.php', $this->output . '/Language/en/Common.php');
        copy(__DIR__ . '/Fixtures/Language/en-GB/Common.php', $this->output . '/Language/en-GB/Common.php');
    }

    protected function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->output, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->output);
        parent::tearDown();
    }

    public function testScansNestedLanguageValuesInDeterministicOrder(): void
    {
        $generator = new TranslationSeedGenerator($this->config());
        self::assertSame([
            ['locale' => 'en', 'namespace' => 'Common', 'key' => 'actions.save', 'value' => 'Save'],
            ['locale' => 'en', 'namespace' => 'Common', 'key' => 'message', 'value' => 'Hello'],
            ['locale' => 'en-GB', 'namespace' => 'Common', 'key' => 'message', 'value' => 'Hello mate'],
        ], $generator->scan());
    }

    public function testGeneratesTrackedSeederAndDeployableNdjson(): void
    {
        $result = (new TranslationSeedGenerator($this->config()))->generate('CoreTranslationsSeeder');
        self::assertSame(3, $result['rowsCount']);
        self::assertFalse($result['tracked']);
        self::assertSame('CoreTranslationsSeeder', $result['class']);
        self::assertFileExists($result['seederPath']);
        self::assertFileExists($result['dataPath']);
        self::assertSame('CoreTranslationsSeeder.php', basename($result['seederPath']));
        self::assertStringContainsString('final class CoreTranslationsSeeder', file_get_contents($result['seederPath']) ?: '');
        self::assertStringContainsString('Data/Translations/CoreTranslationsSeeder.ndjson', file_get_contents($result['seederPath']) ?: '');
        self::assertStringContainsString("hash_file('sha256'", file_get_contents($result['seederPath']) ?: '');
        self::assertCount(3, file($result['dataPath'], FILE_IGNORE_NEW_LINES));
        self::assertSame(2, $result['filesCleared']);
        self::assertStringContainsString('// This comment must survive translation seed generation.', file_get_contents($this->output . '/Language/en/Common.php') ?: '');
        self::assertStringContainsString('return [];', file_get_contents($this->output . '/Language/en/Common.php') ?: '');
        self::assertStringNotContainsString("'message' => 'Hello'", file_get_contents($this->output . '/Language/en/Common.php') ?: '');
    }

    public function testDryRunDoesNotWriteFiles(): void
    {
        $result = (new TranslationSeedGenerator($this->config()))->generate(dryRun: true);
        self::assertSame(3, $result['rowsCount']);
        self::assertSame(0, $result['filesCleared']);
        self::assertFileDoesNotExist($result['seederPath']);
        self::assertFileDoesNotExist($result['dataPath']);
        self::assertStringContainsString("'message' => 'Hello'", file_get_contents($this->output . '/Language/en/Common.php') ?: '');
    }

    public function testKeepFilesGeneratesArtifactsWithoutClearingLanguageFiles(): void
    {
        $result = (new TranslationSeedGenerator($this->config()))->generate(
            name: 'kept translations',
            dryRun: false,
            keepFiles: true,
        );

        self::assertFileExists($result['seederPath']);
        self::assertFileExists($result['dataPath']);
        self::assertSame(0, $result['filesCleared']);
        self::assertStringContainsString("'message' => 'Hello'", file_get_contents($this->output . '/Language/en/Common.php') ?: '');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testUsesTrackedFilenameWhenSeedTrackerIsInstalled(): void
    {
        eval('namespace HistoryHeraldry\\SeedTracker; final class Runner {}');

        $result = (new TranslationSeedGenerator($this->config()))->generate(
            name: 'FileName',
            keepFiles: true,
        );

        self::assertTrue($result['tracked']);
        self::assertSame('FileName', $result['class']);
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}-\d{6}_FileName\.php$/',
            basename($result['seederPath']),
        );
        self::assertStringContainsString('final class FileName extends Seeder', file_get_contents($result['seederPath']) ?: '');
    }

    private function config(): DatabaseTranslations
    {
        $config = new DatabaseTranslations();
        $config->languagePath = $this->output . '/Language';
        $config->seedPath = $this->output . '/Seeds';
        return $config;
    }
}
