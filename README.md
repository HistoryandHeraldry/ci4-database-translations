# CodeIgniter 4 Database Translations

Database-backed translations for CodeIgniter 4, with native language-file fallback and a deployable NDJSON seed generator.

## Requirements

- PHP 8.2 or later
- CodeIgniter 4.7 or later

## Installation

```bash
composer require historyheraldry/ci4-database-translations
```

Choose the primary-key mode before running the migration, then create the table:

```bash
php spark migrate -n 'HistoryHeraldry\DatabaseTranslations'
```

The migration creates a prefixed `translations` table with a unique key over `locale`, `namespace`, and `key`.

## Register the language service

Override the language service in `app/Config/Services.php`:

```php
use CodeIgniter\Language\Language;
use HistoryHeraldry\DatabaseTranslations\Config\DatabaseTranslations;
use HistoryHeraldry\DatabaseTranslations\Language\DatabaseLanguage;
use HistoryHeraldry\DatabaseTranslations\Repositories\TranslationRepository;

public static function language(?string $locale = null, bool $getShared = true)
{
    $locale ??= config('App')->defaultLocale;

    if ($getShared) {
        return static::getSharedInstance('language', $locale);
    }

    try {
        $config = config('DatabaseTranslations');
        $repository = new TranslationRepository(config: $config);

        if ($repository->tableExists()) {
            return new DatabaseLanguage($locale, $repository, $config);
        }
    } catch (\Throwable $exception) {
        log_message('warning', 'Database translations unavailable: {message}', ['message' => $exception->getMessage()]);
    }

    return new Language($locale);
}
```

Existing calls such as `lang('Common.actions.button.save')` continue to use CI4's native language API. Database rows use separate `namespace` and `key` values:

```text
locale:    en
namespace: Common
key:       actions.button.save
value:     Save
```

By default, language files override matching database values. This keeps files useful as the development source while the database supplies missing runtime values.

## Generate translation seeds

Place translations in standard CI4 files such as `app/Language/en/Common.php`, then run:

```bash
php spark translations:generate-seeder
```

Use a descriptive name when useful:

```bash
php spark translations:generate-seeder initial-catalogue
php spark translations:generate-seeder --dry-run
```

The command recursively flattens nested translation arrays and creates:

- a timestamped tracked seeder under `app/Database/Seeds`;
- a matching NDJSON file under `app/Database/Seeds/Data/Translations`.

Commit both files. With `historyheraldry/ci4-seed-tracker` installed, apply the generated seed once with:

```bash
php spark seed
```

Generating a seed does not alter or clear the source language files.

## Configuration

Create `app/Config/DatabaseTranslations.php` when defaults need changing:

```php
<?php

namespace Config;

use HistoryHeraldry\DatabaseTranslations\Config\DatabaseTranslations as BaseConfig;

final class DatabaseTranslations extends BaseConfig
{
    public string $databaseGroup = 'default';
    public string $table = 'translations';
    public bool $filesOverrideDatabase = true;
}
```

The configured database group's table prefix is applied automatically.

### Primary-key modes

The default mode is dependency-free auto-incrementing integer IDs:

```php
public string $primaryKeyMode = self::PRIMARY_KEY_AUTO_INCREMENT;
```

To use UUIDs, configure UUID mode and an identifier generator before running the migration:

```php
use HistoryHeraldry\DatabaseTranslations\Identifiers\RandomUuidGenerator;

public string $primaryKeyMode = self::PRIMARY_KEY_UUID;
public ?string $identifierGenerator = RandomUuidGenerator::class;
```

The package includes a dependency-free UUIDv4 generator. Applications using `michalsn/codeigniter4-uuid` may instead install and configure that package, then select its adapter:

```bash
composer require michalsn/codeigniter4-uuid
```

```php
use HistoryHeraldry\DatabaseTranslations\Identifiers\MichalsnUuidGenerator;

public string $primaryKeyMode = self::PRIMARY_KEY_UUID;
public ?string $identifierGenerator = MichalsnUuidGenerator::class;
```

Custom generators may be used by implementing `HistoryHeraldry\DatabaseTranslations\Contracts\IdentifierGenerator` and configuring the implementation's class name.

The selected primary-key mode is part of the database schema. Do not change it after running the migration without writing a dedicated schema and data-conversion migration.

## Reading translations directly

```php
$repository = new TranslationRepository();

$exact = $repository->getForLocaleAndNamespaces('en-GB', ['Common', 'Checkout']);
$withFallback = $repository->getForLocaleAndNamespacesWithFallback('en-GB', ['Common']);
```

Fallback order is the application's default locale, base locale, then exact locale; later values override earlier ones.

## Optional model

Applications that want conventional CI4 CRUD and query-builder access may use the included model:

```php
use HistoryHeraldry\DatabaseTranslations\Models\TranslationModel;

$translations = new TranslationModel();
$id = $translations->insert([
    'locale' => 'en',
    'namespace' => 'Common',
    'key' => 'actions.button.save',
    'value' => 'Save',
]);

$english = $translations->where('locale', 'en')->findAll();
```

The model reads the configured database group, table, and primary-key mode. It provides validation, timestamps, soft deletes, and configured UUID generation. It is optional: runtime language loading and generated seeders continue to use `TranslationRepository`, and applications may keep their own model layer.

## Licence

Released under the MIT License.
