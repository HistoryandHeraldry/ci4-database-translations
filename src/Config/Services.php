<?php declare(strict_types=1);

namespace HistoryHeraldry\DatabaseTranslations\Config;

use CodeIgniter\Config\BaseService;
use HistoryHeraldry\DatabaseTranslations\Repositories\TranslationRepository;
use HistoryHeraldry\DatabaseTranslations\Services\TranslationSeedGenerator;

final class Services extends BaseService
{
    public static function databaseTranslations(bool $getShared = true): TranslationRepository
    {
        if ($getShared) {
            return static::getSharedInstance('databaseTranslations');
        }
        return new TranslationRepository();
    }

    public static function translationSeedGenerator(bool $getShared = true): TranslationSeedGenerator
    {
        if ($getShared) {
            return static::getSharedInstance('translationSeedGenerator');
        }
        return new TranslationSeedGenerator();
    }
}
