<?php declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use HistoryHeraldry\DatabaseTranslations\Config\DatabaseTranslations;
use HistoryHeraldry\DatabaseTranslations\Language\DatabaseLanguage;
use HistoryHeraldry\DatabaseTranslations\Repositories\TranslationRepository;

final class DatabaseLanguageTest extends CIUnitTestCase
{
    public function testNamespacePrefixDottedKeysAndJsonValuesBecomeNestedCatalogue(): void
    {
        $repository = new class extends TranslationRepository {
            public function __construct() {}

            public function getForLocaleAndNamespaces(string $locale, array $namespaces): array
            {
                return [
                    'Common.actions.save' => 'Save',
                    'Common.options' => '{"first":"One","second":"Two"}',
                ];
            }
        };
        $language = new class($repository, new DatabaseTranslations()) extends DatabaseLanguage {
            public function __construct(TranslationRepository $repository, DatabaseTranslations $config)
            {
                $this->translations = $repository;
                $this->databaseTranslationsConfig = $config;
            }

            public function databaseValues(string $locale, string $namespace): array
            {
                return $this->databaseTranslations($locale, $namespace);
            }
        };

        self::assertSame([
            'actions' => ['save' => 'Save'],
            'options' => ['first' => 'One', 'second' => 'Two'],
        ], $language->databaseValues('en', 'Common'));
    }
}
