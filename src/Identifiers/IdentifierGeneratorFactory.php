<?php declare(strict_types=1);

namespace HistoryHeraldry\DatabaseTranslations\Identifiers;

use HistoryHeraldry\DatabaseTranslations\Config\DatabaseTranslations;
use HistoryHeraldry\DatabaseTranslations\Contracts\IdentifierGenerator;
use RuntimeException;

final class IdentifierGeneratorFactory
{
    public static function create(DatabaseTranslations $config): ?IdentifierGenerator
    {
        if ($config->primaryKeyMode === DatabaseTranslations::PRIMARY_KEY_AUTO_INCREMENT) {
            return null;
        }

        if ($config->primaryKeyMode !== DatabaseTranslations::PRIMARY_KEY_UUID) {
            throw new RuntimeException(sprintf(
                'Unsupported translation primary-key mode "%s". Expected "%s" or "%s".',
                $config->primaryKeyMode,
                DatabaseTranslations::PRIMARY_KEY_AUTO_INCREMENT,
                DatabaseTranslations::PRIMARY_KEY_UUID,
            ));
        }

        $class = $config->identifierGenerator;
        if ($class === null || $class === '') {
            throw new RuntimeException('UUID primary-key mode requires an identifierGenerator in DatabaseTranslations configuration.');
        }
        if (! class_exists($class)) {
            throw new RuntimeException('Configured translation identifier generator was not found: ' . $class);
        }

        $generator = new $class();
        if (! $generator instanceof IdentifierGenerator) {
            throw new RuntimeException('Configured translation identifier generator must implement ' . IdentifierGenerator::class . '.');
        }

        return $generator;
    }
}
