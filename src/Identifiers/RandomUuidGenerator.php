<?php declare(strict_types=1);

namespace HistoryHeraldry\DatabaseTranslations\Identifiers;

use HistoryHeraldry\DatabaseTranslations\Contracts\IdentifierGenerator;
use HistoryHeraldry\DatabaseTranslations\Support\Uuid;

final class RandomUuidGenerator implements IdentifierGenerator
{
    public function generate(): string
    {
        return Uuid::v4();
    }
}
