<?php declare(strict_types=1);

namespace HistoryHeraldry\DatabaseTranslations\Contracts;

interface IdentifierGenerator
{
    public function generate(): string;
}
