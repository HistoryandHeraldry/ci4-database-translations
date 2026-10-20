<?php declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use HistoryHeraldry\DatabaseTranslations\Config\DatabaseTranslations;
use HistoryHeraldry\DatabaseTranslations\Services\TranslationSeedGenerator;

final class TranslationSeedGeneratorTest extends CIUnitTestCase
{
    private string $output;

    protected function setUp(): void
    {
        parent::setUp();
        $this->output = WRITEPATH . 'generator-' . bin2hex(random_bytes(4));
        mkdir($this->output, 0775, true);
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
        $result = (new TranslationSeedGenerator($this->config()))->generate('core translations', false, '2026-10-20-130000');
        self::assertSame(3, $result['rowsCount']);
        self::assertFileExists($result['seederPath']);
        self::assertFileExists($result['dataPath']);
        self::assertStringContainsString('final class CoreTranslations20261020130000Seeder', file_get_contents($result['seederPath']) ?: '');
        self::assertStringContainsString('Data/Translations/2026-10-20-130000_CoreTranslations20261020130000Seeder.ndjson', file_get_contents($result['seederPath']) ?: '');
        self::assertStringContainsString("hash_file('sha256'", file_get_contents($result['seederPath']) ?: '');
        self::assertCount(3, file($result['dataPath'], FILE_IGNORE_NEW_LINES));
    }

    public function testDryRunDoesNotWriteFiles(): void
    {
        $result = (new TranslationSeedGenerator($this->config()))->generate(null, true, '2026-10-20-130001');
        self::assertSame(3, $result['rowsCount']);
        self::assertFileDoesNotExist($result['seederPath']);
        self::assertFileDoesNotExist($result['dataPath']);
    }

    private function config(): DatabaseTranslations
    {
        $config = new DatabaseTranslations();
        $config->languagePath = __DIR__ . '/Fixtures/Language';
        $config->seedPath = $this->output;
        return $config;
    }
}
