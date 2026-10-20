<?php declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use HistoryHeraldry\DatabaseTranslations\Config\DatabaseTranslations;
use HistoryHeraldry\DatabaseTranslations\Contracts\IdentifierGenerator;
use HistoryHeraldry\DatabaseTranslations\Database\Migrations\CreateTranslationsTable;
use HistoryHeraldry\DatabaseTranslations\Models\TranslationModel;

final class TranslationModelTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/../src/Database/Migrations/2025-11-05-134305_CreateTranslationsTable.php';
        $this->db = Database::connect('tests');
        $this->db->initialize();
        Database::forge($this->db)->dropTable('translations', true);
        $this->configure(DatabaseTranslations::PRIMARY_KEY_AUTO_INCREMENT);
        (new CreateTranslationsTable(Database::forge($this->db)))->up();
    }

    protected function tearDown(): void
    {
        Database::forge($this->db)->dropTable('translations', true);
        $this->configure(DatabaseTranslations::PRIMARY_KEY_AUTO_INCREMENT);
        $this->db->close();
        parent::tearDown();
    }

    public function testAutoIncrementCrudTimestampsAndSoftDeletes(): void
    {
        $model = new TranslationModel($this->db, config: $this->packageConfig());
        $id = $model->insert([
            'locale' => 'en',
            'namespace' => 'Common',
            'key' => 'actions.button.save',
            'value' => 'Save',
        ]);

        self::assertIsInt($id);
        $row = $model->find($id);
        self::assertSame('Save', $row['value']);
        self::assertNotEmpty($row['created_at']);
        self::assertNotEmpty($row['updated_at']);

        self::assertTrue($model->delete($id));
        self::assertNull($model->find($id));
        self::assertNotNull($model->withDeleted()->find($id));
    }

    public function testValidationRejectsMalformedTranslation(): void
    {
        $model = new TranslationModel($this->db, config: $this->packageConfig());

        self::assertFalse($model->insert([
            'locale' => str_repeat('x', 11),
            'namespace' => '',
            'key' => '',
            'value' => 'Invalid',
        ]));
        self::assertArrayHasKey('locale', $model->errors());
        self::assertArrayHasKey('namespace', $model->errors());
        self::assertArrayHasKey('key', $model->errors());
    }

    public function testUuidModeGeneratesIdsForSingleAndBatchInserts(): void
    {
        (new CreateTranslationsTable(Database::forge($this->db)))->down();
        SequentialModelIdentifierGenerator::reset();
        $this->configure(DatabaseTranslations::PRIMARY_KEY_UUID, SequentialModelIdentifierGenerator::class);
        (new CreateTranslationsTable(Database::forge($this->db)))->up();

        $model = new TranslationModel($this->db, config: $this->packageConfig(
            DatabaseTranslations::PRIMARY_KEY_UUID,
            SequentialModelIdentifierGenerator::class,
        ));
        $id = $model->insert([
            'locale' => 'en',
            'namespace' => 'Common',
            'key' => 'one',
            'value' => 'One',
        ]);
        $inserted = $model->insertBatch([
            ['locale' => 'en', 'namespace' => 'Common', 'key' => 'two', 'value' => 'Two'],
            ['locale' => 'en', 'namespace' => 'Common', 'key' => 'three', 'value' => 'Three'],
        ]);

        self::assertSame('00000000-0000-4000-8000-000000000001', $id);
        self::assertSame(2, $inserted);
        self::assertSame([
            '00000000-0000-4000-8000-000000000001',
            '00000000-0000-4000-8000-000000000002',
            '00000000-0000-4000-8000-000000000003',
        ], array_column($model->orderBy('id')->findAll(), 'id'));
    }

    private function configure(string $mode, ?string $generator = null): void
    {
        $config = config('DatabaseTranslations');
        $config->primaryKeyMode = $mode;
        $config->identifierGenerator = $generator;
    }

    private function packageConfig(
        string $mode = DatabaseTranslations::PRIMARY_KEY_AUTO_INCREMENT,
        ?string $generator = null,
    ): DatabaseTranslations {
        $config = new DatabaseTranslations();
        $config->databaseGroup = 'tests';
        $config->primaryKeyMode = $mode;
        $config->identifierGenerator = $generator;

        return $config;
    }
}

final class SequentialModelIdentifierGenerator implements IdentifierGenerator
{
    private static int $next = 1;

    public static function reset(): void
    {
        self::$next = 1;
    }

    public function generate(): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', self::$next++);
    }
}
