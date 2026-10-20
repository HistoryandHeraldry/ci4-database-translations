<?php declare(strict_types=1);

namespace HistoryHeraldry\DatabaseTranslations\Config;

use CodeIgniter\Config\BaseConfig;

class DatabaseTranslations extends BaseConfig
{
    public const PRIMARY_KEY_AUTO_INCREMENT = 'auto_increment';
    public const PRIMARY_KEY_UUID = 'uuid';

    public string $databaseGroup = 'default';
    public string $table = 'translations';
    public string $primaryKeyMode = self::PRIMARY_KEY_AUTO_INCREMENT;

    public ?string $identifierGenerator = null;

    public bool $filesOverrideDatabase = true;
    public string $languagePath = APPPATH . 'Language';
    public string $seedPath = APPPATH . 'Database/Seeds';
    public string $seedDataDirectory = 'Data/Translations';
    public string $seedNamespace = 'App\\Database\\Seeds';
}
