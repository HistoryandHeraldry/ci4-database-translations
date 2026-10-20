<?php declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use HistoryHeraldry\DatabaseTranslations\Config\DatabaseTranslations;
use HistoryHeraldry\DatabaseTranslations\Identifiers\IdentifierGeneratorFactory;
use HistoryHeraldry\DatabaseTranslations\Identifiers\MichalsnUuidGenerator;
use HistoryHeraldry\DatabaseTranslations\Identifiers\RandomUuidGenerator;

final class IdentifierGeneratorFactoryTest extends CIUnitTestCase
{
    public function testAutoIncrementRequiresNoGenerator(): void
    {
        self::assertNull(IdentifierGeneratorFactory::create(new DatabaseTranslations()));
    }

    public function testUuidModeRequiresConfiguredGenerator(): void
    {
        $config = new DatabaseTranslations();
        $config->primaryKeyMode = DatabaseTranslations::PRIMARY_KEY_UUID;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('requires an identifierGenerator');
        IdentifierGeneratorFactory::create($config);
    }

    public function testUuidModeAcceptsPluggableGenerator(): void
    {
        $config = new DatabaseTranslations();
        $config->primaryKeyMode = DatabaseTranslations::PRIMARY_KEY_UUID;
        $config->identifierGenerator = RandomUuidGenerator::class;

        $identifier = IdentifierGeneratorFactory::create($config)?->generate();
        self::assertIsString($identifier);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $identifier);
    }

    public function testUnknownModeFailsBeforeSchemaCreation(): void
    {
        $config = new DatabaseTranslations();
        $config->primaryKeyMode = 'snowflake';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported translation primary-key mode');
        IdentifierGeneratorFactory::create($config);
    }

    public function testMichalsnAdapterGeneratesUuid(): void
    {
        $identifier = (new MichalsnUuidGenerator())->generate();

        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $identifier);
    }
}
