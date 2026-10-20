<?php declare(strict_types=1);

namespace HistoryHeraldry\DatabaseTranslations\Contracts;

interface VersionedIdentifierGenerator extends IdentifierGenerator
{
    public function setVersion(string $version): void;
}
