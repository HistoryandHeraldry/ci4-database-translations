<?php declare(strict_types=1);

namespace HistoryHeraldry\DatabaseTranslations\Repositories;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use HistoryHeraldry\DatabaseTranslations\Config\DatabaseTranslations;
use HistoryHeraldry\DatabaseTranslations\Contracts\IdentifierGenerator;
use HistoryHeraldry\DatabaseTranslations\Identifiers\IdentifierGeneratorFactory;
use RuntimeException;

class TranslationRepository
{
    /** @var BaseConnection<mixed, mixed> */
    protected BaseConnection $db;
    protected DatabaseTranslations $config;
    private IdentifierGenerator|null|false $identifierGenerator = false;

    /** @param BaseConnection<mixed, mixed>|null $db */
    public function __construct(
        ?BaseConnection $db = null,
        ?DatabaseTranslations $config = null,
        ?IdentifierGenerator $identifierGenerator = null,
    )
    {
        $this->config = $config ?? config('DatabaseTranslations');
        $this->db = $db ?? Database::connect($this->config->databaseGroup);
        if ($identifierGenerator !== null) {
            $this->identifierGenerator = $identifierGenerator;
        }
    }

    /**
     * @param list<string> $namespaces
     * @return array<string,string>
     */
    public function getForLocaleAndNamespaces(string $locale, array $namespaces): array
    {
        $namespaces = $this->normalizeNamespaces($namespaces);
        if ($namespaces === []) {
            return [];
        }

        $rows = $this->db->table($this->config->table)
            ->select(['namespace', 'key', 'value'])
            ->where('locale', $locale)
            ->whereIn('namespace', $namespaces)
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();

        $translations = [];
        foreach ($rows as $row) {
            if (is_string($row['namespace'] ?? null) && is_string($row['key'] ?? null) && is_string($row['value'] ?? null)) {
                $translations[$row['namespace'] . '.' . $row['key']] = $row['value'];
            }
        }

        return $translations;
    }

    /**
     * @param list<string> $namespaces
     * @return array<string,string>
     */
    public function getForLocaleAndNamespacesWithFallback(string $locale, array $namespaces, ?string $defaultLocale = null): array
    {
        $defaultLocale ??= (string) config('App')->defaultLocale;
        $baseLocale = explode('-', $locale, 2)[0] ?: $locale;
        $translations = [];

        foreach (array_values(array_unique([$defaultLocale, $baseLocale, $locale])) as $candidate) {
            foreach ($this->getForLocaleAndNamespaces($candidate, $namespaces) as $key => $value) {
                $translations[$key] = $value;
            }
        }

        return $translations;
    }

    /** @return list<string> */
    public function namespaces(): array
    {
        $rows = $this->db->table($this->config->table)
            ->distinct()->select('namespace')->where('deleted_at', null)
            ->orderBy('namespace', 'ASC')->get()->getResultArray();

        return array_values(array_filter(array_map(static fn(array $row): ?string => is_string($row['namespace'] ?? null) ? $row['namespace'] : null, $rows)));
    }

    /** @param list<array{locale:string,namespace:string,key:string,value:?string}> $rows */
    public function upsertBatch(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $builder = $this->db->table($this->config->table);
        $this->db->transException(true)->transStart();

        try {
            foreach ($rows as $row) {
                $existing = $builder->select('id')->where([
                    'locale' => $row['locale'],
                    'namespace' => $row['namespace'],
                    'key' => $row['key'],
                ])->get()->getFirstRow('array');

                $now = date('Y-m-d H:i:s');
                if ($existing !== null) {
                    $builder->where('id', $existing['id'])->update(['value' => $row['value'], 'updated_at' => $now, 'deleted_at' => null]);
                } else {
                    $insert = [...$row, 'created_at' => $now, 'updated_at' => $now];
                    $generator = $this->identifierGenerator();
                    if ($generator !== null) {
                        $insert['id'] = $generator->generate();
                    }
                    $builder->insert($insert);
                }
            }
            $this->db->transComplete();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw new RuntimeException('Unable to persist translations: ' . $exception->getMessage(), 0, $exception);
        }

        return count($rows);
    }

    public function tableExists(): bool
    {
        return $this->db->tableExists($this->config->table);
    }

    private function identifierGenerator(): ?IdentifierGenerator
    {
        if ($this->identifierGenerator === false) {
            $this->identifierGenerator = IdentifierGeneratorFactory::create($this->config);
        }

        return $this->identifierGenerator;
    }

    /**
     * @param array<mixed> $namespaces
     * @return list<string>
     */
    private function normalizeNamespaces(array $namespaces): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn(mixed $namespace): string => is_string($namespace) ? trim($namespace) : '', $namespaces),
            static fn(string $namespace): bool => $namespace !== '',
        )));
    }
}
