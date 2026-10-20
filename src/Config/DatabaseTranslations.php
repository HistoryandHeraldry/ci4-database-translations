<?php declare(strict_types=1);

namespace HistoryHeraldry\DatabaseTranslations\Config;

use CodeIgniter\Config\BaseConfig;

class DatabaseTranslations extends BaseConfig
{
    public const PRIMARY_KEY_AUTO_INCREMENT = 'auto_increment';
    public const PRIMARY_KEY_UUID = 'uuid';
    public const UUID_VERSION_1 = 'v1';
    public const UUID_VERSION_4 = 'v4';
    public const UUID_VERSION_6 = 'v6';
    public const UUID_VERSION_7 = 'v7';
    /** @var list<string> */
    public const SUPPORTED_UUID_VERSIONS = [
        self::UUID_VERSION_1,
        self::UUID_VERSION_4,
        self::UUID_VERSION_6,
        self::UUID_VERSION_7,
    ];

    public string $databaseGroup = 'default';
    public string $table = 'translations';
    public string $primaryKeyMode = self::PRIMARY_KEY_AUTO_INCREMENT;
    public string $uuidVersion = self::UUID_VERSION_4;

    public ?string $identifierGenerator = null;

    public bool $filesOverrideDatabase = true;
    public string $languagePath = APPPATH . 'Language';
    public string $seedPath = APPPATH . 'Database/Seeds';
    public string $seedDataDirectory = 'Data/Translations';
    public string $seedNamespace = 'App\\Database\\Seeds';
}
