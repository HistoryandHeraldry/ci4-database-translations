<?php declare(strict_types=1);

namespace HistoryHeraldry\DatabaseTranslations\Identifiers;

use HistoryHeraldry\DatabaseTranslations\Config\DatabaseTranslations;
use HistoryHeraldry\DatabaseTranslations\Contracts\VersionedIdentifierGenerator;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final class SymfonyUuidGenerator implements VersionedIdentifierGenerator
{
    private string $version;

    public function __construct(string $version = DatabaseTranslations::UUID_VERSION_4)
    {
        $this->setVersion($version);
    }

    public function setVersion(string $version): void
    {
        if (! in_array($version, DatabaseTranslations::SUPPORTED_UUID_VERSIONS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported generated UUID version "%s". Expected one of: %s.',
                $version,
                implode(', ', DatabaseTranslations::SUPPORTED_UUID_VERSIONS),
            ));
        }

        $this->version = $version;
    }

    public function generate(): string
    {
        return match ($this->version) {
            DatabaseTranslations::UUID_VERSION_1 => Uuid::v1()->toRfc4122(),
            DatabaseTranslations::UUID_VERSION_4 => Uuid::v4()->toRfc4122(),
            DatabaseTranslations::UUID_VERSION_6 => Uuid::v6()->toRfc4122(),
            DatabaseTranslations::UUID_VERSION_7 => Uuid::v7()->toRfc4122(),
            default => throw new InvalidArgumentException('Unsupported generated UUID version: ' . $this->version),
        };
    }
}
