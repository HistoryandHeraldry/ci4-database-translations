<?php declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use HistoryHeraldry\DatabaseTranslations\Config\DatabaseTranslations;
use HistoryHeraldry\DatabaseTranslations\Contracts\IdentifierGenerator;
use HistoryHeraldry\DatabaseTranslations\Database\Migrations\CreateTranslationsTable;
use HistoryHeraldry\DatabaseTranslations\Repositories\TranslationRepository;

final class TranslationRepositoryTest extends CIUnitTestCase
{
    private TranslationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/../src/Database/Migrations/2026-10-20-120000_CreateTranslationsTable.php';
        $this->db = Database::connect('tests');
        $this->db->initialize();
        $migrationConfig = config('DatabaseTranslations');
        $migrationConfig->primaryKeyMode = DatabaseTranslations::PRIMARY_KEY_AUTO_INCREMENT;
        $migrationConfig->identifierGenerator = null;
        $forge = Database::forge($this->db);
        $forge->dropTable('translations', true);
        (new CreateTranslationsTable($forge))->up();
        $config = new DatabaseTranslations();
        $config->databaseGroup = 'tests';
        $this->repository = new TranslationRepository($this->db, $config);
        self::assertContains($this->db->getPrefix() . 'translations', $this->db->listTables(), json_encode($this->db->listTables()) ?: '');
    }

    protected function tearDown(): void
    {
        Database::forge('tests')->dropTable('translations', true);
        $config = config('DatabaseTranslations');
        $config->primaryKeyMode = DatabaseTranslations::PRIMARY_KEY_AUTO_INCREMENT;
        $config->identifierGenerator = null;
        $this->db->close();
        parent::tearDown();
    }

    public function testUpsertPrefixExactLookupAndFallback(): void
    {
        $this->repository->upsertBatch([
            ['locale' => 'en', 'namespace' => 'Common', 'key' => 'save', 'value' => 'Save'],
            ['locale' => 'en-GB', 'namespace' => 'Common', 'key' => 'save', 'value' => 'Save it'],
        ]);
        $this->repository->upsertBatch([
            ['locale' => 'en', 'namespace' => 'Common', 'key' => 'save', 'value' => 'Store'],
        ]);

        self::assertTrue($this->db->tableExists('translations'));
        self::assertSame(['Common.save' => 'Store'], $this->repository->getForLocaleAndNamespaces('en', ['Common']));
        self::assertSame(['Common.save' => 'Save it'], $this->repository->getForLocaleAndNamespacesWithFallback('en-GB', ['Common']));
        self::assertSame(['Common'], $this->repository->namespaces());
        self::assertSame(2, $this->db->table('translations')->countAllResults());
        self::assertIsInt($this->db->table('translations')->select('id')->get()->getFirstRow()->id);
    }

    public function testMigrationCanBeRolledBackAndReapplied(): void
    {
        $migration = new CreateTranslationsTable(Database::forge($this->db));
        $migration->down();
        self::assertFalse($this->db->tableExists('translations'));
        $migration->up();
        self::assertTrue($this->db->tableExists('translations'));
    }

    public function testUuidModeUsesConfiguredIdentifierGenerator(): void
    {
        $migration = new CreateTranslationsTable(Database::forge($this->db));
        $migration->down();

        $config = config('DatabaseTranslations');
        $config->primaryKeyMode = DatabaseTranslations::PRIMARY_KEY_UUID;
        $config->identifierGenerator = FixedIdentifierGenerator::class;
        $migration->up();

        $repository = new TranslationRepository($this->db, $config);
        $repository->upsertBatch([
            ['locale' => 'en', 'namespace' => 'Common', 'key' => 'save', 'value' => 'Save'],
        ]);

        self::assertSame(FixedIdentifierGenerator::ID, $this->db->table('translations')->select('id')->get()->getFirstRow()->id);
    }
}

final class FixedIdentifierGenerator implements IdentifierGenerator
{
    public const ID = '5d410b12-918f-4dcb-9b0d-f6864c2fa082';

    public function generate(): string
    {
        return self::ID;
    }
}
