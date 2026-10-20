<?php declare(strict_types=1);

namespace HistoryHeraldry\DatabaseTranslations\Identifiers;

use HistoryHeraldry\DatabaseTranslations\Contracts\IdentifierGenerator;
use Michalsn\CodeIgniterUuid\Uuid;
use RuntimeException;

final class MichalsnUuidGenerator implements IdentifierGenerator
{
    private Uuid $uuid;

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

    public function generate(): string
    {
        return $this->uuid->uuid4()->toRfc4122();
    }
}
