<?php declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use HistoryHeraldry\DatabaseTranslations\Config\DatabaseTranslations;
use HistoryHeraldry\DatabaseTranslations\Identifiers\IdentifierGeneratorFactory;
use HistoryHeraldry\DatabaseTranslations\Identifiers\MichalsnUuidGenerator;
use HistoryHeraldry\DatabaseTranslations\Identifiers\RandomUuidGenerator;
use HistoryHeraldry\DatabaseTranslations\Identifiers\SymfonyUuidGenerator;
use PHPUnit\Framework\Attributes\DataProvider;

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

    public function testMichalsnAdapterUsesConfiguredVersion(): void
    {
        $generator = new MichalsnUuidGenerator();
        $generator->setVersion(DatabaseTranslations::UUID_VERSION_7);
        $identifier = $generator->generate();

        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $identifier);
    }

    /** @return iterable<string, array{string, string}> */
    public static function safeUuidVersions(): iterable
    {
        yield 'version 1' => [DatabaseTranslations::UUID_VERSION_1, '1'];
        yield 'version 4' => [DatabaseTranslations::UUID_VERSION_4, '4'];
        yield 'version 6' => [DatabaseTranslations::UUID_VERSION_6, '6'];
        yield 'version 7' => [DatabaseTranslations::UUID_VERSION_7, '7'];
    }

    #[DataProvider('safeUuidVersions')]
    public function testBundledGeneratorSupportsSafeUuidVersions(string $version, string $versionNibble): void
    {
        $identifier = (new SymfonyUuidGenerator($version))->generate();

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-' . $versionNibble . '[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $identifier,
        );
    }

    public function testFactoryPassesConfiguredVersionToVersionedGenerator(): void
    {
        $config = new DatabaseTranslations();
        $config->primaryKeyMode = DatabaseTranslations::PRIMARY_KEY_UUID;
        $config->identifierGenerator = SymfonyUuidGenerator::class;
        $config->uuidVersion = DatabaseTranslations::UUID_VERSION_7;

        $identifier = IdentifierGeneratorFactory::create($config)?->generate();

        self::assertIsString($identifier);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $identifier);
    }

    public function testNameBasedUuidVersionIsRejectedForGeneratedPrimaryKeys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported generated UUID version "v5"');

        new SymfonyUuidGenerator('v5');
    }
}
