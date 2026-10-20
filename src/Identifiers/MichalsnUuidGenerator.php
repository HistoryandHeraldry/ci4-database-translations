<?php declare(strict_types=1);

namespace HistoryHeraldry\DatabaseTranslations\Identifiers;

use HistoryHeraldry\DatabaseTranslations\Config\DatabaseTranslations;
use HistoryHeraldry\DatabaseTranslations\Contracts\VersionedIdentifierGenerator;
use InvalidArgumentException;
use Michalsn\CodeIgniterUuid\Uuid;
use RuntimeException;

final class MichalsnUuidGenerator implements VersionedIdentifierGenerator
{
    private Uuid $uuid;
    private string $version = DatabaseTranslations::UUID_VERSION_4;

    public function __construct(?Uuid $uuid = null)
    {
        if (! class_exists(Uuid::class)) {
            throw new RuntimeException('UUID mode requires michalsn/codeigniter4-uuid. Install and configure it before running the migration.');
        }

        $service = $uuid ?? service('uuid');
        if (! $service instanceof Uuid) {
            throw new RuntimeException('The uuid service must resolve to ' . Uuid::class . '.');
        }

        $this->uuid = $service;
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
        return $this->uuid->generate($this->version)->toRfc4122();
    }
}
